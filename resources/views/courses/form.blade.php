@extends('layouts.workspace')
@section('title', $course->exists ? 'Edit course' : 'Create course')
@section('content')
<form method="post" action="{{ $course->exists ? route('courses.update', $course) : route('courses.store') }}" class="card editor-form course-editor-form">@csrf @if($course->exists)@method('PUT')@endif<div class="form-grid"><label>Semester<input name="semester" value="{{ old('semester', $course->semester ?? config('portfolio.semester')) }}" required maxlength="80"></label><label class="full-width">Course name<input name="title" value="{{ old('title', $course->title) }}" required maxlength="200"></label><label class="full-width">Description<textarea name="description" rows="4">{{ old('description', $course->description) }}</textarea></label><label>Course status<select name="status" required>@foreach(['active', 'inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $course->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label></div>
@unless($course->exists)
<div class="course-assignment-summary" aria-live="polite">
    <span data-assignment-summary="teachers">{{ count(old('teachers', $course->teachers->modelKeys()) ?? []) }} teachers selected</span>
    <span data-assignment-summary="students">{{ count(old('students', $course->students->modelKeys()) ?? []) }} students selected</span>
</div>
@endunless
<div class="form-actions course-assignment-actions">
    <a class="button" href="{{ route('courses.index') }}">Cancel</a>
    @unless($course->exists)
    <label class="sr-only" for="assignment-role">Assign teachers or students</label>
    <select id="assignment-role" data-assignment-role aria-label="Assign teachers or students">
        <option value="">Assign to course</option>
        <option value="teachers">Teacher</option>
        <option value="students">Student</option>
    </select>
    @endunless
    <button class="button button-primary">{{ $course->exists ? 'Save course' : 'Create course' }}</button>
</div>
@unless($course->exists)
@foreach(['teachers' => $teachers, 'students' => $students] as $assignmentRole => $members)
    <div data-assignment-values="{{ $assignmentRole }}">
        @foreach(old($assignmentRole, $course->{$assignmentRole}->modelKeys()) ?? [] as $memberId)
            <input type="hidden" name="{{ $assignmentRole }}[]" value="{{ $memberId }}">
        @endforeach
    </div>
@endforeach
@endunless
</form>
@unless($course->exists)
@foreach(['teachers' => $teachers, 'students' => $students] as $assignmentRole => $members)
<dialog id="assign-{{ $assignmentRole }}" class="course-assignment-dialog" aria-labelledby="assign-{{ $assignmentRole }}-title" data-assignment-dialog="{{ $assignmentRole }}">
    <div class="dialog-heading"><h2 id="assign-{{ $assignmentRole }}-title">Assign {{ $assignmentRole }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Close">&times;</button></div>
    <p class="muted">Select {{ $assignmentRole }} for this course. Save the course to apply your assignments.</p>
    <div class="assignment-table-scroll"><table class="assignment-table"><thead><tr><th scope="col" class="assignment-checkbox-cell">Select</th><th scope="col">{{ $assignmentRole === 'teacher' || $assignmentRole === 'teachers' ? 'Teacher' : 'Student' }} details</th></tr></thead><tbody>
        @forelse($members as $member)
        <tr><td class="assignment-checkbox-cell"><input aria-label="Select {{ $member->name }}" type="checkbox" value="{{ $member->id }}" data-assignment-member></td><td class="assignment-details"><strong>{{ $member->name }}</strong><small>{{ $member->email }}@if($member->university_id) &middot; {{ $member->university_id }}@endif</small></td></tr>
        @empty
        <tr><td colspan="2" class="muted">No active {{ $assignmentRole }} available. <a href="{{ route('users.create') }}">Create a user</a> first.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="form-actions"><button type="button" class="button" data-dialog-close>Cancel</button><button type="button" class="button button-primary" data-assignment-apply>Assign</button></div>
</dialog>
@endforeach
@endunless
@endsection
