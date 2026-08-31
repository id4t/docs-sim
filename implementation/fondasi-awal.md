# Backlog Implementasi Fondasi Awal

Dokumen ini memecah Gelombang 0 pada [`../RENCANA-PENGEMBANGAN.md`](../RENCANA-PENGEMBANGAN.md). Status implementasi harus dibuktikan kembali terhadap baseline branch senior; pekerjaan dari eksperimen multi-faskes tidak otomatis dianggap selesai.

## 1. Instalasi dan organisasi

- [ ] Terapkan satu profil Faskes dan satu PPK internal.
- [ ] Gunakan satu koneksi database MariaDB untuk seluruh modul instalasi.
- [ ] Hapus kebutuhan facility route context, runtime database switching, dan provisioning multi-faskes dari jalur aktif.
- [ ] Bentuk hierarki Ruangan dan Tempat Tidur sebagai child terpisah.

## 2. Identitas dan authorization

- [ ] Terapkan Pengguna, Role, Profesi, permission aksi, dan Akses Ruangan.
- [ ] Tambahkan policy untuk admit, record, verify, finalize, amend, bill, code, submit, dan administrasi.
- [ ] Sediakan MFA untuk superadmin/developer dan break-glass beralasan.
- [ ] Tambahkan matrix test role × Ruangan × Profesi × aksi.

## 3. Lifecycle dan transaction safety

- [ ] Buat command eksplisit untuk final, batal, reopen, amendment, dan reversal.
- [ ] Lindungi generator nomor dengan sequence/unique constraint concurrency-safe.
- [ ] Gunakan transaction dan locking untuk Tagihan, Pembayaran, stok, serta finalisasi.
- [ ] Wajibkan idempotency key pada command yang dapat diulang.
- [ ] Uji konflik paralel pada MariaDB, bukan hanya SQLite.

## 4. Integrasi dan audit

- [ ] Buat transactional outbox dan Submission lifecycle.
- [ ] Terapkan worker lease, batch, backoff, max attempts, dead letter, dan rekonsiliasi.
- [ ] Simpan attempt history dengan redaction dan correlation ID.
- [ ] Simpan credential terenkripsi di database dengan master key dari environment.
- [ ] Terapkan audit append-only pada klinis, finansial, akses, konfigurasi, dan integrasi.

## 5. Operasional

- [ ] Sediakan health check, metric, log terstruktur, queue monitor, dan slow-query monitor.
- [ ] Siapkan migration rehearsal, backup/restore, rollback, dan smoke test.
- [ ] Buat test harness journey Rawat Jalan BPJS.
- [ ] Penuhi gate pada [`../KESIAPAN-PRODUCTION.md`](../KESIAPAN-PRODUCTION.md).

## Exit criteria

- satu deployment hanya membaca dan menulis database instalasinya;
- policy backend menolak aksi di luar Role/Ruangan/Profesi;
- state final tidak dapat dimutasi melalui endpoint generik;
- command uang/stok aman terhadap retry dan concurrency;
- submission pulih dari timeout, duplikasi, dan worker crash;
- error operasional tersedia sebagai worklist yang dapat ditindak.
