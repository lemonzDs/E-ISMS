<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvidence extends Model
{
    protected $fillable = ['internal_audit_id', 'audit_finding_id', 'audit_checklist_item_id', 'uploaded_by', 'path', 'original_name', 'kind', 'summary'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InternalAudit::class, 'internal_audit_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
