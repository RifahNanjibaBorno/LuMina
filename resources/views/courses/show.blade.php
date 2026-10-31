@extends('layouts.workspace')
@section('title', $course->title)
@section('subtitle', $course->semester)
@section('content')
<section class="card course-detail"><h2>Course overview</h2><span class="badge">{{ ucfirst($course->status) }}</span><p class="preserve-lines">{{ $course->description }}</p><p class="muted">{{ $course->teachers->pluck('name')->join(', ') ?: 'No teachers assigned' }} · {{ $course->students->count() }} students</p><div class="form-actions"><a class="button" href="{{ route('repository.index', ['course' => $course->id]) }}">Browse course repository</a>@if($course->status === 'active')<a class="button button-primary" href="{{ route('items.create', ['course' => $course->id]) }}">Upload resource</a>@endif @can('admin')<a class="button" href="{{ route('courses.edit', $course) }}">Edit course</a>@endcan</div></section>
@can('assign-students', $course)<section class="card course-detail"><h2>Course assignments</h2><p class="muted">{{ auth()->user()->can('admin') ? 'Assign teachers and students to this course.' : 'Assign students to this course.' }}</p><button type="button" class="button button-primary" data-dialog-open="course-assignment-dialog">{{ auth()->user()->can('admin') ? 'Assign teachers or students' : 'Assign students' }}</button></section>@endcan
@can('assign-students', $course)
@include('courses.assignment-modal')
@endcan
@endsection
