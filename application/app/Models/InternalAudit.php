<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalAudit extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'scope', 'criteria', 'department_id', 'created_by', 'start_date', 'end_date', 'status', 'lock_version', 'conclusion', 'closed_at'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'closed_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function auditors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'internal_audit_user')->withPivot('is_lead');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(AuditChecklistItem::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AuditEvidence::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active || ! $user->department_id || $user->role === 'admin') {
            $query->whereRaw('1=0');
        } elseif ($user->role === 'officer') {
            $query->where('department_id', $user->department_id);
        } else {
            $query->whereIn('status', ['draft', 'scheduled', 'ongoing', 'closed']);
        }
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draf', 'scheduled' => 'Dijadualkan', 'ongoing' => 'Sedang diaudit', 'closed' => 'Ditutup'][$this->status];
    }

    public function code(): string
    {
        return 'AUD-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
