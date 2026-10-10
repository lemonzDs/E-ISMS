<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Risk;
use App\Models\User;
use App\Notifications\WorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'officer', ?Department $department = null): User
    {
        $department ??= Department::create(['name' => 'Bahagian ujian', 'code' => uniqid()]);

        return User::factory()->create(['role' => $role, 'is_active' => true, 'department_id' => $department->id]);
    }

    private function document(User $owner): Document
    {
        $document = Document::create(['code' => 'DOC-TEST', 'title' => 'Dokumen sulit ujian', 'department_id' => $owner->department_id, 'owner_id' => $owner->id]);
        $document->versions()->create(['number' => 1, 'status' => 'draft', 'path' => 'test.pdf', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10, 'lock_version' => 0]);

        return $document;
    }

    public function test_notifications_are_private_and_read_actions_do_not_affect_other_users(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $owner->notify(new WorkspaceNotification('Notifikasi pemilik', 'account', $owner->id));
        $other->notify(new WorkspaceNotification('Notifikasi pengguna lain', 'account', $other->id));
        $mine = $owner->notifications()->firstOrFail();
        $foreign = $other->notifications()->firstOrFail();
        $this->get('/notifications')->assertRedirect('/login');
        $this->actingAs($owner)->get('/notifications')->assertOk()->assertSee('Notifikasi pemilik')->assertDontSee('Notifikasi pengguna lain');
        $this->post('/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->post('/notifications/'.$foreign->id.'/open')->assertNotFound();
        $this->post('/notifications/'.$mine->id.'/read')->assertRedirect();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->get('/notifications?filter=unread')->assertOk()->assertDontSee('Notifikasi pemilik');
        $this->get('/notifications?filter=invalid')->assertSessionHasErrors('filter');
        $owner->notify(new WorkspaceNotification('Notifikasi kedua', 'account', $owner->id));
        $this->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $owner->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_open_checks_current_permission_after_department_or_role_changes(): void
    {
        $owner = $this->person();
        $document = $this->document($owner);
        $reviewer = $this->person('coordinator');
        $reviewer->notify(new WorkspaceNotification('Dokumen menunggu semakan anda.', 'document', $document->id));
        $alert = $reviewer->notifications()->firstOrFail();
        $reviewer->update(['role' => 'officer']);
        $this->actingAs($reviewer)->post('/notifications/'.$alert->id.'/open')->assertForbidden();
        $this->assertNull($alert->fresh()->read_at);
        $reviewer->update(['role' => 'coordinator']);
        $this->post('/notifications/'.$alert->id.'/open')->assertRedirect('/documents/'.$document->id);
        $this->assertNotNull($alert->fresh()->read_at);
        $reviewer->update(['is_active' => false]);
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_document_workflow_notifies_only_eligible_people_and_preserves_atomic_state(): void
    {
        $owner = $this->person('coordinator');
        $reviewer = $this->person('coordinator');
        $inactive = $this->person('coordinator');
        $inactive->update(['is_active' => false]);
        $approver = $this->person('approver');
        $admin = $this->person('admin');
        $document = $this->document($owner);
        $version = $document->latestVersion;
        $url = '/versions/'.$version->id.'/transition';
        $this->actingAs($owner)->post($url, ['action' => 'submit', 'lock_version' => 0])->assertRedirect();
        $this->assertSame(1, $reviewer->notifications()->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $approver->notifications()->count());
        $this->post($url, ['action' => 'submit', 'lock_version' => 0])->assertStatus(409);
        $this->assertSame(1, $reviewer->notifications()->count());
        $this->actingAs($reviewer)->post($url, ['action' => 'review', 'lock_version' => 1])->assertRedirect();
        $this->assertSame(1, $approver->notifications()->count());
        $this->actingAs($approver)->post($url, ['action' => 'return', 'lock_version' => 2, 'comment' => 'Perlu pembetulan'])->assertRedirect();
        $this->assertSame('Dokumen anda dikembalikan untuk pembetulan.', $owner->notifications()->firstOrFail()->data['message']);
        $this->assertStringNotContainsString($document->title, $owner->notifications()->firstOrFail()->data['message']);
    }

    public function test_notification_failure_rolls_back_transition_audit_and_other_notifications(): void
    {
        $owner = $this->person();
        $this->person('coordinator');
        $document = $this->document($owner);
        DatabaseNotification::creating(fn () => throw new \RuntimeException('Notification unavailable'));
        try {
            $this->actingAs($owner)->post('/versions/'.$document->latestVersion->id.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertStatus(500);
            $this->assertSame('draft', $document->latestVersion->fresh()->status);
            $this->assertDatabaseCount('audit_events', 0);
            $this->assertDatabaseCount('notifications', 0);
        } finally {
            DatabaseNotification::flushEventListeners();
        }
    }

    public function test_registration_and_activation_notify_admin_and_applicant(): void
    {
        $admin = $this->person('admin');
        $officer = $this->person();
        $payload = ['name' => 'Pegawai Baharu', 'email' => 'baharu@example.test', 'department_id' => $officer->department_id, 'password' => 'KataLaluanUjian123', 'password_confirmation' => 'KataLaluanUjian123'];
        $this->post('/register', $payload)->assertRedirect('/login');
        $applicant = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $officer->notifications()->count());
        $alert = $admin->notifications()->firstOrFail();
        $this->actingAs($admin)->post('/notifications/'.$alert->id.'/open')->assertRedirect('/admin/users/'.$applicant->id.'/edit');
        $this->put('/admin/users/'.$applicant->id, [...$payload, 'role' => 'officer', 'is_active' => 1, 'password' => ''])->assertRedirect('/admin/users');
        $this->assertSame(1, $applicant->notifications()->count());
        $this->put('/admin/users/'.$applicant->id, [...$payload, 'role' => 'officer', 'is_active' => 1, 'password' => ''])->assertRedirect('/admin/users');
        $this->assertSame(1, $applicant->notifications()->count());
        $this->actingAs($applicant->fresh())->post('/notifications/'.$applicant->notifications()->firstOrFail()->id.'/open')->assertRedirect('/dashboard');
    }

    public function test_risk_submission_review_and_return_notify_correct_recipients(): void
    {
        $risk = Risk::factory()->create();
        $coordinator = $this->person('coordinator');
        $url = '/risks/'.$risk->id.'/transition';
        $this->actingAs($risk->owner)->post($url, ['action' => 'submit', 'lock_version' => 0])->assertRedirect();
        $alert = $coordinator->notifications()->firstOrFail();
        $this->assertSame('risk', $alert->data['kind']);
        $this->actingAs($coordinator)->post('/notifications/'.$alert->id.'/open')->assertRedirect('/risks/'.$risk->id);
        $this->post($url, ['action' => 'return', 'comment' => 'Betulkan penilaian', 'lock_version' => 1])->assertRedirect();
        $this->assertSame(1, $risk->owner->notifications()->count());
        $this->actingAs($risk->owner)->post($url, ['action' => 'submit', 'lock_version' => 2])->assertRedirect();
        $this->actingAs($coordinator)->post($url, ['action' => 'review', 'lock_version' => 3])->assertRedirect();
        $this->assertSame(2, $risk->owner->notifications()->count());
    }

    public function test_treatment_assignment_submission_and_decision_notify_without_self_verification(): void
    {
        Storage::fake('local');
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $assignee = $this->person('coordinator', $risk->department);
        $reviewer = $this->person('coordinator');
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/actions', ['title' => 'Semak akses', 'description' => 'Ujian', 'assignee_id' => $assignee->id, 'due_date' => now()->addWeek()->toDateString(), 'lock_version' => 0])->assertRedirect();
        $action = $risk->actions()->firstOrFail();
        $this->assertSame(1, $assignee->notifications()->count());
        $this->actingAs($assignee)->post('/risk-actions/'.$action->id.'/submit', ['summary' => 'Bukti ujian', 'file' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti"), 'lock_version' => 1])->assertRedirect();
        $this->assertSame(1, $reviewer->notifications()->count());
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame(0, $risk->owner->notifications()->count());
        $alert = $reviewer->notifications()->firstOrFail();
        $this->actingAs($reviewer)->post('/notifications/'.$alert->id.'/open')->assertRedirect('/risks/'.$risk->id.'/treatment');
        $this->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Bukti lengkap', 'lock_version' => 2])->assertRedirect();
        $this->assertSame(2, $assignee->notifications()->count());
        $this->assertSame(1, $risk->owner->notifications()->count());
        $this->post('/risk-actions/'.$action->id.'/decision', ['action' => 'reopen', 'comment' => 'Semak semula', 'lock_version' => 3])->assertRedirect();
        $this->assertSame(3, $assignee->notifications()->count());
    }

    public function test_notification_text_is_escaped_and_pagination_counts_all_unread(): void
    {
        $user = $this->person();
        for ($i = 0; $i < 16; $i++) {
            $user->notify(new WorkspaceNotification('<script>unsafe</script>', 'account', $user->id));
        }
        $this->actingAs($user)->get('/notifications')->assertOk()->assertViewHas('unreadCount', 16)
            ->assertViewHas('notifications', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
        $this->get('/notifications?page=2')->assertViewHas('notifications', fn ($rows) => $rows->count() === 1);
    }
}
