<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\ResumeParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public const SECTIONS = ['bio', 'education', 'skills', 'projects', 'certifications', 'achievements', 'experience', 'links'];

    public function edit(Request $request): View
    {
        $user = $request->user();
        $projects = $user->items()->where('type', 'project')->with('course')->latest('id')->get();

        return view('portfolio.builder', compact('user', 'projects'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'portfolio_slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('users')->ignore($user)],
            'portfolio_public' => ['nullable', 'boolean'],
            'visible_sections' => ['nullable', 'array'],
            'visible_sections.*' => [Rule::in(self::SECTIONS)],
            'featured' => ['nullable', 'array'],
            'featured.*' => ['integer', Rule::exists('academic_items', 'id')->where('user_id', $user->id)->where('type', 'project')],
        ]);
        DB::transaction(function () use ($user, $data, $request): void {
            $user->update(['portfolio_slug' => $data['portfolio_slug'], 'portfolio_public' => $request->boolean('portfolio_public'), 'visible_sections' => $data['visible_sections'] ?? []]);
            $user->items()->where('type', 'project')->update(['is_featured' => false]);
            $user->items()->where('type', 'project')->whereIn('id', $data['featured'] ?? [])->update(['is_featured' => true]);
            Activity::create(['user_id' => $user->id, 'action' => 'Portfolio saved', 'title' => 'Portfolio visibility and projects']);
        });

        return back()->with('status', 'Your portfolio has been saved.');
    }

    public function import(Request $request, ResumeParser $parser): RedirectResponse
    {
        $request->validate(['resume' => ['required', 'file', 'mimes:pdf,docx', 'extensions:pdf,docx', 'max:5120']]);
        $data = $parser->extract($request->file('resume'));
        $request->session()->put('resume_draft', $data);
        $user = $request->user();
        $oldPath = $user->resume_path;
        $user->update(['resume_path' => $request->file('resume')->store('resumes', 'local'), 'resume_name' => Str::limit(basename($request->file('resume')->getClientOriginalName()), 240, '')]);
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('profile.edit')->with('status', 'Resume extracted. Review the imported fields, then save your profile. Your existing details have not been overwritten.');
    }

    public function preview(Request $request): View
    {
        return $this->renderPortfolio($request->user(), true);
    }

    public function show(string $slug): View
    {
        $user = User::where('portfolio_slug', $slug)->where('portfolio_public', true)->where('is_active', true)->firstOrFail();
        $user->increment('portfolio_views');

        return $this->renderPortfolio($user, false);
    }

    private function renderPortfolio(User $user, bool $preview): View
    {
        $projects = $user->items()->where('type', 'project')->where('is_featured', true)->with('course')->latest('id')->get();

        return view('portfolio.public', compact('user', 'projects', 'preview'));
    }
}
