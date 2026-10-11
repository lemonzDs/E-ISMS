<?php

namespace App\Services;

use App\Models\AuditChecklistItem;
use App\Models\AuditEvent;
use App\Models\AuditFinding;
use App\Models\InternalAudit;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InternalAuditWorkflow
{
    public function create(User $actor, array $data): InternalAudit
    {
        return DB::transaction(function () use ($actor, $data) {
            $users = $this->lockUsers(array_merge([$actor->id, $data['lead_id']], $data['auditor_ids'] ?? []));
            $actor = $users->find($actor->id);
            Gate::forUser($actor)->authorize('create', InternalAudit::class);
            $team = $this->team($data, (int) $data['department_id']);
            $audit = InternalAudit::create(collect($data)->only(['title', 'scope', 'criteria', 'department_id', 'start_date', 'end_date'])->all() + ['created_by' => $actor->id, 'status' => 'draft', 'lock_version' => 0]);
            $audit->auditors()->sync($team);
            $this->record($actor, $audit, 'created', null, ['plan' => $audit->toArray(), 'team' => $team], 'Pelan audit diwujudkan.');
            $this->notify($actor, $audit, 'Pasukan audit baharu telah ditugaskan.', $audit->auditors);

            return $audit;
        }, 3);
    }

    public function plan(User $actor, InternalAudit $audit, array $data): void
    {
        $this->change($actor, $audit, $data, function ($actor, $audit) use ($data) {
            Gate::forUser($actor)->authorize('manage', $audit);
            abort_unless($audit->status === 'draft', 409, 'Pelan sudah dijadualkan.');
            $audit->update(collect($data)->only(['title', 'scope', 'criteria', 'department_id', 'start_date', 'end_date'])->all());
            $audit->auditors()->sync($this->team($data, $audit->department_id));
            $this->notify($actor, $audit, 'Pelan audit telah dikemas kini.', $audit->auditors()->get());
        }, 'plan_updated');
    }

    public function assignTeam(User $actor, InternalAudit $audit, array $data): void
    {
        $this->change($actor, $audit, $data, function ($actor, $audit) use ($data) {
            Gate::forUser($actor)->authorize('manage', $audit);
            $audit->auditors()->sync($this->team($data, $audit->department_id));
            $this->notify($actor, $audit, 'Penugasan pasukan audit telah dikemas kini.', $audit->auditors()->get());
        }, 'team_updated');
    }

    public function transition(User $actor, InternalAudit $audit, array $data): void
    {
        $this->change($actor, $audit, $data, function ($actor, $audit) use ($data) {
            $action = $data['action'];
            Gate::forUser($actor)->authorize($action === 'schedule' ? 'manage' : 'lead', $audit);
            $expected = ['schedule' => 'draft', 'start' => 'scheduled', 'close' => 'ongoing', 'reopen' => 'closed'][$action];
            abort_unless($audit->status === $expected, 409, 'Status audit telah berubah.');
            if ($action === 'schedule') {
                abort_unless($audit->checklist()->exists() && $audit->auditors()->wherePivot('is_lead', true)->where('is_active', true)->exists(), 409, 'Sediakan senarai semak dan ketua juruaudit aktif dahulu.');
            }
            if ($action === 'close') {
                abort_unless($audit->checklist()->exists() && ! $audit->checklist()->where('result', 'pending')->exists() && ! $audit->findings()->where('status', '!=', 'closed')->exists(), 409, 'Semua pemeriksaan dan dapatan perlu diselesaikan dahulu.');
                foreach ($audit->checklist()->where('result', 'nonconform')->get() as $item) {
                    abort_unless($audit->findings()->where('audit_checklist_item_id', $item->id)->where('category', 'nonconformity')->exists(), 409, 'Pemeriksaan tidak mematuhi mesti mempunyai dapatan ketakakuran.');
                }
                foreach ($audit->checklist()->whereIn('result', ['conform', 'nonconform'])->get() as $item) {
                    abort_unless($audit->evidence()->where('audit_checklist_item_id', $item->id)->where('kind', 'inspection')->exists(), 409, 'Muat naik bukti bagi setiap pemeriksaan mematuhi atau tidak mematuhi.');
                }
            }
            $audit->update(['status' => ['schedule' => 'scheduled', 'start' => 'ongoing', 'close' => 'closed', 'reopen' => 'ongoing'][$action], 'closed_at' => $action === 'close' ? now() : null, 'conclusion' => $action === 'close' ? $data['comment'] : $audit->conclusion]);
            $this->notify($actor, $audit, 'Status audit dalaman telah dikemas kini.', $audit->auditors()->get()->merge(User::where('department_id', $audit->department_id)->whereIn('role', ['officer', 'coordinator'])->where('is_active', true)->get()));
        }, $data['action']);
    }

    public function checklist(User $actor, InternalAudit $audit, array $data, ?AuditChecklistItem $item = null): void
    {
        $this->change($actor, $audit, $data, function ($actor, $audit) use ($data, $item) {
            if ($audit->status === 'draft') {
                abort_unless(Gate::forUser($actor)->allows('manage', $audit) || Gate::forUser($actor)->allows('conduct', $audit), 403);
            } else {
                Gate::forUser($actor)->authorize('conduct', $audit);
            }
            abort_unless(in_array($audit->status, ['draft', 'ongoing'], true), 409);
            if ($item) {
                $locked = $audit->checklist()->lockForUpdate()->findOrFail($item->id);
                abort_unless($audit->status === 'ongoing', 409);
                $locked->update(collect($data)->only(['result', 'notes'])->all());
            } else {
                $audit->checklist()->create(collect($data)->only(['requirement', 'reference'])->all());
            }
        }, $item ? 'checklist_checked' : 'checklist_added');
    }

    public function finding(User $actor, InternalAudit $audit, array $data): void
    {
        $this->change($actor, $audit, $data, function ($actor, $audit) use ($data) {
            Gate::forUser($actor)->authorize('conduct', $audit);
            abort_unless($audit->status === 'ongoing', 409);
            $audit->checklist()->findOrFail($data['audit_checklist_item_id']);
            $owner = $this->owner((int) $data['owner_id'], $audit);
            $audit->findings()->create(collect($data)->only(['audit_checklist_item_id', 'title', 'category', 'severity', 'description', 'owner_id', 'due_date'])->all());
            $this->notify($actor, $audit, 'Dapatan audit baharu memerlukan tindakan anda.', collect([$owner]));
        }, 'finding_added');
    }

    public function assignOwner(User $actor, AuditFinding $finding, array $data): void
    {
        $this->change($actor, $finding->audit, $data, function ($actor, $audit) use ($finding, $data) {
            Gate::forUser($actor)->authorize('conduct', $audit);
            $locked = $audit->findings()->lockForUpdate()->findOrFail($finding->id);
            abort_unless($audit->status === 'ongoing' && in_array($locked->status, ['open', 'returned'], true), 409);
            $owner = $this->owner((int) $data['owner_id'], $audit);
            $locked->update(collect($data)->only(['owner_id', 'due_date'])->all());
            $this->notify($actor, $audit, 'Penugasan tindakan dapatan audit telah dikemas kini.', collect([$owner]));
        }, 'finding_assigned', $finding->id);
    }

    public function evidence(User $actor, InternalAudit $audit, array $data, UploadedFile $file, ?AuditFinding $finding = null): void
    {
        $path = $file->store('audit-evidence', 'local');
        try {
            $this->change($actor, $audit, $data, function ($actor, $audit) use ($data, $file, $path, $finding) {
                abort_unless($audit->status === 'ongoing', 409);
                if ($finding) {
                    $locked = $audit->findings()->lockForUpdate()->findOrFail($finding->id);
                    abort_unless($actor->id === $locked->owner_id && in_array($actor->role, ['officer', 'coordinator'], true) && $actor->department_id === $audit->department_id, 403);
                    abort_unless(in_array($locked->status, ['open', 'returned'], true), 409);
                    $locked->update(collect($data)->only(['root_cause', 'correction', 'corrective_action', 'effectiveness_measure'])->all() + ['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'closed_at' => null]);
                    $itemId = $locked->audit_checklist_item_id;
                    $recipients = $audit->auditors()->get();
                } else {
                    Gate::forUser($actor)->authorize('conduct', $audit);
                    $itemId = $audit->checklist()->findOrFail($data['audit_checklist_item_id'])->id;
                    $recipients = collect();
                }
                $audit->evidence()->create(['audit_finding_id' => $finding?->id, 'audit_checklist_item_id' => $itemId, 'uploaded_by' => $actor->id, 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'kind' => $finding ? 'response' : 'inspection', 'summary' => $data['summary']]);
                $this->notify($actor, $audit, 'Tindakan dapatan audit menunggu semakan keberkesanan.', $recipients);
            }, $finding ? 'response_submitted' : 'inspection_uploaded', $finding?->id);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function decide(User $actor, AuditFinding $finding, array $data): void
    {
        $this->change($actor, $finding->audit, $data, function ($actor, $audit) use ($finding, $data) {
            Gate::forUser($actor)->authorize('conduct', $audit);
            $locked = $audit->findings()->lockForUpdate()->findOrFail($finding->id);
            abort_unless($actor->id !== $locked->owner_id && $actor->id !== $locked->submitted_by, 403);
            abort_unless($audit->status === 'ongoing', 409, 'Buka semula audit sebelum mengubah dapatan.');
            abort_unless($locked->status === ($data['action'] === 'reopen' ? 'closed' : 'submitted'), 409);
            if ($data['action'] === 'close') {
                abort_unless($locked->evidence()->where('kind', 'response')->exists(), 409);
            }
            $locked->update(['status' => $data['action'] === 'close' ? 'closed' : 'returned', 'reviewed_by' => $actor->id, 'review_comment' => $data['comment'], 'closed_at' => $data['action'] === 'close' ? now() : null]);
            $this->notify($actor, $audit, 'Semakan tindakan dapatan audit telah selesai. Semak keputusan juruaudit.', collect([$locked->owner]));
        }, 'finding_'.$data['action'], $finding->id);
    }

    private function owner(int $id, InternalAudit $audit): User
    {
        $user = User::findOrFail($id);
        if (! $user->is_active || $user->department_id !== $audit->department_id || ! in_array($user->role, ['officer', 'coordinator'], true)) {
            throw ValidationException::withMessages(['owner_id' => 'Pilih pegawai aktif daripada bahagian yang diaudit.']);
        }

        return $user;
    }

    private function team(array $data, int $departmentId): array
    {
        $ids = array_unique(array_merge([(int) $data['lead_id']], array_map('intval', $data['auditor_ids'] ?? [])));
        $team = [];
        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            if (! $user->is_active || ! $user->department_id || $user->role !== 'coordinator' || $user->department_id === $departmentId) {
                throw ValidationException::withMessages(['lead_id' => 'Pasukan audit mesti penyelaras aktif dari luar bahagian yang diaudit.']);
            }
            $team[$id] = ['is_lead' => $id === (int) $data['lead_id']];
        }

        return $team;
    }

    private function lockUsers(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
    }

    private function change(User $actor, InternalAudit $audit, array $data, \Closure $operation, string $event, ?int $findingId = null): void
    {
        DB::transaction(function () use ($actor, $audit, $data, $operation, $event, $findingId) {
            $users = $this->lockUsers(array_merge([$actor->id], isset($data['lead_id']) ? [$data['lead_id']] : [], isset($data['owner_id']) ? [$data['owner_id']] : [], $data['auditor_ids'] ?? []));
            $actor = $users->find($actor->id);
            $locked = InternalAudit::lockForUpdate()->findOrFail($audit->id);
            Gate::forUser($actor)->authorize('view', $locked);
            abort_unless($locked->lock_version === (int) $data['lock_version'], 409, 'Rekod telah berubah. Muat semula halaman sebelum meneruskan.');
            $before = $this->snapshot($locked);
            $operation($actor, $locked);
            $locked->increment('lock_version');
            $after = $this->snapshot($locked->fresh());
            $this->record($actor, $locked, $event, $before, $after, $data['comment'] ?? $data['summary'] ?? null, $findingId);
        }, 3);
    }

    private function record(User $actor, InternalAudit $audit, string $event, ?array $before, array $after, ?string $comment, ?int $findingId = null): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'internal_audit_id' => $audit->id, 'audit_finding_id' => $findingId, 'action' => 'audit.'.$event, 'before' => $before, 'after' => $after, 'comment' => $comment, 'created_at' => now()]);
    }

    private function snapshot(InternalAudit $audit): array
    {
        return ['plan' => $audit->attributesToArray(), 'team' => $audit->auditors()->get()->map(fn (User $user) => ['id' => $user->id, 'is_lead' => (bool) $user->pivot->is_lead])->all(), 'checklist' => $audit->checklist()->get()->toArray(), 'findings' => $audit->findings()->get()->toArray(), 'evidence' => $audit->evidence()->get(['id', 'audit_checklist_item_id', 'audit_finding_id', 'uploaded_by', 'kind', 'original_name', 'summary', 'created_at'])->toArray()];
    }

    private function notify(User $actor, InternalAudit $audit, string $message, Collection $users): void
    {
        foreach ($users->unique('id') as $user) {
            if ($user->id !== $actor->id && $user->can('view', $audit)) {
                $user->notify(new WorkspaceNotification($message, 'audit', $audit->id));
            }
        }
    }
}
