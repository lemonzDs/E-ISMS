<?php

namespace App\Models;

use Database\Factories\RiskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Risk extends Model
{
    /** @use HasFactory<RiskFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['method' => 'array', 'likelihood' => 'integer', 'impact' => 'integer', 'score' => 'integer', 'lock_version' => 'integer', 'assessment_cycle' => 'integer', 'treatment_revision' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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

        return $user->role === 'officer' ? $query->where('department_id', $user->department_id) : $query;
    }

    public function code(): string
    {
        return 'RSK-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RiskAction::class);
    }

    public function residualAssessments(): HasMany
    {
        return $this->hasMany(ResidualAssessment::class);
    }

    public function residualIsCurrent(): bool
    {
        $latest = $this->residualAssessments()->latest('id')->first();

        return $this->status === 'reviewed' && $latest && $latest->cycle === (int) $this->assessment_cycle && $latest->treatment_revision === (int) $this->treatment_revision;
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draf', 'submitted' => 'Menunggu semakan', 'returned' => 'Dikembalikan', 'reviewed' => 'Disemak'][$this->status];
    }

    public function levelLabel(): string
    {
        return ['low' => 'Rendah', 'medium' => 'Sederhana', 'high' => 'Tinggi'][$this->level];
    }
}
