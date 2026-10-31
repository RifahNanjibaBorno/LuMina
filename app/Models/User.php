<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'university_id', 'profile', 'portfolio_slug', 'portfolio_public', 'visible_sections', 'resume_path', 'resume_name', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'student',
        'is_active' => true,
        'portfolio_public' => false,
        'portfolio_views' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'profile' => 'array',
            'visible_sections' => 'array',
            'portfolio_public' => 'boolean',
        ];
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcademicItem::class);
    }

    public function taughtCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_teacher');
    }

    public function completion(): int
    {
        $profile = $this->profile ?? [];
        $fields = ['bio', 'phone', 'department', 'education', 'skills', 'certifications', 'achievements', 'experience'];
        $complete = count(array_filter($fields, fn (string $field): bool => ! empty($profile[$field])));

        return (int) round(($complete + 2) / 10 * 100);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)->map(fn (string $part): string => mb_substr($part, 0, 1))->implode('');
    }
}
