<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'officer', ?Department $department = null): User
    {
        $department ??= Department::create(['name' => 'Bahagian '.uniqid(), 'code' => uniqid()]);

        return User::factory()->create(['role' => $role, 'department_id' => $department->id, 'is_active' => true]);
    }

    private function record(User $owner, string $status = 'draft'): DocumentVersion
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/example.pdf', '%PDF-1.4 example');
        $document = Document::create(['code' => 'DOC-'.uniqid(), 'title' => 'Polisi ujian', 'department_id' => $owner->department_id, 'owner_id' => $owner->id]);

        return $document->versions()->create(['number' => 1, 'status' => $status, 'path' => 'documents/example.pdf', 'original_name' => 'example.pdf', 'mime' => 'application/pdf', 'size' => 20, 'lock_version' => 0]);
    }

    public function test_guest_cannot_access_documents(): void
    {
        $this->get('/documents')->assertRedirect('/login');
    }

    public function test_login_is_internal_and_logs_out(): void
    {
        $user = $this->person();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/documents');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/register')->assertOk();
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'wrong@example.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'wrong@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_inactive_users_cannot_login_or_keep_a_session(): void
    {
        $user = $this->person();
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/documents')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_officer_cannot_view_other_department_or_download_its_file(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        $outsider = $this->person();
        $this->actingAs($outsider)->get('/documents')->assertOk()->assertDontSee($version->document->code);
        $this->get('/documents/'.$version->document_id)->assertForbidden();
        $this->get('/versions/'.$version->id.'/download')->assertForbidden();
        $this->actingAs($owner)->get('/versions/'.$version->id.'/download')->assertOk();
    }

    public function test_admin_has_no_implicit_document_or_approval_power(): void
    {
        $version = $this->record($this->person(), 'reviewed');
        $admin = $this->person('admin');
        $this->actingAs($admin)->get('/documents/'.$version->document_id)->assertForbidden();
        $this->post('/versions/'.$version->id.'/transition', ['action' => 'approve', 'lock_version' => 0])->assertForbidden();
        $this->get('/admin/users')->assertOk();
    }

    public function test_officer_creates_private_document_with_audit(): void
    {
        Storage::fake('local');
        $owner = $this->person();
        $this->actingAs($owner)->post('/documents', ['code' => 'DOC-001', 'title' => 'Polisi contoh', 'file' => UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf')])->assertRedirect();
        $document = Document::where('code', 'DOC-001')->firstOrFail();
        $this->assertEquals($owner->department_id, $document->department_id);
        $this->assertEquals('draft', $document->versions->first()->status);
        Storage::disk('local')->assertExists($document->versions->first()->path);
        $this->assertDatabaseHas('audit_events', ['action' => 'document.created', 'actor_id' => $owner->id]);
    }

    public function test_invalid_and_oversized_uploads_are_rejected(): void
    {
        $this->actingAs($this->person());
        $this->post('/documents', ['code' => 'DOC-001', 'title' => 'Polisi', 'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->post('/documents', ['code' => 'DOC-001', 'title' => 'Polisi', 'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_full_workflow_audits_and_preserves_approved_version(): void
    {
        $owner = $this->person();
        $reviewer = $this->person('coordinator');
        $approver = $this->person('approver');
        $version = $this->record($owner);
        $url = '/versions/'.$version->id.'/transition';
        $this->actingAs($owner)->post($url, ['action' => 'submit', 'lock_version' => 0])->assertRedirect();
        $this->actingAs($reviewer)->post($url, ['action' => 'review', 'lock_version' => 1])->assertRedirect();
        $this->actingAs($approver)->post($url, ['action' => 'approve', 'lock_version' => 2])->assertRedirect();
        $this->assertEquals('approved', $version->fresh()->status);
        $this->assertDatabaseCount('audit_events', 3);
        $this->actingAs($owner)->put('/versions/'.$version->id, ['title' => 'Changed', 'lock_version' => 3])->assertForbidden();
        $this->post('/documents/'.$version->document_id.'/versions', ['file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf')])->assertRedirect();
        $this->assertEquals(2, $version->document->versions()->count());
        $this->assertEquals('approved', $version->fresh()->status);
        $this->assertEquals('documents/example.pdf', $version->fresh()->path);
        $this->assertEquals('draft', $version->document->versions()->orderByDesc('number')->first()->status);
    }

    public function test_invalid_transition_and_stale_submit_do_not_change_state(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        $this->actingAs($this->person('approver'))->post('/versions/'.$version->id.'/transition', ['action' => 'approve', 'lock_version' => 0])->assertForbidden();
        $this->actingAs($owner)->post('/versions/'.$version->id.'/transition', ['action' => 'submit', 'lock_version' => 9])->assertStatus(409);
        $this->assertEquals('draft', $version->fresh()->status);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_owner_and_reviewer_cannot_approve_their_own_work(): void
    {
        $owner = $this->person('coordinator');
        $version = $this->record($owner, 'submitted');
        $this->actingAs($owner)->post('/versions/'.$version->id.'/transition', ['action' => 'review', 'lock_version' => 0])->assertForbidden();
        $owner->update(['role' => 'approver']);
        $version->update(['status' => 'reviewed', 'reviewer_id' => $owner->id]);
        $this->post('/versions/'.$version->id.'/transition', ['action' => 'approve', 'lock_version' => 0])->assertForbidden();
        $reviewer = $this->person('approver');
        $version->update(['reviewer_id' => $reviewer->id]);
        $this->actingAs($reviewer)->post('/versions/'.$version->id.'/transition', ['action' => 'approve', 'lock_version' => 0])->assertForbidden();
    }

    public function test_return_requires_reason_and_owner_can_correct_and_resubmit(): void
    {
        $owner = $this->person();
        $version = $this->record($owner, 'submitted');
        $url = '/versions/'.$version->id.'/transition';
        $this->actingAs($this->person('coordinator'))->post($url, ['action' => 'return', 'lock_version' => 0])->assertSessionHasErrors('comment');
        $this->post($url, ['action' => 'return', 'lock_version' => 0, 'comment' => 'Betulkan rujukan'])->assertRedirect();
        $this->actingAs($owner)->put('/versions/'.$version->id, ['title' => 'Polisi dibetulkan', 'lock_version' => 1])->assertRedirect();
        $this->post($url, ['action' => 'submit', 'lock_version' => 2])->assertRedirect();
        $this->assertEquals('submitted', $version->fresh()->status);
        $this->assertNull($version->fresh()->reviewer_id);
    }

    public function test_second_pending_version_is_not_created(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        $this->actingAs($owner)->post('/documents/'.$version->document_id.'/versions', ['file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf')])->assertStatus(409);
        $this->assertEquals(1, $version->document->versions()->count());
    }

    public function test_audit_failure_rolls_back_transition(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        AuditEvent::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            app(DocumentWorkflow::class)->transition($owner, $version, 'submit', 0, null);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Audit unavailable', $e->getMessage());
        } finally {
            AuditEvent::flushEventListeners();
        }
        $this->assertEquals('draft', $version->fresh()->status);
    }

    public function test_admin_cannot_escalate_self_and_officer_cannot_manage_accounts(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->put('/admin/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'approver', 'department_id' => $admin->department_id, 'is_active' => 1])->assertSessionHasErrors('role');
        $this->actingAs($this->person())->get('/admin/users')->assertForbidden();
    }

    public function test_relocated_owner_cannot_create_version_in_previous_department(): void
    {
        $owner = $this->person();
        $version = $this->record($owner, 'approved');
        $owner->update(['department_id' => $this->person()->department_id]);
        $this->actingAs($owner)->post('/documents/'.$version->document_id.'/versions', ['file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')])->assertForbidden();
        $this->assertDatabaseCount('document_versions', 1);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertCount(1, Storage::disk('local')->allFiles('documents'));
    }

    public function test_admin_cannot_strand_pending_ownership_with_role_or_department_change(): void
    {
        $owner = $this->person();
        $this->record($owner);
        $this->actingAs($this->person('admin'));
        $data = ['name' => $owner->name, 'email' => $owner->email, 'role' => 'officer', 'department_id' => $this->person()->department_id, 'is_active' => 1];
        $this->put('/admin/users/'.$owner->id, $data)->assertSessionHasErrors('department_id');
        $data['department_id'] = $owner->department_id;
        $data['role'] = 'approver';
        $this->put('/admin/users/'.$owner->id, $data)->assertSessionHasErrors('role');
        $this->assertEquals('officer', $owner->fresh()->role);
        $data['role'] = 'officer';
        $data['is_active'] = 0;
        $this->put('/admin/users/'.$owner->id, $data)->assertRedirect();
        $this->assertFalse($owner->fresh()->is_active);
    }

    public function test_unsupported_role_does_not_leak_register_metadata(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        $unknown = $this->person('unsupported', $owner->department);
        $this->actingAs($unknown)->get('/documents')->assertOk()->assertDontSee($version->document->code);
    }

    public function test_approver_owner_cannot_edit_or_submit_drafts(): void
    {
        $owner = $this->person('approver');
        $version = $this->record($owner);
        $this->actingAs($owner)->put('/versions/'.$version->id, ['title' => 'Change', 'lock_version' => 0])->assertForbidden();
        $this->post('/versions/'.$version->id.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertForbidden();
    }

    public function test_repeated_submission_from_stale_page_returns_conflict(): void
    {
        $owner = $this->person();
        $version = $this->record($owner);
        $this->actingAs($owner)->post('/versions/'.$version->id.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertRedirect();
        $this->post('/versions/'.$version->id.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertStatus(409);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_admin_account_form_renders_fields_and_creates_user_with_audit(): void
    {
        $admin = $this->person('admin');
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('id="name"', false)->assertSee('id="password"', false)->assertDontSee('@include');
        $this->post('/admin/users', ['name' => 'Pegawai baharu', 'email' => 'new@example.test', 'password' => 'temporary-long-password', 'role' => 'officer', 'department_id' => $admin->department_id, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'role' => 'officer', 'is_active' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created', 'actor_id' => $admin->id]);
        $this->assertTrue(Hash::check('temporary-long-password', User::where('email', 'new@example.test')->firstOrFail()->password));
    }
}
