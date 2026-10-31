<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAcademicItemRequest;
use App\Models\AcademicItem;
use App\Models\Activity;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'semester' => ['nullable', 'string', 'max:80'], 'technology' => ['nullable', 'string', 'max:100'], 'domain' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', 'in:project,report,notes,exam,assignment,notice,material'], 'course' => ['nullable', 'integer']]);
        $query = AcademicItem::query()->visibleTo($request->user())->with(['course', 'user']);
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function (Builder $search) use ($term): void {
                $search->where('title', 'like', $term)->orWhere('description', 'like', $term)->orWhere('technology', 'like', $term)
                    ->orWhereHas('course', fn (Builder $course): Builder => $course->where('title', 'like', $term))
                    ->orWhereHas('user', fn (Builder $user): Builder => $user->where('name', 'like', $term));
            });
        }
        $query->when($request->filled('course'), fn (Builder $q): Builder => $q->where('course_id', $request->integer('course')))
            ->when($request->filled('type'), fn (Builder $q): Builder => $q->where('type', $request->string('type')->toString()))
            ->when($request->filled('technology'), fn (Builder $q): Builder => $q->where('technology', 'like', '%'.$request->string('technology')->toString().'%'))
            ->when($request->filled('domain'), fn (Builder $q): Builder => $q->where('domain', 'like', '%'.$request->string('domain')->toString().'%'))
            ->when($request->filled('semester'), fn (Builder $q): Builder => $q->whereHas('course', fn (Builder $course): Builder => $course->where('semester', $request->string('semester')->toString())));
        if ($request->routeIs('projects.index')) {
            $query->where('type', 'project');
            if ($request->user()->role === 'student') {
                $query->where('user_id', $request->user()->id);
            }
        }
        $items = $query->latest('id')->get();
        $courses = Course::orderBy('title')->get();
        $semesters = $courses->pluck('semester')->unique();

        return view('repository.index', compact('items', 'courses', 'semesters'));
    }

    public function create(Request $request): View
    {
        return $this->form($request, new AcademicItem);
    }

    public function edit(Request $request, AcademicItem $item): View
    {
        Gate::authorize('update', $item);

        return $this->form($request, $item);
    }

    private function form(Request $request, AcademicItem $item): View
    {
        $user = $request->user();
        $courses = Course::where('status', 'active')->when($user->role !== 'admin', function (Builder $q) use ($user): void {
            $q->where(fn (Builder $course): Builder => $course->whereHas('teachers', fn (Builder $teachers): Builder => $teachers->where('users.id', $user->id))->orWhereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id)));
        })->orderBy('title')->get();

        return view('repository.form', compact('item', 'courses'));
    }

    public function store(SaveAcademicItemRequest $request): RedirectResponse
    {
        $item = $request->user()->items()->create($this->itemData($request));
        Activity::create(['user_id' => $request->user()->id, 'title' => $item->title, 'action' => 'Upload created', 'course_code' => (string) $item->course->id]);

        return redirect()->route('items.show', $item)->with('status', 'Your upload has been saved.');
    }

    public function update(SaveAcademicItemRequest $request, AcademicItem $item): RedirectResponse
    {
        $oldPaths = array_column($item->uploadedFiles(), 'path');
        $item->update($this->itemData($request, $item));
        if ($request->hasFile('file') && $oldPaths) {
            Storage::disk('local')->delete($oldPaths);
        }
        Activity::create(['user_id' => $request->user()->id, 'title' => $item->title, 'action' => 'Upload updated', 'course_code' => (string) $item->course->id]);

        return redirect()->route('items.show', $item)->with('status', 'Changes saved.');
    }

    /** @return array<string, mixed> */
    private function itemData(SaveAcademicItemRequest $request, ?AcademicItem $item = null): array
    {
        $data = $request->safe()->except(['file', 'files']);
        $uploads = $request->file('files', []);
        $attachments = $item?->uploadedFiles() ?? [];
        if ($request->hasFile('file')) {
            $uploads[] = $request->file('file');
            $attachments = [];
        }
        foreach ($uploads as $file) {
            $attachments[] = ['path' => $file->store('repository', 'local'), 'name' => Str::limit(basename($file->getClientOriginalName()), 240, ''), 'size' => $file->getSize()];
        }
        if ($uploads) {
            $data += ['attachments' => $attachments, 'file_path' => $attachments[0]['path'], 'file_name' => $attachments[0]['name'], 'file_size' => array_sum(array_column($attachments, 'size'))];
        }

        return $data;
    }

    public function show(Request $request, AcademicItem $item): View
    {
        abort_unless(AcademicItem::visibleTo($request->user())->whereKey($item->id)->exists(), 404);
        $item->load(['course', 'user']);

        return view('repository.show', compact('item'));
    }

    public function download(Request $request, AcademicItem $item): StreamedResponse
    {
        Gate::authorize('view', $item);
        $request->validate(['attachment' => ['nullable', 'integer', 'min:0']]);
        $attachment = $item->uploadedFiles()[$request->integer('attachment')] ?? null;
        abort_unless($attachment && Storage::disk('local')->exists($attachment['path']), 404);
        $item->increment('downloads');
        Activity::create(['user_id' => $request->user()->id, 'title' => $item->title, 'action' => 'File downloaded', 'course_code' => (string) $item->course->id]);

        return Storage::disk('local')->download($attachment['path'], $attachment['name'], ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, AcademicItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);
        Storage::disk('local')->delete(array_column($item->uploadedFiles(), 'path'));
        Activity::create(['user_id' => $request->user()->id, 'title' => $item->title, 'action' => 'Upload removed']);
        $item->delete();

        return redirect()->route('repository.index')->with('status', 'The upload has been removed.');
    }
}
