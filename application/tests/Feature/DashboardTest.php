<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Risk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'officer', ?Department $department = null): User
    {
        $department ??= Department::create(['name' => 'Bahagian ujian', 'code' => uniqid()]);

        return User::factory()->create(['role' => $role, 'department_id' => $department->id, 'is_active' => true]);
    }

    private function document(User $owner, string $status): Document
    {
        $document = Document::create(['code' => uniqid('DOC-'), 'title' => 'Dokumen ujian', 'owner_id' => $owner->id, 'department_id' => $owner->department_id]);
        $document->versions()->create(['number' => 1, 'status' => $status, 'path' => 'test.pdf', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10]);

        return $document;
    }

    public function test_guest_and_inactive_account_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $user = $this->person();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_officer_sees_own_work_and_only_department_treatments(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $ownDocument = $this->document($owner, 'returned');
        $this->document($other, 'returned');
        $ownRisk = Risk::factory()->create(['owner_id' => $owner->id, 'department_id' => $owner->department_id, 'status' => 'returned']);
        $foreignRisk = Risk::factory()->create(['owner_id' => $other->id, 'department_id' => $other->department_id, 'status' => 'reviewed']);
        $foreignRisk->actions()->create(['title' => 'Tindakan sulit bahagian lain', 'description' => 'Contoh', 'assignee_id' => $other->id, 'cycle' => 1, 'status' => 'open', 'due_date' => '2020-01-01']);
        $this->actingAs($owner)->get('/dashboard')->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('id')->all() === [$ownDocument->id])
            ->assertViewHas('risks', fn ($rows) => $rows->pluck('id')->all() === [$ownRisk->id])
            ->assertViewHas('overdue', 0)->assertDontSee('Tindakan sulit bahagian lain');
    }

    public function test_coordinator_queue_excludes_self_review_and_old_document_versions(): void
    {
        $coordinator = $this->person('coordinator');
        $owner = $this->person();
        $waiting = $this->document($owner, 'submitted');
        $this->document($coordinator, 'submitted');
        $old = $this->document($owner, 'submitted');
        $old->versions()->create(['number' => 2, 'status' => 'approved', 'path' => 'new.pdf', 'original_name' => 'new.pdf', 'mime' => 'application/pdf', 'size' => 10]);
        $reviewRisk = Risk::factory()->create(['owner_id' => $owner->id, 'department_id' => $owner->department_id, 'status' => 'submitted']);
        Risk::factory()->create(['owner_id' => $coordinator->id, 'department_id' => $coordinator->department_id, 'status' => 'submitted']);
        $this->actingAs($coordinator)->get('/dashboard')->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('id')->all() === [$waiting->id])
            ->assertViewHas('risks', fn ($rows) => $rows->pluck('id')->all() === [$reviewRisk->id]);
    }

    public function test_approver_queue_excludes_own_documents_and_documents_they_reviewed(): void
    {
        $approver = $this->person('approver');
        $owner = $this->person();
        $waiting = $this->document($owner, 'reviewed');
        $this->document($approver, 'reviewed');
        $reviewedBySelf = $this->document($owner, 'reviewed');
        $reviewedBySelf->latestVersion->update(['reviewer_id' => $approver->id]);
        $this->document($owner, 'submitted');
        $this->actingAs($approver)->get('/dashboard')->assertOk()
            ->assertViewHas('documents', fn ($rows) => $rows->pluck('id')->all() === [$waiting->id])
            ->assertViewHas('risks', fn ($rows) => $rows->total() === 0);
    }

    public function test_deadlines_use_malaysia_date_and_only_current_unverified_actions(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 17:00:00', 'UTC'));
        $risk = Risk::factory()->create(['status' => 'reviewed', 'assessment_cycle' => 2]);
        $base = ['description' => 'Ujian', 'assignee_id' => $risk->owner_id, 'cycle' => 2, 'status' => 'open'];
        $risk->actions()->create($base + ['title' => 'Lewat', 'due_date' => '2026-10-10']);
        $risk->actions()->create($base + ['title' => 'Hari ini', 'due_date' => '2026-10-11']);
        $risk->actions()->create($base + ['title' => 'Tujuh hari', 'due_date' => '2026-10-18']);
        $risk->actions()->create($base + ['title' => 'Lapan hari', 'due_date' => '2026-10-19']);
        $risk->actions()->create([...$base, 'status' => 'submitted', 'title' => 'Bukti menunggu', 'due_date' => '2026-11-01']);
        $risk->actions()->create([...$base, 'status' => 'verified', 'title' => 'Sudah disahkan', 'due_date' => '2026-10-01']);
        $risk->actions()->create([...$base, 'cycle' => 1, 'title' => 'Kitaran lama', 'due_date' => '2026-10-01']);
        $response = $this->actingAs($risk->owner)->get('/dashboard')->assertOk()->assertViewHas('overdue', 1)->assertViewHas('soon', 2);
        $response->assertViewHas('actions', fn ($rows) => $rows->total() === 4)->assertDontSee('Kitaran lama')->assertDontSee('Sudah disahkan')->assertDontSee('Lapan hari');
        $risk->update(['status' => 'draft']);
        $this->get('/dashboard')->assertViewHas('overdue', 0)->assertViewHas('actions', fn ($rows) => $rows->total() === 0);
        $this->travelBack();
    }

    public function test_admin_only_sees_account_overview_with_pending_accounts_first(): void
    {
        $admin = $this->person('admin');
        $pending = $this->person();
        $pending->update(['is_active' => false, 'registration_pending' => true, 'name' => '<script>unsafe</script>']);
        $inactive = $this->person();
        $inactive->update(['is_active' => false]);
        $this->document($admin, 'draft');
        Risk::factory()->create(['title' => 'Risiko bukan untuk pentadbir']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertViewHas('pendingUsers', fn ($rows) => $rows->pluck('id')->all() === [$pending->id])
            ->assertViewMissing('documents')->assertViewMissing('risks')->assertViewMissing('actions')
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false)->assertDontSee('Risiko bukan untuk pentadbir');
    }

    public function test_paginated_queue_counts_all_records_and_keeps_stable_pages(): void
    {
        $owner = $this->person();
        $first = $this->document($owner, 'draft');
        for ($i = 0; $i < 8; $i++) {
            $this->document($owner, 'draft');
        }
        $this->actingAs($owner)->get('/dashboard')->assertViewHas('documents', fn ($rows) => $rows->total() === 9 && $rows->count() === 8)->assertSee($first->code);
        $this->get('/dashboard?documents_page=2')->assertViewHas('documents', fn ($rows) => $rows->total() === 9 && $rows->count() === 1)->assertDontSee($first->code);
    }
}
