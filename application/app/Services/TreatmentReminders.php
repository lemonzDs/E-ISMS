<?php

namespace App\Services;

use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TreatmentReminders
{
    public function send(): int
    {
        $today = now('Asia/Kuala_Lumpur')->startOfDay();
        $sent = 0;
        RiskAction::whereIn('status', ['open', 'returned'])
            ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->whereHas('risk', fn ($risk) => $risk->where('status', 'reviewed')->whereColumn('risks.assessment_cycle', 'risk_actions.cycle'))
            ->chunkById(100, function ($actions) use ($today, &$sent) {
                foreach ($actions as $action) {
                    $sent += $this->sendForAction($action, $today);
                }
            });

        return $sent;
    }

    private function sendForAction(RiskAction $candidate, Carbon $today): int
    {
        return DB::transaction(function () use ($candidate, $today) {
            $assignee = User::lockForUpdate()->find($candidate->assignee_id);
            $risk = Risk::lockForUpdate()->find($candidate->risk_id);
            $action = RiskAction::lockForUpdate()->find($candidate->id);
            if (! $assignee || ! $risk || ! $action || $action->assignee_id !== $assignee->id || $action->risk_id !== $risk->id || ! $assignee->department_id) {
                return 0;
            }
            $action->setRelation('risk', $risk);
            if (! app(RiskTreatment::class)->canSubmit($assignee, $action) || $action->due_date->toDateString() > $today->copy()->addDays(7)->toDateString()) {
                return 0;
            }
            $stage = $action->due_date->toDateString() < $today->toDateString() ? 'overdue' : 'upcoming';
            $key = ['risk_action_id' => $action->id, 'assignee_id' => $assignee->id, 'due_date' => $action->due_date->toDateString(), 'stage' => $stage];
            if (DB::table('risk_action_reminders')->where($key)->exists()) {
                return 0;
            }
            DB::table('risk_action_reminders')->insert($key + ['created_at' => now()]);
            $message = $stage === 'overdue'
                ? 'Tindakan rawatan anda telah lewat tarikh sasaran '.$action->due_date->format('d/m/Y').'. Sila semak dan kemas kini bukti.'
                : 'Tindakan rawatan anda mempunyai tarikh sasaran '.$action->due_date->format('d/m/Y').', dalam 7 hari termasuk hari ini. Sila semak kemajuan.';
            $assignee->notify(new WorkspaceNotification($message, 'treatment', $risk->id));

            return 1;
        });
    }
}
