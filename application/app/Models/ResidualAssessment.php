<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidualAssessment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['method' => 'array', 'action_ids' => 'array', 'score' => 'integer', 'cycle' => 'integer', 'treatment_revision' => 'integer'];
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }
}
