<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:150'],
            'semester' => ['nullable', 'string', 'max:80'],
            'batch' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'education' => ['nullable', 'string', 'max:6000'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'certifications' => ['nullable', 'string', 'max:6000'],
            'achievements' => ['nullable', 'string', 'max:6000'],
            'experience' => ['nullable', 'string', 'max:6000'],
            'projects' => ['nullable', 'string', 'max:6000'],
            'github' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $profile = array_merge($user->profile ?? [], collect($data)->except(['name', 'email', 'avatar'])->all());
        if ($request->hasFile('avatar')) {
            $oldPath = $user->avatar_path;
            $user->avatar_path = $request->file('avatar')->store('avatars', 'local');
            if ($oldPath) {
                Storage::disk('local')->delete($oldPath);
            }
        }
        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'profile' => $profile])->save();
        $request->session()->forget('resume_draft');
        Activity::create(['user_id' => $user->id, 'action' => 'Profile updated', 'title' => 'Student profile']);

        return back()->with('status', 'Your profile has been saved.');
    }

    public function avatar(Request $request): BinaryFileResponse
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
