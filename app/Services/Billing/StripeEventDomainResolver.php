<?php

namespace App\Services\Billing;

use App\Models\ProjectInvoice;
use App\Models\ProjectSubscription;
use Stripe\Event;
use Stripe\StripeObject;

/**
 * Decides which billing domain owns a Stripe event so a single Stripe webhook
 * endpoint can serve both SaaS billing and Custom Project Billing.
 */
class StripeEventDomainResolver
{
    public function isProjectBilling(Event $event): bool
    {
        $object = $event->data->object ?? null;

        if (! $object instanceof StripeObject) {
            return false;
        }

        if ($this->taggedAsProjectBilling($object)) {
            return true;
        }

        $subscriptionId = $this->subscriptionId($event, $object);

        if ($subscriptionId && ProjectSubscription::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->exists()) {
            return true;
        }

        $invoiceId = $this->invoiceId($event, $object);

        return (bool) $invoiceId && ProjectInvoice::query()
            ->where('stripe_invoice_id', $invoiceId)
            ->exists();
    }

    private function taggedAsProjectBilling(StripeObject $object): bool
    {
        $tag = config('billing.domain_tag');

        // Subscription metadata reaches invoices through subscription_details.
        return ($object->metadata?->billing_domain ?? null) === $tag
            || ($object->subscription_details?->metadata?->billing_domain ?? null) === $tag
            || ($object->parent?->subscription_details?->metadata?->billing_domain ?? null) === $tag;
    }

    private function subscriptionId(Event $event, StripeObject $object): ?string
    {
        if (str_starts_with($event->type, 'customer.subscription.')) {
            return $this->id($object->id ?? null);
        }

        return $this->id($object->subscription ?? null)
            ?: $this->id($object->parent?->subscription_details?->subscription ?? null);
    }

    private function invoiceId(Event $event, StripeObject $object): ?string
    {
        if (str_starts_with($event->type, 'invoice.')) {
            return $this->id($object->id ?? null);
        }

        return $this->id($object->invoice ?? null);
    }

    private function id(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return is_object($value) && isset($value->id) ? (string) $value->id : null;
    }
}
