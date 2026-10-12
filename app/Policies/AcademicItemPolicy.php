<?php

namespace App\Policies;

use App\Models\AcademicItem;
use App\Models\User;

class AcademicItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AcademicItem $academicItem): bool
    {
        return AcademicItem::query()->visibleTo($user)->whereKey($academicItem->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AcademicItem $academicItem): bool
    {
        return $user->role === 'admin' || $academicItem->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AcademicItem $academicItem): bool
    {
        return $this->update($user, $academicItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AcademicItem $academicItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AcademicItem $academicItem): bool
    {
        return false;
    }
}
