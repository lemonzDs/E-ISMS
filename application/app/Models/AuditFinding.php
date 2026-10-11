<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditFinding extends Model
{
    use HasFactory;

    protected $fillable = ['internal_audit_id', 'audit_checklist_item_id', 'title', 'category', 'severity', 'description', 'owner_id', 'due_date', 'status', 'root_cause', 'correction', 'corrective_action', 'effectiveness_measure', 'submitted_by', 'reviewed_by', 'review_comment', 'submitted_at', 'closed_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'submitted_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(InternalAudit::class, 'internal_audit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AuditEvidence::class);
    }

    public function statusLabel(): string
    {
        return ['open' => 'Untuk tindakan', 'submitted' => 'Menunggu semakan', 'returned' => 'Perlu pembetulan', 'closed' => 'Ditutup'][$this->status];
    }

    public function categoryLabel(): string
    {
        return ['nonconformity' => 'Ketakakuran', 'observation' => 'Pemerhatian', 'improvement' => 'Peluang penambahbaikan'][$this->category];
    }
}
