@extends('layouts.workspace')
@section('title', 'System monitoring')
@section('subtitle', 'Upload, download and account activity across LUMiNA.')
@section('content')
<section class="card"><h2>Activity log</h2>@forelse($activities as $activity)<div class="activity-row"><strong>{{ $activity->course_code ?? 'System' }}</strong><span>{{ $activity->title }}</span><span class="muted">{{ $activity->action }}</span><small>{{ $activity->created_at->format('M j, Y H:i') }}</small></div>@empty<x-empty title="No activity recorded" description="Repository and account actions will appear here." />@endforelse<div class="pagination">{{ $activities->links() }}</div></section>
@endsection
