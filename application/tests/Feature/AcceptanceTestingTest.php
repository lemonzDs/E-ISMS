<?php

namespace Tests\Feature;

use App\Models\AcceptanceSession;
use App\Models\AuditEvent;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AcceptanceTestingTest extends TestCase
{
    use RefreshDatabase;

    private function uatSession(): AcceptanceSession
    {
        $session = AcceptanceSession::factory()->create();
        $session->scenarios()->create(['module' => 'Dokumen', 'title' => 'Semakan dokumen', 'steps' => 'Hantar dan semak', 'expected' => 'Semakan disimpan']);

        return $session;
    }

    private function write(AcceptanceSession $session, string $url, array $data = []): TestResponse
    {
        return $this->post($url, $data + ['lock_version' => $session->fresh()->lock_version]);
    }

    private function start(AcceptanceSession $session): void
    {
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'start', 'comment' => 'Ujian bermula'])->assertRedirect();
    }

    private function recordResult(AcceptanceSession $session, string $result = 'passed'): void
    {
        $this->actingAs($session->tester);
        $this->write($session, '/uat-scenarios/'.$session->scenarios()->firstOrFail()->id.'/result', ['result' => $result, 'observed' => 'Hasil sebenar dan langkah semakan contoh'])->assertRedirect();
    }

    private function approver(AcceptanceSession $session): User
    {
        return User::factory()->create(['role' => 'approver', 'is_active' => true, 'department_id' => $session->department_id]);
    }

    public function test_complete_manual_uat_with_independent_acceptance_and_frozen_results(): void
    {
        $session = $this->uatSession();
        $this->start($session);
        $this->recordResult($session);
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Semua senario lulus'])->assertRedirect();
        $this->actingAs($this->approver($session));
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'approve', 'comment' => 'Diterima bagi versi ini'])->assertRedirect();
        $this->assertSame('approved', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->approved_at);
        $this->get('/uat/'.$session->id)->assertOk()->assertSee('Penerimaan disahkan');
        $this->get('/uat/'.$session->id.'/report')->assertOk()->assertSee('Diterima bagi versi ini')->assertSee('pilot-v1');
        $this->actingAs($session->tester);
        $this->write($session, '/uat-scenarios/'.$session->scenarios()->firstOrFail()->id.'/result', ['result' => 'failed', 'observed' => 'Cuba'])->assertStatus(409);
        $this->assertDatabaseCount('acceptance_attempts', 1);
        $this->assertDatabaseHas('audit_events', ['acceptance_session_id' => $session->id, 'action' => 'uat.approve']);
    }

    public function test_default_scenarios_are_pending_and_invalid_testers_cannot_be_assigned(): void
    {
        $session = $this->uatSession();
        $this->actingAs($session->creator);
        $data = $session->only(['title', 'build_reference', 'scope', 'department_id', 'tester_id']) + ['status' => 'approved', 'approved_by' => $session->creator->id];
        $this->post('/uat', $data)->assertRedirect();
        $new = AcceptanceSession::latest('id')->firstOrFail();
        $this->assertSame('draft', $new->status);
        $this->assertNull($new->approved_by);
        $this->assertSame(10, $new->scenarios()->count());
        $this->assertDatabaseCount('acceptance_attempts', 0);
        $this->post('/uat', array_replace($data, ['tester_id' => $session->creator->id]))->assertSessionHasErrors('tester_id');
        $session->tester->update(['is_active' => false]);
        $this->post('/uat', $data)->assertSessionHasErrors('tester_id');
        $this->assertDatabaseCount('acceptance_sessions', 2);
    }

    public function test_fix_requires_retest_and_preserves_every_result_including_blocked(): void
    {
        $session = $this->uatSession();
        $this->start($session);
        $this->recordResult($session, 'blocked');
        $scenario = $session->scenarios()->firstOrFail();
        $attempt = $scenario->attempts()->firstOrFail();
        $this->write($session, '/uat-scenarios/'.$scenario->id.'/result', ['result' => 'passed', 'observed' => 'Cuba'])->assertStatus(409);
        $this->actingAs($session->creator);
        $this->write($session, '/uat-attempts/'.$attempt->id.'/fix', ['comment' => 'Kemudahan ujian kini tersedia'])->assertRedirect();
        $this->assertSame('blocked', $attempt->fresh()->result);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Cuba'])->assertStatus(409);
        $this->recordResult($session, 'failed');
        $failed = $scenario->attempts()->latest('id')->firstOrFail();
        $this->actingAs($session->creator);
        $this->write($session, '/uat-attempts/'.$attempt->id.'/fix', ['comment' => 'Cuba ubah sejarah'])->assertStatus(409);
        $this->write($session, '/uat-attempts/'.$failed->id.'/fix', ['comment' => 'Masalah telah dibetulkan'])->assertRedirect();
        $this->recordResult($session);
        $this->assertSame(['blocked', 'failed', 'passed'], $scenario->attempts()->orderBy('id')->pluck('result')->all());
        $this->assertDatabaseHas('audit_events', ['acceptance_session_id' => $session->id, 'action' => 'uat.fix_recorded']);
    }

    public function test_roles_department_boundaries_and_unassigned_tester_cannot_bypass_controls(): void
    {
        $session = $this->uatSession();
        $this->get('/uat')->assertRedirect('/login');
        $outsider = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $session->creator->department_id]);
        $this->actingAs($outsider)->get('/uat')->assertOk()->assertDontSee($session->title);
        $this->get('/uat/'.$session->id)->assertForbidden();
        $this->get('/uat/'.$session->id.'/report')->assertForbidden();
        $this->actingAs($session->tester)->get('/uat/'.$session->id)->assertOk();
        $this->get('/uat/create')->assertForbidden();
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'start', 'comment' => 'Test'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'department_id' => $session->department_id]);
        $this->actingAs($admin)->get('/uat')->assertForbidden();
        $this->get('/uat/'.$session->id)->assertForbidden();
        $this->start($session);
        $other = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $session->department_id]);
        $this->actingAs($other);
        $this->write($session, '/uat-scenarios/'.$session->scenarios()->firstOrFail()->id.'/result', ['result' => 'passed', 'observed' => 'Test'])->assertForbidden();
        $this->write($session, '/uat/'.$session->id.'/assign', ['tester_id' => $other->id, 'comment' => 'Test'])->assertForbidden();
    }

    public function test_all_scenarios_must_pass_and_return_allows_further_testing(): void
    {
        $session = $this->uatSession();
        $this->start($session);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Cuba'])->assertStatus(409);
        $this->recordResult($session);
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Sedia'])->assertRedirect();
        $this->actingAs($this->approver($session));
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'return', 'comment' => 'Semak sekali lagi'])->assertRedirect();
        $this->assertSame('testing', $session->fresh()->status);
        $this->assertNull($session->fresh()->approved_at);
        $this->recordResult($session, 'failed');
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Cuba'])->assertStatus(409);
        $this->assertDatabaseCount('acceptance_attempts', 2);
    }

    public function test_past_participant_cannot_approve_even_after_role_change(): void
    {
        $session = $this->uatSession();
        $this->start($session);
        $this->recordResult($session);
        $old = $session->tester;
        $new = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $session->department_id]);
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/assign', ['tester_id' => $new->id, 'comment' => 'Penguji ganti'])->assertRedirect();
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'submit', 'comment' => 'Sedia'])->assertRedirect();
        $old->update(['role' => 'approver']);
        $this->actingAs($old->fresh());
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'approve', 'comment' => 'Test'])->assertForbidden();
        $this->assertSame('review', $session->fresh()->status);
    }

    public function test_private_pdf_validation_stale_uploads_and_download_boundaries(): void
    {
        Storage::fake('local');
        $session = $this->uatSession();
        $this->start($session);
        $this->actingAs($session->tester);
        $scenario = $session->scenarios()->firstOrFail();
        $data = ['result' => 'passed', 'observed' => 'Bukti ujian sebenar'];
        $this->write($session, '/uat-scenarios/'.$scenario->id.'/result', $data + ['file' => UploadedFile::fake()->create('bad.html', 1, 'text/html')])->assertSessionHasErrors('file');
        $this->write($session, '/uat-scenarios/'.$scenario->id.'/result', $data + ['file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post('/uat-scenarios/'.$scenario->id.'/result', $data + ['lock_version' => 0, 'file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertStatus(409);
        $this->assertSame([], Storage::disk('local')->allFiles('uat-evidence'));
        $this->write($session, '/uat-scenarios/'.$scenario->id.'/result', $data + ['file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertRedirect();
        $attempt = $scenario->attempts()->firstOrFail();
        $this->get('/uat-attempts/'.$attempt->id.'/evidence')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/uat/'.$session->id)->assertOk()->assertDontSee($attempt->evidence_path);
        $this->get('/uat/'.$session->id.'/report')->assertOk()->assertDontSee($attempt->evidence_path);
        $this->actingAs($session->creator)->get('/uat-attempts/'.$attempt->id.'/evidence')->assertOk();
        $outsider = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $session->creator->department_id]);
        $this->actingAs($outsider)->get('/uat-attempts/'.$attempt->id.'/evidence')->assertForbidden();
        Storage::disk('local')->delete($attempt->evidence_path);
        $this->actingAs($session->tester)->get('/uat-attempts/'.$attempt->id.'/evidence')->assertNotFound();
    }

    public function test_scenario_definition_is_frozen_after_start_and_stale_writes_do_not_overwrite(): void
    {
        $session = $this->uatSession();
        $this->actingAs($session->creator);
        $data = ['module' => 'Akses', 'title' => 'Semak had akses', 'steps' => 'Buka rekod lain', 'expected' => 'Akses ditolak'];
        $this->write($session, '/uat/'.$session->id.'/scenarios', $data)->assertRedirect();
        $this->post('/uat/'.$session->id.'/scenarios', $data + ['lock_version' => 0])->assertStatus(409)->assertSee('Kembali ke daftar');
        $this->start($session);
        $this->write($session, '/uat/'.$session->id.'/scenarios', $data)->assertStatus(409);
        $this->assertSame(2, $session->scenarios()->count());
    }

    public function test_cancellation_freezes_session_and_reassignment_preserves_existing_history(): void
    {
        $session = $this->uatSession();
        $this->start($session);
        $this->recordResult($session);
        $tester = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $session->department_id]);
        $this->actingAs($session->creator);
        $this->write($session, '/uat/'.$session->id.'/assign', ['tester_id' => $tester->id, 'comment' => 'Penguji ganti'])->assertRedirect();
        $this->assertNotSame($tester->id, $session->scenarios()->firstOrFail()->attempts()->firstOrFail()->tested_by);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'cancel', 'comment' => 'Versi ujian diganti'])->assertRedirect();
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->write($session, '/uat/'.$session->id.'/transition', ['action' => 'start', 'comment' => 'Test'])->assertStatus(409);
        $this->write($session, '/uat/'.$session->id.'/assign', ['tester_id' => $tester->id, 'comment' => 'Test'])->assertStatus(409);
        $this->actingAs($tester);
        $this->write($session, '/uat-scenarios/'.$session->scenarios()->firstOrFail()->id.'/result', ['result' => 'passed', 'observed' => 'Test'])->assertStatus(409);
    }

    public function test_failed_history_write_rolls_back_result_notification_and_pdf(): void
    {
        Storage::fake('local');
        $session = $this->uatSession();
        $this->start($session);
        $scenario = $session->scenarios()->firstOrFail();
        $notifications = $session->creator->notifications()->count();
        $version = $session->fresh()->lock_version;
        AuditEvent::creating(function (AuditEvent $event) {
            if ($event->action === 'uat.result_recorded') {
                throw new \RuntimeException('Test rollback');
            }
        });
        try {
            $this->actingAs($session->tester);
            $this->write($session, '/uat-scenarios/'.$scenario->id.'/result', ['result' => 'failed', 'observed' => 'Masalah contoh', 'file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertStatus(500);
        } finally {
            AuditEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('acceptance_attempts', 0);
        $this->assertSame($version, $session->fresh()->lock_version);
        $this->assertSame($notifications, $session->creator->notifications()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('uat-evidence'));
    }

    public function test_notifications_require_recipient_ownership_and_current_access(): void
    {
        $session = $this->uatSession();
        $tester = $session->tester;
        $tester->notify(new WorkspaceNotification('Semak sesi UAT', 'uat', $session->id));
        $notice = $tester->notifications()->firstOrFail();
        $this->actingAs($session->creator)->post('/notifications/'.$notice->id.'/open')->assertNotFound();
        $this->actingAs($tester)->post('/notifications/'.$notice->id.'/open')->assertRedirect('/uat/'.$session->id);
        $notice->refresh()->update(['read_at' => null]);
        $tester->update(['department_id' => $session->creator->department_id]);
        $this->actingAs($tester->fresh())->post('/notifications/'.$notice->id.'/open')->assertForbidden();
        $this->assertNull($notice->fresh()->read_at);
    }

    public function test_account_change_cannot_strand_an_open_uat_session(): void
    {
        $session = $this->uatSession();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'department_id' => $session->department_id]);
        $this->actingAs($admin);
        foreach ([$session->creator, $session->tester] as $user) {
            $this->put('/admin/users/'.$user->id, $user->only(['name', 'email', 'department_id']) + ['role' => 'approver', 'is_active' => 1])->assertSessionHasErrors('role');
        }
        $session->update(['status' => 'cancelled']);
        $tester = $session->tester;
        $this->put('/admin/users/'.$tester->id, $tester->only(['name', 'email', 'department_id']) + ['role' => 'approver', 'is_active' => 1])->assertRedirect();
        $this->assertSame('approver', $tester->fresh()->role);
    }
}
