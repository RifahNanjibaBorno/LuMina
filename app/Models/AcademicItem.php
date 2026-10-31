<?php

namespace App\Models;

use Database\Factories\AcademicItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicItem extends Model
{
    /** @use HasFactory<AcademicItemFactory> */
    use HasFactory;

    public const TYPES = ['project', 'report', 'notes', 'exam', 'assignment', 'notice', 'material'];

    protected $fillable = ['course_id', 'title', 'description', 'type', 'technology', 'domain', 'team_members', 'source_url', 'visibility', 'file_path', 'file_name', 'file_size', 'attachments', 'is_featured'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'attachments' => 'array'];
    }

    /** @return list<array{path: string, name: string, size: int}> */
    public function uploadedFiles(): array
    {
        return $this->attachments ?? ($this->file_path ? [['path' => $this->file_path, 'name' => $this->file_name, 'size' => (int) $this->file_size]] : []);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->role === 'admin') {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where(function (Builder $public): void {
                $public->where('visibility', 'public')->whereHas('user', fn (Builder $owner): Builder => $owner->where('is_active', true));
            });
            if ($user) {
                if ($user->role === 'teacher') {
                    $visible->orWhere(function (Builder $supervised) use ($user): void {
                        $supervised->whereHas('user', fn (Builder $owner): Builder => $owner->where('role', 'student'))
                            ->whereHas('course', fn (Builder $course): Builder => $course->whereHas('teachers', fn (Builder $teachers): Builder => $teachers->where('users.id', $user->id))->whereHas('students', fn (Builder $students): Builder => $students->whereColumn('users.id', 'academic_items.user_id')));
                    });
                }
                $visible->orWhere('user_id', $user->id)
                    ->orWhere(function (Builder $courseItems) use ($user): void {
                        $courseItems->where('visibility', 'course')->whereHas('course', function (Builder $courses) use ($user): void {
                            $courses->whereHas('teachers', fn (Builder $teachers): Builder => $teachers->where('users.id', $user->id))->orWhereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id));
                        });
                    });
            }
        });
    }
}
