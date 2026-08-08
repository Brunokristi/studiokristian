<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\ContractInstance;

class ContractInstancePolicy
{
    public function view(ClientContact $contact, ContractInstance $contract): bool
    {
        return $contact->can('view', $contract->project);
    }

    public function accept(ClientContact $contact, ContractInstance $contract): bool
    {
        return $contact->can_accept_documents
            && $this->view($contact, $contract)
            && in_array($contract->status, ['sent', 'viewed'], true);
    }
}