<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $fillable = ['title', 'semester', 'description', 'status'];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_teacher');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcademicItem::class);
    }

    public function isAccessibleTo(User $user): bool
    {
        return $user->role === 'admin' || $this->teachers()->whereKey($user->id)->exists() || $this->students()->whereKey($user->id)->exists();
    }
}
