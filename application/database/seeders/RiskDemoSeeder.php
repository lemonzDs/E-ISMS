<?php

namespace Database\Seeders;

use App\Models\Risk;
use App\Models\User;
use App\Services\RiskWorkflow;
use Illuminate\Database\Seeder;

class RiskDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data contoh risiko hanya dibenarkan di local/testing.');
        }
        $owner = User::where('email', 'pegawai@example.test')->first();
        if (! $owner || ! $owner->is_active || $owner->role !== 'officer') {
            return;
        }
        $title = '[DEMO] Akses tidak dibatalkan selepas pertukaran pegawai';
        if (Risk::where('owner_id', $owner->id)->where('title', $title)->exists()) {
            return;
        }
        $workflow = app(RiskWorkflow::class);
        $risk = $workflow->create($owner, ['title' => $title, 'asset_process' => 'Akaun aplikasi contoh', 'threat' => 'Penggunaan akses lama tanpa kebenaran', 'vulnerability' => 'Semakan senarai pengguna tidak berkala', 'consequence' => 'Maklumat dalaman terdedah', 'existing_controls' => 'Akses melalui akaun berdaftar dan semakan manual', 'likelihood' => 3, 'impact' => 5, 'rationale' => 'Data sintetik: pertukaran pegawai memerlukan semakan akses.']);
        $workflow->transition($owner, $risk, 'submit', 0, null);
    }
}
