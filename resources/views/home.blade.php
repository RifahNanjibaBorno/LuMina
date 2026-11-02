@extends('layouts.base')
@section('title', 'Home')
@section('body-class', 'landing-body')
@section('body')
<header class="landing-header"><x-brand :light="true" :subtitle="false" /><nav aria-label="Main navigation"><a class="current" href="{{ route('home') }}">Home</a><a href="{{ route('repository.index') }}">Repository</a><a href="#about">About</a>@auth<a class="button button-primary" href="{{ route('dashboard') }}">My workspace</a>@else<a class="button" href="{{ route('login') }}">Log in</a><a class="button button-primary" href="{{ route('register') }}">Create account</a>@endauth</nav></header>
<main class="landing-content">
    <section class="hero">
        <div class="hero-copy"><h1>Turn the academic <br class="hero-break">journey into a portfolio <br class="hero-break">that speaks.</h1><div class="hero-actions"><a class="button button-primary" href="{{ auth()->check() ? (auth()->user()->role === 'student' ? route('portfolio.edit') : route('dashboard')) : route('register') }}">Build my portfolio</a><a class="button" href="{{ route('repository.index') }}">Explore repository</a></div></div>
        <div class="hero-preview"><div class="import-preview"><strong>AI Resume Import</strong><small>PDF / DOCX → structured profile sections</small></div><div class="preview-row">Education &amp; Skills</div><div class="preview-row warm">Projects &amp; Certifications</div><div class="preview-row green">Achievements &amp; Experience</div></div>
    </section>
    <section class="features" id="about"><h2>Everything students and teachers need in one place</h2><div class="feature-grid">
        <article class="feature-card"><span class="feature-icon document-icon" aria-hidden="true"><span class="document-shape"><i></i><i></i><b>✓</b></span></span><h3>Portfolio Builder</h3><p>Generate, edit, customize and share.</p></article>
        <article class="feature-card"><span class="feature-icon books-icon" aria-hidden="true"><i></i><i></i><i></i></span><h3>Academic Repository</h3><p>Store course-wise projects and resources.</p></article>
        <article class="feature-card"><span class="feature-icon search-icon" aria-hidden="true"><img src="{{ asset('assets/figma/search-ring.svg') }}" alt=""><i></i><b>+</b></span><h3>Smart Search</h3><p>Filter by semester, technology and resource type.</p></article>
    </div></section>
</main>
@endsection
