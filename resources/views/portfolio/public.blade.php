@extends('layouts.base')
@section('title', $user->name.' — Portfolio')
@section('body-class', 'public-portfolio-body resume-page')
@section('body')
@php
    $sections = $user->visible_sections ?? App\Http\Controllers\PortfolioController::SECTIONS;
    $profile = $user->profile ?? [];
    $skills = preg_split('/[\r\n,;]+/u', $profile['skills'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
@endphp
<nav class="resume-toolbar" aria-label="Portfolio actions">
    <x-brand :subtitle="false" />
    <div class="public-actions">
        @if($preview)
            <a class="button" href="{{ route('portfolio.edit') }}">Back to builder</a>
            <span class="badge">Private preview</span>
        @endif
        <button class="button" type="button" data-print>Print / Save PDF</button>
    </div>
</nav>
<main class="resume-sheet">
    <header class="resume-masthead">
        <div class="resume-portrait">
            @if($user->avatar_path)
                <img src="{{ $preview ? route('profile.avatar') : route('portfolio.avatar', $user->portfolio_slug) }}" alt="{{ $user->name }}'s portrait">
            @else
                <span aria-label="{{ $user->name }}'s initials">{{ $user->initials() }}</span>
            @endif
        </div>
        <div class="resume-identity">
            <h1>{{ $user->name }}</h1>
            <p>{{ $profile['department'] ?? 'Student portfolio' }}</p>
        </div>
    </header>
    <div class="resume-columns">
        <aside class="resume-sidebar" aria-label="Contact and skills">
            @if(in_array('links', $sections))
                <section class="resume-side-section">
                    <h2>Contact</h2>
                    <ul class="resume-contact">
                        @if(!empty($profile['phone']))
                            <li><span aria-hidden="true">☎</span><span>{{ $profile['phone'] }}</span></li>
                        @endif
                        <li><span aria-hidden="true">✉</span><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></li>
                        @foreach(['github' => 'GitHub', 'linkedin' => 'LinkedIn'] as $key => $label)
                            @if(!empty($profile[$key]) && preg_match('/^https?:\/\//', $profile[$key]))
                                <li><span aria-hidden="true">↗</span><a href="{{ $profile[$key] }}" target="_blank" rel="noopener noreferrer">{{ $label }}<small>{{ preg_replace('~^https?://(?:www\.)?~', '', $profile[$key]) }}</small></a></li>
                            @endif
                        @endforeach
                    </ul>
                </section>
            @endif
            @if(in_array('skills', $sections) && !empty($profile['skills']))
                <section class="resume-side-section">
                    <h2>Skills</h2>
                    <ul class="resume-skills">
                        @foreach($skills as $skill)<li>{{ trim($skill) }}</li>@endforeach
                    </ul>
                </section>
            @endif
            @foreach(['certifications' => 'Certifications', 'achievements' => 'Achievements'] as $key => $label)
                @if(in_array($key, $sections) && !empty($profile[$key]))
                    <section class="resume-side-section">
                        <h2>{{ $label }}</h2>
                        <p class="preserve-lines">{{ $profile[$key] }}</p>
                    </section>
                @endif
            @endforeach
        </aside>
        <div class="resume-main">
            @foreach(['bio' => 'Profile', 'experience' => 'Work experience', 'education' => 'Education', 'projects' => 'Projects'] as $key => $label)
                @if(in_array($key, $sections) && (!empty($profile[$key]) || ($key === 'projects' && $projects->isNotEmpty())))
                    <section class="resume-section">
                        <span class="resume-section-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                @if($key === 'bio')
                                    <circle cx="12" cy="8" r="3"/><path d="M5 20v-2a7 7 0 0 1 14 0v2z"/>
                                @elseif($key === 'education')
                                    <path d="m2 9 10-5 10 5-10 5zM6 11v6c4 3 8 3 12 0v-6M22 9v8"/>
                                @elseif($key === 'experience')
                                    <rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 12c5 3 13 3 18 0M10 13h4"/>
                                @else
                                    <path d="M3 7h7l2 3h9v10H3zM3 7V4h7l2 3h9v3"/>
                                @endif
                            </svg>
                        </span>
                        <h2>{{ $label }}</h2>
                        @if(!empty($profile[$key]))
                            <div class="resume-entry"><p class="preserve-lines">{{ $profile[$key] }}</p></div>
                        @endif
                        @if($key === 'projects')
                            @foreach($projects as $project)
                                <article class="resume-entry resume-project">
                                    <h3>{{ $project->title }}</h3>
                                    <p class="resume-project-meta">{{ $project->course?->title }}@if($project->technology) · {{ $project->technology }}@endif</p>
                                    <p class="preserve-lines">{{ $project->description }}</p>
                                    @if($project->source_url && preg_match('/^https?:\/\//', $project->source_url))
                                        <a class="resume-source" href="{{ $project->source_url }}" target="_blank" rel="noopener noreferrer">View source code <span aria-hidden="true">↗</span></a>
                                    @endif
                                </article>
                            @endforeach
                        @endif
                    </section>
                @endif
            @endforeach
        </div>
    </div>
    <footer class="resume-footer">Built with LUMiNA · Academic Portfolio &amp; Repository</footer>
</main>
@endsection
