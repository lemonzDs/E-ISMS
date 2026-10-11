<?php

namespace Database\Seeders;

use App\Models\AcceptanceSession;
use App\Models\User;
use App\Services\AcceptanceTesting;
use Illuminate\Database\Seeder;

class AcceptanceDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Data contoh UAT hanya untuk local/testing.');
        }
        if (AcceptanceSession::where('title', 'UAT rintis ICT (contoh)')->exists()) {
            return;
        }
        $creator = User::where('email', 'penyelaras@example.test')->where('is_active', true)->where('role', 'coordinator')->firstOrFail();
        $tester = User::where('email', 'pegawai@example.test')->where('is_active', true)->where('role', 'officer')->firstOrFail();
        app(AcceptanceTesting::class)->create($creator, ['title' => 'UAT rintis ICT (contoh)', 'build_reference' => 'rintis-UAT-v1', 'scope' => 'Semakan aliran e-ISMS dalam Laragon menggunakan data contoh. Rekod keputusan selepas ujian sebenar oleh pegawai; jangan tandakan Lulus berdasarkan andaian.', 'department_id' => $tester->department_id, 'tester_id' => $tester->id]);
    }
}
