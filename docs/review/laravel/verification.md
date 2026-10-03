# Pengesahan fasa Laravel — 3 Oktober 2026

Persekitaran: Laravel 13.34.0, PHP Laragon 8.3.30, MySQL 8.4.3, Node 24.21.0, Vite 8.3.2. Preview PHP di `127.0.0.1:8099`, document root `application/public`. Apache/vhost belum diuji.

- `php artisan test`: **22 lulus, 98 assertions**, SQLite in-memory.
- `php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml`: **22 lulus, 98 assertions**, MySQL `e_isms_test`, credential terasing daripada pembangunan.
- Vite production build: lulus, stylesheet dan JavaScript tempatan, tiada font luar.
- `node scripts/check-laravel.cjs`: **11 semakan lulus**, tiada JavaScript errors. Bukti terperinci: `checks.json` dan screenshots dalam folder ini.

Browser meliputi landing desktop/mobile, CSRF dan laluan private, senarai/carian mengikut bahagian, upload PDF sebenar, hantar/kembali/betulkan/hantar semula, semakan dan kelulusan oleh akaun berasingan, audit/download, denial silang bahagian, versi baharu dengan versi diluluskan dikekalkan, paparan mobile, tambah bahagian/akaun dan inactive login. Rekod E2E, bahagian dan akaun sintetik kekal di database pembangunan; akaun E2E dinyahaktifkan.

Regression RED→GREEN direkodkan semasa kerja: guest documents404->loginredirect; relocated owner new-version302->403; pending owner capability changes accepted->validation rejection; unknown role metadata exposed->hidden; approver-owner could submit->403; stale repeated submit403->409; admin form field missing->rendered and account/audit created.

Review bebas menemui dua isu (new-version selepas pertukaran bahagian dan pemilik pending yang kehilangan capability). Kedua-duanya dibetulkan, diuji dan disemak semula tanpa baki finding material dalam pembetulan itu. Semakan browser tambahan membetulkan directive Blade bercantum dalam borang tambah akaun. Laluan storan automatik Laravel dimatikan; download hanya melalui controller berpolicy.

Had pengesahan: browser desktop automatik pada 1440px/390px, bukan telefon fizikal atau screen reader. Tiada concurrency stress test berbilang connection, pemeriksaan malware, backup/restore, import, mail keluar, UAT organisasi atau pensijilan aksesibiliti/pematuhan. Suite menguji lock_version lapuk, row-lock workflow dan rollback audit; ini tidak menggantikan load/concurrency test untuk operasi. Aturan peranan, bahagian, pelulus dan retensi masih cadangan rintis.
