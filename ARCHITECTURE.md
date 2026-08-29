# Architecture — SIMGOS

## Bentuk sistem

SIMGOS menggunakan modular monolith: modul dibangun dan dirilis sebagai satu produk, sedangkan boundary domain tetap eksplisit. Modul tidak boleh berubah menjadi satu-folder-per-tabel tanpa pemilik invariant dan orchestration lintas-domain yang jelas.

Arsitektur target:

```text
Satu repository dan build pipeline
├── Grup A — deployment/server A
│   ├── simgos_control
│   ├── Faskes A1 — database operasional A1
│   └── Faskes A2 — database operasional A2
└── Grup B — deployment/server B
    ├── simgos_control
    └── Faskes B1 — database operasional B1
```

Grup adalah deployment boundary. Faskes adalah data, identity, configuration, and integration boundary.

Satu Grup memakai satu instance MariaDB: satu control DB dan satu database operasional per Faskes. Batas awal adalah 10 Faskes aktif per Grup dan dinaikkan hanya setelah load test. Seluruh Faskes memakai satu domain utama dengan route `/f/{facility_code}` dan API `/api/v1/f/{facility_code}`.

## Isolasi Faskes

Setiap Faskes mempunyai sendiri:

- pasien lokal dan Nomor Rekam Medis;
- Pendaftaran, Kunjungan, rekam medis, Tagihan, Pembayaran, dan Episode Klaim;
- pegawai, Membership, role, Unit Layanan, tarif, stok, rekening, dan sequence dokumen;
- kode BPJS/Kemenkes, Organization/Location SATUSEHAT, endpoint, credential, serta scheduler;
- audit trail dan kebijakan retensi.

Request wajib mempunyai facility context yang berasal dari route lalu divalidasi terhadap Membership atau pengecualian aktor global yang eksplisit, bukan `facility_id` bebas dari payload atau satu pilihan global di session. Semua query dan job harus berjalan dalam scope Faskes. Tes isolasi wajib membuktikan identitas dari Faskes A tidak dapat membaca atau menulis data Faskes B. Detail kewenangan berada di [`implementation/membership-access.md`](./implementation/membership-access.md).

Faskes baru dibuat sebagai draft oleh Admin Grup. Operator menjalankan provisioning database dan migrasi lewat CLI; runtime web tidak memiliki privilege `CREATE DATABASE`. Kontrak lengkap berada di [`implementation/multi-schema-facility.md`](./implementation/multi-schema-facility.md) dan [`implementation/facility-provisioning.md`](./implementation/facility-provisioning.md).

Master Patient Index lintas Faskes adalah capability terpisah pada fase lanjutan. MPI tidak memberi izin otomatis untuk berbagi rekam medis atau transaksi.

## Bounded contexts

- **Identity & Facility:** Grup, Faskes, Membership, role, Unit Layanan, credential, capability profile.
- **Patient Administration:** pasien lokal, Pendaftaran, antrean, rujukan, Coverage, SEP.
- **Clinical Encounter:** Kunjungan, Encounter, asesmen, diagnosis, tindakan, order, hasil, resep, resume, finalisasi.
- **Pharmacy & Inventory:** katalog, stok, dispensing, retur, pergerakan barang.
- **Billing & Cashier:** tarif, Tagihan, final tagihan, sesi kasir, Pembayaran, penjamin tagihan.
- **Coding & Claim:** ICD, grouper, Episode Klaim, berkas, pengajuan, dispute, rekonsiliasi.
- **Interoperability:** adapter BPJS, E-Klaim, SATUSEHAT, outbox, submission, monitoring.

Modul UI dapat menggabungkan data beberapa context untuk menyelesaikan tugas, tetapi kepemilikan data dan invariant tetap berada pada context asal.

## Workflow orchestration

Journey lintas context dikoordinasikan oleh application service/process manager, bukan controller CRUD yang saling mengetahui tabel. Setiap transisi penting mempunyai:

- command dan aktor yang berwenang;
- precondition serta transition rule;
- transaksi lokal yang atomik;
- audit event;
- side effect eksternal melalui outbox;
- compensating/manual-resolution path bila side effect gagal.

Response BPJS atau SATUSEHAT bukan source of truth rekam medis internal. Sistem menyimpan external ID dan status sinkronisasi tanpa menyerahkan ownership catatan klinis kepada sistem luar.

## Konsistensi dan concurrency

- Nomor dokumen memakai sequence/unique constraint yang aman, bukan `count()+1`.
- Pembayaran, kalkulasi Tagihan, finalisasi, dan perubahan stok memakai database transaction serta row/version locking yang sesuai.
- Request create yang dapat diulang memiliki idempotency key.
- State transition ilegal ditolak backend walaupun UI menyembunyikan aksinya.
- Koreksi setelah finalisasi dilakukan melalui record atau command beralasan, bukan update status bebas.

## Integrasi eksternal

Antrean Online, VClaim, E-Klaim, Aplicares, ICare, dan SATUSEHAT adalah adapter terpisah yang dihubungkan oleh ID internal Kunjungan/Coverage/Episode Klaim. Setiap adapter memiliki konfigurasi per Faskes, versioned contract, timeout, retry policy, rate limit, redaction, audit, dan health status.

Pengiriman asynchronous menggunakan transactional outbox. Worker melakukan claim/lease record, exponential backoff, batas percobaan, dead-letter/manual resolution, dan idempotent reconciliation. POST versus PUT ditentukan oleh keberadaan remote ID yang tervalidasi.

## Capability profile

Core tidak mengasumsikan semua Faskes memakai integrasi sama:

- `FKRTL_BPJS`: Antrean Online, VClaim, E-Klaim, Aplicares/ICare sesuai layanan.
- `FKTP_BPJS`: PCare dan workflow FKTP pada slice terpisah.
- `SATUSEHAT`: resource pipeline sesuai layanan yang diaktifkan.
- `RAWAT_INAP`, `IGD`, `LAB`, `RAD`, `FARMASI`: capability klinis/operasional per Faskes.

Capability mengendalikan konfigurasi dan workflow yang tersedia, bukan menggantikan permission pengguna.
