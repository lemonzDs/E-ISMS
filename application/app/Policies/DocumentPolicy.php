<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->is_active && ($user->role === 'officer' && $user->department_id === $document->department_id || in_array($user->role, ['coordinator', 'approver'], true));
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->department_id && in_array($user->role, ['officer', 'coordinator'], true);
    }

    public function newVersion(User $user, Document $document): bool
    {
        return $this->create($user) && $this->view($user, $document) && $user->id === $document->owner_id;
    }
}
