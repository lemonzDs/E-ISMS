<?php

namespace Database\Seeders;

use App\Models\InternalAudit;
use App\Models\User;
use App\Services\InternalAuditWorkflow;
use Illuminate\Database\Seeder;

class InternalAuditDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Data contoh audit hanya untuk local/testing.');
        }
        if (InternalAudit::where('title', 'Audit kawalan akses kewangan (contoh)')->exists()) {
            return;
        }
        $lead = User::where('email', 'penyelaras@example.test')->where('is_active', true)->where('role', 'coordinator')->firstOrFail();
        $owner = User::where('email', 'pegawai.kewangan@example.test')->where('is_active', true)->where('role', 'officer')->firstOrFail();
        $workflow = app(InternalAuditWorkflow::class);
        $audit = $workflow->create($lead, ['title' => 'Audit kawalan akses kewangan (contoh)', 'scope' => 'Semakan daftar pengguna, penamatan akses dan rekod semakan bagi aplikasi kewangan contoh.', 'criteria' => 'Polisi kawalan akses contoh, prosedur penamatan akaun dan daftar kebenaran pengguna.', 'department_id' => $owner->department_id, 'start_date' => now('Asia/Kuala_Lumpur')->toDateString(), 'end_date' => now('Asia/Kuala_Lumpur')->addDay()->toDateString(), 'lead_id' => $lead->id]);
        $workflow->checklist($lead, $audit, ['requirement' => 'Akaun pegawai yang bertukar tugas ditamatkan dan direkodkan.', 'reference' => 'PRO-AKSES-02 (contoh)', 'lock_version' => $audit->fresh()->lock_version]);
        foreach (['schedule', 'start'] as $action) {
            $workflow->transition($lead, $audit, ['action' => $action, 'comment' => 'Data contoh untuk review modul audit.', 'lock_version' => $audit->fresh()->lock_version]);
        }
        $item = $audit->checklist()->firstOrFail();
        $workflow->checklist($lead, $audit, ['result' => 'nonconform', 'notes' => 'Sampel contoh mendapati akaun lama masih aktif selepas pertukaran tugas.', 'lock_version' => $audit->fresh()->lock_version], $item);
        $workflow->finding($lead, $audit, ['audit_checklist_item_id' => $item->id, 'title' => 'Penamatan akses selepas pertukaran tugas', 'category' => 'nonconformity', 'severity' => 'medium', 'description' => 'Akaun lama dalam sampel contoh masih aktif. Rekod penamatan dan pengesahan akses perlu dilengkapkan.', 'owner_id' => $owner->id, 'due_date' => now('Asia/Kuala_Lumpur')->addWeek()->toDateString(), 'lock_version' => $audit->fresh()->lock_version]);
    }
}
