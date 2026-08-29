# Provisioning Faskes

**Status:** kontrak diterima dan schema gate hijau; command belum diimplementasikan.

Prasyarat implementasi command adalah `php artisan facility:schema-plan` berakhir sukses. Prasyarat tersebut terpenuhi pada 29 Agustus 2026 setelah dependency foreign key lintas boundary dilepas; lihat [`multi-schema-facility.md`](./multi-schema-facility.md#hasil-audit-migrasi-29-agustus-2026).

Dokumen ini menetapkan jalur penambahan Faskes yang jarang dilakukan: Admin Grup menyiapkan draft melalui UI, lalu operator menjalankan provisioning database melalui CLI. Jalur ini sengaja tidak memberi runtime web privilege untuk membuat database.

## Aktor dan batas tanggung jawab

| Aktor | Tanggung jawab |
|---|---|
| Admin Grup | Membuat draft Faskes, memilih PPK, mengisi konfigurasi, Membership awal, dan mengaktifkan Faskes setelah siap. |
| Operator deployment | Menjalankan command provisioning/retry dan menangani kegagalan infrastruktur. |
| Provisioner CLI | Memvalidasi draft, membuat database, menjalankan migrasi/seed/health check, dan mencatat checkpoint. |
| Runtime web/worker | Memakai database Faskes yang sudah siap; tidak mempunyai privilege `CREATE DATABASE`, `DROP DATABASE`, atau pemberian grant. |

## Prasyarat draft

Sebelum command dapat dijalankan, draft wajib mempunyai:

- Facility code unik dan sesuai format URL;
- satu `ppk_id` internal yang belum dipakai Faskes lain;
- zona waktu;
- status `draft` atau `provisioning_failed`;
- database name yang dibentuk sistem dari identifier immutable, bukan input SQL bebas;
- audit event pembuat draft.

Nama/alamat/kode eksternal tetap dibaca dari PPK. Detail kontak, branding, credential integrasi, dan Membership tambahan boleh dilengkapi setelah database berhasil dibuat.

## Alur operator

Contoh interface yang dituju:

```bash
php artisan facility:provision RS-A
php artisan facility:provision RS-A --retry
```

Nama binary PHP mengikuti environment deployment; dokumentasi tidak mengunci `php84` atau path mesin tertentu.

Alur:

```text
Admin membuat draft
  → operator menjalankan command
  → provisioner mengunci record Faskes
  → menjalankan checkpoint idempotent
  → status provisioned
  → Admin melengkapi Membership/integrasi
  → health check akhir
  → Admin mengaktifkan Faskes
```

## Checkpoint provisioning

| Urutan | Checkpoint | Hasil yang disimpan |
|---:|---|---|
| 1 | `validate` | Draft, PPK, code, database name, dan status valid. |
| 2 | `reserve` | Record terkunci dan status menjadi `provisioning`. |
| 3 | `create_database` | Database ada dengan charset/collation baku. |
| 4 | `grant_runtime` | User runtime hanya mendapat privilege minimum pada database Faskes. |
| 5 | `migrate` | Semua migrasi operasional berhasil; versi dicatat. |
| 6 | `seed` | Referensi/config minimum tersedia tanpa data demo. |
| 7 | `health_check` | Koneksi, versi schema, read/write minimum, dan cleanup berhasil. |
| 8 | `complete` | Status menjadi `provisioned` dan audit event tercatat. |

Setiap checkpoint aman dijalankan ulang. `--retry` melanjutkan keadaan yang sudah ada, bukan membuat database baru atau mengulang seed non-idempotent.

## Status

| Status | Arti | Traffic operasional |
|---|---|---|
| `draft` | Metadata disiapkan, provisioning belum dimulai. | Ditolak |
| `provisioning` | Command sedang berjalan. | Ditolak |
| `provisioning_failed` | Satu checkpoint gagal dan dapat di-retry. | Ditolak |
| `provisioned` | Database siap, konfigurasi operasional belum diaktifkan. | Ditolak |
| `active` | Siap digunakan. | Diizinkan sesuai Membership |
| `suspended` | Dinonaktifkan tanpa menghapus data. | Ditolak |

Hanya satu eksekusi provisioning boleh memegang lock untuk satu Faskes. Eksekusi kedua berhenti dengan pesan yang jelas.

## Kegagalan dan retry

Record kegagalan minimum menyimpan:

- `facility_id`;
- checkpoint terakhir dan versi migrasi;
- kode error stabil;
- pesan aman untuk operator;
- correlation/command ID;
- waktu mulai, gagal, dan aktor operator;
- detail teknis ter-redact pada application log.

Kode error awal:

| Kode | Makna |
|---|---|
| `FACILITY_DRAFT_INVALID` | Draft/PPK/code tidak valid. |
| `FACILITY_PROVISIONING_LOCKED` | Provisioning lain sedang berjalan. |
| `FACILITY_DATABASE_CREATE_FAILED` | Pembuatan database gagal. |
| `FACILITY_DATABASE_GRANT_FAILED` | Pemberian privilege runtime gagal. |
| `FACILITY_MIGRATION_FAILED` | Migrasi operasional gagal. |
| `FACILITY_SEED_FAILED` | Seed minimum gagal. |
| `FACILITY_HEALTH_CHECK_FAILED` | Database belum aman diaktifkan. |

Provisioner tidak menjalankan `DROP DATABASE` otomatis saat gagal. Operator memperbaiki penyebab lalu menjalankan `--retry`. Penghapusan draft/database adalah operasi terpisah yang memerlukan verifikasi target, backup bila berisi data, dan konfirmasi eksplisit.

## Batas keamanan

- Credential privileged hanya tersedia pada CLI/CI operator, tidak pada web/queue runtime.
- Identifier database hanya berasal dari builder internal dan divalidasi allowlist.
- Command menolak Facility code ambigu, PPK terpakai, atau database yang dimiliki Faskes lain.
- Secret tidak dicetak ke terminal atau log.
- Runtime user hanya memiliki privilege data/migrasi yang memang diperlukan; akun provisioning dipisahkan.
- Aktivasi tidak otomatis setelah provisioning agar konfigurasi dan otorisasi dapat diperiksa.
- Semua perubahan status dan percobaan dicatat sebagai audit event.

## Migrasi dan seed

- Migrasi control-plane tetap dijalankan melalui deployment biasa.
- Provisioner hanya menjalankan migrasi operasional pada database target.
- Migrasi harus kompatibel untuk dijalankan pada satu Faskes tanpa mengubah Faskes lain.
- Seed hanya berisi referensi/config minimum; data pasien, transaksi, credential, dan data demo dilarang.
- Versi schema target harus sama dengan versi aplikasi sebelum status `provisioned` diberikan.

## Health check dan aktivasi

Health check provisioning minimum membuktikan:

1. koneksi memakai database yang benar;
2. versi migrasi lengkap;
3. query read/write sementara berhasil dan dibersihkan;
4. resolver tidak fallback ke control DB atau Faskes lain;
5. runtime credential tidak mempunyai `CREATE DATABASE` atau `DROP DATABASE`.

Sebelum aktivasi, Admin Grup memastikan:

- PPK dan identitas Faskes benar;
- Admin Faskes mempunyai Membership aktif;
- Ruangan/layanan minimum tersedia sesuai capability;
- credential integrasi wajib telah diisi dan lolos connection test bila capability diaktifkan;
- backup memasukkan database baru;
- monitoring mengenali `facility_id` baru.

## Tampilan UI minimum

Halaman detail Faskes cukup menampilkan:

- status dan checkpoint terakhir;
- waktu/aktor percobaan terakhir;
- kode error dan arahan aman bagi operator;
- versi schema;
- checklist kesiapan aktivasi;
- tombol aktivasi/suspend sesuai permission.

UI tidak memuat tombol “buat database”, editor SQL, credential privileged, atau output stack trace. Self-service provisioning penuh ditunda karena penambahan Faskes jarang dan risikonya tidak sebanding.

## Verifikasi implementasi

- Provisioning draft valid menghasilkan tepat satu database.
- Retry setelah gagal migrasi melanjutkan database yang sama.
- Dua command bersamaan hanya mengizinkan satu pemegang lock.
- PPK yang sudah dipakai dan database milik Faskes lain ditolak.
- Gagal pada tiap checkpoint tidak mengaktifkan Faskes dan tidak menghapus data.
- Runtime credential terbukti tidak dapat membuat/menghapus database.
- Aktivasi ditolak sebelum checklist minimum terpenuhi.
- Audit event dan log terstruktur dapat ditelusuri memakai command/correlation ID.

## Rollout awal

Provision dua Faskes canary. Setelah keduanya lulus isolasi, retry, restore, dan target performa dalam [`multi-schema-facility.md`](./multi-schema-facility.md), barulah pola ini dipakai untuk Faskes berikutnya. Jangan membangun orchestrator provisioning web atau sistem plugin provisioning sebelum jalur CLI nyata menjadi hambatan operasional.
