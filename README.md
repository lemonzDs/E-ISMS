# e-ISMS SUK Pahang — Laravel dan mockup

## Fasa semasa: rintis Laravel

`application/` kini mengandungi Laravel 13.34.0, PHP >=8.3, MySQL 8.4, Blade/Vite, kawalan dokumen serta daftar risiko dan rawatan. Login dalaman, akaun/bahagian, fail private, versi, semakan, kelulusan dokumen dan audit tersedia untuk data contoh. Aturan ini belum diluluskan sebagai proses operasi SUK Pahang.

Modul risiko (`/risks`) menyediakan penilaian contoh 5×5, carian/penapis, semakan penyelaras dan sejarah penilaian. Dari butiran risiko, buka **Pelan rawatan & risiko baki** untuk menetapkan pelaksana/tarikh sasaran, menghantar bukti PDF private (maksimum 10 MB), mengesahkan atau mengembalikan tindakan dan menilai risiko baki selepas semua tindakan disahkan. Membuka semula atau mengubah pelan membatalkan status terkini penilaian baki terdahulu; sejarah dikekalkan. Semakan penilaian dan pengesahan tindakan tidak bermaksud penerimaan risiko. Kaedah rasmi dan kuasa penerimaan SUK masih perlu disahkan.

Preview tempatan: `http://127.0.0.1:8099/`. Jalankan `./scripts/Start-ISMS.ps1` dari PowerShell selepas MySQL Laragon dimulakan. Akaun demo dan kata laluan tempatan berada dalam `docs/local-demo-access.md` yang diabaikan Git. Jangan gunakan akaun demo untuk data sebenar.

Panduan pemasangan: [docs/laragon-setup.md](docs/laragon-setup.md). Spesifikasi rintis: [docs/phase-1-design.md](docs/phase-1-design.md). Laravel document root mesti `application/public`; root projek dilindungi `.htaccess` apabila Apache digunakan. `.env`, fail dokumen dan pangkalan data tempatan tidak dimasukkan dalam Git.

## Mockup asal untuk rujukan

Landing page Laravel kini menggunakan reka bentuk korporat dengan jata Pahang, latar seni bina PPSAS, warna biru gelap dan emas serta paparan responsif. Pautan log masuk membawa pengguna ke ruang kerja mengikut peranan. Rekod sumber visual tersedia dalam [docs/landing-direction.md](docs/landing-direction.md).

Fasa semasa: mockup v0.2 untuk Penyelaras ISMS, dengan arah formal, ringkas dan berorientasikan kerja. Portal memaparkan daftar perhatian dan direktori modul; ruang kerja menggunakan navigasi cerah dan ringkasan berformat daftar. Semua data adalah rekaan. Tiada login, database, upload atau kelulusan sebenar; perubahan hanya kekal sehingga halaman dimuat semula.

## Buka mockup

Buka `mockup/landing.html` untuk landing page dan `mockup/index.html` untuk ruang kerja. Kedua-duanya boleh dibuka terus dalam browser tanpa pemasangan dependency atau sambungan Internet.

Mockup asal masih boleh dibuka terus atau dilayan pada port 8098. Root Apache projek kini dilindungi untuk mengelakkan fail aplikasi terdedah. Gunakan document root `application/public` untuk Laravel; virtual host Laragon belum diubah secara automatik.

Pilihan pelayan tempatan dari PowerShell di folder projek:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -S 127.0.0.1:8098 -t mockup
```

Buka `http://127.0.0.1:8098/landing.html`. Pelayan ini untuk preview tempatan sahaja. Hentikan dengan Ctrl+C jika dijalankan dalam terminal sendiri.

## Cuba untuk review

1. Papan pemuka: klik kategori tindakan dan petak peta risiko.
2. Daftar risiko: cari, tapis, tukar halaman, buka butiran dan tambah risiko rekaan.
3. Dokumen: buka status, versi dan kandungan contoh.
4. Tindakan: simulasi hantar untuk pengesahan; lihat perubahan pada papan pemuka dan jejak audit.
5. Kawalan & SoA: lihat justifikasi dan rujukan bukti contoh.
6. Laporan: muat turun CSV data contoh.

## Arah Laravel

Laravel 13.34.0 + PHP >=8.3 + MySQL 8.4 + Blade/Vite dipasang dalam `application/`; dependency dikunci. Risiko dan tindakan kini berfungsi dalam rintis Laravel; SoA dan keputusan penerimaan risiko belum dilaksanakan. Selepas kemas kini kod, jalankan `php artisan migrate` dan `npm run build` dari `application/`. Data contoh risiko boleh ditambah dengan `php artisan db:seed --class=RiskDemoSeeder` selepas `DemoSeeder`.

`.briefing` kekal sebagai cadangan urusan. `PRODUCT.md`, `DESIGN.md` dan `UX-CONTRACT.md` merekodkan keputusan mockup serta perkara belum disahkan.

Lihat `docs/roadmap.md` untuk cadangan fasa seterusnya dan `docs/review/verification.md` untuk bukti 18 semakan browser yang lulus serta batasan audit statik.
