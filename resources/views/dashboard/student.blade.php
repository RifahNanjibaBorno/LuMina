@extends('layouts.workspace')
@section('title', 'Student Dashboard')
@section('content')
<section class="greeting"><h2>Hello, {{ explode(' ', $user->name)[0] }}</h2><p>Here is a quick view of your academic profile and repository activity.</p></section>
<div class="stats-grid">
    <x-stat label="Portfolio completion" :value="$user->completion().'%'" description="Review your profile sections" />
    <x-stat label="Projects" :value="$stats['projects']" :description="'Across '.$stats['courses'].' courses'" />
    <x-stat label="Resources" :value="$stats['resources']" description="Notes, reports and exams" />
    <x-stat label="Public views" :value="$user->portfolio_views" :description="$user->portfolio_public ? 'Share link enabled' : 'Share link disabled'" />
</div>
<div class="dashboard-columns student-columns">
    <section class="card readiness"><h2>Portfolio readiness</h2><p class="muted">Complete these items before sharing</p><div class="readiness-items">
        @foreach(['experience' => 'Add work experience', 'skills' => 'Review extracted skills', 'projects' => 'Choose featured projects', 'visibility' => 'Set visibility preferences'] as $key => $label)
        <div class="readiness-row"><span class="check-box {{ ($key === 'projects' ? $stats['featured'] > 0 : ($key === 'visibility' ? $user->portfolio_public : !empty($user->profile[$key]))) ? 'checked' : '' }}" aria-hidden="true">✓</span><a href="{{ $key === 'skills' || $key === 'experience' ? route('profile.edit').'#'.$key : route('portfolio.edit') }}">{{ $label }}</a></div>
        @endforeach
    </div><a class="button button-teal continue-button" href="{{ route('portfolio.edit') }}">Continue editing</a></section>
    <section class="card quick-actions"><h2>Quick actions</h2><a href="{{ route('portfolio.edit') }}#resume">Upload resume</a><a href="{{ route('items.create', ['type' => 'project']) }}">Add project</a><a href="{{ route('repository.index') }}">Browse repository</a><button type="button" data-copy="{{ route('portfolio.public', $user->portfolio_slug ?? 'unpublished') }}" @disabled(!$user->portfolio_public)>Share portfolio</button></section>
</div>
<section class="card recent-activity"><h2>Recent academic activity</h2>
    @forelse($activities as $activity)<div class="activity-row"><strong>{{ $activity->course_code ?? 'Profile' }}</strong><span>{{ $activity->title }}</span><span class="muted">{{ $activity->action }}</span><small>{{ $activity->created_at->diffForHumans() }}</small></div>@empty<x-empty title="Your activity will appear here" description="Add a project, import a resume or explore your course resources." />@endforelse
</section>
@endsection
