<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RiskWorkflow
{
    public const METHOD = ['version' => 'pilot-5x5-v1', 'label' => 'Matriks contoh 5×5 · Belum disahkan SUK', 'low_max' => 4, 'medium_max' => 12];

    public function create(User $actor, array $data): Risk
    {
        Gate::forUser($actor)->authorize('create', Risk::class);

        return DB::transaction(function () use ($actor, $data) {
            $risk = Risk::create($this->assessment($data, self::METHOD) + ['owner_id' => $actor->id, 'department_id' => $actor->department_id, 'status' => 'draft', 'lock_version' => 0]);
            $this->audit($actor, $risk, 'created', null, null);

            return $risk;
        });
    }

    public function update(User $actor, Risk $risk, array $data): void
    {
        DB::transaction(function () use ($actor, $risk, $data) {
            $locked = $this->locked($actor, $risk, (int) $data['lock_version']);
            Gate::forUser($actor)->authorize('update', $locked);
            $before = $locked->toArray();
            $locked->fill($this->assessment($data, $locked->method));
            $locked->lock_version++;
            $locked->save();
            $this->audit($actor, $locked, 'updated', $before, null);
        });
    }

    public function transition(User $actor, Risk $risk, string $action, int $expected, ?string $comment): void
    {
        DB::transaction(function () use ($actor, $risk, $action, $expected, $comment) {
            $locked = $this->locked($actor, $risk, $expected);
            Gate::forUser($actor)->authorize('transition', [$locked, $action]);
            if (in_array($action, ['return', 'reassess'], true) && trim($comment ?? '') === '') {
                throw ValidationException::withMessages(['comment' => 'Sila nyatakan sebab keputusan.']);
            }
            $before = $locked->toArray();
            $locked->status = ['submit' => 'submitted', 'review' => 'reviewed', 'return' => 'returned', 'reassess' => 'draft'][$action];
            if ($action === 'reassess') {
                abort_if($locked->actions()->where('cycle', $locked->assessment_cycle)->where('status', '!=', 'verified')->exists(), 409, 'Selesaikan tindakan rawatan sebelum memulakan penilaian semula.');
                $locked->assessment_cycle++;
            }
            $locked->reviewer_id = $action === 'review' ? $actor->id : null;
            $locked->reviewed_at = $action === 'review' ? now() : null;
            $locked->lock_version++;
            $locked->save();
            $this->audit($actor, $locked, $action, $before, $comment);
        });
    }

    private function assessment(array $data, array $method): array
    {
        $fields = Arr::only($data, ['title', 'asset_process', 'threat', 'vulnerability', 'consequence', 'existing_controls', 'rationale', 'likelihood', 'impact']);
        $score = (int) $fields['likelihood'] * (int) $fields['impact'];

        return $fields + ['method' => $method, 'score' => $score, 'level' => $score <= $method['low_max'] ? 'low' : ($score <= $method['medium_max'] ? 'medium' : 'high')];
    }

    private function locked(User $actor, Risk $risk, int $expected): Risk
    {
        $locked = Risk::lockForUpdate()->findOrFail($risk->id);
        Gate::forUser($actor)->authorize('view', $locked);
        abort_unless($locked->lock_version === $expected, 409, 'Rekod telah berubah. Muat semula halaman sebelum meneruskan.');

        return $locked;
    }

    private function audit(User $actor, Risk $risk, string $action, ?array $before, ?string $comment): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'risk_id' => $risk->id, 'action' => 'risk.'.$action, 'before' => $before, 'after' => $risk->fresh()->toArray(), 'comment' => $comment, 'created_at' => now()]);
    }
}
