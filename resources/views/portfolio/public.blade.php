@extends('layouts.base')
@section('title', $user->name.' — Portfolio')
@section('body-class', 'public-portfolio-body')
@section('body')
@php($sections = $user->visible_sections ?? App\Http\Controllers\PortfolioController::SECTIONS)
@php($profile = $user->profile ?? [])
<header class="public-header"><x-brand :subtitle="false" /><div class="public-actions">@if($preview)<a class="button" href="{{ route('portfolio.edit') }}">Back to builder</a><span class="badge">Private preview</span>@endif<button class="button" type="button" data-print>Print / Save PDF</button></div></header>
<main class="public-portfolio"><section class="portfolio-intro"><div class="avatar-large">{{ $user->initials() }}</div><div><p class="eyebrow">Student portfolio</p><h1>{{ $user->name }}</h1><p>{{ $profile['department'] ?? 'Academic portfolio' }}</p>@if(in_array('bio', $sections) && !empty($profile['bio']))<p class="portfolio-bio">{{ $profile['bio'] }}</p>@endif</div></section>
@foreach(['education', 'skills', 'certifications', 'achievements', 'experience'] as $section)@if(in_array($section, $sections) && !empty($profile[$section]))<section class="card portfolio-section"><h2>{{ ucfirst($section) }}</h2><p class="preserve-lines">{{ $profile[$section] }}</p></section>@endif @endforeach
@if(in_array('projects', $sections))<section class="portfolio-section"><h2>Featured projects</h2><div class="public-project-grid">@forelse($projects as $project)<article class="card"><span class="badge">{{ $project->course->title }}</span><h3>{{ $project->title }}</h3><p>{{ $project->description }}</p><small>{{ $project->technology }}</small>@if($project->source_url)<p><a href="{{ $project->source_url }}" target="_blank" rel="noopener noreferrer">View source code →</a></p>@endif</article>@empty<p class="muted">No projects featured yet.</p>@endforelse</div>@if(!empty($profile['projects']))<p class="preserve-lines">{{ $profile['projects'] }}</p>@endif</section>@endif
@if(in_array('links', $sections) && (!empty($profile['github']) || !empty($profile['linkedin'])))<section class="card portfolio-section"><h2>Connect</h2><div class="public-actions">@foreach(['github' => 'GitHub', 'linkedin' => 'LinkedIn'] as $key => $label)@if(!empty($profile[$key]) && preg_match('/^https?:\/\//', $profile[$key]))<a class="button" href="{{ $profile[$key] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endif @endforeach</div></section>@endif
<footer class="portfolio-footer">Built with LUMiNA · Academic Portfolio &amp; Repository</footer></main>
@endsection
