<?php

namespace App\Services\ProjectBilling;

use App\Models\BillingProduct;
use App\Models\Project;
use App\Models\ProjectBillingItem;
use App\Models\ProjectInvoice;
use App\Models\ProjectSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Illuminate\Support\Carbon;

/**
 * All recurring items of one project belong to a single Stripe Subscription.
 */
class ProjectSubscriptionService
{
    public function __construct(private StripeProjectBillingGateway $stripe)
    {
    }

    public function start(
        Project $project,
        string $collectionMethod = 'send_invoice',
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?array $billingItemIds = null
    ): ProjectSubscription
    {
        $company = $project->company;

        if (! $company) {
            throw new RuntimeException('The project has no client company to bill.');
        }

        $existing = ProjectSubscription::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [ProjectSubscription::STATUS_ACTIVE, ProjectSubscription::STATUS_PAST_DUE])
            ->first();

        if ($existing) {
            return $this->addPendingItems($existing, $project, $billingItemIds);
        }

        $itemsQuery = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->where('billing_type', BillingProduct::TYPE_RECURRING)
            ->whereIn('status', [ProjectBillingItem::STATUS_PENDING, ProjectBillingItem::STATUS_ACTIVE])
            ->whereNull('project_subscription_id');

        if ($billingItemIds) {
            $itemsQuery->whereIn('id', $billingItemIds);
        }

