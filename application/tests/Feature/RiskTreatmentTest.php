<?php

namespace Tests\Feature;

use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiskTreatmentTest extends TestCase
{
    use RefreshDatabase;

    private function reviewer(Risk $risk): User
    {
        return User::factory()->create(['role' => 'coordinator', 'department_id' => $risk->department_id, 'is_active' => true]);
    }

    private function action(Risk $risk): RiskAction
    {
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/actions', ['title' => 'Semak akses', 'description' => 'Batalkan akaun lama', 'assignee_id' => $risk->owner_id, 'due_date' => now()->addWeek()->toDateString(), 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();

        return RiskAction::where('risk_id', $risk->id)->latest('id')->firstOrFail();
    }

    private function submit(Risk $risk, RiskAction $action): void
    {
        $this->actingAs($risk->owner)->post('/risk-actions/'.$action->id.'/submit', ['lock_version' => $risk->fresh()->lock_version, 'summary' => 'Akses telah disemak', 'file' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\nBukti contoh")])->assertRedirect();
    }

    public function test_complete_treatment_cycle_private_evidence_and_residual_calculation(): void
    {
        Storage::fake('local');
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $action = $this->action($risk);
        $this->submit($risk, $action);
        $evidence = $action->evidence()->firstOrFail();
        $this->get('/risk-evidence/'.$evidence->id)->assertOk();
        $reviewer = $this->reviewer($risk);
        $this->actingAs($reviewer)->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Bukti mencukupi', 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();
        $this->assertSame('verified', $action->fresh()->status);
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/residual', ['likelihood' => 1, 'impact' => 2, 'rationale' => 'Akses lama dibatalkan', 'controls' => 'Semakan akses berkala', 'lock_version' => $risk->fresh()->lock_version, 'score' => 25])->assertRedirect();
        $residual = $risk->residualAssessments()->firstOrFail();
        $this->assertSame(2, $residual->score);
        $this->assertSame('low', $residual->level);
        $this->assertSame(9, $risk->fresh()->score);
        $this->assertSame('reviewed', $risk->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['risk_id' => $risk->id, 'action' => 'treatment.residual']);
        $this->get('/risks/'.$risk->id.'/treatment')->assertOk()->assertSee('Risiko baki');
    }

    public function test_roles_tenants_and_self_verification_cannot_bypass_treatment_controls(): void
    {
        Storage::fake('local');
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $action = $this->action($risk);
        $this->submit($risk, $action);
        $evidence = $action->evidence()->firstOrFail();
        foreach ([Risk::factory()->create()->owner, User::factory()->create(['role' => 'admin', 'is_active' => true, 'department_id' => $risk->department_id])] as $outsider) {
            $this->actingAs($outsider)->get('/risks/'.$risk->id.'/treatment')->assertForbidden();
            $this->get('/risk-evidence/'.$evidence->id)->assertForbidden();
            $this->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Cuba', 'lock_version' => $risk->fresh()->lock_version])->assertForbidden();
        }
        $risk->owner->update(['role' => 'coordinator']);
        $this->actingAs($risk->owner->fresh())->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Cuba', 'lock_version' => $risk->fresh()->lock_version])->assertForbidden();
    }

    public function test_pending_work_blocks_residual_and_reassessment_and_stale_actions_are_rejected(): void
    {
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $action = $this->action($risk);
        $this->post('/risks/'.$risk->id.'/residual', ['likelihood' => 1, 'impact' => 1, 'controls' => 'Contoh', 'rationale' => 'Contoh', 'lock_version' => $risk->fresh()->lock_version])->assertStatus(409);
        $this->post('/risks/'.$risk->id.'/transition', ['action' => 'reassess', 'comment' => 'Baharu', 'lock_version' => $risk->fresh()->lock_version])->assertStatus(409);
        $this->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Contoh', 'lock_version' => 0])->assertStatus(409);
        $this->post('/risk-actions/'.$action->id.'/submit', ['summary' => 'Siap', 'lock_version' => $risk->fresh()->lock_version])->assertSessionHasErrors('file');
    }

    public function test_return_resubmit_and_reopen_preserve_evidence_and_invalidate_residual(): void
    {
        Storage::fake('local');
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $action = $this->action($risk);
        $reviewer = $this->reviewer($risk);
        $this->submit($risk, $action);
        $this->actingAs($reviewer)->post('/risk-actions/'.$action->id.'/decision', ['action' => 'return', 'comment' => 'Tambah bukti', 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();
        $this->submit($risk, $action);
        $this->assertSame(2, $action->evidence()->count());
        $this->actingAs($reviewer)->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Lengkap', 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/residual', ['likelihood' => 2, 'impact' => 2, 'controls' => 'Akses disekat', 'rationale' => 'Bukti lengkap', 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();
        $this->assertTrue($risk->fresh()->residualIsCurrent());
        $this->actingAs($reviewer)->post('/risk-actions/'.$action->id.'/decision', ['action' => 'reopen', 'comment' => 'Kawalan tidak berkesan', 'lock_version' => $risk->fresh()->lock_version])->assertRedirect();
        $this->assertFalse($risk->fresh()->residualIsCurrent());
        $this->assertSame(1, $risk->residualAssessments()->count());
    }

    public function test_assignment_validation_revision_and_empty_plan_guards(): void
    {
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $outsider = Risk::factory()->create()->owner;
        $payload = ['title' => 'Tindakan', 'description' => 'Butiran', 'assignee_id' => $outsider->id, 'due_date' => '2026-01-01', 'lock_version' => 0];
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/actions', $payload)->assertSessionHasErrors('assignee_id');
        $this->post('/risks/'.$risk->id.'/residual', ['likelihood' => 1, 'impact' => 1, 'rationale' => 'Contoh', 'controls' => 'Contoh', 'lock_version' => 0])->assertStatus(409);
        $this->post('/risks/'.$risk->id.'/residual', ['likelihood' => 0, 'impact' => 6, 'rationale' => 'Contoh', 'controls' => 'Contoh', 'lock_version' => 0])->assertSessionHasErrors(['likelihood', 'impact']);
        $action = $this->action($risk);
        $this->put('/risk-actions/'.$action->id, array_replace($payload, ['assignee_id' => $risk->owner_id, 'lock_version' => 1, 'comment' => 'Tarikh dibetulkan']))->assertRedirect();
        $this->assertTrue($action->fresh()->overdue());
        $this->put('/risk-actions/'.$action->id, array_replace($payload, ['assignee_id' => $risk->owner_id, 'lock_version' => 1, 'comment' => 'Lapuk']))->assertStatus(409);
        $this->post('/risk-actions/'.$action->id.'/submit', ['summary' => 'Cuba', 'lock_version' => 2, 'file' => UploadedFile::fake()->createWithContent('script.pdf', '<script>bad</script>')->mimeType('text/html')])->assertSessionHasErrors('file');
    }

    public function test_old_cycles_are_readonly_and_new_actions_invalidate_previous_residual(): void
    {
        Storage::fake('local');
        $risk = Risk::factory()->create(['status' => 'reviewed']);
        $action = $this->action($risk);
        $this->submit($risk, $action);
        $reviewer = $this->reviewer($risk);
        $this->actingAs($reviewer)->post('/risk-actions/'.$action->id.'/decision', ['action' => 'verify', 'comment' => 'Lengkap', 'lock_version' => 2])->assertRedirect();
        $this->actingAs($risk->owner)->post('/risks/'.$risk->id.'/residual', ['likelihood' => 1, 'impact' => 1, 'controls' => 'Kawalan', 'rationale' => 'Alasan', 'lock_version' => 3])->assertRedirect();
        $this->post('/risks/'.$risk->id.'/transition', ['action' => 'reassess', 'comment' => 'Perubahan proses', 'lock_version' => 4])->assertRedirect();
        $this->assertSame(2, $risk->fresh()->assessment_cycle);
        $this->assertFalse($risk->fresh()->residualIsCurrent());
        $this->post('/risks/'.$risk->id.'/transition', ['action' => 'submit', 'lock_version' => 5]);
        $this->actingAs($reviewer)->post('/risks/'.$risk->id.'/transition', ['action' => 'review', 'lock_version' => 6]);
        $this->post('/risk-actions/'.$action->id.'/decision', ['action' => 'reopen', 'comment' => 'Rekod lama', 'lock_version' => 7])->assertForbidden();
        $this->get('/risk-evidence/'.$action->evidence()->first()->id)->assertOk();
        $this->action($risk);
        $this->assertFalse($risk->fresh()->residualIsCurrent());
    }
}
