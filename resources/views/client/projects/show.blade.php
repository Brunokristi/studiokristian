@extends('layouts.client', ['title' => $project->name])

@section('content')
<a href="{{ route('client.dashboard') }}" class="portal-muted" style="font-size: 12px; text-decoration: none;">← Všetky projekty</a>
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 20px; align-items: end; margin: 28px 0 40px;">
    <div><p class="portal-muted" style="font-size: 12px; text-transform: uppercase;">{{ $project->serviceProduct?->name ?? 'Projekt' }}</p><h1 style="font-size: clamp(34px, 6vw, 58px); line-height: 1; margin: 10px 0 0; letter-spacing: 0;">{{ $project->name }}</h1></div>
    <span style="font-size: 12px; text-transform: uppercase; border: 1px solid var(--portal-line); padding: 8px 10px;">{{ $project->portal_status }}</span>
</div>
<nav style="display: flex; overflow-x: auto; gap: 24px; border-bottom: 1px solid var(--portal-line); padding-bottom: 14px; margin-bottom: 28px; font-size: 12px; text-transform: uppercase;">
    <strong>Prehľad</strong><span class="portal-muted">Zmluvy</span><span class="portal-muted">Cenové ponuky</span><span class="portal-muted">Súbory</span><span class="portal-muted">Služby a účty</span><span class="portal-muted">Návody</span><span class="portal-muted">Podpora</span>
</nav>
<section>
    <h2 style="font-size: 18px; margin: 0 0 14px;">Zmluvy</h2>
    <div style="display: grid; gap: 8px;">
        @forelse($project->contracts as $contract)
            <a href="{{ route('client.contracts.show', $contract) }}" class="portal-panel" style="display: grid; grid-template-columns: 1fr auto; gap: 16px; padding: 18px; color: inherit; text-decoration: none;">
                <div><strong>{{ $contract->title }}</strong><div class="portal-muted" style="font-size: 12px; margin-top: 7px;">Verzia {{ $contract->version }} @if($contract->accepted_at)· prijatá {{ $contract->accepted_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}@endif</div></div>
                <span style="font-size: 12px; text-transform: uppercase;">{{ $contract->status }}</span>
            </a>
        @empty
            <div class="portal-panel portal-muted" style="padding: 20px;">Pre tento projekt zatiaľ nie sú dostupné zmluvy.</div>
        @endforelse
    </div>
</section>
<section style="margin-top:28px"><h2 style="font-size:18px;margin:0 0 14px">Cenové ponuky</h2><div style="display:grid;gap:8px">@forelse($project->priceOffers as $offer)<a href="{{ route('client.offers.show',$offer) }}" class="portal-panel" style="display:flex;justify-content:space-between;padding:18px;color:inherit;text-decoration:none"><span><strong>{{ $offer->number }}</strong><small class="portal-muted" style="display:block;margin-top:6px">Verzia {{ $offer->version }} · {{ number_format($offer->total,2,',',' ') }} {{ $offer->currency }}</small></span><span style="font-size:12px;text-transform:uppercase">{{ $offer->status }}</span></a>@empty<div class="portal-muted">Žiadne cenové ponuky.</div>@endforelse</div></section>
<section style="margin-top:28px"><h2 style="font-size:18px;margin:0 0 14px">Súbory</h2><div style="display:grid;gap:8px">@forelse($project->files as $file)<a href="{{ route('client.files.download',$file) }}" class="portal-panel" style="display:flex;justify-content:space-between;padding:16px;color:inherit;text-decoration:none"><span>{{ $file->display_name }}</span><span class="portal-muted">{{ number_format($file->size/1024,1) }} KB ↓</span></a>@empty<div class="portal-muted">Žiadne klientské súbory.</div>@endforelse</div></section>
<section style="margin-top:28px"><h2 style="font-size:18px;margin:0 0 14px">Služby a účty</h2><div style="display:grid;gap:8px">@forelse($project->serviceAccounts as $account)<div class="portal-panel" style="padding:18px"><strong>{{ $account->service_name }}</strong><div class="portal-muted" style="font-size:12px;margin-top:8px">Vlastník: {{ $account->account_owner }} · Platí: {{ $account->billing_owner ?: 'neuvedené' }} · Spravuje: {{ $account->renewal_responsibility ?: 'neuvedené' }}</div>@if($account->login_url)<a href="{{ $account->login_url }}" rel="noopener noreferrer" target="_blank" style="display:inline-block;margin-top:10px;color:inherit">Prihlásenie ↗</a>@endif<p style="font-family:sans-serif;font-size:13px;margin:10px 0 0">{{ $account->credential?->access_instructions ?: 'Prístupové údaje sú bezpečne zdieľané samostatne.' }}</p></div>@empty<div class="portal-muted">Žiadne služby.</div>@endforelse</div></section>
@endsection