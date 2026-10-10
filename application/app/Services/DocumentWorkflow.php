<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentWorkflow
{
    public function create(User $actor, array $data, UploadedFile $file): Document
    {
        Gate::forUser($actor)->authorize('create', Document::class);
        $path = $file->store('documents', 'local');
        try {
            return DB::transaction(function () use ($actor, $data, $file, $path) {
                $document = Document::create(['code' => $data['code'], 'title' => $data['title'], 'department_id' => $actor->department_id, 'owner_id' => $actor->id]);
                $version = $document->versions()->create($this->fileData($file, $path) + ['number' => 1, 'title' => $document->title]);
                $this->audit($actor, $version, 'document.created', null);

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function newVersion(User $actor, Document $document, UploadedFile $file): void
    {
        Gate::forUser($actor)->authorize('newVersion', $document);
        $path = $file->store('documents', 'local');
        try {
            DB::transaction(function () use ($actor, $document, $file, $path) {
                $locked = Document::lockForUpdate()->findOrFail($document->id);
                Gate::forUser($actor)->authorize('newVersion', $locked);
                $latest = $locked->versions()->orderByDesc('number')->firstOrFail();
                abort_unless($latest->status === 'approved', 409, 'Versi semasa belum diluluskan. Selesaikan versi itu dahulu.');
                $version = $locked->versions()->create($this->fileData($file, $path) + ['number' => $latest->number + 1, 'title' => $locked->title]);
                $this->audit($actor, $version, 'version.created', null);
                $locked->touch();
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function update(User $actor, DocumentVersion $version, string $title, int $expected, ?UploadedFile $file): void
    {
        Gate::forUser($actor)->authorize('update', $version);
        $path = $file?->store('documents', 'local');
        try {
            DB::transaction(function () use ($actor, $version, $title, $expected, $file, $path) {
                $locked = $this->locked($version);
                Gate::forUser($actor)->authorize('update', $locked);
                $this->checkLock($locked, $expected);
                $before = $locked->only(['title', 'status', 'lock_version', 'original_name']);
                $locked->fill(['title' => $title, 'lock_version' => $locked->lock_version + 1]);
                if ($file) {
                    $locked->fill($this->fileData($file, $path));
                }
                $locked->save();
                $locked->document->update(['title' => $title]);
                $this->audit($actor, $locked, 'version.updated', $before);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
    }

    public function transition(User $actor, DocumentVersion $version, string $action, int $expected, ?string $comment): void
    {
        DB::transaction(function () use ($actor, $version, $action, $expected, $comment) {
            $locked = $this->locked($version);
            Gate::forUser($actor)->authorize('download', $locked);
            $this->checkLock($locked, $expected);
            Gate::forUser($actor)->authorize('transition', [$locked, $action]);
            $before = $locked->only(['status', 'lock_version', 'reviewer_id', 'approver_id']);
            $locked->status = ['submit' => 'submitted', 'review' => 'reviewed', 'approve' => 'approved', 'return' => 'returned'][$action];
            if ($action === 'submit') {
                $locked->submitted_at = now();
                $locked->reviewer_id = null;
                $locked->reviewed_at = null;
                $locked->approver_id = null;
                $locked->approved_at = null;
            }
            if ($action === 'review') {
                $locked->reviewer_id = $actor->id;
                $locked->reviewed_at = now();
            }
            if ($action === 'approve') {
                $locked->approver_id = $actor->id;
                $locked->approved_at = now();
            }
            $locked->lock_version++;
            $locked->save();
            $locked->document->touch();
            $this->audit($actor, $locked, 'version.'.$action, $before, $comment);
            app(WorkspaceAlerts::class)->document($actor, $locked, $action);
        });
    }

    private function locked(DocumentVersion $version): DocumentVersion
    {
        Document::lockForUpdate()->findOrFail($version->document_id);

        return DocumentVersion::lockForUpdate()->findOrFail($version->id);
    }

    private function checkLock(DocumentVersion $version, int $expected): void
    {
        abort_unless($version->lock_version === $expected, 409, 'Rekod telah berubah. Muat semula halaman dan semak versi terkini sebelum meneruskan.');
    }

    /** @return array{path: string, original_name: string, mime: ?string, size: int|false} */
    private function fileData(UploadedFile $file, string $path): array
    {
        return ['path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
    }

    private function audit(User $actor, DocumentVersion $version, string $action, ?array $before, ?string $comment = null): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'document_id' => $version->document_id, 'document_version_id' => $version->id, 'action' => $action, 'before' => $before, 'after' => $version->fresh()->only(['number', 'title', 'status', 'lock_version', 'original_name', 'reviewer_id', 'approver_id']), 'comment' => $comment, 'created_at' => now()]);
    }
}
