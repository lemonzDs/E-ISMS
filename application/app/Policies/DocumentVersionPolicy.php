<?php

namespace App\Policies;

use App\Models\DocumentVersion;
use App\Models\User;

class DocumentVersionPolicy
{
    public function download(User $user, DocumentVersion $version): bool
    {
        return (new DocumentPolicy)->view($user, $version->document);
    }

    public function update(User $user, DocumentVersion $version): bool
    {
        return (new DocumentPolicy)->create($user) && $this->download($user, $version) && $user->id === $version->document->owner_id && in_array($version->status, ['draft', 'returned'], true) && $version->id === $version->document->latestVersion->id;
    }

    public function transition(User $user, DocumentVersion $version, string $action): bool
    {
        if (! $this->download($user, $version) || $version->id !== $version->document->latestVersion->id) {
            return false;
        }
        if ($action === 'submit') {
            return $this->update($user, $version);
        }
        if ($user->id === $version->document->owner_id) {
            return false;
        }
        if ($version->status === 'submitted') {
            return $user->role === 'coordinator' && in_array($action, ['review', 'return'], true);
        }
        if ($version->status === 'reviewed') {
            return $user->role === 'approver' && $user->id !== $version->reviewer_id && in_array($action, ['approve', 'return'], true);
        }

        return false;
    }
}
