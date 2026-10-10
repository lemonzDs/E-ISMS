<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'officer', ?Department $department = null): User
    {
        $department ??= Department::create(['name' => 'Bahagian '.uniqid(), 'code' => uniqid()]);

        return User::factory()->create(['role' => $role, 'department_id' => $department->id, 'is_active' => true]);
    }

    private function document(User $owner, string $title = 'Dokumen contoh'): Document
    {
        $document = Document::create(['code' => uniqid('DOC-'), 'title' => $title, 'department_id' => $owner->department_id, 'owner_id' => $owner->id]);
        $document->versions()->create(['number' => 1, 'status' => 'draft', 'path' => 'private-secret.pdf', 'original_name' => 'secret.pdf', 'mime' => 'application/pdf', 'size' => 10]);

        return $document;
    }

    private function rows(string $content): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_guests_inactive_users_and_technical_admin_cannot_export_business_data(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/risks/export')->assertRedirect('/login');
        $admin = $this->person('admin');
        $this->actingAs($admin)->get('/reports')->assertForbidden();
        $this->get('/reports/documents/export')->assertForbidden();
        $admin->update(['role' => 'officer', 'is_active' => false]);
        $this->get('/reports/risks/export')->assertRedirect('/login');
    }

    public function test_officer_reports_and_all_exports_exclude_other_departments(): void
    {
        $owner = $this->person();
        $foreign = $this->person();
        $this->document($owner, 'Dokumen bahagian saya');
        $this->document($foreign, 'Dokumen sulit bahagian lain');
        $ownRisk = Risk::factory()->create(['owner_id' => $owner->id, 'department_id' => $owner->department_id, 'status' => 'reviewed', 'title' => 'Risiko bahagian saya']);
        $foreignRisk = Risk::factory()->create(['owner_id' => $foreign->id, 'department_id' => $foreign->department_id, 'status' => 'reviewed', 'title' => 'Risiko sulit bahagian lain']);
        foreach ([$ownRisk, $foreignRisk] as $risk) {
            $risk->actions()->create(['title' => $risk->title, 'description' => 'Contoh', 'assignee_id' => $risk->owner_id, 'due_date' => '2020-01-01', 'cycle' => 1, 'status' => 'open']);
        }
        $this->actingAs($owner)->get('/reports')->assertOk()->assertViewHas('totals', ['documents' => 1, 'risks' => 1, 'actions' => 1, 'overdue' => 1]);
        foreach (['documents', 'risks', 'actions'] as $dataset) {
            $response = $this->get('/reports/'.$dataset.'/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $content = $response->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
            $this->assertStringNotContainsString('sulit bahagian lain', $content);
            $this->assertStringNotContainsString('private-secret.pdf', $content);
            $this->assertCount(2, $this->rows($content));
            $this->get('/reports/'.$dataset.'/export?department_id='.$foreign->department_id)->assertForbidden();
        }
        $this->get('/reports?department_id='.$foreign->department_id)->assertForbidden();
    }

    #[TestWith(['coordinator'])]
    #[TestWith(['approver'])]
    public function test_coordinator_and_approver_can_filter_reports_and_exports_by_department(string $role): void
    {
        $user = $this->person($role);
        $owner = $this->person();
        $this->document($user, 'Dokumen pertama');
        $this->document($owner, 'Dokumen pilihan');
        $this->actingAs($user)->get('/reports')->assertViewHas('totals', fn ($totals) => $totals['documents'] === 2);
        $this->get('/reports?department_id='.$owner->department_id)->assertViewHas('totals', fn ($totals) => $totals['documents'] === 1);
        $content = $this->get('/reports/documents/export?department_id='.$owner->department_id)->assertOk()->streamedContent();
        $this->assertStringContainsString('Dokumen pilihan', $content);
        $this->assertStringNotContainsString('Dokumen pertama', $content);
        $this->get('/reports/unknown/export')->assertNotFound();
        $this->get('/reports?department_id=99999')->assertSessionHasErrors('department_id');
    }

    public function test_document_summary_uses_latest_version_once_and_actions_only_current_cycle(): void
    {
        $owner = $this->person();
        $document = $this->document($owner);
        $document->versions()->create(['number' => 2, 'status' => 'approved', 'path' => 'new.pdf', 'original_name' => 'new.pdf', 'mime' => 'application/pdf', 'size' => 10]);
        $risk = Risk::factory()->create(['owner_id' => $owner->id, 'department_id' => $owner->department_id, 'status' => 'reviewed', 'assessment_cycle' => 2]);
        foreach ([[1, 'open'], [2, 'verified'], [2, 'submitted']] as [$cycle, $status]) {
            $risk->actions()->create(['title' => 'Kitaran '.$cycle.' '.$status, 'description' => 'Contoh', 'assignee_id' => $owner->id, 'due_date' => '2020-01-01', 'cycle' => $cycle, 'status' => $status]);
        }
        $this->actingAs($owner)->get('/reports')->assertViewHas('documentCounts', ['draft' => 0, 'submitted' => 0, 'reviewed' => 0, 'returned' => 0, 'approved' => 1])->assertViewHas('totals', ['documents' => 1, 'risks' => 1, 'actions' => 2, 'overdue' => 1]);
        $rows = $this->rows($this->get('/reports/documents/export')->streamedContent());
        $this->assertSame(['2', 'Diluluskan'], array_slice($rows[1], 4));
        $content = $this->get('/reports/actions/export')->streamedContent();
        $this->assertStringNotContainsString('Kitaran 1', $content);
        $this->assertCount(3, $this->rows($content));
        $risk->update(['status' => 'draft']);
        $this->get('/reports')->assertViewHas('totals', fn ($totals) => $totals['actions'] === 0);
    }

    #[TestWith(['=1+1'])]
    #[TestWith([' +SUM(1,2)'])]
    #[TestWith(['-2+3'])]
    #[TestWith(['@SUM(1,2)'])]
    #[TestWith(["\t=1+1"])]
    public function test_csv_neutralizes_formula_cells_without_breaking_quotes_or_newlines(string $title): void
    {
        $owner = $this->person();
        $this->document($owner, $title);
        $rows = $this->rows($this->actingAs($owner)->get('/reports/documents/export')->streamedContent());
        $this->assertSame("'".$title, $rows[1][1]);
    }

    public function test_csv_round_trips_malay_quotes_commas_and_newlines(): void
    {
        $owner = $this->person();
        $title = "Polisi \"Keselamatan\", bahagian\nSemakan kedua";
        $this->document($owner, $title);
        $rows = $this->rows($this->actingAs($owner)->get('/reports/documents/export')->streamedContent());
        $this->assertSame($title, $rows[1][1]);
    }
}
