# Cadangan pelaksanaan Laravel — untuk review

Status: cadangan teknikal, belum menjadi sistem operasi sebenar.

## Sasaran
Laravel 13.x, PHP 8.3 minimum, MySQL 8.4, Blade, CSS melalui Vite dan JavaScript ringan. Gunakan patch stabil terkini yang serasi semasa pemasangan serta simpan `composer.lock` dan lockfile npm. Laravel 13 disahkan melalui [nota keluaran rasmi](https://laravel.com/framework/docs/releases) dan [keperluan pemasangan](https://laravel.com/framework/docs/deployment) pada 2 Oktober 2026.

Laragon tempatan: PHP 8.3.30 disahkan melalui CLI; folder MySQL 8.4.3 tersedia. PHP dan Composer tidak berada dalam PATH sesi semasa. Sambungan DB, ekstensi PHP, konfigurasi Apache dan Composer akan disahkan semasa setup aplikasi. Tiada servis sedia ada diubah dalam fasa mockup.

## Bentuk aplikasi
Monolit modular Laravel, sesuai untuk operasi dalaman dan penyelenggaraan pasukan kecil. Blade mengelakkan keperluan pelayan frontend berasingan selepas build. Modul: Identity, Documents, Risk, Actions, Controls, Reporting dan Audit. Gunakan Form Requests, Policies, Eloquent, migrations, transactions dan application services untuk perubahan status; bukan semua logik dalam controller.

## Model data cadangan
- departments, users, roles, permissions dan rekod skop akses.
- scopes, assets_processes, risks, risk_assessments, treatment_plans.
- controls, applicability_decisions dan hubungan risk_control.
- documents, document_versions, reviews dan approvals.
- actions, evidence_files, evidence_links dan audit_events.

Gunakan foreign keys, indeks bagi skop/status/tarikh, pagination pelayan dan optimistic concurrency untuk mengelakkan rekod ditimpa oleh dua pegawai. Kaedah risiko perlu berversi supaya perubahan matriks tidak mengubah penilaian sejarah.

## Kawalan pelaksanaan
- Login dalaman tanpa pendaftaran awam; kuasa pentadbir tidak memberi kelulusan urusan secara automatik.
- Policies menyemak peranan, skop bahagian dan akses rekod bagi setiap paparan, mutasi, lampiran dan eksport.
- Lampiran di storan private; akses melalui controller dibenarkan, bukan URL public. Had saiz, jenis fail dan pemeriksaan fail ditentukan sebelum penggunaan sebenar.
- Semakan dan kelulusan dilaksana dalam transaksi; audit dicatat bersama perubahan. Versi diterbitkan tidak ditimpa.
- CSRF, validasi pelayan, output escaping, rate limits dan session protection menggunakan kemudahan Laravel.
- Tiada soft delete atau tempoh retensi ditetapkan sebelum polisi organisasi disahkan.
- Masa disimpan UTC, dipaparkan Asia/Kuala_Lumpur; tarikh sasaran ialah date-only.
- Notifikasi tempatan melalui database/mail log dahulu. Penghantaran e-mel sebenar memerlukan konfigurasi yang dipersetujui.
- Queue database dan scheduler Laravel mencukupi sebagai permulaan; Redis bukan prasyarat MVP.

## Fasa pembangunan selepas review
1. Asas projek, identiti, matriks akses, skop dan audit.
2. Dokumen terkawal dan kelulusan berdasarkan borang sebenar.
3. Risiko, rawatan, tindakan dan bukti; kaedah risiko disahkan.
4. SoA, laporan, import rintis dan ujian penerimaan pengguna.

Sebelum setiap fasa, sahkan aturan urusan yang masih terbuka. Audit dalaman penuh dan insiden ialah fasa lanjutan mengikut `.briefing`.

## Strategi pengesahan
Feature tests untuk akses silang bahagian/rekod/lampiran/eksport, pengasingan pelulus, transisi tidak sah, sejarah versi, transaksi audit dan konflik kemas kini. Ujian integrasi menggunakan MySQL sasaran. Browser tests untuk laluan pegawai, borang, penapisan dan telefon. Ujian import, sandaran/pemulihan dan UAT dibuat sebelum data sebenar digunakan.

## Laragon
Document root aplikasi Laravel mestilah folder `public`; jangan dedahkan root projek, `.env` atau storan dalaman. Cadangan URL `isms.test` tertakluk pada konfigurasi Laragon pengguna. Mockup semasa di `mockup/index.html` boleh dibuka terus atau dilayan oleh PHP tempatan; ia belum menggunakan Laravel.
