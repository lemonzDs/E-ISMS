<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChecklistItem extends Model
{
    protected $fillable = ['internal_audit_id', 'requirement', 'reference', 'result', 'notes'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InternalAudit::class, 'internal_audit_id');
    }

    public function resultLabel(): string
    {
        return ['pending' => 'Belum diperiksa', 'conform' => 'Mematuhi', 'nonconform' => 'Tidak mematuhi', 'not_applicable' => 'Tidak berkenaan'][$this->result];
    }
}
