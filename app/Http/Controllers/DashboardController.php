<?php

namespace App\Http\Controllers;

use App\Models\AcademicItem;
use App\Models\Activity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $courses = Course::query()->withCount('students')->when($user->role === 'teacher', fn ($query) => $query->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $user->id)))->when($user->role === 'student', fn ($query) => $query->whereHas('students', fn ($students) => $students->where('users.id', $user->id)))->orderBy('title')->get();
        $items = AcademicItem::query()->visibleTo($user)->with(['course', 'user'])->when($user->role === 'student', fn ($query) => $query->where('user_id', $user->id))->when($user->role === 'teacher', fn ($query) => $query->whereIn('course_id', $courses->modelKeys()));
        $stats = [
            'projects' => (clone $items)->where('type', 'project')->count(),
            'resources' => AcademicItem::visibleTo($user)->where('type', '!=', 'project')->when($user->role !== 'admin', fn ($query) => $query->whereIn('course_id', $courses->modelKeys()))->count(),
            'featured' => (clone $items)->where('type', 'project')->where('is_featured', true)->count(),
            'users' => User::count(),
            'courses' => $courses->count(),
            'students' => User::whereHas('courses', fn ($query) => $query->whereIn('courses.id', $courses->modelKeys()))->count(),
            'uploads' => AcademicItem::count(),
            'downloads' => AcademicItem::sum('downloads'),
            'storage' => AcademicItem::sum('file_size'),
        ];
        $recentItems = (clone $items)->where('type', 'project')->latest('id')->limit(3)->get();
        $activities = Activity::query()->when($user->role !== 'admin', fn ($query) => $query->where('user_id', $user->id))->latest('id')->limit(5)->get();
        $users = $user->role === 'admin' ? User::orderBy('id')->limit(4)->get() : collect();

        return view('dashboard.'.$user->role, compact('user', 'courses', 'stats', 'recentItems', 'activities', 'users'));
    }
}
