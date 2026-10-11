<?php

namespace App\Models;

use Database\Factories\AcceptanceSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcceptanceSession extends Model
{
    /** @use HasFactory<AcceptanceSessionFactory> */
    use HasFactory;

    protected $fillable = ['department_id', 'created_by', 'tester_id', 'approved_by', 'title', 'build_reference', 'scope', 'status', 'lock_version', 'decision_comment', 'approved_at'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'approved_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scenarios(): HasMany
    {
        return $this->hasMany(AcceptanceScenario::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active || ! $user->department_id || ! in_array($user->role, ['officer', 'coordinator', 'approver'], true)) {
            return $query->whereRaw('1=0');
        }

        return $user->role === 'officer' ? $query->where('department_id', $user->department_id) : $query;
    }

    public function code(): string
    {
        return 'UAT-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draf', 'testing' => 'Sedang diuji', 'review' => 'Menunggu pengesahan', 'approved' => 'Disahkan', 'cancelled' => 'Dibatalkan'][$this->status];
    }
}
