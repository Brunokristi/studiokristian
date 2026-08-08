@extends('layouts.client', ['title' => 'Projekty'])

@section('content')
<p class="portal-muted" style="font-size: 12px; text-transform: uppercase;">{{ auth('client')->user()->company->name }}</p>
<h1 style="font-size: clamp(32px, 6vw, 52px); margin: 12px 0 40px; letter-spacing: 0;">Vaše projekty</h1>
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 12px;">
    @forelse($projects as $project)
        <a href="{{ route('client.projects.show', $project) }}" class="portal-panel" style="padding: 22px; min-height: 190px; display: flex; flex-direction: column; justify-content: space-between; color: inherit; text-decoration: none;">
            <div><p class="portal-muted" style="font-size: 12px; margin: 0 0 12px;">{{ $project->serviceProduct?->name ?? 'Projekt' }}</p><h2 style="font-size: 22px; margin: 0;">{{ $project->name }}</h2></div>
            <div style="display: flex; justify-content: space-between; align-items: end;"><span style="font-size: 12px; text-transform: uppercase;">{{ $project->portal_status }}</span><span style="font-size:12px">@if(($project->pending_contracts_count + $project->pending_offers_count) > 0)Action required: {{ $project->pending_contracts_count + $project->pending_offers_count }}@else<span aria-hidden="true">↗</span>@endif</span></div>
        </a>
    @empty
        <div class="portal-panel portal-muted" style="padding: 24px;">Momentálne tu nie sú žiadne aktívne projekty.</div>
    @endforelse
</div>
@endsection