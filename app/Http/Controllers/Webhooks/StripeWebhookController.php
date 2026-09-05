<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\StripeWebhookEvent;
use App\Services\Billing\StripeWebhookService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookService $service): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json([
                'message' => 'Stripe webhook is not configured.',
            ], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );
        } catch (UnexpectedValueException $exception) {
            Log::warning('Stripe webhook received invalid payload.');

            return response()->json([
                'message' => 'Invalid payload.',
            ], 400);
        } catch (SignatureVerificationException $exception) {
            Log::warning('Stripe webhook signature verification failed.');

            return response()->json([
                'message' => 'Invalid signature.',
            ], 400);
        }

        $eventId = (string) $event->id;
        $eventType = (string) $event->type;

        try {
            $webhookEvent = StripeWebhookEvent::query()->firstOrCreate(
                [
                    'stripe_event_id' => $eventId,
                ],
                [
                    'type' => $eventType,
                    'payload' => json_decode($payload, true) ?: [],
                ]
            );
        } catch (QueryException $exception) {
            $webhookEvent = StripeWebhookEvent::query()
                ->where('stripe_event_id', $eventId)
                ->firstOrFail();
        }

        Log::info('Stripe webhook processing started.', [
            'stripe_event_id' => $eventId,
            'type' => $eventType,
        ]);

        $duplicate = false;

        try {
            DB::transaction(function () use ($service, $event, $eventId, &$webhookEvent, &$duplicate): void {
                $webhookEvent = StripeWebhookEvent::query()
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
            $webhookEvent->forceFill([
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            Log::error('Stripe webhook processing failed.', [
                'stripe_event_id' => $eventId,
                'type' => $eventType,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook processing failed.',
            ], 500);
        }

        if ($duplicate) {
            Log::info('Stripe webhook duplicate event acknowledged.', [
                'stripe_event_id' => $eventId,
                'type' => $eventType,
            ]);

            return response()->json([
                'received' => true,
                'duplicate' => true,
            ]);
        }

        Log::info('Stripe webhook processing completed.', [
            'stripe_event_id' => $eventId,
            'type' => $eventType,
        ]);

        return response()->json([
            'received' => true,
        ]);
    }
}