@extends('layouts.client', ['title' => $contract->title])

@section('content')
<a href="{{ route('client.projects.show', $contract->project) }}" class="portal-muted" style="font-size: 12px; text-decoration: none;">← {{ $contract->project->name }}</a>
<div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 20px; align-items: end; margin: 28px 0;">
    <div><p class="portal-muted" style="font-size: 12px; text-transform: uppercase;">Zmluva · Verzia {{ $contract->version }}</p><h1 style="font-size: clamp(32px, 6vw, 52px); line-height: 1.05; margin: 10px 0 0; letter-spacing: 0;">{{ $contract->title }}</h1></div>
    <a class="portal-button portal-button--quiet" href="{{ route('client.contracts.download', $contract) }}"><i class="bi bi-download" aria-hidden="true"></i> Stiahnuť PDF</a>
</div>

@if(session('status'))<div class="portal-panel" role="status" style="padding: 16px; margin-bottom: 18px; border-color: #718315;">{{ session('status') }}</div>@endif

<article class="portal-panel" style="padding: clamp(22px, 5vw, 56px); font-family: sans-serif; white-space: pre-wrap; line-height: 1.7;">{{ $contract->rendered_content }}</article>

@if($contract->status === 'accepted')
    <section class="portal-panel" style="margin-top: 18px; padding: clamp(22px, 5vw, 36px); border-top: 6px solid var(--portal-ink);">
        <p class="portal-muted" style="font-size: 12px; text-transform: uppercase;">Dokončené</p><h2 style="font-size: 28px; margin: 10px 0 24px;">Zmluva uzatvorená</h2>
        <dl style="display: grid; grid-template-columns: minmax(110px, 1fr) 2fr; gap: 10px; font-size: 13px;"><dt class="portal-muted">Akceptoval/a</dt><dd style="margin: 0;">{{ $contract->acceptance->signer_name }} za {{ $contract->project->company->name }}</dd><dt class="portal-muted">Dátum a čas</dt><dd style="margin: 0;"><time data-utc="{{ $contract->accepted_at->toIso8601String() }}">{{ $contract->accepted_at->timezone(config('app.timezone'))->format('d.m.Y H:i T') }}</time></dd><dt class="portal-muted">Verzia</dt><dd style="margin: 0;">{{ $contract->version }}</dd></dl>
    </section>
@elseif(in_array($contract->status, ['sent', 'viewed'], true))
    <section class="portal-panel" style="margin-top: 18px; padding: clamp(22px, 5vw, 36px);">
        <p style="font-family: sans-serif;">Dokument prijímate za spoločnosť <strong>{{ $contract->project->company->name }}</strong>.</p>
        @if(auth('client')->user()->can_accept_documents)
            <form method="POST" action="{{ route('client.contracts.accept', $contract) }}" style="margin-top: 24px;">@csrf
                <input type="hidden" name="request_identifier" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="timezone" id="timezone">
                <label style="display: flex; gap: 12px; align-items: start; margin: 16px 0; font-family: sans-serif;"><input type="checkbox" name="read_and_agreed" value="1" required style="margin-top: 4px;"> <span>Prečítal/a som si celý dokument a súhlasím s jeho znením.</span></label>
                <label style="display: flex; gap: 12px; align-items: start; margin: 16px 0 24px; font-family: sans-serif;"><input type="checkbox" name="authorized_to_act" value="1" required style="margin-top: 4px;"> <span>Vyhlasujem, že som oprávnený/á konať za spoločnosť {{ $contract->project->company->name }}.</span></label>
                <button class="portal-button" type="submit">Prijímam a uzatváram zmluvu</button>
            </form>
            <script>document.getElementById('timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone;</script>
        @else
            <p class="portal-muted" style="font-family: sans-serif;">Na prijatie dokumentu nemáte pridelené oprávnenie.</p>
        @endif
    </section>
@endif
@endsection