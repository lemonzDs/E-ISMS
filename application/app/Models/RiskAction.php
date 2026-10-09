<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cycle' => 'integer', 'due_date' => 'date', 'verified_at' => 'datetime'];
    }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(RiskEvidence::class);
    }

    public function statusLabel(): string
    {
        return ['open' => 'Belum dihantar', 'submitted' => 'Menunggu pengesahan', 'returned' => 'Perlu pembetulan', 'verified' => 'Disahkan'][$this->status];
    }

    public function overdue(): bool
    {
        return $this->status !== 'verified' && $this->due_date->format('Y-m-d') < now('Asia/Kuala_Lumpur')->toDateString();
    }
}
