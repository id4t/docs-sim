# Isolasi Database per Faskes

**Status:** keputusan arsitektur diterima; fondasi katalog dan audit migrasi sedang berjalan, isolasi runtime belum aktif.

Dokumen ini adalah kontrak utama untuk Facility context, kepemilikan data, koneksi database, queue, logging, performa, migrasi, dan backup per Faskes. Proses penambahan Faskes dijelaskan lebih rinci di [`facility-provisioning.md`](./facility-provisioning.md).

## Keputusan final

| ID | Keputusan |
|---|---|
| D1 | Faskes dan PPK adalah entitas berbeda. Satu Faskes menunjuk tepat satu PPK internal; PPK lain dapat tetap menjadi referensi eksternal. |
| D2 | Satu deployment Grup memakai satu `simgos_control` dan satu database operasional MariaDB per Faskes pada instance yang sama. |
| D3 | Batas awal adalah 10 Faskes aktif per Grup. Kenaikan batas harus berdasarkan hasil ukur. |
| D4 | Faskes hanya menyimpan identitas teknis, status, zona waktu, kontak, dan branding; identitas layanan bersumber dari PPK. |
| D5 | Satu domain utama memakai prefix `/f/{facility_code}` dan `/api/v1/f/{facility_code}`. Prefix adalah selector, bukan bukti otorisasi. |
| D6 | User berada pada control DB; role, profesi, status, masa aktif, dan akses Ruangan ditentukan oleh Membership per Faskes. |
| D7 | Master nasional/bersama berada pada control DB; data pelayanan dan konfigurasi operasional berada pada database Faskes. |
| D8 | Credential integrasi per Faskes dienkripsi di control DB; outbox, submission, dan riwayat percobaan berada pada database Faskes. |
| D9 | Admin membuat draft lewat UI; operator menjalankan provisioning idempotent lewat CLI. Runtime web tidak memiliki privilege `CREATE DATABASE`. |

## Hubungan dengan SIMPel

SIMPel menggunakan `master.ppk` sebagai direktori fasilitas dan `aplikasi.instansi.PPK` sebagai identitas Faskes instalasi. Mental model tersebut dipertahankan: PPK tetap sumber identitas fasilitas. SIMGOS menambahkan boundary multi-Faskes yang tidak dibuktikan oleh dokumentasi SIMPel:

```text
Grup 1 ── * Faskes 1 ── 1 PPK internal
                         └── 1 database operasional

PPK tanpa Faskes = fasilitas eksternal/rujukan
```

Satu PPK internal tidak boleh dipakai oleh dua Faskes aktif dalam satu Grup.

## Topologi

Pada MariaDB, `SCHEMA` adalah sinonim `DATABASE`. Desain ini disebut database-per-Faskes:

```text
Deployment Grup A
├── aplikasi Laravel yang sama
└── satu instance MariaDB
    ├── simgos_control
    ├── simgos_facility_rs_a
    ├── simgos_facility_klinik_b
    └── simgos_facility_lab_c
```

Grup adalah deployment/server boundary. Faskes bukan server tersendiri. Semua Faskes dalam Grup memakai domain, aplikasi, dan instance MariaDB yang sama, tetapi data operasionalnya terpisah secara database.

## Model Faskes

Field minimum:

| Field | Aturan |
|---|---|
| `id` | Immutable; tidak bermakna bisnis. |
| `code` | Unik dalam Grup, stabil, aman dipakai pada URL. |
| `ppk_id` | Wajib dan unik untuk Faskes internal. |
| `database_name` | Dibuat sistem, allowlist, immutable setelah database terbentuk. |
| `status` | Mengikuti lifecycle provisioning di bawah. |
| `timezone` | Wajib; default deployment dapat `Asia/Jakarta`. |
| `email`, `website` | Opsional. |
| `branding` | Opsional; logo/warna tanpa menduplikasi data PPK. |

Nama, jenis, kelas, kode Kemenkes/BPJS, alamat, dan wilayah tidak diduplikasi ke Faskes; nilai tersebut dibaca dari PPK. Snapshot hanya boleh dibuat bila dokumen transaksi memang membutuhkan bukti historis.

Lifecycle minimum:

```text
draft → provisioning → provisioned → active ↔ suspended
              └──────→ provisioning_failed → provisioning
```

Hanya `active` yang menerima traffic operasional. `suspended` tetap dapat dipelihara operator, tetapi request dan job bisnis ditolak sebelum membuka database operasional.

## Kepemilikan data

### Control DB

