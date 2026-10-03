<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('DemoSeeder hanya dibenarkan untuk local/testing.');
        }
        $password = config('isms.demo_password');
        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException('Tetapkan ISMS_DEMO_PASSWORD sekurang-kurangnya 12 aksara untuk data contoh.');
        }
        $ict = Department::firstOrCreate(['code' => 'DEMO-ICT'], ['name' => 'Bahagian ICT (contoh)']);
        $finance = Department::firstOrCreate(['code' => 'DEMO-KEW'], ['name' => 'Bahagian Kewangan (contoh)']);
        $people = [];
        foreach (['admin' => 'Pentadbir Demo', 'penyelaras' => 'Penyelaras Demo', 'pegawai' => 'Pegawai ICT Demo', 'pelulus' => 'Pelulus Demo', 'pegawai.kewangan' => 'Pegawai Kewangan Demo'] as $key => $name) {
            $role = ['admin' => 'admin', 'penyelaras' => 'coordinator', 'pegawai' => 'officer', 'pelulus' => 'approver', 'pegawai.kewangan' => 'officer'][$key];
            $people[$key] = User::firstOrCreate(['email' => $key.'@example.test'], ['name' => $name, 'password' => $password, 'role' => $role, 'department_id' => $key === 'pegawai.kewangan' ? $finance->id : $ict->id, 'is_active' => true]);
        }
        $workflow = app(DocumentWorkflow::class);
        foreach (['DEMO-DOC-001' => ['Polisi keselamatan maklumat (contoh)', 'pegawai', 'draft'], 'DEMO-DOC-002' => ['Prosedur kawalan akses (contoh)', 'pegawai', 'submitted'], 'DEMO-DOC-003' => ['Panduan sandaran (contoh)', 'pegawai', 'reviewed'], 'DEMO-DOC-004' => ['Rekod kewangan (contoh)', 'pegawai.kewangan', 'approved']] as $code => [$title,$owner,$status]) {
            if (Document::where('code', $code)->exists()) {
                continue;
            }
            $temp = tempnam(sys_get_temp_dir(), 'isms-demo-');
            file_put_contents($temp, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
            try {
                $document = $workflow->create($people[$owner], ['code' => $code, 'title' => $title], new UploadedFile($temp, 'dokumen-contoh.pdf', 'application/pdf', null, true));
                $version = $document->versions()->firstOrFail();
                if ($status !== 'draft') {
                    $workflow->transition($people[$owner], $version, 'submit', 0, 'Data contoh rintis');
                }
                if (in_array($status, ['reviewed', 'approved'], true)) {
                    $workflow->transition($people['penyelaras'], $version, 'review', 1, 'Semakan contoh');
                }
                if ($status === 'approved') {
                    $workflow->transition($people['pelulus'], $version, 'approve', 2, 'Kelulusan contoh');
                }
            } finally {
                unlink($temp);
            }
        }
    }
}
