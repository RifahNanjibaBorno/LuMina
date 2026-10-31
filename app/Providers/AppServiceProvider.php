<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => $user->role === 'admin');
        Gate::define('assign-students', fn (User $user, Course $course): bool => $user->role === 'admin' || ($user->role === 'teacher' && $course->teachers()->whereKey($user->id)->exists()));
        Gate::define('view-user-profile', fn (User $viewer, User $subject): bool => $viewer->role === 'admin' || ($viewer->role === 'teacher' && $subject->role === 'student' && $subject->courses()->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $viewer->id))->exists()));
        Gate::define('student', fn (User $user): bool => $user->role === 'student');
        Gate::define('staff', fn (User $user): bool => in_array($user->role, ['teacher', 'admin'], true));
    }
}
