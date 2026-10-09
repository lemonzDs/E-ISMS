<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'officer'): User
    {
        $department = Department::create(['name' => 'Bahagian ujian', 'code' => uniqid()]);

        return User::factory()->create(['role' => $role, 'department_id' => $department->id, 'is_active' => true]);
    }

    private function payload(): array
    {
        return ['title' => 'Kehilangan rekod', 'asset_process' => 'Rekod contoh', 'threat' => 'Akses tanpa kebenaran', 'vulnerability' => 'Semakan akses tidak berkala', 'consequence' => 'Pendedahan maklumat', 'existing_controls' => 'Akses mengikut peranan', 'likelihood' => 4, 'impact' => 5, 'rationale' => 'Penilaian contoh untuk ujian'];
    }

    public function test_creation_calculates_score_and_prevents_ownership_and_status_injection(): void
    {
        $owner = $this->person();
        $this->actingAs($owner)->post('/risks', $this->payload() + ['score' => 1, 'status' => 'reviewed', 'department_id' => 999, 'owner_id' => 999])->assertRedirect();
        $risk = Risk::firstOrFail();
        $this->assertSame(20, $risk->score);
        $this->assertSame('high', $risk->level);
        $this->assertSame('draft', $risk->status);
        $this->assertSame($owner->id, $risk->owner_id);
        $this->assertSame($owner->department_id, $risk->department_id);
        $this->assertDatabaseHas('audit_events', ['risk_id' => $risk->id, 'action' => 'risk.created']);
    }

    public function test_visibility_and_mutation_are_scoped_to_role_department_and_owner(): void
    {
        $owner = $this->person();
        $this->actingAs($owner)->post('/risks', $this->payload());
        $risk = Risk::firstOrFail();
        foreach ([$this->person(), $this->person('admin')] as $outsider) {
            $this->actingAs($outsider)->get('/risks/'.$risk->id)->assertForbidden();
            $this->put('/risks/'.$risk->id, $this->payload() + ['lock_version' => 0])->assertForbidden();
        }
        $colleague = User::factory()->create(['role' => 'officer', 'department_id' => $owner->department_id, 'is_active' => true]);
        $this->actingAs($colleague)->get('/risks/'.$risk->id)->assertOk();
        $this->put('/risks/'.$risk->id, $this->payload() + ['lock_version' => 0])->assertForbidden();
        $this->actingAs($this->person('approver'))->get('/risks/'.$risk->id)->assertOk();
        $this->post('/risks', $this->payload())->assertForbidden();
    }

    public function test_review_return_resubmit_and_reassessment_preserve_history_and_reject_stale_writes(): void
    {
        $owner = $this->person();
        $reviewer = $this->person('coordinator');
        $this->actingAs($owner)->post('/risks', $this->payload());
        $risk = Risk::firstOrFail();
        $url = '/risks/'.$risk->id;
        $this->post($url.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertRedirect();
        $this->post($url.'/transition', ['action' => 'submit', 'lock_version' => 0])->assertStatus(409);
        $this->put($url, $this->payload() + ['lock_version' => 1])->assertForbidden();
        $this->actingAs($reviewer)->post($url.'/transition', ['action' => 'return', 'lock_version' => 1])->assertSessionHasErrors('comment');
        $this->post($url.'/transition', ['action' => 'return', 'lock_version' => 1, 'comment' => 'Jelaskan impak'])->assertRedirect();
        $this->actingAs($owner)->put($url, array_replace($this->payload(), ['impact' => 2, 'lock_version' => 2]))->assertRedirect();
        $this->post($url.'/transition', ['action' => 'submit', 'lock_version' => 3])->assertRedirect();
        $this->actingAs($reviewer)->post($url.'/transition', ['action' => 'review', 'lock_version' => 4, 'comment' => 'Disemak'])->assertRedirect();
        $this->assertSame('reviewed', $risk->fresh()->status);
        $this->actingAs($owner)->post($url.'/transition', ['action' => 'reassess', 'lock_version' => 5, 'comment' => 'Perubahan proses'])->assertRedirect();
        $this->assertSame('draft', $risk->fresh()->status);
        $this->assertSame(7, $risk->auditEvents()->count());
        $this->assertSame(20, $risk->auditEvents()->where('action', 'risk.created')->first()->after['score']);
    }

    public function test_self_review_and_invalid_scores_are_rejected(): void
    {
        $owner = $this->person('coordinator');
        $this->actingAs($owner)->post('/risks', array_replace($this->payload(), ['likelihood' => 0, 'impact' => 6]))->assertSessionHasErrors(['likelihood', 'impact']);
        $this->post('/risks', $this->payload());
        $risk = Risk::firstOrFail();
        $this->post('/risks/'.$risk->id.'/transition', ['action' => 'submit', 'lock_version' => 0]);
        $this->post('/risks/'.$risk->id.'/transition', ['action' => 'review', 'lock_version' => 1])->assertForbidden();
    }

    public function test_guest_and_inactive_users_cannot_access_risks(): void
    {
        $this->get('/risks')->assertRedirect('/login');
        $user = $this->person();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get('/risks')->assertRedirect('/login');
    }

    public function test_filters_counts_and_search_do_not_leak_other_departments(): void
    {
        $mine = Risk::factory()->create(['title' => '<script>alert(1)</script>']);
        $other = Risk::factory()->create(['title' => 'Risiko sulit bahagian lain']);
        $this->actingAs($mine->owner)->get('/risks?q='.urlencode($other->code()))->assertOk()->assertDontSee($other->title);
        $this->get('/risks?level=medium')->assertOk()->assertSee($mine->code())->assertDontSee($other->title);
        $this->get('/risks/'.$mine->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/risks?level=high')->assertOk()->assertDontSee($mine->code());
        $this->get('/risks/'.$mine->id.'/edit')->assertOk();
        $this->get('/risks/create')->assertOk();
    }

    public function test_score_boundaries_and_historical_method_survive_edits(): void
    {
        $owner = $this->person();
        foreach ([[1, 1, 1, 'low'], [2, 2, 4, 'low'], [1, 5, 5, 'medium'], [3, 4, 12, 'medium'], [3, 5, 15, 'high'], [5, 5, 25, 'high']] as [$likelihood,$impact,$score,$level]) {
            $this->actingAs($owner)->post('/risks', array_replace($this->payload(), compact('likelihood', 'impact')))->assertRedirect();
            $risk = Risk::latest('id')->first();
            $this->assertSame($score, $risk->score);
            $this->assertSame($level, $risk->level);
        }
        $method = array_replace($risk->method, ['version' => 'historical-test', 'low_max' => 10]);
        $risk->update(['method' => $method]);
        $this->put('/risks/'.$risk->id, array_replace($this->payload(), ['likelihood' => 2, 'impact' => 3, 'lock_version' => 0, 'method' => ['low_max' => 1]]))->assertRedirect();
        $this->assertSame('low', $risk->fresh()->level);
        $this->assertSame($method, $risk->fresh()->method);
        $this->put('/risks/'.$risk->id, $this->payload() + ['lock_version' => 0])->assertStatus(409);
    }

    public function test_owner_account_changes_cannot_orphan_risk_and_deactivation_remains_possible(): void
    {
        $risk = Risk::factory()->create();
        $owner = $risk->owner;
        $admin = $this->person('admin');
        $data = $owner->only(['name', 'email', 'department_id', 'is_active']) + ['role' => 'approver'];
        $this->actingAs($admin)->put('/admin/users/'.$owner->id, $data)->assertSessionHasErrors('role');
        $this->put('/admin/users/'.$owner->id, array_replace($data, ['role' => 'officer', 'department_id' => $admin->department_id]))->assertSessionHasErrors('role');
        $this->put('/admin/users/'.$owner->id, array_replace($data, ['role' => 'officer', 'is_active' => false]))->assertRedirect();
        $this->assertFalse($owner->fresh()->is_active);
    }

    public function test_patch_updates_require_version_and_reject_stale_versions(): void
    {
        $risk = Risk::factory()->create();
        $this->actingAs($risk->owner)->patch('/risks/'.$risk->id, $this->payload())->assertSessionHasErrors('lock_version');
        $this->patch('/risks/'.$risk->id, $this->payload() + ['lock_version' => 0])->assertRedirect();
        $this->assertSame(20, $risk->fresh()->score);
        $this->patch('/risks/'.$risk->id, $this->payload() + ['lock_version' => 0])->assertStatus(409);
    }
}
