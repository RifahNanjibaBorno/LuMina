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

    public const TYPES = ['project', 'report', 'notes', 'exam', 'assignment'];

    protected $fillable = ['course_id', 'title', 'description', 'type', 'technology', 'domain', 'team_members', 'source_url', 'visibility', 'file_path', 'file_name', 'file_size', 'is_featured'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
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
                $visible->orWhere('user_id', $user->id)
                    ->orWhere(function (Builder $courseItems) use ($user): void {
                        $courseItems->where('visibility', 'course')->whereHas('course', function (Builder $courses) use ($user): void {
                            $courses->where('teacher_id', $user->id)->orWhereHas('students', fn (Builder $students): Builder => $students->where('users.id', $user->id));
                        });
                    });
            }
        });
    }
}
