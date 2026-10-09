<?php

namespace App\Policies;

use App\Models\Risk;
use App\Models\User;

class RiskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['officer', 'coordinator', 'approver'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Risk $risk): bool
    {
        return $this->viewAny($user) && ($user->role !== 'officer' || $user->department_id === $risk->department_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->department_id && in_array($user->role, ['officer', 'coordinator'], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Risk $risk): bool
    {
        return $this->owns($user, $risk) && in_array($risk->status, ['draft', 'returned'], true);
    }

    private function owns(User $user, Risk $risk): bool
    {
        return $this->create($user) && $this->view($user, $risk) && $user->id === $risk->owner_id;
    }

    public function transition(User $user, Risk $risk, string $action): bool
    {
        if (! $this->view($user, $risk)) {
            return false;
        }

        return match ($action) {
            'submit' => $this->update($user, $risk),
            'reassess' => $this->owns($user, $risk) && $risk->status === 'reviewed',
            'review', 'return' => $user->role === 'coordinator' && $user->id !== $risk->owner_id && $risk->status === 'submitted',
            default => false,
        };
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Risk $risk): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Risk $risk): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Risk $risk): bool
    {
        return false;
    }
}
