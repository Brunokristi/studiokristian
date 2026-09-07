<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ProjectBillingWebhookEvent;
use App\Services\ProjectBilling\ProjectBillingWebhookService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * Dedicated Stripe webhook endpoint for Custom Project Billing. Keeps its own
 * idempotency ledger so it never shares processing state with SaaS billing.
 */
class ProjectBillingWebhookController extends Controller
{
    public function __invoke(Request $request, ProjectBillingWebhookService $service): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret = config('services.stripe.project_billing_webhook_secret')
            ?: config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Project billing webhook secret is not configured.');

            return response()->json(['message' => 'Webhook is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $eventId = (string) $event->id;

        try {
            ProjectBillingWebhookEvent::query()->firstOrCreate(
                ['stripe_event_id' => $eventId],
                ['type' => (string) $event->type, 'payload' => json_decode($payload, true) ?: []]
            );
        } catch (QueryException) {
            ProjectBillingWebhookEvent::query()->where('stripe_event_id', $eventId)->firstOrFail();
        }

        $duplicate = false;
        $webhookEvent = null;

        try {
            DB::transaction(function () use ($service, $event, $eventId, &$duplicate, &$webhookEvent): void {
                $webhookEvent = ProjectBillingWebhookEvent::query()
                    ->where('stripe_event_id', $eventId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($webhookEvent->processed_at) {
                    $duplicate = true;

                    return;
                }

                $service->process($event);

                $webhookEvent->forceFill([
                    'processed_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            ProjectBillingWebhookEvent::query()
                ->where('stripe_event_id', $eventId)
                ->update(['failed_at' => now(), 'error_message' => $exception->getMessage()]);

            Log::error('Project billing webhook processing failed.', [
                'stripe_event_id' => $eventId,
                'type' => $event->type,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }

        return response()->json(['received' => true, 'duplicate' => $duplicate]);
    }
}
