# Fasa pembangunan e-ISMS SUK Pahang

Tarikh kemas kini: 10 Oktober 2026
Status: cadangan susunan pembangunan selepas review mockup; bukan komitmen jadual atau skop yang telah disahkan organisasi.

## Kedudukan semasa
Mockup asal dikekalkan. `application/` menyediakan Laravel, login/peranan/bahagian, database, fail dokumen private, versi, semakan/kelulusan cadangan dan audit. Pada 9 Oktober 2026, daftar risiko, penilaian contoh 5×5, tindakan rawatan, bukti PDF private, pengesahan bebas dan penilaian risiko baki turut tersedia. Rintis menggunakan data sintetik; kaedah dan kuasa rasmi organisasi belum disahkan. SoA dan keputusan penerimaan risiko masih belum dilaksanakan.

Keutamaan seterusnya ialah menjadikan proses teras berfungsi dan boleh dipercayai dalam Laravel di Laragon. Penambahan modul baharu datang selepas asas ini.

## Fasa 1 — Asas Laravel dan akses
**Hasil:** pengguna dalaman boleh masuk dan melihat rekod dalam skop yang dibenarkan.

- Setup Laravel stabil terkini yang serasi, Blade/Vite dan MySQL tempatan.
- Akaun dalaman, bahagian, peranan dan skop ISMS. Pendaftaran kendiri pegawai tersedia; akses hanya selepas semakan dan pengaktifan pentadbir.
- Matriks akses yang disahkan pemilik proses; pentadbir teknikal tidak menerima kuasa kelulusan secara automatik.
- Polisi akses bagi rekod, butiran, fail dan eksport; transaksi dan jejak audit.
- Data contoh seeder untuk pembangunan, diasingkan daripada data rintis sebenar.

**Syarat mula:** tentukan skop rintis, pemilik projek dan siapa boleh menyediakan, menyemak serta meluluskan rekod.
**Penerimaan:** ujian akses silang bahagian dan rekod serta percubaan akses melalui pautan terus lulus.

## Fasa 2 — Dokumen dan kelulusan sebenar
**Hasil:** satu proses dokumen lengkap daripada draf kepada versi berkuat kuasa.

- Daftar dokumen, versi, pemilik, tarikh semakan dan lampiran private.
- Semakan, ulasan, pemulangan untuk pembetulan dan kelulusan mengikut kuasa sebenar.
- Versi berkuat kuasa tidak ditimpa; perubahan menghasilkan versi baharu.
- Bukti dan rekod sejarah migrasi ditandakan dengan sumbernya.

**Syarat mula:** borang contoh sebenar, klasifikasi maklumat, aturan kelulusan dan polisi penyimpanan.
**Penerimaan:** penyedia tidak dapat melangkaui semakan; fail tidak dapat dimuat turun tanpa akses; sejarah versi kekal lengkap.

## Fasa 3 — Risiko, rawatan dan bukti tindakan
**Hasil:** satu risiko boleh dinilai, diberi rawatan, disusuli dan diputuskan oleh pihak berkuasa.

- Daftar aset/proses minimum dan pemilik risiko.
- Kaedah risiko berversi berdasarkan matriks yang diluluskan organisasi.
- Penilaian awal, kawalan sedia ada, pelan rawatan dan penilaian risiko baki.
- Tindakan, pelaksana, sasaran, bukti, semakan dan pembukaan semula apabila tidak memadai.
- Keputusan penerimaan risiko dan sejarah alasan; tindakan siap tidak menerima risiko secara automatik.
- Kawalan/SoA dengan justifikasi kebolehgunaan dan pautan bukti.

**Penerimaan:** transisi tidak sah ditolak; dua pegawai tidak menimpa perubahan tanpa amaran; semua keputusan boleh dijejaki.

## Fasa 4 — Rintis, laporan dan peringatan
**Hasil:** satu bahagian dapat menggunakan sistem untuk kerja harian.

- Import rekod manual dengan semakan pendua dan pengesahan pemilik data.
- Papan pemuka berdasarkan rekod sebenar; laporan dan eksport mengikut akses.
- Peringatan tarikh sasaran serta eskalasi kepada pemilik yang dipersetujui.
- Notifikasi dalam aplikasi dahulu; e-mel ditambah apabila kemudahan dan penerima disahkan.
- Latihan, UAT, sandaran, ujian pemulihan dan pelan peralihan operasi.

**Penerimaan:** jumlah rekod import sepadan, laporan tepat dan pengguna rintis menyelesaikan aliran utama tanpa bantuan pembangun.

## Tambahan selepas proses teras stabil

| Keutamaan | Modul | Faedah dan prasyarat |
| --- | --- | --- |
| 1 | Audit dalaman & tindakan pembetulan | Jadual audit, dapatan, ketidakakuran, punca, pembetulan dan semakan keberkesanan; menggunakan bukti serta tindakan sedia ada |
| 2 | Kajian semula pengurusan | Agenda, bahan mesyuarat, keputusan dan tindakan; memerlukan laporan yang dipercayai |
| 3 | Insiden keselamatan maklumat | Pelaporan, triage, eskalasi, pemulihan dan pengajaran; aturan pelaporan serta akses kepada insiden mesti disahkan dahulu |
| 4 | Latihan & kesedaran | Jadual, kehadiran, pengakuan polisi dan bukti kompetensi; elakkan menyalin sistem latihan sedia ada |
| 5 | Integrasi organisasi | SSO/direktori pengguna, aset dan e-mel; selepas sistem sumber serta pemilik integrasi dikenal pasti |

## Cadangan pilihan pertama
Asas akses, aliran dokumen, risiko/rawatan, halaman log masuk korporat dan pendaftaran pegawai telah tersedia. Papan pemuka `/dashboard` kini menyenaraikan tugasan dokumen/risiko mengikut peranan, tindakan semasa yang lewat atau hampir tarikh sasaran serta permohonan akaun untuk pentadbir. Kiraan menggunakan rekod dalam skop akses dan tarikh Malaysia; tiada penghantaran peringatan automatik.

Notifikasi dalam aplikasi kini tersedia bagi pendaftaran/pengaktifan akaun, dokumen, risiko dan rawatan. Menu notifikasi menyokong penapis belum dibaca, penandaan dibaca dan pembukaan rekod dengan semakan akses semasa. Notifikasi bermula dengan perubahan urusan baharu; e-mel dan peringatan tarikh berjadual belum diaktifkan.

Baki seterusnya: peringatan tarikh dan e-mel selepas tetapan disahkan; keputusan penerimaan risiko dan SoA selepas aturan organisasi dipersetujui; laporan/eksport, import rekod manual, UAT, sandaran serta ujian pemulihan. Untuk modul baharu selepas MVP, utamakan **audit dalaman dan tindakan pembetulan** kerana ia menggunakan semula dokumen, bukti, pemilik dan tindakan yang sudah dibina.

Elakkan membina semua modul serentak. Bukti yang tersusun, kuasa kelulusan yang jelas dan laporan tepat memberi nilai lebih awal daripada menambah bilangan menu.

## Keputusan yang masih diperlukan untuk real-case
- Skop rintis, pengguna dan pemilik data.
- Contoh borang serta laluan kelulusan sebenar.
- Matriks risiko dan ambang penerimaan yang diluluskan.
- Akses, klasifikasi, penyimpanan serta pelupusan maklumat.
- Sasaran kapasiti, masa pemulihan, sokongan operasi dan tarikh peralihan.

Rujukan teknikal: `docs/laravel-spec.md`. Rujukan urusan: `.briefing`. Roadmap ini tidak mengubah status pensijilan atau menggantikan polisi organisasi.
