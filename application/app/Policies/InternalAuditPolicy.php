<?php

namespace App\Policies;

use App\Models\InternalAudit;
use App\Models\User;

class InternalAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->department_id && in_array($user->role, ['officer', 'coordinator', 'approver'], true);
    }

    public function view(User $user, InternalAudit $audit): bool
    {
        return $this->viewAny($user) && ($user->role !== 'officer' || $user->department_id === $audit->department_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->role === 'coordinator';
    }

    public function manage(User $user, InternalAudit $audit): bool
    {
        return $this->create($user) && $audit->created_by === $user->id;
    }

    public function conduct(User $user, InternalAudit $audit): bool
    {
        return $this->create($user) && $user->department_id !== $audit->department_id && $audit->auditors()->where('users.id', $user->id)->exists();
    }

    public function lead(User $user, InternalAudit $audit): bool
    {
        return $this->conduct($user, $audit) && $audit->auditors()->where('users.id', $user->id)->wherePivot('is_lead', true)->exists();
    }
}
