<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Support\Collection;

class WorkspaceAlerts
{
    public function registration(User $applicant): void
    {
        $this->send(User::where('is_active', true)->whereNotNull('department_id')->where('role', 'admin')->get(), $applicant, 'Permohonan akaun pegawai baharu menunggu semakan.', 'registration', $applicant->id);
    }

    public function activation(User $actor, User $account): void
    {
        $this->send(collect([$account]), $actor, 'Permohonan akaun anda telah diluluskan. Akses ruang kerja kini tersedia.', 'account', $account->id);
    }

    public function document(User $actor, DocumentVersion $version, string $action): void
    {
        $version->load('document');
        if (in_array($action, ['submit', 'review'], true)) {
            $role = $action === 'submit' ? 'coordinator' : 'approver';
            $decision = $action === 'submit' ? 'review' : 'approve';
            $recipients = $this->activeRole($role)->filter(fn (User $user) => $user->can('transition', [$version, $decision]));
            $message = $action === 'submit' ? 'Dokumen menunggu semakan anda.' : 'Dokumen menunggu kelulusan anda.';
        } else {
            $recipients = collect([$version->document->owner]);
            $message = $action === 'approve' ? 'Dokumen anda telah diluluskan.' : 'Dokumen anda dikembalikan untuk pembetulan.';
        }
        $this->send($recipients, $actor, $message, 'document', $version->document_id);
    }

    public function risk(User $actor, Risk $risk, string $action): void
    {
        if ($action === 'submit') {
            $recipients = $this->activeRole('coordinator')->filter(fn (User $user) => $user->can('transition', [$risk, 'review']));
            $message = 'Penilaian risiko menunggu semakan anda.';
        } else {
            $recipients = collect([$risk->owner]);
            $message = $action === 'review' ? 'Penilaian risiko anda telah disemak. Pelan rawatan boleh disediakan.' : 'Penilaian risiko anda dikembalikan untuk pembetulan.';
        }
        $this->send($recipients, $actor, $message, 'risk', $risk->id);
    }

    public function treatment(User $actor, RiskAction $action, string $event): void
    {
        $action->load('risk', 'assignee');
        if ($event === 'submitted') {
            $recipients = $this->activeRole('coordinator')->filter(fn (User $user) => app(RiskTreatment::class)->canReview($user, $action));
            $message = 'Bukti tindakan rawatan menunggu pengesahan anda.';
        } else {
            $recipients = in_array($event, ['created', 'updated'], true) ? collect([$action->assignee]) : collect([$action->assignee, $action->risk->owner]);
            $message = match ($event) {
                'created' => 'Tindakan rawatan baharu telah ditugaskan kepada anda.',
                'updated' => 'Butiran tindakan rawatan yang ditugaskan kepada anda telah dikemas kini.',
                'verify' => 'Bukti tindakan rawatan telah disahkan.',
                'reopen' => 'Tindakan rawatan dibuka semula untuk pembetulan.',
                default => 'Bukti tindakan rawatan dikembalikan untuk pembetulan.',
            };
        }
        $this->send($recipients->filter(fn (User $user) => $user->can('view', $action->risk)), $actor, $message, 'treatment', $action->risk_id);
    }

    private function activeRole(string $role): Collection
    {
        return User::where('is_active', true)->whereNotNull('department_id')->where('role', $role)->get();
    }

    private function send(Collection $recipients, User $actor, string $message, string $kind, int $targetId): void
    {
        foreach ($recipients->unique('id') as $recipient) {
            if ($recipient->is_active && $recipient->department_id && $recipient->id !== $actor->id) {
                $recipient->notify(new WorkspaceNotification($message, $kind, $targetId));
            }
        }
    }
}
