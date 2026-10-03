<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'number', 'title', 'status', 'path', 'original_name', 'mime', 'size', 'lock_version', 'reviewer_id', 'approver_id', 'submitted_at', 'reviewed_at', 'approved_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draf', 'submitted' => 'Menunggu semakan', 'reviewed' => 'Menunggu kelulusan', 'returned' => 'Dikembalikan', 'approved' => 'Diluluskan'][$this->status] ?? $this->status;
    }
}
