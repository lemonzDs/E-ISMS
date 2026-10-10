<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use App\Services\TreatmentReminders;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TreatmentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function action(string $dueDate = '2026-10-15'): RiskAction
    {
        $risk = Risk::factory()->create(['status' => 'reviewed']);

        return $risk->actions()->create(['title' => 'Tindakan contoh', 'description' => 'Bukti contoh', 'assignee_id' => $risk->owner_id, 'cycle' => 1, 'status' => 'open', 'due_date' => $dueDate]);
    }

    public function test_upcoming_and_overdue_are_sent_once_per_deadline_in_malaysia_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 17:00:00', 'UTC'));
        $action = $this->action('2026-10-11');
        $this->artisan('isms:remind-treatments')->expectsOutput('1 peringatan baharu dihantar.')->assertSuccessful();
        $this->artisan('isms:remind-treatments')->expectsOutput('0 peringatan baharu dihantar.')->assertSuccessful();
        $this->assertDatabaseHas('risk_action_reminders', ['stage' => 'upcoming', 'risk_action_id' => $action->id]);
        $this->travelTo(Carbon::parse('2026-10-11 17:00:00', 'UTC'));
        $this->artisan('isms:remind-treatments')->expectsOutput('1 peringatan baharu dihantar.')->assertSuccessful();
        $this->artisan('isms:remind-treatments')->expectsOutput('0 peringatan baharu dihantar.')->assertSuccessful();
        $this->assertDatabaseCount('risk_action_reminders', 2);
        $this->assertSame(2, $action->assignee->notifications()->count());
        $alert = $action->assignee->notifications()->latest('id')->firstOrFail();
        $this->assertSame('treatment', $alert->data['kind']);
        $this->actingAs($action->assignee)->post('/notifications/'.$alert->id.'/open')->assertRedirect('/risks/'.$action->risk_id.'/treatment');
        $this->travelBack();
    }

    public function test_seven_day_boundary_and_future_deadlines_are_counted_correctly(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 08:00:00', 'Asia/Kuala_Lumpur'));
        $this->action('2026-10-18');
        $this->action('2026-10-19');
        $this->action('2026-10-10');
        $this->assertSame(2, app(TreatmentReminders::class)->send());
        $this->assertDatabaseCount('notifications', 2);
        $this->travelBack();
    }

    #[TestWith(['submitted'])]
    #[TestWith(['verified'])]
    public function test_submitted_and_verified_actions_are_not_reminded(string $status): void
    {
        $action = $this->action('2020-01-01');
        $action->update(['status' => $status]);
        $this->assertSame(0, app(TreatmentReminders::class)->send());
        $this->assertDatabaseCount('risk_action_reminders', 0);
    }

    public function test_old_cycles_and_unreviewed_risks_do_not_create_reminders(): void
    {
        $old = $this->action('2020-01-01');
        $old->risk->update(['assessment_cycle' => 2]);
        $draft = $this->action('2020-01-01');
        $draft->risk->update(['status' => 'draft']);
        $this->assertSame(0, app(TreatmentReminders::class)->send());
        $this->assertDatabaseCount('notifications', 0);
    }

    #[TestWith(['inactive'])]
    #[TestWith(['department'])]
    #[TestWith(['role'])]
    public function test_assignees_without_current_permission_are_excluded(string $reason): void
    {
        $action = $this->action('2020-01-01');
        $attributes = match ($reason) {
            'inactive' => ['is_active' => false],
            'department' => ['department_id' => Department::create(['code' => 'OTHER', 'name' => 'Bahagian lain'])->id],
            'role' => ['role' => 'approver'],
        };
        $action->assignee->update($attributes);
        $this->assertSame(0, app(TreatmentReminders::class)->send());
        $this->assertDatabaseCount('risk_action_reminders', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_reassignment_and_changed_deadline_allow_new_delivery_without_duplicates(): void
    {
        $this->travelTo(Carbon::parse('2026-10-11 08:00:00', 'Asia/Kuala_Lumpur'));
        $action = $this->action();
        $this->assertSame(1, app(TreatmentReminders::class)->send());
        $newAssignee = User::factory()->create(['department_id' => $action->risk->department_id, 'role' => 'officer', 'is_active' => true]);
        $action->update(['assignee_id' => $newAssignee->id]);
        $this->assertSame(1, app(TreatmentReminders::class)->send());
        $action->update(['due_date' => '2026-10-16', 'status' => 'returned']);
        $this->assertSame(1, app(TreatmentReminders::class)->send());
        $this->assertSame(0, app(TreatmentReminders::class)->send());
        $this->assertSame(2, $newAssignee->notifications()->count());
        $this->assertDatabaseCount('risk_action_reminders', 3);
        $this->travelBack();
    }

    public function test_failure_does_not_claim_delivery_and_retry_can_send(): void
    {
        $action = $this->action('2020-01-01');
        DatabaseNotification::creating(fn () => throw new \RuntimeException('Notification unavailable'));
        try {
            app(TreatmentReminders::class)->send();
            $this->fail('Notification creation must fail');
        } catch (\RuntimeException $error) {
            $this->assertSame('Notification unavailable', $error->getMessage());
            $this->assertDatabaseCount('risk_action_reminders', 0);
            $this->assertDatabaseCount('notifications', 0);
        } finally {
            DatabaseNotification::flushEventListeners();
        }
        $this->assertSame(1, app(TreatmentReminders::class)->send());
        $this->assertSame(1, $action->assignee->notifications()->count());
    }

    public function test_database_enforces_delivery_uniqueness(): void
    {
        $action = $this->action('2020-01-01');
        app(TreatmentReminders::class)->send();
        $key = ['risk_action_id' => $action->id, 'assignee_id' => $action->assignee_id, 'due_date' => '2020-01-01', 'stage' => 'overdue', 'created_at' => now()];
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('risk_action_reminders')->insert($key);
    }

    public function test_daily_schedule_is_due_at_eight_am_malaysia_time(): void
    {
        $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'isms:remind-treatments'));
        $this->assertNotNull($event);
        $this->travelTo(Carbon::parse('2026-10-11 07:59:00', 'Asia/Kuala_Lumpur'));
        $this->assertFalse($event->isDue($this->app));
        $this->travelTo(Carbon::parse('2026-10-11 08:00:00', 'Asia/Kuala_Lumpur'));
        $this->assertTrue($event->isDue($this->app));
        $this->travelBack();
    }
}
