<?php

namespace App\Policies;

use App\Models\AcceptanceSession;
use App\Models\User;

class AcceptanceSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->department_id && in_array($user->role, ['officer', 'coordinator', 'approver'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AcceptanceSession $acceptanceSession): bool
    {
        return $this->viewAny($user) && ($user->role !== 'officer' || $user->department_id === $acceptanceSession->department_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->role === 'coordinator';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AcceptanceSession $acceptanceSession): bool
    {
        return $this->create($user) && $user->id === $acceptanceSession->created_by;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AcceptanceSession $acceptanceSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AcceptanceSession $acceptanceSession): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AcceptanceSession $acceptanceSession): bool
    {
        return false;
    }

    public function execute(User $user, AcceptanceSession $session): bool
    {
        return $this->view($user, $session) && $user->id === $session->tester_id && $user->department_id === $session->department_id && in_array($user->role, ['officer', 'coordinator'], true);
    }

    public function approve(User $user, AcceptanceSession $session): bool
    {
        return $this->view($user, $session) && $user->role === 'approver' && $user->id !== $session->created_by && $user->id !== $session->tester_id && ! $session->scenarios()->whereHas('attempts', fn ($query) => $query->where('tested_by', $user->id)->orWhere('fixed_by', $user->id))->exists();
    }
}
