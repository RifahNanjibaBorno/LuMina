<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCourseRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::with(['teachers'])->withCount(['students', 'items'])
            ->when($request->user()->role === 'teacher', fn ($q) => $q->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $request->user()->id)))
            ->orderBy('title')->get();

        $assignmentCourses = Course::with(['teachers', 'students'])->when($request->user()->role === 'teacher', fn ($query) => $query->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $request->user()->id)))->orderBy('title')->get();
        $teachers = $request->user()->can('admin') ? User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get() : collect();
        $students = User::where('role', 'student')->where('is_active', true)->orderBy('name')->get();

        return view('courses.index', compact('courses', 'assignmentCourses', 'teachers', 'students'));
    }

    public function assign(Request $request): RedirectResponse
    {
        Gate::authorize('staff');
        abort_unless($request->user()->can('admin') || $request->input('role') === 'student', 403);
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'role' => ['required', Rule::in(['teacher', 'student'])],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', $request->input('role'))->where('is_active', true)],
            'return_to_course' => ['nullable', 'boolean'],
        ]);
        $course = Course::findOrFail($data['course_id']);
        Gate::authorize($data['role'] === 'teacher' ? 'admin' : 'assign-students', $course);
        $relationship = $data['role'] === 'teacher' ? 'teachers' : 'students';
        if ($request->user()->can('admin')) {
            $course->{$relationship}()->syncOrFail($data['members'] ?? []);
        } else {
            $course->students()->syncWithoutDetachingOrFail($data['members'] ?? []);
        }

        return redirect()->route($request->boolean('return_to_course') ? 'courses.show' : 'courses.index', $request->boolean('return_to_course') ? [$course] : [])->with('status', ucfirst($relationship).' assigned to '.$course->title.'.');
    }

    public function create(): View
    {
        Gate::authorize('admin');

        return $this->form(new Course);
    }

    public function edit(Course $course): View
    {
        Gate::authorize('admin');

        return $this->form($course);
    }

    private function form(Course $course): View
    {
        $teachers = User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get();
        $students = User::where('role', 'student')->where('is_active', true)->orderBy('name')->get();
        $course->load(['teachers', 'students']);

        return view('courses.form', compact('course', 'teachers', 'students'));
    }

    public function store(SaveCourseRequest $request): RedirectResponse
    {
        $course = DB::transaction(function () use ($request): Course {
            $course = Course::create($request->safe()->except(['teachers', 'students']));
            if ($request->exists('teachers')) {
                $course->teachers()->sync($request->validated('teachers') ?? []);
            }
            if ($request->exists('students')) {
                $course->students()->sync($request->validated('students') ?? []);
            }

            return $course;
        });

        return redirect()->route('courses.show', $course)->with('status', 'Course and assignments created.');
    }

    public function update(SaveCourseRequest $request, Course $course): RedirectResponse
    {
        DB::transaction(function () use ($request, $course): void {
            $course->update($request->safe()->except(['teachers', 'students']));
            if ($request->exists('teachers')) {
                $course->teachers()->sync($request->validated('teachers') ?? []);
            }
            if ($request->exists('students')) {
                $course->students()->sync($request->validated('students') ?? []);
            }
        });

        return redirect()->route('courses.show', $course)->with('status', 'Course updated.');
    }

    public function show(Request $request, Course $course): View
    {
        abort_unless($course->isAccessibleTo($request->user()), 403);
        $course->load(['teachers', 'students']);
        $teachers = $request->user()->role === 'admin' ? User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get() : collect();
        $students = $request->user()->can('assign-students', $course) ? User::where('role', 'student')->where('is_active', true)->orderBy('name')->get() : collect();

        return view('courses.show', compact('course', 'teachers', 'students'));
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('assign-students', $course);
        $data = $request->validate(['students' => ['nullable', 'array'], 'students.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)]]);
        if ($request->user()->can('admin')) {
            $course->students()->syncOrFail($data['students'] ?? []);
        } else {
            $course->students()->syncWithoutDetachingOrFail($data['students'] ?? []);
        }
        Activity::create(['user_id' => $request->user()->id, 'action' => 'Enrollment updated', 'title' => $course->title, 'course_code' => (string) $course->id]);

        return back()->with('status', 'Student assignments saved.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('admin');
        foreach ($course->items()->get() as $item) {
            Storage::disk('local')->delete(array_column($item->uploadedFiles(), 'path'));
        }
        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Course and its repository entries removed.');
    }
}
