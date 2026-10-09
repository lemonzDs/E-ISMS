<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskEvidence extends Model
{
    protected $table = 'risk_evidence';

    protected $guarded = ['id'];

    public function action(): BelongsTo
    {
        return $this->belongsTo(RiskAction::class, 'risk_action_id');
    }
}
