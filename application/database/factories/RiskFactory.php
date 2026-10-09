<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Risk;
use App\Models\User;
use App\Services\RiskWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Risk>
 */
class RiskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->state(fn () => ['role' => 'officer', 'department_id' => Department::create(['name' => 'Bahagian contoh', 'code' => uniqid()])->id]),
            'department_id' => fn (array $attributes) => User::findOrFail($attributes['owner_id'])->department_id,
            'title' => 'Risiko contoh', 'asset_process' => 'Proses contoh',
            'threat' => 'Ancaman contoh', 'vulnerability' => 'Kelemahan contoh',
            'consequence' => 'Kesan contoh', 'existing_controls' => 'Kawalan contoh',
            'rationale' => 'Alasan contoh', 'likelihood' => 3, 'impact' => 3,
            'score' => 9, 'level' => 'medium', 'method' => RiskWorkflow::METHOD,
            'status' => 'draft', 'lock_version' => 0,
        ];
    }
}