- Grup, Faskes, PPK, dan referensi wilayah/nasional;
- User, Membership, role/profesi, akses Ruangan yang direferensikan dengan stable code;
- katalog database, status provisioning, dan versi migrasi;
- konfigurasi serta credential integrasi terenkripsi;
- template konfigurasi Grup;
- audit control-plane;
- metadata failed job yang diperlukan saat database Faskes tidak tersedia.

### Database Faskes

- pasien lokal dan Nomor Rekam Medis;
- pegawai/petugas operasional dan Ruangan;
- layanan, tarif, paket, penjamin/rekening lokal;
- Pendaftaran, antrean, Kunjungan, Encounter, dan seluruh rekam medis;
- farmasi, persediaan, order, hasil, dan dispensing;
- Tagihan, Pembayaran, klaim, dan sequence dokumen;
- mapping identitas eksternal;
- outbox, Submission, attempt history, dan audit operasional.

### Aturan lintas database

- Jangan membuat foreign key SQL lintas database.
- Referensi control-to-Faskes memakai ID atau stable code yang tervalidasi.
- Satu ID lokal hanya unik bersama `facility_id` bila keluar dari database asal.
- Transaksi SQL tidak menyeberang antar-Faskes.
- Laporan Grup memakai proyeksi/agregat, bukan join bebas lintas database pada request pengguna.

## Dampak modul existing

Audit awal menemukan 475 direktori modul, 489 model, dan 538 migrasi. Hampir semua model masih mengandalkan koneksi default, sehingga resolver harus terpusat; menambahkan pemilihan koneksi pada setiap controller akan rawan bocor.

| Kelompok | Jumlah modul awal | Target kepemilikan |
|---|---:|---|
| MedicalRecord | 177 | Faskes |
| General | 108 | Klasifikasi per tabel: referensi nasional ke control, konfigurasi operasional ke Faskes |
| Layanan | 56 | Faskes |
| Pendaftaran | 27 | Faskes |
| Pembayaran | 24 | Faskes |
| Inventory | 23 | Faskes |
| SATUSEHAT | 13 | Konfigurasi/credential di control; outbox/submission di Faskes |
| BerkasKlaim | 12 | Faskes |
| BPJS | 10 | Konfigurasi/credential di control; transaksi/submission di Faskes |
| Pembatalan, Penjualan, lainnya | 25 | Umumnya Faskes; wajib dicatat pada ledger klasifikasi sebelum migrasi |

Jumlah tersebut adalah inventaris awal, bukan status implementasi. Ledger tabel `control | facility | review` harus diselesaikan sebelum tabel dipindahkan.

### Hasil audit migrasi 28 Agustus 2026

Validator `php artisan facility:schema-plan` telah mengklasifikasikan 24 modul control dan 451 modul facility, mencakup 542 migration termasuk migration root. Tidak ada modul existing yang belum mempunyai owner.

Provisioning masih diblokir dengan sengaja karena migration operasional mempunyai foreign key ke tabel control:

| Tabel control | Migration operasional terdampak |
|---|---:|
| `users` | 106 |
| `genders` | 4 |
| `professions`, `religions`, `blood_types` | masing-masing 2 |
| `countries`, `educations`, `ethnicities`, `languages`, `marital_statuses`, `occupations` | masing-masing 1 |

Angka dihitung per file migration dan akan berubah saat dependency dipindahkan. Command wajib tetap gagal sampai seluruh foreign key lintas boundary dihilangkan. Detail implementasi berada di [`../../RME-Backend/docs/architecture/facility-database-migration.md`](../../RME-Backend/docs/architecture/facility-database-migration.md).

Vertical slice `diagnosis_codes` selesai pada 28 Agustus 2026: model dan validasi dapat memakai koneksi control, query penutupan episode tidak lagi melakukan join lintas database, serta lima FK operasional dilepas tanpa mengubah ID diagnosis existing. Mode satu database tetap menjadi default sampai `CONTROL_DB_CONNECTION=control` diaktifkan saat rollout dua database.

## Routing dan Facility context

URL target:

```text
/f/{facility_code}/pendaftaran
/api/v1/f/{facility_code}/registrations
```

Alur request:

```text
route code
  → muat Faskes dari control DB
  → validasi status active
  → validasi Membership user
  → resolve database_name dari record tepercaya
  → aktifkan koneksi operasional
  → pasang log context
  → jalankan use case
  → bersihkan context/koneksi pada finally
```

Aturan wajib:

