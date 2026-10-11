<?php

namespace Database\Factories;

use App\Models\AcceptanceSession;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcceptanceSession>
 */
class AcceptanceSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => fn () => Department::create(['code' => uniqid('UAT-'), 'name' => 'Bahagian ujian contoh'])->id,
            'created_by' => User::factory()->state(fn () => ['role' => 'coordinator', 'is_active' => true, 'department_id' => Department::create(['code' => uniqid('COORD-'), 'name' => 'Bahagian penyelaras contoh'])->id]),
            'tester_id' => fn (array $attributes) => User::factory()->create(['role' => 'officer', 'is_active' => true, 'department_id' => $attributes['department_id']])->id,
            'title' => 'Sesi UAT contoh', 'build_reference' => 'pilot-v1', 'scope' => 'Ujian manual dengan data contoh', 'status' => 'draft', 'lock_version' => 0,
        ];
    }
}
