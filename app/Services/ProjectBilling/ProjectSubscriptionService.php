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

/**
 * All recurring items of one project belong to a single Stripe Subscription.
 */
class ProjectSubscriptionService
{
    public function __construct(private StripeProjectBillingGateway $stripe)
    {
    }

    public function start(Project $project, string $collectionMethod = 'send_invoice'): ProjectSubscription
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
            throw new RuntimeException('This project already has an active recurring subscription.');
        }

        $items = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->where('billing_type', BillingProduct::TYPE_RECURRING)
            ->whereIn('status', [ProjectBillingItem::STATUS_PENDING, ProjectBillingItem::STATUS_ACTIVE])
            ->whereNull('project_subscription_id')
            ->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('There are no recurring billing items to subscribe.');
        }

        $customerId = $this->stripe->resolveCustomer($company);

        $subscription = DB::transaction(fn () => ProjectSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => ProjectSubscription::STATUS_DRAFT,
            'collection_method' => $collectionMethod,
            'currency' => $items->first()->currency ?: config('billing.currency'),
            'stripe_customer_id' => $customerId,
        ]));

        $stripeItems = [];

        try {
            foreach ($items as $item) {
                $priceId = $item->stripe_price_id ?: $this->createPrice($item);
                $item->update(['stripe_price_id' => $priceId]);

                $stripeItems[] = [
                    'price' => $priceId,
                    'quantity' => $item->quantity,
                ];
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
                // Retrying the same local subscription never creates a second one in Stripe.
                'project-subscription-'.$subscription->id
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
        $this->finalizeFirstInvoice($stripeSubscription);

        return $subscription->fresh('items');
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
}
