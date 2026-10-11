<?php

namespace Database\Factories;

use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\InternalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditFinding>
 */
class AuditFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'internal_audit_id' => InternalAudit::factory(),
            'audit_checklist_item_id' => fn (array $attributes) => AuditChecklistItem::create(['internal_audit_id' => $attributes['internal_audit_id'], 'requirement' => 'Semakan akses', 'reference' => 'POL-01', 'result' => 'nonconform', 'notes' => 'Akaun lama aktif'])->id,
            'owner_id' => fn (array $attributes) => User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => InternalAudit::findOrFail($attributes['internal_audit_id'])->department_id])->id,
            'title' => 'Akses perlu disemak', 'category' => 'nonconformity', 'severity' => 'medium', 'description' => 'Akaun lama masih aktif', 'due_date' => now()->addWeek()->toDateString(), 'status' => 'open',
        ];
    }
}
