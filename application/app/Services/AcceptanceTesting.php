<?php

namespace App\Services;

use App\Models\AcceptanceAttempt;
use App\Models\AcceptanceScenario;
use App\Models\AcceptanceSession;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AcceptanceTesting
{
    public function create(User $actor, array $data): AcceptanceSession
    {
        return DB::transaction(function () use ($actor, $data) {
            $users = User::whereIn('id', [$actor->id, $data['tester_id']])->orderBy('id')->lockForUpdate()->get();
            $actor = $users->find($actor->id);
            Gate::forUser($actor)->authorize('create', AcceptanceSession::class);
            $this->validateTester((int) $data['tester_id'], (int) $data['department_id']);
            $session = AcceptanceSession::create(collect($data)->only(['title', 'build_reference', 'scope', 'department_id', 'tester_id'])->all() + ['created_by' => $actor->id, 'status' => 'draft', 'lock_version' => 0]);
            foreach ($this->catalogue() as $scenario) {
                $session->scenarios()->create($scenario);
            }
            $this->audit($actor, $session, 'created', null, $session->attributesToArray(), 'Sesi UAT diwujudkan dengan senario rintis.');

            return $session;
        }, 3);
    }

    public function scenario(User $actor, AcceptanceSession $session, array $data): void
    {
        $this->change($actor, $session, $data, 'scenario_added', function (User $actor, AcceptanceSession $session) use ($data) {
            Gate::forUser($actor)->authorize('update', $session);
            abort_unless($session->status === 'draft', 409, 'Senario dibekukan selepas ujian bermula.');
            $session->scenarios()->create(collect($data)->only(['module', 'title', 'steps', 'expected'])->all());
        });
    }

    public function assign(User $actor, AcceptanceSession $session, array $data): void
    {
        $this->change($actor, $session, $data, 'tester_assigned', function (User $actor, AcceptanceSession $session) use ($data) {
            Gate::forUser($actor)->authorize('update', $session);
            abort_unless(in_array($session->status, ['draft', 'testing'], true), 409);
            $this->validateTester((int) $data['tester_id'], $session->department_id);
            $session->update(['tester_id' => $data['tester_id']]);
            $this->notify($actor, $session, collect([$session->tester()->firstOrFail()]), 'Penugasan ujian penerimaan sistem telah dikemas kini.');
        });
    }

    public function transition(User $actor, AcceptanceSession $session, array $data): void
    {
        $this->change($actor, $session, $data, $data['action'], function (User $actor, AcceptanceSession $session) use ($data) {
            $action = $data['action'];
            Gate::forUser($actor)->authorize(in_array($action, ['approve', 'return'], true) ? 'approve' : 'update', $session);
            if ($action === 'cancel') {
                abort_unless(in_array($session->status, ['draft', 'testing'], true), 409);
            } else {
                abort_unless($session->status === ['start' => 'draft', 'submit' => 'testing', 'approve' => 'review', 'return' => 'review'][$action], 409, 'Status sesi telah berubah.');
            }
            if ($action === 'start') {
                $this->validateTester($session->tester_id, $session->department_id);
                abort_unless($session->scenarios()->exists(), 409);
            }
            if (in_array($action, ['submit', 'approve'], true)) {
                abort_unless($this->ready($session), 409, 'Setiap senario mesti mempunyai keputusan terkini Lulus.');
            }
            $session->update(['status' => ['start' => 'testing', 'submit' => 'review', 'approve' => 'approved', 'return' => 'testing', 'cancel' => 'cancelled'][$action], 'decision_comment' => $data['comment'], 'approved_by' => $action === 'approve' ? $actor->id : null, 'approved_at' => $action === 'approve' ? now() : null]);
            $recipients = $action === 'submit' ? User::where('role', 'approver')->where('is_active', true)->get()->filter(fn (User $user) => $user->can('approve', $session)) : collect([$session->creator, $session->tester]);
            $this->notify($actor, $session, $recipients, 'Status sesi ujian penerimaan sistem telah dikemas kini.');
        });
    }

    public function record(User $actor, AcceptanceScenario $scenario, array $data, ?UploadedFile $file = null): void
    {
        $path = $file?->store('uat-evidence', 'local');
        try {
            $this->change($actor, $scenario->session, $data, 'result_recorded', function (User $actor, AcceptanceSession $session) use ($scenario, $data, $path, $file) {
                Gate::forUser($actor)->authorize('execute', $session);
                abort_unless($session->status === 'testing', 409);
                $locked = $session->scenarios()->lockForUpdate()->findOrFail($scenario->id);
                $latest = $locked->attempts()->latest('id')->first();
                abort_if($latest && in_array($latest->result, ['failed', 'blocked'], true) && ! $latest->fix_note, 409, 'Penyelaras perlu merekodkan pembetulan atau penyelesaian halangan sebelum ujian semula.');
                $locked->attempts()->create(['tested_by' => $actor->id, 'result' => $data['result'], 'observed' => $data['observed'], 'evidence_path' => $path, 'evidence_name' => $file ? basename($file->getClientOriginalName()) : null]);
                if ($data['result'] !== 'passed') {
                    $this->notify($actor, $session, collect([$session->creator]), 'Keputusan UAT memerlukan pembetulan atau penyelesaian halangan.');
                }
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function fix(User $actor, AcceptanceAttempt $attempt, array $data): void
    {
        $this->change($actor, $attempt->scenario->session, $data, 'fix_recorded', function (User $actor, AcceptanceSession $session) use ($attempt, $data) {
            Gate::forUser($actor)->authorize('update', $session);
            abort_unless($session->status === 'testing', 409);
            $scenario = $session->scenarios()->lockForUpdate()->findOrFail($attempt->acceptance_scenario_id);
            $latest = $scenario->attempts()->latest('id')->firstOrFail();
            abort_unless($latest->id === $attempt->id && $latest->result !== 'passed' && ! $latest->fix_note, 409, 'Keputusan ini tidak lagi menunggu pembetulan.');
            $latest->update(['fix_note' => $data['comment'], 'fixed_by' => $actor->id, 'fixed_at' => now()]);
            $this->notify($actor, $session, collect([$session->tester]), 'Pembetulan UAT telah direkodkan. Jalankan ujian semula.');
        });
    }

    public function ready(AcceptanceSession $session): bool
    {
        $scenarios = $session->scenarios()->with('latestAttempt')->get();

        return $scenarios->isNotEmpty() && $scenarios->every(fn (AcceptanceScenario $scenario) => $scenario->latestAttempt?->result === 'passed');
    }

    private function validateTester(int $id, int $departmentId): void
    {
        $user = User::findOrFail($id);
        if (! $user->is_active || $user->department_id !== $departmentId || ! in_array($user->role, ['officer', 'coordinator'], true)) {
            throw ValidationException::withMessages(['tester_id' => 'Pilih pegawai atau penyelaras aktif dalam bahagian sesi UAT.']);
        }
    }

    private function change(User $actor, AcceptanceSession $session, array $data, string $event, \Closure $operation): void
    {
        DB::transaction(function () use ($actor, $session, $data, $event, $operation) {
            $users = User::whereIn('id', array_merge([$actor->id], isset($data['tester_id']) ? [$data['tester_id']] : []))->orderBy('id')->lockForUpdate()->get();
            $actor = $users->find($actor->id);
            $locked = AcceptanceSession::lockForUpdate()->findOrFail($session->id);
            Gate::forUser($actor)->authorize('view', $locked);
            abort_unless($locked->lock_version === (int) $data['lock_version'], 409, 'Rekod telah berubah. Muat semula sesi UAT.');
            $before = $this->snapshot($locked);
            $operation($actor, $locked);
            $locked->increment('lock_version');
            $this->audit($actor, $locked, $event, $before, $this->snapshot($locked->fresh()), $data['comment'] ?? $data['observed'] ?? null);
        }, 3);
    }

    private function snapshot(AcceptanceSession $session): array
    {
        return ['session' => $session->attributesToArray(), 'scenarios' => $session->scenarios()->with(['attempts' => fn ($query) => $query->select(['id', 'acceptance_scenario_id', 'tested_by', 'result', 'observed', 'evidence_name', 'fix_note', 'fixed_by', 'fixed_at', 'created_at'])])->get()->toArray()];
    }

    private function audit(User $actor, AcceptanceSession $session, string $event, ?array $before, array $after, ?string $comment): void
    {
        AuditEvent::create(['actor_id' => $actor->id, 'acceptance_session_id' => $session->id, 'action' => 'uat.'.$event, 'before' => $before, 'after' => $after, 'comment' => $comment, 'created_at' => now()]);
    }

    private function notify(User $actor, AcceptanceSession $session, Collection $users, string $message): void
    {
        foreach ($users->unique('id') as $user) {
            if ($user->id !== $actor->id && $user->can('view', $session)) {
                $user->notify(new WorkspaceNotification($message, 'uat', $session->id));
            }
        }
    }

    public function catalogue(): array
    {
        return [
            ['module' => 'Akaun & akses', 'title' => 'Permohonan pegawai dan pengaktifan', 'steps' => 'Mohon akaun contoh. Cuba log masuk sebelum aktif. Minta pentadbir menyemak dan mengaktifkan akaun. Log masuk semula.', 'expected' => 'Akaun tidak aktif ditolak; akaun disahkan mendapat akses sebagai pegawai bahagian.'],
            ['module' => 'Akaun & akses', 'title' => 'Had akses antara bahagian', 'steps' => 'Gunakan dua akaun pegawai bahagian berbeza. Cuba URL rekod dan bukti milik bahagian lain. Cuba akses menu pentadbir.', 'expected' => 'Rekod dan bukti bahagian lain serta pengurusan pengguna tidak boleh dibuka.'],
            ['module' => 'Dokumen', 'title' => 'Semakan, kelulusan dan versi dokumen', 'steps' => 'Pegawai cipta dan hantar dokumen PDF contoh. Penyelaras menyemak; pelulus lain meluluskan. Cuba mengedit versi diluluskan.', 'expected' => 'Semakan sendiri ditolak; versi diluluskan dikekalkan dan perubahan baharu menggunakan versi seterusnya.'],
            ['module' => 'Risiko', 'title' => 'Penilaian risiko dan rawatan', 'steps' => 'Daftar risiko contoh, hantar untuk semakan bebas. Tugaskan rawatan, hantar bukti dan sahkan melalui penyelaras lain. Nilai risiko baki.', 'expected' => 'Skor dikira oleh sistem. Risiko baki memerlukan tindakan disahkan; pengesahan bukan penerimaan risiko rasmi.'],
            ['module' => 'Audit', 'title' => 'Audit dalaman hingga penutupan', 'steps' => 'Sediakan pelan luar bahagian juruaudit, senarai semak dan dapatan. Pegawai hantar respons; juruaudit semak keberkesanan. Lengkapkan bukti dan tutup audit.', 'expected' => 'Dapatan terbuka menghalang penutupan; respons dan bukti terdahulu dikekalkan apabila dihantar semula.'],
            ['module' => 'Notifikasi', 'title' => 'Pemberitahuan dan akses rekod semasa', 'steps' => 'Hantar urusan contoh kepada pegawai lain. Buka notifikasi, tandakan dibaca dan cuba membuka notifikasi pengguna lain.', 'expected' => 'Pemberitahuan hanya milik penerima; pembukaan rekod menyemak akses semasa.'],
            ['module' => 'Laporan', 'title' => 'Laporan, eksport dan cetakan', 'steps' => 'Bandingkan kiraan laporan dengan daftar. Eksport CSV. Cetak laporan audit ke PDF. Uji penapis bahagian dengan akaun pegawai.', 'expected' => 'Kiraan sepadan, eksport ikut akses dan laporan PDF mempunyai susun atur yang boleh dibaca.'],
            ['module' => 'Integriti rekod', 'title' => 'Perubahan serentak dan fail tidak sah', 'steps' => 'Buka rekod sama dalam dua tab. Simpan tab pertama kemudian tab kedua. Cuba muat naik fail bukan PDF pada bukti audit.', 'expected' => 'Simpanan lama ditolak tanpa menimpa rekod; fail tidak sah ditolak.'],
            ['module' => 'Paparan', 'title' => 'Telefon, papan kekunci dan portal pegawai', 'steps' => 'Buka portal, log masuk, daftar dan ruang kerja pada telefon. Gunakan Tab untuk borang dan jadual.', 'expected' => 'Teks boleh dibaca, borang boleh digunakan dan jadual lebar boleh dileret tanpa halaman melimpah.'],
            ['module' => 'Operasi', 'title' => 'Sandaran dan pemulihan dalam persekitaran berasingan', 'steps' => 'Bersama pentadbir operasi, sediakan sandaran database dan fail private. Pulihkan ke persekitaran ujian berasingan; semak rekod dan muat turun bukti. Jangan pulihkan ke database operasi.', 'expected' => 'Rekod serta bukti pulih dan boleh dibuka. Jika kemudahan pemulihan belum tersedia, rekodkan Terhalang.'],
        ];
    }
}
