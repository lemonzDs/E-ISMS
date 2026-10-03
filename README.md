# e-ISMS SUK Pahang — mockup review

Fasa semasa: mockup v0.2 untuk Penyelaras ISMS, dengan arah formal, ringkas dan berorientasikan kerja. Portal memaparkan daftar perhatian dan direktori modul; ruang kerja menggunakan navigasi cerah dan ringkasan berformat daftar. Semua data adalah rekaan. Tiada login, database, upload atau kelulusan sebenar; perubahan hanya kekal sehingga halaman dimuat semula.

## Buka mockup

Buka `mockup/landing.html` untuk landing page dan `mockup/index.html` untuk ruang kerja. Kedua-duanya boleh dibuka terus dalam browser tanpa pemasangan dependency atau sambungan Internet.

Jika Apache Laragon sedang berjalan dengan document root lalai, gunakan `http://localhost/ISMS/`; halaman masuk akan membawa anda ke landing page. Jika virtual host `isms.test` sudah didaftarkan kepada folder projek ini, gunakan `http://isms.test/`.

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

Laravel 13.x + PHP >=8.3 + MySQL 8.4 + Blade/Vite, dicadangkan untuk implementasi selepas review mockup. Lihat `docs/laravel-spec.md` untuk seni bina, fasa, kawalan akses dan pengesahan. Kod mockup ialah bahan review, bukan sistem Laravel siap.

`.briefing` kekal sebagai cadangan urusan. `PRODUCT.md`, `DESIGN.md` dan `UX-CONTRACT.md` merekodkan keputusan mockup serta perkara belum disahkan.

Lihat `docs/roadmap.md` untuk cadangan fasa seterusnya dan `docs/review/verification.md` untuk bukti 18 semakan browser yang lulus serta batasan audit statik.
