<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RiskTreatment
{
    public function canManage(User $user, Risk $risk): bool
    {
        return $user->can('view', $risk) && $risk->status === 'reviewed' && ($user->role === 'coordinator' || ($user->id === $risk->owner_id && $user->role === 'officer'));
    }

    public function canSubmit(User $user, RiskAction $action): bool
    {
        return $user->can('view', $action->risk) && $action->risk->status === 'reviewed' && (int) $action->risk->assessment_cycle === $action->cycle && $user->id === $action->assignee_id && in_array($user->role, ['officer', 'coordinator'], true) && in_array($action->status, ['open', 'returned'], true);
    }

    public function canReview(User $user, RiskAction $action): bool
    {
        return $user->can('view', $action->risk) && $user->role === 'coordinator' && $user->id !== $action->assignee_id && $user->id !== $action->risk->owner_id && $action->risk->status === 'reviewed' && (int) $action->risk->assessment_cycle === $action->cycle;
    }

    public function create(User $user, Risk $risk, array $data): void
    {
        DB::transaction(function () use ($user, $risk, $data) {
            $assignee = User::lockForUpdate()->findOrFail($data['assignee_id']);
            $risk = $this->locked($user, $risk, (int) $data['lock_version']);
            abort_unless($this->canManage($user, $risk), 403);
            $this->validateAssignee($assignee, $risk);
            $action = $risk->actions()->create(['title' => $data['title'], 'description' => $data['description'], 'assignee_id' => $assignee->id, 'due_date' => $data['due_date'], 'cycle' => $risk->assessment_cycle, 'status' => 'open']);
            $this->record($user, $risk, 'created', ['action' => $action->toArray()]);
        });
    }

    public function update(User $user, RiskAction $action, array $data): void
    {
        DB::transaction(function () use ($user, $action, $data) {
            $assignee = User::lockForUpdate()->findOrFail($data['assignee_id']);
            $risk = $this->locked($user, $action->risk, (int) $data['lock_version']);
            $action = $action->fresh();
            abort_unless($this->canManage($user, $risk), 403);
            abort_unless($action->cycle === (int) $risk->assessment_cycle && in_array($action->status, ['open', 'returned'], true), 409);
            $this->validateAssignee($assignee, $risk);
            $before = $action->toArray();
            $action->update(['title' => $data['title'], 'description' => $data['description'], 'assignee_id' => $assignee->id, 'due_date' => $data['due_date']]);
            $this->record($user, $risk, 'updated', ['action' => $action->fresh()->toArray()], $before, $data['comment']);
        });
    }

    public function submit(User $user, RiskAction $action, array $data, UploadedFile $file): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($user, $action, $data, $file, &$path) {
                $risk = $this->locked($user, $action->risk, (int) $data['lock_version']);
                $action = $action->fresh();
                abort_unless($this->canSubmit($user, $action), 403);
                $path = $file->store('risk-evidence', 'local');
                $evidence = $action->evidence()->create(['uploader_id' => $user->id, 'path' => $path, 'original_name' => basename($file->getClientOriginalName()), 'size' => $file->getSize(), 'summary' => $data['summary']]);
                $before = $action->toArray();
                $action->update(['status' => 'submitted', 'reviewer_id' => null, 'verified_at' => null]);
                $this->record($user, $risk, 'submitted', ['action' => $action->fresh()->toArray(), 'evidence_id' => $evidence->id], $before, $data['summary']);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function decide(User $user, RiskAction $action, array $data): void
    {
        DB::transaction(function () use ($user, $action, $data) {
            $risk = $this->locked($user, $action->risk, (int) $data['lock_version']);
            $action = $action->fresh();
            abort_unless($this->canReview($user, $action), 403);
            $expected = $data['action'] === 'reopen' ? 'verified' : 'submitted';
            abort_unless($action->status === $expected, 409, 'Status tindakan telah berubah.');
            abort_unless($action->evidence()->exists(), 409, 'Bukti diperlukan sebelum pengesahan.');
            $before = $action->toArray();
            $verified = $data['action'] === 'verify';
            $action->update(['status' => $verified ? 'verified' : 'returned', 'reviewer_id' => $verified ? $user->id : null, 'verified_at' => $verified ? now() : null]);
            $this->record($user, $risk, $data['action'], ['action' => $action->fresh()->toArray()], $before, $data['comment']);
        });
    }

    public function residual(User $user, Risk $risk, array $data): void
    {
        DB::transaction(function () use ($user, $risk, $data) {
            $risk = $this->locked($user, $risk, (int) $data['lock_version']);
            abort_unless($this->canManage($user, $risk) && $user->id === $risk->owner_id, 403);
            $actions = $risk->actions()->where('cycle', $risk->assessment_cycle)->get();
            abort_if($actions->isEmpty() || $actions->contains(fn ($action) => $action->status !== 'verified'), 409, 'Semua tindakan dalam kitaran ini perlu disahkan dahulu.');
            $score = (int) $data['likelihood'] * (int) $data['impact'];
            $assessment = $risk->residualAssessments()->create(['assessor_id' => $user->id, 'cycle' => $risk->assessment_cycle, 'treatment_revision' => $risk->treatment_revision, 'likelihood' => $data['likelihood'], 'impact' => $data['impact'], 'score' => $score, 'level' => $score <= $risk->method['low_max'] ? 'low' : ($score <= $risk->method['medium_max'] ? 'medium' : 'high'), 'controls' => $data['controls'], 'rationale' => $data['rationale'], 'method' => $risk->method, 'action_ids' => $actions->pluck('id')->all()]);
            $this->record($user, $risk, 'residual', ['assessment' => $assessment->toArray()], null, null, false);
        });
    }

    private function validateAssignee(User $user, Risk $risk): void
    {
        if (! $user->is_active || $user->department_id !== $risk->department_id || ! in_array($user->role, ['officer', 'coordinator'], true)) {
            throw ValidationException::withMessages(['assignee_id' => 'Pilih pegawai aktif daripada bahagian risiko ini.']);
        }
    }

    private function locked(User $user, Risk $risk, int $expected): Risk
    {
        $risk = Risk::lockForUpdate()->findOrFail($risk->id);
        Gate::forUser($user)->authorize('view', $risk);
        abort_unless($risk->lock_version === $expected, 409, 'Rekod telah berubah. Muat semula sebelum meneruskan.');

        return $risk;
    }

    private function record(User $user, Risk $risk, string $action, array $after, ?array $before = null, ?string $comment = null, bool $changesTreatment = true): void
    {
        $risk->lock_version++;
        if ($changesTreatment) {
            $risk->treatment_revision++;
        }
        $risk->save();
        AuditEvent::create(['actor_id' => $user->id, 'risk_id' => $risk->id, 'action' => 'treatment.'.$action, 'before' => $before, 'after' => $after + ['cycle' => $risk->assessment_cycle, 'treatment_revision' => $risk->treatment_revision], 'comment' => $comment, 'created_at' => now()]);
    }
}
