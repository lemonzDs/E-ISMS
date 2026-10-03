# Laravel document workflow implementation plan

Goal: satu aliran dokumen rintis lengkap dalam Laragon.
Architecture: Laravel monolith dalam application/, Blade, policies, workflow service dan transaksi audit.
Spec: docs/phase-1-design.md. Execution: inline dalam sesi yang pengguna minta diteruskan.

## Tasks

- [x] Scaffold Laravel 13 dan Vite; kunci dependency, konfigurasi MySQL development/testing berasingan.
- [x] Tulis feature tests untuk identity, scope, upload, transition, immutable versions, stale locks dan audit sebelum implementation; lihat RED.
- [x] Migrations departments/users/documents/document_versions/audit_events; model relationships dan query visibleTo(User).
- [x] Login/logout throttled dan active-user middleware; admin account/department management.
- [x] DocumentPolicy dan DocumentWorkflow service: update/submit/review/return/approve, transaksi row lock dan conflict 409.
- [x] DocumentController/Form Requests: private PDF/DOCX max 10 MB, lists/details/download/version/new draft.
- [x] Blade shell dan landing menggunakan identiti formal sedia ada; validation, empty states, pagination, accessible form labels dan mobile.
- [x] Local-only seeder dengan password disuntik; migrate development, seed; suite SQLite dan MySQL test DB, build dan browser E2E.
- [x] Dokumentasi operasi/demo/andaian/pemulihan; semak diff, simpan commit selepas semakan.

Review focus: stale page vs duplicate submit; previous approved file vs replacement; inactive session; cross-department direct URLs; fail audit insert rolls back transition. Semua menjadi feature tests.

Completion ledger: SQLite22/MySQL22 tests, 98 assertions each; Vite build; 11 browser checks. Review findings and regression fixes in docs/review/laravel/verification.md. MySQL dedicated local accounts and separate databases configured. Ruling: local pilot defaults only; official workflow and owner transfer remain pending. No production deployment.
