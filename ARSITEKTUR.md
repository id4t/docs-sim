# Arsitektur — SIMGOS

## Bentuk sistem

SIMGOS menggunakan modular monolith: modul dibangun dan dirilis sebagai satu produk, sedangkan boundary domain tetap eksplisit. Modul tidak boleh berubah menjadi satu-folder-per-tabel tanpa pemilik invariant dan orchestration lintas-domain yang jelas.

Arsitektur target:

```text
Satu codebase dan build pipeline
└── Satu instalasi Faskes
    ├── frontend pada /
    ├── backend pada /api
    ├── satu database MariaDB
    ├── worker queue dan scheduler
    └── konfigurasi integrasi terenkripsi
```

Faskes adalah deployment, data, identity, configuration, and integration boundary. Tidak ada facility context lintas runtime pada target aktif.

## Batas instalasi

Setiap instalasi mempunyai sendiri:

- pasien dan Nomor Rekam Medis;
- Pendaftaran, Kunjungan, rekam medis, Tagihan, Pembayaran, dan Episode Klaim;
- pegawai, akun pengguna, role, Unit Layanan, tarif, stok, rekening, dan sequence dokumen;
- kode BPJS/Kemenkes, Organization/Location SATUSEHAT, endpoint, credential, serta scheduler;
- audit trail dan kebijakan retensi.

Semua query dan job berjalan dalam satu boundary instalasi. Isolasi yang wajib dijaga saat ini adalah antar domain, antar role, antar unit layanan, dan antar lingkungan deployment.

## Bounded contexts

- **Identity & Access:** user, role, profesi, akses ruangan/unit, credential aplikasi.
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

Antrean Online, VClaim, E-Klaim, Aplicares, ICare, dan SATUSEHAT adalah adapter terpisah yang dihubungkan oleh ID internal Kunjungan/Coverage/Episode Klaim. Setiap adapter memiliki konfigurasi instalasi tunggal, versioned contract, timeout, retry policy, rate limit, redaction, audit, dan health status. Credential berupa ciphertext di database agar dapat dirotasi melalui UI; master encryption key tetap berada pada environment server.

Pengiriman asynchronous menggunakan transactional outbox. Worker melakukan claim/lease record, exponential backoff, batas percobaan, dead-letter/manual resolution, dan idempotent reconciliation. POST versus PUT ditentukan oleh keberadaan remote ID yang tervalidasi.

## Capability profile

Core tidak mengasumsikan semua instalasi memakai integrasi sama:

- `FKRTL_BPJS`: Antrean Online, VClaim, E-Klaim, Aplicares/ICare sesuai layanan.
- `FKTP_BPJS`: PCare dan workflow FKTP pada slice terpisah.
- `SATUSEHAT`: resource pipeline sesuai layanan yang diaktifkan.
- `RAWAT_INAP`, `IGD`, `LAB`, `RAD`, `FARMASI`: capability klinis/operasional per instalasi.

Capability mengendalikan konfigurasi dan workflow yang tersedia, bukan menggantikan permission pengguna.
