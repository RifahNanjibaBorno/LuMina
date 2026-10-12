<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCourseRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $courses = Course::with(['teacher'])->withCount(['students', 'items'])
            ->when($request->user()->role === 'teacher', fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->orderBy('code')->paginate(12);

        return view('courses.index', compact('courses'));
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

        return view('courses.form', compact('course', 'teachers'));
    }

    public function store(SaveCourseRequest $request): RedirectResponse
    {
        $course = Course::create($request->validated());

        return redirect()->route('courses.show', $course)->with('status', 'Course created. Assign students below.');
    }

    public function update(SaveCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated() + ['is_archived' => false]);

        return redirect()->route('courses.show', $course)->with('status', 'Course updated.');
    }

    public function show(Request $request, Course $course): View
    {
        abort_unless($course->isAccessibleTo($request->user()), 403);
        $course->load(['teacher', 'students']);
        $items = $course->items()->visibleTo($request->user())->with('user')->latest('id')->paginate(10);
        $students = $request->user()->role === 'admin' ? User::where('role', 'student')->where('is_active', true)->orderBy('name')->get() : collect();

        return view('courses.show', compact('course', 'items', 'students'));
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $request->validate(['students' => ['nullable', 'array'], 'students.*' => ['integer', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)]]);
        $course->students()->sync($data['students'] ?? []);
        Activity::create(['user_id' => $request->user()->id, 'action' => 'Enrollment updated', 'title' => $course->title, 'course_code' => $course->code]);

        return back()->with('status', 'Student assignments saved.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        Gate::authorize('admin');
        foreach ($course->items()->whereNotNull('file_path')->pluck('file_path') as $path) {
            Storage::disk('local')->delete($path);
        }
        $course->delete();

        return redirect()->route('courses.index')->with('status', 'Course and its repository entries removed.');
    }
}
