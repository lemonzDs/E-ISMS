<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    protected $fillable = ['code', 'title', 'department_id', 'owner_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('number', 'max');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active || ! in_array($user->role, ['officer', 'coordinator', 'approver'], true)) {
            return $query->whereRaw('1 = 0');
        }
        if (in_array($user->role, ['coordinator', 'approver'], true)) {
            return $query;
        }

        return $query->where('department_id', $user->department_id);
    }
}
