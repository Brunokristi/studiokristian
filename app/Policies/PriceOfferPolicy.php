<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\PriceOffer;

class PriceOfferPolicy
{
    public function view(ClientContact $contact, PriceOffer $offer): bool
    {
        return $contact->can('view', $offer->project);
    }

    public function accept(ClientContact $contact, PriceOffer $offer): bool
    {
        return $contact->can_accept_documents && $this->view($contact, $offer)
            && in_array($offer->status, ['sent', 'viewed'], true);
    }
}