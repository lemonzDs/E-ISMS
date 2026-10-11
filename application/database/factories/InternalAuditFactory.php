<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\InternalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalAudit>
 */
class InternalAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->state(fn () => ['role' => 'coordinator', 'is_active' => true, 'department_id' => Department::create(['code' => uniqid('TEAM-'), 'name' => 'Bahagian juruaudit contoh'])->id]),
            'department_id' => fn () => Department::create(['code' => uniqid('AUD-'), 'name' => 'Bahagian diaudit contoh'])->id,
            'title' => 'Audit kawalan akses contoh', 'scope' => 'Semakan akses aplikasi', 'criteria' => 'Polisi akses contoh',
            'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'status' => 'draft', 'lock_version' => 0,
        ];
    }
}
