<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\GoogleAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function analytics(Request $request, GoogleAnalytics $analytics): View
    {
        Gate::authorize('admin');
        $validated = $request->validate(['days' => ['sometimes', 'integer', 'in:7,30,90']]);
        $days = (int) ($validated['days'] ?? 30);
        $configured = $analytics->configured();
        $report = null;
        $error = null;

        if ($configured) {
            try {
                $report = $analytics->report($days);
            } catch (\Exception) {
                $error = 'Google Analytics could not be loaded. Check the service account credentials, property access and Google Analytics Data API, then try again.';
            }
        }

        return view('admin.analytics', [
            'days' => $days,
            'configured' => $configured,
            'report' => $report,
            'error' => $error,
            'propertyId' => config('services.google_analytics.property_id'),
        ]);
    }

    public function users(Request $request): View
    {
        Gate::authorize('staff');
        $users = User::query()->when($request->user()->role === 'teacher', fn ($q) => $q->where('role', 'student')->whereHas('courses', fn ($courses) => $courses->whereHas('teachers', fn ($teachers) => $teachers->where('users.id', $request->user()->id))))
            ->orderBy('name')->get();

        return view('admin.users', compact('users'));
    }

    public function create(): View
    {
        Gate::authorize('admin');

        return view('admin.create-user');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'university_id' => ['required', 'string', 'max:30', 'unique:users,university_id'],
            'role' => ['required', 'in:teacher,student'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);
        $user = new User;
        $user->fill(collect($data)->except('role')->all());
        $user->role = $data['role'];
        $user->is_active = true;
        $user->portfolio_slug = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $user->save();
        Activity::create(['user_id' => $request->user()->id, 'action' => 'User created', 'title' => $user->name]);

        return redirect()->route('users.index')->with('status', ucfirst($user->role).' account created.');
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view-user-profile', $user);
        $items = $user->items()->visibleTo($request->user())->when($request->user()->role === 'teacher', fn ($items) => $items->whereHas('course.teachers', fn ($teachers) => $teachers->where('users.id', $request->user()->id)))->with('course')->latest('id')->get();

        return view('admin.profile', compact('user', 'items'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('admin');

        return view('admin.user', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)], 'role' => ['required', 'in:student,teacher,admin'], 'is_active' => ['required', 'boolean']]);
        if ($request->user()->id === $user->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'You cannot disable or demote your own administrator account.']);
        }
        $user->forceFill($data)->save();
        Activity::create(['user_id' => $request->user()->id, 'action' => 'User access updated', 'title' => $user->name]);

        return redirect()->route('users.index')->with('status', 'User details and access updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('admin');
        abort_if($request->user()->id === $user->id, 403);
        $paths = $user->items()->get()->flatMap(fn ($item): array => array_column($item->uploadedFiles(), 'path'))->all();
        foreach (array_filter(array_merge($paths, [$user->resume_path, $user->avatar_path])) as $path) {
            Storage::disk('local')->delete($path);
        }
        Activity::create(['user_id' => $request->user()->id, 'action' => 'User removed', 'title' => $user->name]);
        $user->delete();

        return redirect()->route('users.index')->with('status', 'User and their uploads removed.');
    }

    public function monitoring(): View
    {
        Gate::authorize('admin');
        $activities = Activity::latest('id')->paginate(25);

        return view('admin.monitoring', compact('activities'));
    }
}
