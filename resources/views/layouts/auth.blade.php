@extends('layouts.base')
@section('body-class', 'auth-body')
@section('body')
<main class="auth-layout">
    <section class="auth-story">
        <img class="auth-circle circle-one" src="{{ asset('assets/figma/auth-circle-one.svg') }}" alt="">
        <img class="auth-circle circle-two" src="{{ asset('assets/figma/auth-circle-two.svg') }}" alt="">
        <img class="auth-circle circle-three" src="{{ asset('assets/figma/auth-circle-three.svg') }}" alt="">
        <x-brand :light="true" />
        <div class="auth-story-copy"><h1>One academic identity.<br>Projects, resources and portfolio<br>- all connected.</h1><p>Designed for Students, Teachers and Administrators with secure role-based access.</p>
        <div class="auth-value"><strong>Resume → AI extraction → Portfolio</strong><small>Fast onboarding without retyping your CV.</small></div></div>
    </section>
    <section class="auth-form-area"><div class="auth-card"><h2>@yield('title')</h2><p class="muted">@yield('subtitle')</p><x-flash />@yield('content')</div></section>
</main>
@endsection
