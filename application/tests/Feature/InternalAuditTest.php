<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\AuditFinding;
use App\Models\InternalAudit;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InternalAuditTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): InternalAudit
    {
        $audit = InternalAudit::factory()->create();
        $audit->auditors()->attach($audit->created_by, ['is_lead' => true]);

        return $audit;
    }

    private function write(InternalAudit $audit, string $url, array $data = []): TestResponse
    {
        return $this->post($url, $data + ['lock_version' => $audit->fresh()->lock_version]);
    }

    private function begin(InternalAudit $audit): void
    {
        $this->actingAs($audit->creator);
        $this->write($audit, '/audits/'.$audit->id.'/checklist', ['requirement' => 'Semak akses', 'reference' => 'POL-AKSES-01'])->assertRedirect();
        foreach (['schedule', 'start'] as $action) {
            $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => $action, 'comment' => 'Audit rintis'])->assertRedirect();
        }
    }

    private function response(InternalAudit $audit, AuditFinding $finding): void
    {
        $this->actingAs($finding->owner);
        $this->write($audit, '/audit-findings/'.$finding->id.'/submit', ['root_cause' => 'Semakan belum berkala', 'correction' => 'Akaun lama ditamatkan', 'corrective_action' => 'Semakan suku tahun', 'effectiveness_measure' => 'Tiada akaun lama dalam sampel', 'summary' => 'Bukti tindakan', 'file' => UploadedFile::fake()->createWithContent('tindakan.pdf', "%PDF-1.4\nContoh")])->assertRedirect();
    }

    public function test_full_audit_cycle_with_private_evidence_independent_closure_and_report(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $item = $audit->checklist()->firstOrFail();
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'nonconform', 'notes' => 'Akaun lama masih aktif'])->assertRedirect();
        $owner = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id]);
        $this->write($audit, '/audits/'.$audit->id.'/findings', ['audit_checklist_item_id' => $item->id, 'owner_id' => $owner->id, 'title' => 'Akaun lama aktif', 'description' => 'Semakan sampel mendapati akaun lama', 'category' => 'nonconformity', 'severity' => 'medium', 'due_date' => now()->addWeek()->toDateString()])->assertRedirect();
        $finding = $audit->findings()->firstOrFail();
        $this->write($audit, '/audits/'.$audit->id.'/evidence', ['audit_checklist_item_id' => $item->id, 'summary' => 'Sampel akaun', 'file' => UploadedFile::fake()->createWithContent('pemeriksaan.pdf', "%PDF-1.4\nContoh")])->assertRedirect();
        $this->response($audit, $finding);
        $this->assertSame('submitted', $finding->fresh()->status);
        $this->actingAs($audit->creator);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'close', 'comment' => 'Sampel susulan mengesahkan akses ditamatkan'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Semua dapatan telah disahkan berkesan'])->assertRedirect();
        $this->assertSame('closed', $audit->fresh()->status);
        $this->get('/audits/'.$audit->id)->assertOk()->assertSee('Audit ditutup')->assertDontSee($audit->evidence()->firstOrFail()->path);
        $this->get('/audits/'.$audit->id.'/report')->assertOk()->assertSee('Semua dapatan telah disahkan berkesan');
        $evidence = $audit->evidence()->firstOrFail();
        $this->get('/audit-evidence/'.$evidence->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_events', ['internal_audit_id' => $audit->id, 'action' => 'audit.close']);
        $this->assertGreaterThan(0, $owner->notifications()->count());
    }

    public function test_view_scopes_and_mutation_privileges_are_enforced_for_every_role(): void
    {
        $audit = $this->audit();
        $owner = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id]);
        $outsider = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->creator->department_id]);
        $this->get('/audits')->assertRedirect('/login');
        $this->actingAs($outsider)->get('/audits')->assertOk()->assertDontSee($audit->title);
        $this->get('/audits/'.$audit->id)->assertForbidden();
        $this->get('/audits/'.$audit->id.'/report')->assertForbidden();
        $this->actingAs($owner)->get('/audits/'.$audit->id)->assertOk();
        $this->get('/audits/create')->assertForbidden();
        $this->write($audit, '/audits/'.$audit->id.'/checklist', ['requirement' => 'Test', 'reference' => 'Test'])->assertForbidden();
        foreach (['admin', 'approver'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'department_id' => $audit->department_id]);
            $this->actingAs($user)->get('/audits/'.$audit->id)->assertStatus($role === 'admin' ? 403 : 200);
            $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'schedule', 'comment' => 'Test'])->assertForbidden();
        }
        $unassigned = User::factory()->create(['role' => 'coordinator', 'is_active' => true, 'department_id' => $audit->creator->department_id]);
        $this->actingAs($unassigned);
        $this->write($audit, '/audits/'.$audit->id.'/checklist', ['requirement' => 'Test', 'reference' => 'Test'])->assertForbidden();
    }

    public function test_plan_validation_requires_independent_active_team_and_ordered_dates(): void
    {
        $audit = $this->audit();
        $this->actingAs($audit->creator);
        $data = $audit->only(['title', 'scope', 'criteria', 'department_id']) + ['start_date' => '2026-10-11', 'end_date' => '2026-10-12', 'lead_id' => $audit->creator->id];
        $this->post('/audits', $data)->assertRedirect();
        $this->assertDatabaseCount('internal_audits', 2);
        $local = User::factory()->create(['role' => 'coordinator', 'is_active' => true, 'department_id' => $audit->department_id]);
        $this->post('/audits', array_replace($data, ['lead_id' => $local->id]))->assertSessionHasErrors('lead_id');
        $this->post('/audits', array_replace($data, ['end_date' => '2026-10-10']))->assertSessionHasErrors('end_date');
        $this->post('/audits', array_replace($data, ['auditor_ids' => [$local->id]]))->assertSessionHasErrors('lead_id');
        $local->update(['department_id' => $audit->creator->department_id, 'is_active' => false]);
        $this->post('/audits', array_replace($data, ['lead_id' => $local->id]))->assertSessionHasErrors('lead_id');
        $this->assertDatabaseCount('internal_audits', 2);
    }

    public function test_scheduling_closure_and_stale_writes_cannot_skip_required_work(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->actingAs($audit->creator);
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'schedule', 'comment' => 'Test'])->assertStatus(409);
        $this->begin($audit);
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Test'])->assertStatus(409);
        $item = $audit->checklist()->firstOrFail();
        $this->post('/audit-checklist/'.$item->id.'/check', ['result' => 'conform', 'notes' => 'Test', 'lock_version' => 0])->assertStatus(409);
        $this->assertSame('pending', $item->fresh()->result);
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'nonconform', 'notes' => 'Test'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Test'])->assertStatus(409);
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'conform', 'notes' => 'Sampel mematuhi'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Test'])->assertStatus(409);
        $this->write($audit, '/audits/'.$audit->id.'/evidence', ['audit_checklist_item_id' => $item->id, 'summary' => 'Sampel', 'file' => UploadedFile::fake()->createWithContent('sampel.pdf', "%PDF-1.4\nContoh")])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Tiada dapatan'])->assertRedirect();
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'nonconform', 'notes' => 'Test'])->assertStatus(409);
    }

    public function test_return_resubmit_reopen_preserves_prior_responses_and_evidence(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $this->response($audit, $finding);
        $this->actingAs($audit->creator);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'return', 'comment' => 'Tambah sampel susulan'])->assertRedirect();
        $this->response($audit, $finding);
        $this->actingAs($audit->creator);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'close', 'comment' => 'Berkesan'])->assertRedirect();
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'reopen', 'comment' => 'Kelemahan berulang'])->assertRedirect();
        $this->assertSame('returned', $finding->fresh()->status);
        $this->assertNull($finding->fresh()->closed_at);
        $this->assertSame(2, $finding->evidence()->count());
        foreach ($finding->evidence as $evidence) {
            Storage::disk('local')->assertExists($evidence->path);
        }
        $this->assertDatabaseHas('audit_events', ['audit_finding_id' => $finding->id, 'action' => 'audit.finding_return']);
        $this->assertDatabaseHas('audit_events', ['audit_finding_id' => $finding->id, 'action' => 'audit.finding_reopen']);
    }

    public function test_files_and_foreign_checklist_are_rejected_without_leaking_or_orphaning_uploads(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $other = AuditFinding::factory()->create();
        $data = ['audit_checklist_item_id' => $other->audit_checklist_item_id, 'summary' => 'Contoh'];
        $this->write($audit, '/audits/'.$audit->id.'/evidence', $data + ['file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertNotFound();
        $this->assertSame([], Storage::disk('local')->allFiles('audit-evidence'));
        $this->write($audit, '/audits/'.$audit->id.'/evidence', $data + ['file' => UploadedFile::fake()->create('script.html', 1, 'text/html')])->assertSessionHasErrors('file');
        $this->write($audit, '/audits/'.$audit->id.'/evidence', $data + ['file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('audit_evidence', 0);
    }

    public function test_only_assignee_can_respond_and_only_assigned_auditor_can_review(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $other = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id]);
        $this->actingAs($other);
        $this->write($audit, '/audit-findings/'.$finding->id.'/submit', ['root_cause' => 'Test', 'correction' => 'Test', 'corrective_action' => 'Test', 'effectiveness_measure' => 'Test', 'summary' => 'Test', 'file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles('audit-evidence'));
        $this->response($audit, $finding);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'close', 'comment' => 'Test'])->assertForbidden();
        $unassigned = User::factory()->create(['role' => 'coordinator', 'is_active' => true, 'department_id' => $audit->creator->department_id]);
        $this->actingAs($unassigned);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'close', 'comment' => 'Test'])->assertForbidden();
        $this->actingAs($other);
        $this->get('/audit-evidence/'.$finding->evidence()->firstOrFail()->id)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->creator->department_id]));
        $this->get('/audit-evidence/'.$finding->evidence()->firstOrFail()->id)->assertForbidden();
    }

    public function test_failed_history_write_rolls_back_state_notification_and_uploaded_file(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $beforeVersion = $audit->fresh()->lock_version;
        $beforeNotifications = $audit->creator->notifications()->count();
        AuditEvent::creating(function (AuditEvent $event) {
            if ($event->action === 'audit.response_submitted') {
                throw new \RuntimeException('Test rollback');
            }
        });
        try {
            $this->actingAs($finding->owner);
            $this->write($audit, '/audit-findings/'.$finding->id.'/submit', ['root_cause' => 'Test', 'correction' => 'Test', 'corrective_action' => 'Test', 'effectiveness_measure' => 'Test', 'summary' => 'Test', 'file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertStatus(500);
        } finally {
            AuditEvent::flushEventListeners();
        }
        $this->assertSame('open', $finding->fresh()->status);
        $this->assertSame($beforeVersion, $audit->fresh()->lock_version);
        $this->assertSame($beforeNotifications, $audit->creator->notifications()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('audit-evidence'));
    }

    public function test_plan_edits_owner_transfer_and_team_recovery_are_audited(): void
    {
        $audit = $this->audit();
        $this->actingAs($audit->creator);
        $data = $audit->only(['title', 'scope', 'criteria', 'department_id']) + ['start_date' => $audit->start_date->toDateString(), 'end_date' => $audit->end_date->toDateString(), 'lead_id' => $audit->creator->id, 'lock_version' => 0];
        $this->put('/audits/'.$audit->id, array_replace($data, ['title' => 'Pelan baharu']))->assertRedirect();
        $this->assertSame('Pelan baharu', $audit->fresh()->title);
        $this->begin($audit);
        $this->put('/audits/'.$audit->id, array_replace($data, ['lock_version' => $audit->fresh()->lock_version]))->assertStatus(409);
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $owner = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id]);
        $this->write($audit, '/audit-findings/'.$finding->id.'/assign', ['owner_id' => $owner->id, 'due_date' => now()->addWeeks(2)->toDateString(), 'comment' => 'Tukar pelaksana'])->assertRedirect();
        $this->assertSame($owner->id, $finding->fresh()->owner_id);
        $lead = User::factory()->create(['role' => 'coordinator', 'is_active' => true, 'department_id' => $audit->creator->department_id]);
        $this->write($audit, '/audits/'.$audit->id.'/team', ['lead_id' => $lead->id, 'comment' => 'Ketua ganti'])->assertRedirect();
        $this->assertSame([$lead->id], $audit->auditors()->pluck('users.id')->all());
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Test'])->assertForbidden();
        $this->assertDatabaseHas('audit_events', ['internal_audit_id' => $audit->id, 'action' => 'audit.team_updated']);
    }

    public function test_account_change_does_not_strand_audit_responsibilities(): void
    {
        $audit = $this->audit();
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'department_id' => $audit->department_id]);
        $this->actingAs($admin);
        foreach ([$audit->creator, $finding->owner] as $user) {
            $this->put('/admin/users/'.$user->id, $user->only(['name', 'email', 'department_id']) + ['role' => 'approver', 'is_active' => 1])->assertSessionHasErrors('role');
        }
    }

    public function test_not_applicable_audit_can_close_and_reopen_with_history(): void
    {
        $audit = $this->audit();
        $this->begin($audit);
        $item = $audit->checklist()->firstOrFail();
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'not_applicable', 'notes' => 'Proses di luar skop operasi bahagian'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Tidak berkenaan dengan justifikasi'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'reopen', 'comment' => 'Skop perlu diperiksa semula'])->assertRedirect();
        $this->assertSame('ongoing', $audit->fresh()->status);
        $this->assertNull($audit->fresh()->closed_at);
        $this->assertDatabaseHas('audit_events', ['internal_audit_id' => $audit->id, 'action' => 'audit.reopen']);
        $this->get('/dashboard')->assertOk()->assertSee('Tindakan dapatan audit');
    }

    public function test_team_member_can_inspect_but_only_lead_can_start_or_close_audit(): void
    {
        $audit = $this->audit();
        $this->begin($audit);
        $member = User::factory()->create(['role' => 'coordinator', 'is_active' => true, 'department_id' => $audit->creator->department_id]);
        $audit->auditors()->attach($member->id, ['is_lead' => false]);
        $this->actingAs($member);
        $item = $audit->checklist()->firstOrFail();
        $this->write($audit, '/audit-checklist/'.$item->id.'/check', ['result' => 'not_applicable', 'notes' => 'Tidak berkenaan'])->assertRedirect();
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Test'])->assertForbidden();
        $this->actingAs($audit->creator);
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'close', 'comment' => 'Semakan selesai'])->assertRedirect();
        $this->actingAs($member);
        $this->write($audit, '/audits/'.$audit->id.'/transition', ['action' => 'reopen', 'comment' => 'Test'])->assertForbidden();
    }

    public function test_audit_notification_rechecks_current_access_and_belongs_to_recipient(): void
    {
        $audit = $this->audit();
        $owner = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id]);
        $owner->notify(new WorkspaceNotification('Audit untuk semakan', 'audit', $audit->id));
        $notification = $owner->notifications()->firstOrFail();
        $this->actingAs($audit->creator)->post('/notifications/'.$notification->id.'/open')->assertNotFound();
        $this->actingAs($owner)->post('/notifications/'.$notification->id.'/open')->assertRedirect('/audits/'.$audit->id);
        $notification->refresh()->update(['read_at' => null]);
        $owner->update(['department_id' => $audit->creator->department_id]);
        $this->actingAs($owner->fresh())->post('/notifications/'.$notification->id.'/open')->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_invalid_assignee_foreign_checklist_and_stale_evidence_cannot_create_records(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $other = AuditFinding::factory()->create();
        $data = ['audit_checklist_item_id' => $audit->checklist()->firstOrFail()->id, 'owner_id' => $other->owner_id, 'title' => 'Test', 'description' => 'Test', 'category' => 'nonconformity', 'severity' => 'high', 'due_date' => now()->toDateString()];
        $this->write($audit, '/audits/'.$audit->id.'/findings', $data)->assertSessionHasErrors('owner_id');
        $data['owner_id'] = User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $audit->department_id])->id;
        $data['audit_checklist_item_id'] = $other->audit_checklist_item_id;
        $this->write($audit, '/audits/'.$audit->id.'/findings', $data)->assertNotFound();
        $this->assertSame(0, $audit->findings()->count());
        $this->post('/audits/'.$audit->id.'/evidence', ['audit_checklist_item_id' => $audit->checklist()->firstOrFail()->id, 'summary' => 'Test', 'lock_version' => 0, 'file' => UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\nContoh")])->assertStatus(409);
        $this->assertSame([], Storage::disk('local')->allFiles('audit-evidence'));
    }

    public function test_closed_audit_requires_reopening_before_finding_is_reopened_and_missing_evidence_returns_404(): void
    {
        Storage::fake('local');
        $audit = $this->audit();
        $this->begin($audit);
        $finding = AuditFinding::factory()->create(['internal_audit_id' => $audit->id]);
        $this->response($audit, $finding);
        $this->actingAs($audit->creator);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'close', 'comment' => 'Berkesan'])->assertRedirect();
        $audit->update(['status' => 'closed']);
        $this->write($audit, '/audit-findings/'.$finding->id.'/decision', ['action' => 'reopen', 'comment' => 'Test'])->assertStatus(409);
        $this->assertSame('closed', $finding->fresh()->status);
        $evidence = $finding->evidence()->firstOrFail();
        Storage::disk('local')->delete($evidence->path);
        $this->get('/audit-evidence/'.$evidence->id)->assertNotFound();
    }
}
