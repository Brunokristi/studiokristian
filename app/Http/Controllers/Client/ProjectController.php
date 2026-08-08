<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load([
            'company', 'serviceProduct',
            'contracts' => fn ($query) => $query->whereIn('status', ['sent', 'viewed', 'accepted', 'superseded'])->latest(),
            'priceOffers' => fn ($query) => $query->whereIn('status', ['sent', 'viewed', 'accepted'])->latest(),
            'files' => fn ($query) => $query->where('visibility', 'client')->latest(),
            'guides' => fn ($query) => $query->where('client_visible', true)->orderBy('sort_order'),
            'serviceAccounts' => fn ($query) => $query->where('client_visible', true)->with('credential')->orderBy('service_name'),
        ]);

        return view('client.projects.show', ['project' => $project]);
    }
}