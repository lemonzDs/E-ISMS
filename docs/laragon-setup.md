# Menjalankan rintis Laravel di Laragon

## Keadaan komputer ini

Laravel `application/`: framework 13.34.0, PHP Laragon 8.3.30, MySQL 8.4.3, Vite 8.3.2. Dependency dikunci. Database pembangunan `e_isms_dev` dan ujian `e_isms_test` menggunakan akaun database berasingan, masing-masing terhad kepada databasenya. MySQL sedia ada dimulakan dengan bind 127.0.0.1; tiada database lama dipadam.

Mulakan MySQL melalui Laragon selepas komputer dihidupkan semula. Dari folder ISMS:

```powershell
./scripts/Start-ISMS.ps1
```

Buka http://127.0.0.1:8099/. Ctrl+C menghentikan pelayan. Skrip menggunakan PHP Laragon dengan had muat naik 10 MB dan post 12 MB; tiada perubahan kepada php.ini global. MySQL perlu berjalan sebelum log masuk.

Untuk Apache/vhost Laragon, tetapkan **document root `C:/laragon/www/ISMS/application/public`** dan `APP_URL` kepada URL vhost, kemudian `php artisan config:clear`. Root projek `.htaccess` menolak akses; folder public memberi akses secara eksplisit. Jangan buang perlindungan root untuk menyelesaikan 403. Konfigurasi vhost belum diubah atau diuji dalam fasa ini.

## Akaun demo tempatan

Kata laluan rawak tempatan ada dalam `docs/local-demo-access.md`, yang tidak dimuat naik ke Git. Akaun: `admin@example.test`, `penyelaras@example.test`, `pegawai@example.test`, `pelulus@example.test`, `pegawai.kewangan@example.test`. Semua identiti, bahagian dan dokumen ialah rekaan. Seeder menambah jika belum wujud; ia tidak menetapkan semula kata laluan akaun sedia ada.

## Pemasangan selepas clone

1. Gunakan PHP >=8.3 dan Composer 2; aktifkan pdo_mysql, mbstring, fileinfo, openssl, curl, dom, xml dan zip. Node mesti memenuhi keperluan Vite 8 (`^20.19.0 || >=22.12.0`).
2. Dalam `application/`, jalankan `composer install` dan `npm ci`, kemudian `npm run build`.
3. Salin `.env.example` kepada `.env`; cipta database MySQL `e_isms_dev` dan akaun khusus. Masukkan credential sendiri dalam `.env`. Jalankan `php artisan key:generate` dan `php artisan migrate`.
4. Untuk data contoh sahaja, tetapkan `APP_ENV=local` dan `ISMS_DEMO_PASSWORD` baharu (minimum 12 aksara), kemudian `php artisan db:seed --class=DemoSeeder`. Seeder menolak production. Fail `docs/local-demo-access.md` tidak tersedia selepas clone; gunakan password yang anda tetapkan.
5. Jalankan skrip pelayan atau Apache dengan document root public. Jangan jalankan `migrate:fresh` pada database pembangunan yang mempunyai rekod diperlukan.

## Ujian

```powershell
# Dari application/, dengan PHP dalam PATH:
php artisan test
php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml
php vendor/bin/pint --test
npm run build
```

Suite pertama menggunakan SQLite in-memory. Suite MySQL hanya dibenarkan menggunakan `e_isms_test`; **RefreshDatabase membina semula jadual database ujian tersebut**. Sediakan database berasingan dan akaun `e_isms_test` dengan hak hanya kepada database itu. Salin `.env` ke `.env.testing`, ubah APP_ENV=testing dan semua credential kepada database ujian. Jangan guna credential pembangunan.

Browser: `node scripts/check-laravel.cjs` dari root. Playwright perlu tersedia; tetapkan `ISMS_PLAYWRIGHT_PATH` kepada modulnya dan `ISMS_CHROME` jika Chrome berada di lokasi lain. Skrip menggunakan password daripada `.env`, tidak mencetaknya, dan meninggalkan rekod `E2E-*` sintetik dalam database pembangunan. Screenshot/bukti di `docs/review/laravel/`.

## Batasan sebelum real-case

Perlu sahkan kuasa pelulus, klasifikasi/retensi, borang sebenar, pemindahan pemilik dan konfigurasi operasi. Pemilik dengan dokumen pending tidak boleh ditukar bahagian atau kepada peranan yang menghalang penyediaan; deactivation dibenarkan, kerja menunggu reactivation. Tiada pemindahan pemilik, pendaftaran awam, self-service reset password, e-mel keluar, pemeriksaan malware, import rekod atau modul risiko sebenar dalam fasa ini. Pentadbir boleh menetapkan semula kata laluan melalui pengurusan akaun.

Versi diluluskan tidak boleh diedit melalui aplikasi. Ini bukan storan WORM atau perlindungan terhadap pentadbir database. Audit transaksi tidak bermaksud pensijilan ISO. Sandaran/pemulihan dan UAT operasi ialah fasa berikutnya.