        $items = $itemsQuery->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Add a new recurring service before starting recurring billing. Previously canceled services cannot be reactivated automatically.');
        }

        $earliestItemStart = $items
            ->pluck('starts_at')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sort()
            ->first();

        $startsAt = $startsAt ?: ($earliestItemStart?->toDateString());

        $customerId = $this->stripe->resolveCustomer($company);

        $subscription = DB::transaction(fn () => ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_DRAFT,
            'collection_method' => $collectionMethod,
            'currency' => $items->first()->currency ?: config('billing.currency'),
            'starts_at' => $startsAt ?: today()->toDateString(),
            'ends_at' => $endsAt,
            'stripe_customer_id' => $customerId,
        ]));

        $stripeItems = [];
        $futureStart = $startsAt
            && $this->stripe->isFutureForCustomer(
                $customerId,
                Carbon::parse($startsAt)->timestamp
            );
        $backdateStart = $startsAt
            && ! $futureStart
            && Carbon::parse($startsAt)->isPast()
            ? Carbon::parse($startsAt)->timestamp
            : null;

        try {
            foreach ($items as $item) {
                if ($item->starts_at && Carbon::parse($item->starts_at)->isFuture() && ! $futureStart) {
                    continue;
                }

                $priceId = $item->stripe_price_id ?: $this->createPrice($item);
                $item->update(['stripe_price_id' => $priceId]);

                $stripeItems[] = [
                    'price' => $priceId,
                    'quantity' => $item->quantity,
                ];
            }

            if ($futureStart) {
                $schedule = $this->stripe->createFutureSchedule(
                    $customerId,
                    $stripeItems,
                    Carbon::parse($startsAt)->timestamp,
                    $collectionMethod,
                    [
                        'project_id' => (string) $project->id,
                        'company_id' => (string) $company->id,
                        'project_subscription_id' => (string) $subscription->id,
                    ],
                    $endsAt ? Carbon::parse($endsAt)->timestamp : null
                );

                $subscription->update([
                    'stripe_schedule_id' => $schedule->id,
                    'status' => ProjectSubscription::STATUS_DRAFT,
                ]);

                foreach ($items as $item) {
                    $item->update([
                        'project_subscription_id' => $subscription->id,
                        'status' => ProjectBillingItem::STATUS_PENDING,
                    ]);
                }

                return $subscription->fresh('items');
            }

            $stripeSubscription = $this->stripe->createSubscription(
                $customerId,
                $stripeItems,
                $collectionMethod,
                [
                    'project_id' => (string) $project->id,
                    'company_id' => (string) $company->id,
                    'project_subscription_id' => (string) $subscription->id,
                ],
                'project-subscription-'.$subscription->id,
                $backdateStart
            );
        } catch (\Throwable $exception) {
            // Never leave an orphaned local subscription behind after a Stripe failure.
            $subscription->delete();

            throw $exception;
        }

        $subscription->update([
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status === 'active'
                ? ProjectSubscription::STATUS_ACTIVE
                : ProjectSubscription::STATUS_DRAFT,
            'current_period_start' => $this->timestamp($stripeSubscription->current_period_start ?? null),
            'current_period_end' => $this->timestamp($stripeSubscription->current_period_end ?? null),
        ]);

        $this->attachSubscriptionItems($subscription, $items, $stripeSubscription);
        $this->syncFutureLifecycle($subscription, $project);
        $this->finalizeFirstInvoice($stripeSubscription);

        return $subscription->fresh('items');
    }

    /**
     * Adds newly configured recurring services to the project's existing Stripe
     * subscription instead of creating a second renewal stream.
     */
    private function addPendingItems(ProjectSubscription $subscription, Project $project, ?array $billingItemIds = null): ProjectSubscription
    {
        $itemsQuery = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->where('billing_type', BillingProduct::TYPE_RECURRING)
            ->whereIn('status', [ProjectBillingItem::STATUS_PENDING, ProjectBillingItem::STATUS_ACTIVE])
            ->whereNull('project_subscription_id');

        if ($billingItemIds) {
            $itemsQuery->whereIn('id', $billingItemIds);
        }

        $items = $itemsQuery->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('Add a new recurring service before adding services to recurring billing.');
        }

        $stripeSubscription = $this->stripe->retrieveSubscription($subscription->stripe_subscription_id);
        $stripeItems = collect($stripeSubscription->items->data ?? [])
            ->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity ?? 1,
            ])
            ->values()
            ->all();

        $currentItems = $items->filter(fn (ProjectBillingItem $item) =>
            ! $item->starts_at || ! Carbon::parse($item->starts_at)->isFuture()
        );

        foreach ($currentItems as $item) {
            $priceId = $item->stripe_price_id ?: $this->createPrice($item);
            $item->update(['stripe_price_id' => $priceId]);
            $stripeItems[] = ['price' => $priceId, 'quantity' => $item->quantity];
        }

        $updated = $this->stripe->updateSubscriptionItems(
            $subscription->stripe_subscription_id,
            $stripeItems
        );

        $this->attachSubscriptionItems($subscription, $currentItems, $updated);
        $this->syncFutureLifecycle($subscription, $project);

        return $subscription->fresh('items');
    }

    /**
     * Builds Stripe phases at every local service boundary. Stripe then activates
     * and removes subscription items automatically without a Laravel timer.
     */
    private function syncFutureLifecycle(ProjectSubscription $subscription, Project $project): void
    {
        if (! $subscription->stripe_subscription_id) {
            return;
        }

        // A future subscription start is represented by a Stripe Schedule, not a trial.
        if ($subscription->starts_at && Carbon::parse($subscription->starts_at)->isFuture()) {
            return;
        }

        $items = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->where('billing_type', BillingProduct::TYPE_RECURRING)
            ->whereNotNull('stripe_price_id')
            ->get();

        $hasFutureBoundary = $items->contains(fn (ProjectBillingItem $item) =>
            ($item->starts_at && Carbon::parse($item->starts_at)->isFuture())
            || ($item->ends_at && Carbon::parse($item->ends_at)->isFuture())
        ) || ($subscription->ends_at && Carbon::parse($subscription->ends_at)->isFuture());

        if (! $hasFutureBoundary) {
            return;
        }

        $schedule = $subscription->stripe_schedule_id
            ? $this->stripe->retrieveSchedule($subscription->stripe_schedule_id)
            : $this->stripe->createScheduleFromSubscription($subscription->stripe_subscription_id);

        $subscription->update(['stripe_schedule_id' => $schedule->id]);

        if (! $subscription->ends_at && $schedule->end_behavior === 'cancel') {
            $phase = $this->resolvedCurrentPhase($schedule);

            if ($phase) {
                $this->stripe->updateSchedule($schedule->id, [
                    'end_behavior' => 'release',
                    'phases' => [[
                        'start_date' => $phase->start_date,
                        'items' => $this->schedulePhaseItems($phase),
                    ]],
                ]);
            }

            return;
        }

        $current = $schedule->phases[0] ?? null;
        if (! $current) {
            return;
        }

        $boundaries = collect([$current->end_date ?? null])
            ->merge($items->flatMap(fn (ProjectBillingItem $item) => [
                $item->starts_at && Carbon::parse($item->starts_at)->isFuture()
                    ? Carbon::parse($item->starts_at)->timestamp : null,
                $item->ends_at && Carbon::parse($item->ends_at)->isFuture()
                    ? Carbon::parse($item->ends_at)->timestamp : null,
            ]))
            ->merge([$subscription->ends_at && Carbon::parse($subscription->ends_at)->isFuture()
                ? Carbon::parse($subscription->ends_at)->timestamp : null])
            ->filter()
            ->map(fn ($date) => (int) $date)
            ->filter(fn (int $date) => $date > time())
            ->unique()
            ->sort()
            ->values();

        if ($boundaries->isEmpty()) {
            return;
        }

        $phaseStart = (int) ($current->start_date ?? time());
        $phases = [];

        foreach ($boundaries as $boundary) {
            $phases[] = [
                'start_date' => $phaseStart,
                'end_date' => $boundary,
                'items' => $this->scheduleItemsAt($items, Carbon::createFromTimestamp($phaseStart)),
                'proration_behavior' => 'none',
            ];
            $phaseStart = $boundary;
        }

        $phases[] = [
            'start_date' => $phaseStart,
            'items' => $this->scheduleItemsAt($items, Carbon::createFromTimestamp($phaseStart)),
            'proration_behavior' => 'none',
        ];

        $this->stripe->updateSchedule($schedule->id, [
            'end_behavior' => $subscription->ends_at ? 'cancel' : 'release',
            'phases' => $phases,
        ]);
    }

    private function scheduleItemsAt($items, Carbon $at): array
    {
        return $items
            ->filter(fn (ProjectBillingItem $item) => $item->isEffectiveOn($at))
            ->map(fn (ProjectBillingItem $item) => [
                'price' => $item->stripe_price_id,
                'quantity' => $item->quantity,
            ])
            ->values()
            ->all();
    }

    public function updateItem(ProjectBillingItem $item, array $changes): ProjectBillingItem
    {
        $subscription = $item->subscription;

        if (! $subscription?->stripe_subscription_id || ! $item->stripe_subscription_item_id) {
            $item->update($changes);

            return $item->fresh();
        }

        $unitAmount = array_key_exists('unit_amount', $changes)
            ? (int) $changes['unit_amount']
            : (int) $item->unit_amount;
        $quantity = array_key_exists('quantity', $changes)
            ? (int) $changes['quantity']
            : (int) $item->quantity;
        $endsNow = array_key_exists('ends_at', $changes)
            && $changes['ends_at']
            && ! Carbon::parse($changes['ends_at'])->isFuture();

        $newPriceId = null;

        if ($unitAmount !== (int) $item->unit_amount) {
            $item->update(['unit_amount' => $unitAmount]);
            $newPriceId = $this->createPrice($item->fresh());
        }

        $stripeSubscription = $this->stripe->retrieveSubscription($subscription->stripe_subscription_id);
        $stripeItems = collect($stripeSubscription->items->data ?? [])
            ->map(function ($stripeItem) use ($item, $quantity, $newPriceId, $endsNow) {
                if ($stripeItem->id !== $item->stripe_subscription_item_id) {
                    return ['id' => $stripeItem->id, 'quantity' => $stripeItem->quantity ?? 1];
                }

                if ($endsNow) {
                    return ['id' => $stripeItem->id, 'deleted' => true];
                }

                return [
                    'id' => $stripeItem->id,
                    'price' => $newPriceId ?? $stripeItem->price->id,
                    'quantity' => $quantity,
                ];
            })
            ->values()
            ->all();

        $this->stripe->updateSubscriptionItems(
            $subscription->stripe_subscription_id,
            $stripeItems
        );

        $item->update($changes);

        if ($endsNow) {
            $item->update(['status' => ProjectBillingItem::STATUS_CANCELED]);
        }

        if ($newPriceId) {
            $item->update(['stripe_price_id' => $newPriceId]);
        }

        $this->recordVersion($item->fresh());
        $this->syncFutureLifecycle($subscription->fresh(), $item->project);

        return $item->fresh();
    }

    private function recordVersion(ProjectBillingItem $item): void
    {
        $item->versions()->create([
            'starts_at' => $this->dateString($item->getRawOriginal('starts_at')) ?: today()->format('Y-m-d'),
            'ends_at' => $this->dateString($item->getRawOriginal('ends_at')),
            'unit_amount' => $item->unit_amount,
            'quantity' => $item->quantity,
            'interval' => $item->interval,
            'interval_count' => $item->interval_count,
            'stripe_price_id' => $item->stripe_price_id,
        ]);
    }

    /**
     * Stripe leaves a subscription's first invoice in draft for about an hour. Finalizing it
     * now produces the hosted payment link so the customer can be invoiced immediately.
     */
    private function finalizeFirstInvoice(object $stripeSubscription): void
    {
        $latest = $stripeSubscription->latest_invoice ?? null;
        $invoiceId = is_string($latest) ? $latest : ($latest->id ?? null);

        if (! $invoiceId) {
            return;
        }

        try {
            $stripeInvoice = $this->stripe->retrieveInvoice($invoiceId);

            if (($stripeInvoice->status ?? null) !== 'draft') {
                return;
            }

            // Assign and push our invoice number while the invoice is still a draft,
            // otherwise Stripe stamps its own number at finalization.
            app(ProjectBillingWebhookService::class)->syncInvoiceFromStripe(
                $stripeInvoice,
                ProjectInvoice::STATUS_DRAFT
            );

            $this->stripe->finalizeInvoice($invoiceId, true);
        } catch (\Throwable $exception) {
            Log::warning('Unable to finalize the first subscription invoice.', [
                'stripe_invoice_id' => $invoiceId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function cancel(ProjectSubscription $subscription, bool $atPeriodEnd = true): ProjectSubscription
    {
        if ($subscription->stripe_schedule_id && $atPeriodEnd) {
            $schedule = $this->stripe->retrieveSchedule($subscription->stripe_schedule_id);
            $this->stripe->updateSchedule($schedule->id, [
                'end_behavior' => 'cancel',
                'phases' => $this->schedulePhases($schedule),
            ]);

            $subscription->update(['cancel_at_period_end' => true]);

            return $subscription->fresh('items');
        }

        if ($subscription->stripe_subscription_id) {
            $this->stripe->cancelSubscription($subscription->stripe_subscription_id, $atPeriodEnd);
        }

        $subscription->update($atPeriodEnd
            ? ['cancel_at_period_end' => true]
            : [
                'status' => ProjectSubscription::STATUS_CANCELED,
                'cancel_at_period_end' => false,
                'canceled_at' => now(),
                'ended_at' => now(),
            ]);

        if (! $atPeriodEnd) {
            $subscription->items()->update(['status' => ProjectBillingItem::STATUS_CANCELED]);
        }

        return $subscription->fresh('items');
    }

    public function cancelAt(ProjectSubscription $subscription, Carbon $endsAt): ProjectSubscription
    {
        if ($subscription->stripe_schedule_id) {
            $schedule = $this->stripe->retrieveSchedule($subscription->stripe_schedule_id);
            $current = $this->resolvedCurrentPhase($schedule);

            if ($current) {
                $this->stripe->updateSchedule($schedule->id, [
                    'end_behavior' => 'cancel',
                    'phases' => [[
                        'start_date' => $current->start_date,
                        'end_date' => $endsAt->timestamp,
                        'items' => $this->schedulePhaseItems($current),
                    ]],
                ]);
            }
        } elseif ($subscription->stripe_subscription_id) {
            $this->stripe->cancelSubscriptionAt(
                $subscription->stripe_subscription_id,
                $endsAt->timestamp
            );
        }

        $subscription->update([
            'ends_at' => $endsAt->toDateString(),
            'cancel_at_period_end' => false,
        ]);

        return $subscription->fresh('items');
    }

    public function pause(ProjectSubscription $subscription, bool $paused): ProjectSubscription
    {
        if ($subscription->stripe_subscription_id) {
            $this->stripe->pauseSubscription($subscription->stripe_subscription_id, $paused);
        }

        $subscription->update([
            'status' => $paused
                ? ProjectSubscription::STATUS_PAUSED
                : ProjectSubscription::STATUS_ACTIVE,
        ]);

        return $subscription->fresh('items');
    }

    public function pauseAt(ProjectSubscription $subscription, Carbon $pauseAt): ProjectSubscription
    {
        if (! $subscription->stripe_subscription_id) {
            throw new RuntimeException('This subscription is not connected to Stripe.');
        }

        $schedule = $subscription->stripe_schedule_id
            ? $this->stripe->retrieveSchedule($subscription->stripe_schedule_id)
            : $this->stripe->createScheduleFromSubscription($subscription->stripe_subscription_id);

        $current = $this->resolvedCurrentPhase($schedule);

        if (! $current) {
            throw new RuntimeException('Stripe did not return a schedule phase.');
        }

        $this->stripe->updateSchedule($schedule->id, [
            'phases' => [
                [
                    'start_date' => $current->start_date,
                    'end_date' => $pauseAt->timestamp,
                    'items' => $this->schedulePhaseItems($current),
                ],
                [
                    'start_date' => $pauseAt->timestamp,
                    'items' => $this->schedulePhaseItems($current),
                    'pause_collection' => ['behavior' => 'void'],
                ],
            ],
        ]);

        $subscription->update([
            'stripe_schedule_id' => $schedule->id,
            'pause_at' => $pauseAt,
        ]);

        return $subscription->fresh('items');
    }

    private function schedulePhaseItems(object $phase): array
    {
        return collect($phase->items ?? [])
            ->map(fn ($item) => [
                'price' => is_object($item->price ?? null)
                    ? $item->price->id
                    : $item->price,
                'quantity' => $item->quantity ?? 1,
            ])
            ->values()
            ->all();
    }

    private function resolvedCurrentPhase(object $schedule): ?object
    {
        $current = $schedule->current_phase ?? null;

        if ($current && ! empty($current->items)) {
            return $current;
        }

        $start = (int) ($current->start_date ?? 0);
        $end = (int) ($current->end_date ?? 0);

        return collect($schedule->phases ?? [])->first(function ($phase) use ($start, $end): bool {
            return ($start === 0 || (int) $phase->start_date === $start)
                && ($end === 0 || (int) $phase->end_date === $end);
        }) ?: ($schedule->phases[0] ?? null);
    }

    private function schedulePhases(object $schedule): array
    {
        return collect($schedule->phases ?? [])
            ->map(function ($phase): array {
                $payload = [
                    'start_date' => $phase->start_date,
                    'items' => $this->schedulePhaseItems($phase),
                ];

                if (isset($phase->end_date)) {
                    $payload['end_date'] = $phase->end_date;
                }

                if (isset($phase->pause_collection)) {
                    $payload['pause_collection'] = $phase->pause_collection;
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    private function createPrice(ProjectBillingItem $item): string
    {
        $productId = $item->product?->stripe_product_id;

        if (! $productId) {
            $product = $this->stripe->createProduct(
                $item->product?->name ?: $item->name,
                $item->product?->description ?: $item->description,
                ['billing_product_id' => (string) ($item->billing_product_id ?? '')]
            );

            $productId = $product->id;
            $item->product?->update(['stripe_product_id' => $productId]);
        }

        $price = $this->stripe->createRecurringPrice(
            $productId,
            (int) $item->unit_amount,
            $item->currency ?: config('billing.currency'),
            $item->interval ?: 'month',
            (int) ($item->interval_count ?: 1),
            ['project_billing_item_id' => (string) $item->id]
        );

        return $price->id;
    }

    private function attachSubscriptionItems($subscription, $items, $stripeSubscription): void
    {
        $stripeItems = collect($stripeSubscription->items->data ?? []);

        foreach ($items as $item) {
            $matched = $stripeItems->first(
                fn ($stripeItem) => ($stripeItem->price->id ?? null) === $item->stripe_price_id
            );

            $item->update([
                'project_subscription_id' => $subscription->id,
                'status' => ProjectBillingItem::STATUS_ACTIVE,
                'stripe_subscription_item_id' => $matched->id ?? null,
            ]);
        }
    }

    private function timestamp(mixed $value): ?\Illuminate\Support\Carbon
    {
        return is_numeric($value) && (int) $value > 0
            ? \Illuminate\Support\Carbon::createFromTimestamp((int) $value)
            : null;
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }
}
