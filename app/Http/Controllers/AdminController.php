<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function users(Request $request): View
    {
        Gate::authorize('staff');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $users = User::query()->when($request->user()->role === 'teacher', fn ($q) => $q->where('role', 'student')->whereHas('courses', fn ($courses) => $courses->where('teacher_id', $request->user()->id)))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$request->string('q')->toString().'%')->orWhere('email', 'like', '%'.$request->string('q')->toString().'%')->orWhere('university_id', 'like', '%'.$request->string('q')->toString().'%')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.users', compact('users'));
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
        $paths = $user->items()->whereNotNull('file_path')->pluck('file_path')->all();
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
