@extends('layouts.client', ['title' => 'Prihlásenie'])

@section('content')
<div
    id="client-login"
    data-status="{{ session('status') }}"
    data-error="{{ $errors->first() }}"
    data-email="{{ old('email') }}"
></div>
@endsection