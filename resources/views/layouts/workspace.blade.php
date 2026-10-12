@extends('layouts.base')
@section('body-class', 'workspace-body')
@section('body')
@php
    $account = auth()->user();
    $role = $account?->role ?? 'guest';
    $navigation = match($role) {
        'admin' => [['dashboard', 'Overview'], ['users.index', 'Users'], ['courses.index', 'Courses'], ['repository.index', 'Resources'], ['monitoring', 'Monitoring']],
        'teacher' => [['dashboard', 'Dashboard'], ['courses.index', 'Courses'], ['users.index', 'Students'], ['projects.index', 'Projects'], ['repository.index', 'Resources']],
        'student' => [['dashboard', 'Dashboard'], ['profile.edit', 'My Profile'], ['portfolio.edit', 'Portfolio Builder'], ['projects.index', 'Projects'], ['repository.index', 'Repository']],
        default => [['home', 'Home'], ['repository.index', 'Repository'], ['login', 'Log in'], ['register', 'Create account']],
    };
@endphp
<aside class="sidebar" id="workspace-navigation">
    <x-brand :light="true" />
    <nav aria-label="Workspace navigation">
        @foreach($navigation as [$route, $label])
        <a href="{{ route($route) }}" class="nav-item {{ request()->routeIs($route, str_replace('.index', '.*', $route)) ? 'active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif><span>{{ $loop->iteration }}.</span>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="sidebar-footer"><p>{{ ucfirst($role) }} Workspace</p><strong>{{ config('portfolio.semester') }}</strong>
        @auth<form action="{{ route('logout') }}" method="post">@csrf<button class="logout-button">Log out</button></form>@endauth
    </div>
</aside>
<main class="workspace">
    <header class="workspace-header">
        <div><button class="menu-button" type="button" data-menu-toggle aria-controls="workspace-navigation" aria-expanded="false">☰ <span>Menu</span></button><h1>@yield('title')</h1><p>@yield('subtitle', 'Automated portfolio & academic repository')</p></div>
        @auth<a class="account-chip" href="{{ route('profile.edit') }}"><span><strong>{{ $account->name }}</strong><small>{{ ucfirst($role) }}</small></span><span class="avatar-mini">@if($account->avatar_path)<img src="{{ route('profile.avatar') }}" alt="">@endif</span></a>@else<a class="button" href="{{ route('login') }}">Log in</a>@endauth
    </header>
    <div class="workspace-content"><x-flash />@yield('content')</div>
</main>
@endsection