1. `facility_code` hanya selector. Otorisasi selalu berasal dari Membership.
2. Nama database berasal dari record allowlist, bukan payload atau string route langsung.
3. Request tanpa context valid harus berhenti; dilarang fallback ke database default.
4. Model control-plane selalu memakai koneksi control yang eksplisit.
5. Model operasional memakai resolver koneksi aktif yang sama.
6. Jangan menyimpan satu Faskes aktif global pada session: dua tab dapat membuka Faskes berbeda.
7. Worker berumur panjang membersihkan context pada `finally` untuk mencegah kebocoran ke request/job berikutnya.

## Membership dan otorisasi

Satu User dapat mempunyai Membership berbeda pada beberapa Faskes. Membership minimum menyimpan:

- `user_id` dan `facility_id`;
- role dan profesi pada Faskes tersebut;
- status dan masa aktif;
- daftar/cakupan Ruangan yang diizinkan.

Role global tidak boleh dipakai untuk memberi akses operasional. Admin Grup hanya mengelola control-plane; akses data klinis tetap membutuhkan Membership yang sesuai.

## Queue, scheduler, cache, dan storage

- Setiap job operasional membawa `facility_id` immutable, bukan nama database atau credential.
- Job middleware mengulang resolve, status check, Membership/system authorization, log context, dan cleanup.
- Scheduler membuat job terpisah per Faskes aktif. Satu job tidak berkeliling mengubah koneksi untuk banyak Faskes.
- Cache key, lock, rate-limit key, sequence, dan storage path wajib memuat `facility_id` atau stable facility code.
- Failed job harus tetap terlihat ketika database Faskes mati.
- Retry harus idempotent dan selalu me-resolve context dari awal.

## Credential dan integrasi

- Credential BPJS/SATUSEHAT/E-Klaim disimpan per Faskes di control DB dan dienkripsi.
- Kunci enkripsi berada di luar database.
- Runtime hanya memperoleh credential setelah Facility context dan capability tervalidasi.
- Secret, token, NIK lengkap, payload klinis penuh, dan response mentah tidak masuk log.
- Submission, remote ID, attempt history, retry state, dan reconciliation berada pada database Faskes karena mengikuti transaksi asal.
- Interface pembacaan credential dibuat kecil agar secret manager dapat ditambahkan nanti tanpa mengubah workflow; secret manager belum dibutuhkan pada fase awal.

## Logging dan audit

Gunakan satu aliran log terstruktur yang bisa difilter, bukan satu file log per Faskes. Field minimum:

```json
{
  "request_id": "uuid",
  "facility_id": "uuid",
  "facility_code": "RS-A",
  "user_id": 42,
  "module": "Pendaftaran",
  "operation": "CreateRegistration",
  "error_code": "FACILITY_DATABASE_UNAVAILABLE"
}
```

Pisahkan:

- application log untuk diagnosis teknis;
- audit event untuk aktor, waktu, aksi, serta before/after yang aman;
- operational worklist untuk Submission gagal yang perlu retry atau tindakan petugas.

Error integrasi tidak dianggap selesai hanya karena tertulis di log.

## Kontrak performa awal

Target kapasitas per Faskes adalah 1.000 pasien/hari dengan headroom 3×. Baseline sebelum produksi:

| Metrik | Target |
|---|---:|
| User aktif bersamaan | 100 per Faskes |
| Burst aplikasi | 50 request/detik per deployment |
| Read internal p95 | < 300 ms |
| Write internal p95 | < 500 ms |
| Request internal p99 | < 1 detik |
| Pencarian pasien p95 | < 300 ms |
| Error internal | < 0,5% |
| CPU dan koneksi DB normal | < 70% |

Cara mencapainya sebelum menambah cache/infra:

- index komposit mengikuti filter dan urutan query nyata;
- exact index untuk NIK, Nomor Rekam Medis, nomor peserta, dan nomor dokumen;
- pencarian nama memakai prefix/FULLTEXT yang dibuktikan dengan `EXPLAIN`, bukan `%kata%` tanpa batas;
- eager loading dan projection untuk mencegah N+1 serta kolom berlebih;
- cursor pagination untuk daftar besar;
- integrasi eksternal berjalan async melalui outbox;
- monitor cumulative query time dan slow query per `facility_id`;
- uji beban dilakukan pada dua Faskes canary sebelum batas 10 Faskes dianggap aman.

## Failure mode

