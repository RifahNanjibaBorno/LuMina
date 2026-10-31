@extends('layouts.workspace')
@section('title', 'Courses')
@section('subtitle', 'Course-wise projects, resources and student memberships.')
@section('content')
<div class="result-heading"><h2>{{ auth()->user()->role === 'teacher' ? 'Assigned courses' : 'Course management' }}</h2>@can('staff')<div class="course-management-actions"><label class="sr-only" for="course-management-role">Assign to course</label><select id="course-management-role" data-management-role><option value="">Assign to course</option>@can('admin')<option value="teacher">Teacher</option>@endcan<option value="student">Student</option></select>@can('admin')<a class="button button-primary" href="{{ route('courses.create') }}">Create course</a>@endcan</div>@endcan</div><section class="card courses-data-card"><div class="table-scroll">
<table id="courses-table" class="display" data-datatable>
    <thead><tr><th>Course name</th><th>Semester</th><th>Assigned teachers</th><th>Students</th><th>Resources</th><th>Status</th>@can('admin')<th data-orderable="false" data-searchable="false">Actions</th>@endcan</tr></thead>
    <tbody>
    @foreach($courses as $course)
        <tr>
            <td><a class="course-name-link" href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></td>
            <td>{{ $course->semester }}</td>
            <td>{{ $course->teachers->pluck('name')->join(', ') ?: 'Not assigned' }}</td>
            <td>{{ $course->students_count }}</td>
            <td>{{ $course->items_count }}</td>
            <td><span class="badge {{ $course->status === 'active' ? 'badge-green' : 'badge-red' }}">{{ ucfirst($course->status) }}</span></td>
            @can('admin')
            <td><div class="course-table-actions">
                <a class="button button-primary btn-primary" href="{{ route('courses.edit', $course) }}">Edit</a>
                <form action="{{ route('courses.destroy', $course) }}" method="post" data-confirm="Delete this course, its teacher and student assignments, and all resources and attached files permanently?">
                    @csrf @method('DELETE')
                    <button type="submit" class="button button-danger btn-danger" title="Delete course">Delete</button>
                </form>
            </div></td>
            @endcan
        </tr>
    @endforeach
    </tbody>
</table>
</div></section>
@can('staff')
@foreach((auth()->user()->can('admin') ? ['teacher' => $teachers, 'student' => $students] : ['student' => $students]) as $assignmentRole => $members)
<dialog id="manage-{{ $assignmentRole }}" class="course-assignment-dialog" aria-labelledby="manage-{{ $assignmentRole }}-title" data-management-dialog>
    <form method="post" action="{{ route('courses.assign') }}">
        @csrf
        <input type="hidden" name="role" value="{{ $assignmentRole }}">
        <div class="dialog-heading"><h2 id="manage-{{ $assignmentRole }}-title">Assign {{ $assignmentRole === 'teacher' ? 'teachers' : 'students' }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Close">&times;</button></div>
        <label>Course<select name="course_id" required data-management-course data-add-only="{{ auth()->user()->can('admin') ? 'false' : 'true' }}"><option value="">Select a course</option>@foreach($assignmentCourses as $assignmentCourse)<option value="{{ $assignmentCourse->id }}" data-members="{{ $assignmentCourse->{$assignmentRole === 'teacher' ? 'teachers' : 'students'}->modelKeys() ? json_encode($assignmentCourse->{$assignmentRole === 'teacher' ? 'teachers' : 'students'}->modelKeys()) : '[]' }}">{{ $assignmentCourse->title }}</option>@endforeach</select></label>
        <p class="muted">Choose a course, then select the {{ $assignmentRole === 'teacher' ? 'teachers' : 'students' }} to assign. @can('admin')Unchecked users will be unassigned from this course.@else Already assigned students cannot be removed. Only administrators can unassign students.@endcan</p>
        <div class="assignment-table-scroll"><table class="assignment-table"><thead><tr><th scope="col" class="assignment-checkbox-cell">Select</th><th scope="col">{{ $assignmentRole === 'teacher' || $assignmentRole === 'teachers' ? 'Teacher' : 'Student' }} details</th></tr></thead><tbody>
            @forelse($members as $member)
            <tr><td class="assignment-checkbox-cell"><input aria-label="Select {{ $member->name }}" type="checkbox" name="members[]" value="{{ $member->id }}" data-management-member disabled></td><td class="assignment-details"><strong>{{ $member->name }}</strong><small>{{ $member->email }}@if($member->university_id) &middot; {{ $member->university_id }}@endif</small></td></tr>
            @empty
            <tr><td colspan="2" class="muted">No active users available. @can('admin')<a href="{{ route('users.create') }}">Create a user</a> first.@else Contact an administrator.@endcan</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="form-actions"><button type="button" class="button" data-dialog-close>Cancel</button><button class="button button-primary">Assign</button></div>
    </form>
</dialog>
@endforeach
@endcan
@endsection
