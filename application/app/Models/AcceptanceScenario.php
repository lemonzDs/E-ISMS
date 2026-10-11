<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AcceptanceScenario extends Model
{
    protected $fillable = ['acceptance_session_id', 'module', 'title', 'steps', 'expected'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcceptanceSession::class, 'acceptance_session_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AcceptanceAttempt::class);
    }

    public function latestAttempt(): HasOne
    {
        return $this->hasOne(AcceptanceAttempt::class)->latestOfMany();
    }
}
