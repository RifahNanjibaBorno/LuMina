<dialog id="course-assignment-dialog" class="course-assignment-dialog" aria-labelledby="course-assignment-title">
    <form method="post" action="{{ route('courses.assign') }}">
        @csrf
        <input type="hidden" name="course_id" value="{{ $course->id }}">
        <input type="hidden" name="return_to_course" value="1">
        <div class="dialog-heading"><h2 id="course-assignment-title">{{ auth()->user()->can('admin') ? 'Assign teachers or students' : 'Assign students' }}</h2><button type="button" class="dialog-close" data-dialog-close aria-label="Close">&times;</button></div>
        <p>{{ $course->title }}</p>
        @can('admin')
        <label>Assign<select name="role" required data-course-assignment-role><option value="">Select Teacher or Student</option><option value="teacher">Teacher</option><option value="student">Student</option></select></label>
        @else
        <input type="hidden" name="role" value="student">
        @endcan
        @foreach((auth()->user()->can('admin') ? ['teacher' => $teachers, 'student' => $students] : ['student' => $students]) as $assignmentRole => $members)
        <div data-course-member-group="{{ $assignmentRole }}" @if(auth()->user()->can('admin')) hidden @endif>
            <p class="muted">Select the {{ $assignmentRole === 'teacher' ? 'teachers' : 'students' }} for this course. @can('admin')Unchecked users will be unassigned.@else Already assigned students cannot be removed. Only administrators can unassign students.@endcan</p>
            <div class="assignment-table-scroll"><table class="assignment-table"><thead><tr><th scope="col" class="assignment-checkbox-cell">Select</th><th scope="col">{{ ucfirst($assignmentRole) }} details</th></tr></thead><tbody>
                @forelse($members as $member)
                <tr><td class="assignment-checkbox-cell"><input type="checkbox" name="members[]" value="{{ $member->id }}" aria-label="Select {{ $member->name }}" @checked($course->{$assignmentRole === 'teacher' ? 'teachers' : 'students'}->contains($member)) @disabled(auth()->user()->can('admin') || $course->students->contains($member))></td><td class="assignment-details"><strong>{{ $member->name }}</strong><small>{{ $member->email }}@if($member->university_id) &middot; {{ $member->university_id }}@endif</small></td></tr>
                @empty
                <tr><td colspan="2">No active users available. @can('admin')<a href="{{ route('users.create') }}">Create a user</a> first.@else Contact an administrator.@endcan</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        @endforeach
        <div class="form-actions"><button type="button" class="button" data-dialog-close>Cancel</button><button class="button button-primary">Assign</button></div>
    </form>
</dialog>
