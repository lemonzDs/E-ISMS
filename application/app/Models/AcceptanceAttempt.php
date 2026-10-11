<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcceptanceAttempt extends Model
{
    protected $fillable = ['acceptance_scenario_id', 'tested_by', 'result', 'observed', 'evidence_path', 'evidence_name', 'fix_note', 'fixed_by', 'fixed_at'];

    protected function casts(): array
    {
        return ['fixed_at' => 'datetime'];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(AcceptanceScenario::class, 'acceptance_scenario_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function fixer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fixed_by');
    }

    public function resultLabel(): string
    {
        return ['passed' => 'Lulus', 'failed' => 'Gagal', 'blocked' => 'Terhalang'][$this->result];
    }
}