| Kondisi | Perilaku target |
|---|---|
| Database Faskes A mati | Request/job A gagal dengan kode stabil; Faskes B tetap berjalan. |
| Facility context hilang | Tolak sebelum query; jangan fallback. |
| User memalsukan code/ID Faskes | Tolak berdasarkan Membership. |
| Job lama berjalan setelah suspend | Berhenti sebelum koneksi operasional dibuka. |
| Migrasi satu Faskes gagal | Tandai Faskes itu gagal; database lain tidak di-rollback. |
| Worker selesai A lalu mengambil B | Bersihkan A dan resolve B dari awal. |
| Integrasi timeout | Simpan attempt yang retryable tanpa menghilangkan transaksi klinis. |
| Provisioning diulang | Lanjutkan checkpoint terakhir secara idempotent; jangan membuat database kedua. |

## Migrasi, backup, dan pemulihan

- Migrasi control dijalankan sekali; migrasi operasional dijalankan per database Faskes.
- Versi migrasi dan kegagalan dicatat per Faskes.
- Perubahan destruktif memerlukan preflight, backup, dan rollback yang dibuktikan.
- Backup control DB dan seluruh database Faskes dicatat sebagai satu recovery set Grup.
- Satu database Faskes harus dapat dipulihkan tanpa menimpa Faskes lain.
- Restore production memeriksa versi schema dan merekonsiliasi outbox/job setelah titik backup.
- Uji restore berkala menjadi acceptance operasional.

## Urutan implementasi

1. Selesaikan ledger kepemilikan tabel dan hilangkan dependency lintas boundary; validator ownership modul sudah tersedia, foreign key lintas database masih menjadi blocker.
2. Tambahkan katalog Faskes, relasi PPK, dan Membership di control DB; katalog/draft Faskes sudah tersedia, Membership belum.
3. Implementasikan provisioning CLI sesuai [`facility-provisioning.md`](./facility-provisioning.md).
4. Implementasikan resolver HTTP, job middleware, dan cleanup context.
5. Tambahkan log context serta scoping cache/storage/sequence.
6. Provision dua Faskes canary dengan schema identik.
7. Pindahkan satu vertical slice lengkap dan buktikan isolasinya.
8. Ukur performa, perbaiki index/query yang nyata, lalu lanjutkan modul berikutnya.

Stop condition fase pertama: dua Faskes canary lulus isolasi, failure isolation, restore, dan target performa. Jangan membuat provisioning web otomatis, cross-Faskes reporting klinis, atau secret manager sebelum kebutuhan tersebut terbukti.

## Acceptance

- Dua Faskes dapat memiliki local ID sama tanpa cross-read/write.
- Request/job berurutan A lalu B pada proses sama tidak membawa context A.
- HTTP, queue, scheduler, cache, storage, sequence, audit, dan integrasi resolve ke Faskes yang sama.
- Satu database Faskes yang mati tidak mematikan Faskes lain.
- Admin tanpa Membership klinis tidak dapat membaca data operasional.
- Error dapat difilter melalui `facility_id` dan ditelusuri dengan `request_id`/`job_id`.
- Migrasi dan provisioning melaporkan checkpoint/kegagalan per Faskes.
- Backup satu Faskes berhasil direstore pada environment uji.
- Uji cross-Faskes denial menjadi gate CI.
- Dua canary memenuhi kontrak performa di atas.

## Keputusan lanjutan yang belum ditetapkan

- strategi migrasi data deployment yang saat ini memakai satu database;
- format final immutable Facility ID;
- bentuk proyeksi laporan Grup;
- kenaikan batas di atas 10 Faskes berdasarkan hasil load test;
- ledger final setiap tabel existing.

## Sumber

- [Konfigurasi aplikasi SIMGos V2](https://docs.simgos2.simpel.web.id/docs/konfigurasi/aplikasi/): hubungan PPK dengan identitas instansi instalasi.
- [MariaDB — CREATE DATABASE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/create-database): `SCHEMA` adalah sinonim `DATABASE` pada MariaDB.
- [Laravel 13 — Multiple Database Connections](https://laravel.com/framework/docs/13.x/database#using-multiple-database-connections): koneksi bernama dan runtime configuration.
- [Laravel 13 — Contextual Logging](https://laravel.com/framework/docs/13.x/logging#contextual-information): context bersama untuk seluruh channel log.
- [Laravel 13 — Job Middleware](https://laravel.com/framework/docs/13.x/queues#job-middleware): pembungkus Facility context pada eksekusi job.
- [Laravel 13 — Cursor Pagination](https://laravel.com/framework/docs/13.x/pagination#cursor-pagination): pagination efisien untuk data besar.
- [Laravel 13 — Database Monitoring](https://laravel.com/framework/docs/13.x/database#monitoring-cumulative-query-time): pemantauan cumulative query time.
