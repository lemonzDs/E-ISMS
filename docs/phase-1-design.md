# Laravel dan kawalan dokumen — rintis tempatan

Skop diluluskan pengguna pada 3 Oktober 2026: asas Laravel, akaun dalaman, dokumen, semakan, kelulusan dan audit. Proses di bawah ialah andaian rintis, bukan kuasa kelulusan rasmi SUK Pahang.

Laravel berada dalam `application/`; mockup asal dikekalkan untuk rujukan. Document root mesti `application/public`. PHP >=8.3, Laravel ^13, MySQL 8.4, Blade/Vite. Dependency dikunci. Tiada pendaftaran awam atau penghantaran e-mel sebenar.

Peranan: admin mengurus akaun dan bahagian; coordinator melihat semua bahagian dan menyemak; officer menyediakan dokumen bahagian sendiri; approver meluluskan dokumen yang telah disemak. Admin hanya boleh mengurus identiti, bukan membaca fail atau meluluskan dokumen. Semua akaun memerlukan bahagian. Akaun tidak aktif ditolak termasuk sesi sedia ada.

Dokumen mempunyai kod unik, tajuk, bahagian dan pemilik. Versi mempunyai nombor, fail private, status dan lock_version. Satu versi boleh diproses pada satu masa. Versi diluluskan kekal immutable; versi baharu tidak mengubah fail sejarah.

Transisi: draft -> submitted oleh pemilik; submitted -> reviewed atau returned oleh coordinator; reviewed -> approved atau returned oleh approver. Penyemak bukan pemilik; pelulus bukan pemilik atau penyemak. returned boleh diedit dan dihantar semula oleh pemilik. Ulasan diperlukan apabila dikembalikan. Transisi, semakan concurrency dan audit dibuat dalam transaksi dengan row lock. Input lock_version lapuk mendapat 409. Fail diperiksa MIME dan dihadkan PDF/DOCX, 10 MB; pemeriksaan malware dan polisi retensi perlu ditambah sebelum data sebenar.

Senarai, butiran dan muat turun semuanya mengikut policy dan bahagian. Senarai mempunyai carian/status/pagination. Fail disimpan di disk local private dan hanya melalui controller; tiada storage symlink. Tiada pemadaman rekod pada rintis.

Demo seeder hanya local/testing dan memerlukan password melalui persekitaran; tiada kata laluan default atau sebenar dalam Git. MySQL pembangunan `e_isms_dev`; ujian menggunakan database berasingan. Ujian SQLite pantas diikuti suite MySQL. Audit mencatat actor, action, document/version, before/after dan masa UTC; UI memaparkan Asia/Kuala_Lumpur.

Ujian penerimaan: akses silang bahagian/fail ditolak, tiada self approval, transisi tidak sah dan konflik ditolak, sejarah versi dan audit konsisten, login throttling/logout/deactivation, validasi file, borang mobile dan aliran end-to-end. Tiada pensijilan atau readiness real-case didakwa.

Review ruling: new-version memerlukan akses view semasa selain pemilikan. Pertukaran bahagian/peranan yang menghalang pemilik menyelesaikan versi pending ditolak; deactivation tetap dibenarkan dan pentadbir menerima count/recovery guidance. Owner transfer ditangguhkan. Repeated submit dari halaman lapuk mendapat 409 selepas semakan visibility dan sebelum transition policy, supaya akses luar tetap 403.
