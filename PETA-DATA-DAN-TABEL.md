# Peta Data dan Tabel SIMGOS

**Status:** kontrak desain logis
**Tujuan:** menetapkan owner, relasi, cardinality, constraint, lifecycle, dan pemetaan legacy sebelum migration fisik dibuat

Nama tabel aplikasi baru mengikuti konvensi repository senior saat capability diimplementasikan. Nama di dokumen ini adalah nama kanonik; tabel SIMPel hanya bukti sumber dan tidak wajib disalin strukturnya.

## Peta lintas domain

```text
Pasien
├── IdentifierPasien
├── Alamat/Kontak/Keluarga
└── Pendaftaran
    ├── Coverage ── SEP
    └── Kunjungan
        ├── PenempatanBed
        ├── CatatanRekamMedis
        ├── Tindakan
        ├── Order ── ItemOrder ── Hasil
        ├── Resep ── Dispensing
        ├── ItemTagihan ── Tagihan ── AlokasiPenjamin
        │                             └── Pembayaran ── SesiKasir
        └── EpisodeKlaim
            ├── Coding/Grouping
            ├── BerkasKlaim
            └── Submission

Aggregate final internal
└── OutboxEvent ── Submission ── PercobaanSubmission ── sistem eksternal
```

## Aturan ID dan relasi

- Setiap entity memiliki ID internal stabil; NIK, NRM, nomor BPJS, SEP, dan ID SATUSEHAT bukan primary key internal.
- Foreign key memakai suffix `_id` dan menunjuk entity kanonik.
- Nomor dokumen memakai unique constraint dan generator concurrency-safe.
- Salinan nama/kode hanya diperbolehkan sebagai snapshot yang diberi nama jelas.
- Data final, klinis, finansial, audit, dan submission tidak di-hard-delete.
- Relasi lintas domain dilakukan melalui service/command/event owner, bukan update tabel langsung.

## Organisasi, identitas, dan akses

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Catatan target |
|---|---|---|---|---|
| `Faskes` | tepat 1 per instalasi | Orang 1 | `master.ppk` | profil boundary deployment |
| `PPK` | 1 internal, banyak eksternal | Orang 1 | `master.ppk` | internal atau fasilitas rujukan; bukan tenant |
| `Ruangan` | hierarchy melalui `parent_id`; kode unik | Orang 1 | `master.ruangan` | jenis: instalasi, unit layanan, ruang/kamar |
| `TempatTidur` | banyak per Ruangan kamar | Orang 2 | `master.tempat_tidur` | status operasional terpisah dari penempatan |
| `Pegawai` | identifier pegawai unik | Orang 1 | `master.pegawai`/schema `pegawai` | profil SDM operasional |
| `Profesi` | master aktif/nonaktif | Orang 2 | referensi/profesi legacy | syarat aksi klinis |
| `Pengguna` | username unik; terkait pegawai bila relevan | Orang 4 | `aplikasi.pengguna` | akun individual, bukan akun bersama |
| `Role` | banyak permission | Orang 4 | group akses legacy | mengizinkan modul/aksi |
| `AksesRuangan` | unik pengguna–Ruangan–masa berlaku | Orang 4 | `aplikasi.pengguna_akses_ruangan` | membatasi scope data |
| `CredentialIntegrasi` | unik sistem+environment+versi aktif | Orang 4 | `integrasi`/`signature` legacy | ciphertext di DB; master key di environment; audit rotasi |

Hak efektif adalah irisan permission Role, scope Ruangan, syarat Profesi, status akun, dan capability instalasi. Break-glass menambah akses sementara dengan alasan, durasi, audit khusus, dan notifikasi.

## Pasien dan pendaftaran

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Konsumen |
|---|---|---|---|---|
| `Pasien` | NRM unik per instalasi | Orang 1 | `master.pasien` | semua domain operasional |
| `IdentifierPasien` | unik jenis+nilai bila terverifikasi | Orang 1 | `master.kartu_identitas_pasien`, `master.kartu_asuransi_pasien` | pencarian, BPJS, SATUSEHAT |
| `AlamatPasien` | banyak, satu utama aktif | Orang 1 | tabel alamat/wilayah legacy | pendaftaran, klaim |
| `KontakPasien` | banyak, jenis+prioritas | Orang 1 | `master.kontak_pasien` | komunikasi |
| `KeluargaPasien` | banyak per Pasien | Orang 1 | `master.keluarga_pasien`, `master.kontak_keluarga_pasien` | penanggung jawab |
| `PenggabunganPasien` | sumber hanya boleh digabung sekali | Orang 1 | `master.pasien_log` sebagai referensi parsial | audit relasi pasien duplikat |
| `Pendaftaran` | nomor unik; banyak per Pasien | Orang 1 | `pendaftaran.pendaftaran` | Layanan, RM, Billing, Klaim |
| `Coverage` | banyak per Pendaftaran; satu utama | Orang 1 | `pendaftaran.penjamin` | BPJS, Billing, Klaim |
| `SEP` | nomor SEP unik; terkait Coverage/Pendaftaran | Orang 4 | `bpjs.kunjungan` | pendaftaran, klaim |

Pencarian duplikat memakai NIK, nomor BPJS, NRM, dan kombinasi nama–tanggal lahir–jenis kelamin. Kemiripan hanya memberi kandidat; penggabungan adalah command terpisah yang diaudit.

## Layanan dan rekam medis

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Catatan |
|---|---|---|---|---|
| `Kunjungan` | banyak per Pendaftaran; nomor unik | Orang 2 | `pendaftaran.kunjungan` | simpul kerja satu Ruangan/unit |
| `PenempatanBed` | histori berurutan; maksimal satu aktif per Kunjungan/Bed | Orang 2 | tujuan/mutasi/bed legacy | pindah bed menutup record lama |
| `Tindakan` | banyak per Kunjungan | Orang 2 | `layanan.tindakan_medis` | fakta layanan billable |
| `PelaksanaTindakan` | banyak per Tindakan | Orang 2 | `layanan.petugas_tindakan_medis` | peran dan waktu pelaksana |
| `Order` | banyak per Kunjungan | Orang 2 | `layanan.order_lab`, `order_rad`, `order_resep` | tipe lab/rad/resep/konsul |
| `ItemOrder` | banyak per Order | Orang 2 | `layanan.order_detil_lab`, `order_detil_rad`, `order_detil_resep` | pemeriksaan/barang yang diminta |
| `Hasil` | banyak per ItemOrder; versi/amendment | Orang 2 | `layanan.hasil_lab`, `hasil_rad`, tabel hasil khusus | preliminary/final/amended |
| `Resep` | banyak per Kunjungan | Orang 2 | `layanan.order_resep` | order terapi obat |
| `Dispensing` | banyak per Resep/ItemOrder | Orang 2 | tabel farmasi/resep terlayani legacy | penyerahan dan retur obat |
| `Anamnesis` | versi/catatan per Kunjungan | Orang 2 | `medicalrecord.anamnesis` | structured + narasi |
| `Alergi` | banyak per Pasien/Kunjungan | Orang 2 | `medicalrecord.riwayat_alergi` | terminologi dan reaksi |
| `TandaVital` | append-only per Kunjungan+waktu | Orang 2 | `medicalrecord.tanda_vital` | data terstruktur |
| `DiagnosisKlinis` | banyak; satu utama sesuai aturan | Orang 2 | `medicalrecord.diagnosis` | catatan dokter |
| `CodingDiagnosis` | banyak per Episode Klaim/Kunjungan | Orang 3 | `medicalrecord.diagnosa` | ICD untuk klaim, tidak menimpa diagnosis dokter |
| `CPPT` | append-only + verifikasi | Orang 2 | `medicalrecord.cppt`, `verifikasi_cppt` | SOAP dan counter-sign |
| `ResumeMedis` | satu aktif per episode, versi amendment | Orang 2 | `medicalrecord.resume` | bahan klaim dan Composition |
| `DokumenKlinis` | banyak per Kunjungan | Orang 2 | document storage/medicalrecord | surat, consent, transfer, operasi |

Unit asal membuat Order dan boleh membatalkan sebelum diterima. Unit tujuan memiliki penerimaan, pelaksanaan, dan Hasil. Rekam Medis memiliki isi klinis; Layanan memiliki lifecycle operasional Kunjungan, tindakan, order, dan hasil.

## Farmasi dan inventory

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Aturan |
|---|---|---|---|---|
| `Barang` | kode unik; mapping eksternal terpisah | Orang 2 | `master.barang` | obat/BHP/alkes berdasarkan jenis |
| `LokasiStok` | terkait Ruangan/depo/gudang | Orang 2 | Ruangan/depo legacy | scope akses stok |
| `SaldoStok` | unik Barang+Lokasi+batch/expiry bila dipakai | Orang 2 | tabel stok inventory | cache dari ledger, bukan sumber mutasi bebas |
| `TransaksiStok` | append-only | Orang 2 | `inventory.transaksi_stok_ruangan` dan tabel mutasi | masuk/keluar/koreksi/retur |
| `DokumenStok` | header + item | Orang 2 | permintaan/pengiriman/penerimaan legacy | state approval dan receipt |
| `StokOpname` | periode+lokasi unik saat aktif | Orang 2 | tabel opname inventory | selisih menghasilkan transaksi koreksi |
| `Penyedia` | kode unik, aktif/nonaktif | Orang 2 | master penyedia/rekanan | pembelian/penerimaan |

Saldo tidak boleh diubah langsung. Semua perubahan berasal dari TransaksiStok yang atomik dan dapat ditelusuri ke dokumen sumber.

## Billing, kasir, dan klaim

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Aturan |
|---|---|---|---|---|
| `Tarif` | effective-dated per tindakan/kelas/penjamin | Orang 3 | `master.tarif` | menyimpan komponen dan dasar SK |
| `ItemTagihan` | banyak per Tagihan; source key unik | Orang 3 | `pembayaran.rincian_tagihan` | snapshot tarif saat layanan sah |
| `Tagihan` | satu atau lebih per episode sesuai aturan | Orang 3 | `pembayaran.tagihan`, `tagihan_pendaftaran` | total dihitung atomik |
| `AlokasiPenjamin` | banyak per Tagihan; total ≤ nilai tagihan | Orang 3 | `pembayaran.penjamin_tagihan` | BPJS/asuransi/pasien/subsidi |
| `SesiKasir` | satu sesi aktif per kasir/loket | Orang 3 | `pembayaran.transaksi_kasir` | open/close dan rekonsiliasi |
| `Pembayaran` | append-only; idempotency key unik | Orang 3 | `pembayaran.pembayaran_tagihan` | payment/refund/reversal, tidak edit histori |
| `PembatalanTagihan` | banyak, beralasan | Orang 3 | `pembayaran.pembatalan_tagihan` | membuka final melalui command |
| `EpisodeKlaim` | unik episode+penjamin+jenis klaim | Orang 3 | `inacbg.inacbg`, `pembayaran.tagihan_klaim` | mengikat SEP, klinis, tagihan |
| `Grouping` | versi per Episode Klaim | Orang 3 | `inacbg.grouping`, `hasil_grouping` | hasil grouper tidak menimpa klinis |
| `BerkasKlaim` | banyak dokumen/versi | Orang 3 | `berkas_klaim.berkas`, `berkas_detil` | bundel dan urutan dokumen |
| `ReadinessKlaim` | satu proyeksi/checklist per episode | Orang 3 | `berkas_klaim.kelengkapan` | tidak mengubah sumber klinis |

Domain asal menerbitkan fakta layanan. Billing menjadi satu-satunya pembuat ItemTagihan. Perubahan tarif baru tidak mengubah snapshot transaksi lama.

## Integrasi, audit, dan dokumen

| Entity kanonik | Cardinality/constraint | Owner | Tabel legacy utama | Aturan |
|---|---|---|---|---|
| `MappingEksternal` | unik sistem+jenis+ID internal+masa berlaku | owner domain + Orang 4 | tabel mapping BPJS/Kemenkes | kode eksternal tidak menjadi ID core |
| `OutboxEvent` | event key unik | owner aggregate | tidak seragam pada legacy | ditulis atomik bersama transaksi internal |
| `Submission` | operation/idempotency key unik per target | Orang 4 | schema `kemkes-ihs`, `bpjs`, `inacbg` | status pengiriman dan remote ID |
| `PercobaanSubmission` | append-only per percobaan | Orang 4 | log tersebar legacy | waktu, hasil, HTTP aman, correlation ID |
| `AuditEvent` | append-only | Orang 4 | schema `logs`, audit modul | aktor, aksi, objek, alasan, hasil |
| `Dokumen` | ID+versi+checksum unik | owner domain | `document-storage` | metadata di DB, isi di file/object storage |
| `TemplateDokumen` | versi+masa berlaku | owner domain | `cetakan`/Jasper/template legacy | berbeda dari hasil cetak |

Credential tersimpan terenkripsi di database. Master encryption key berada pada environment server. UI hanya menampilkan nilai tersamarkan dan setiap rotasi menghasilkan audit.

## Lifecycle kanonik

| Aggregate | Lifecycle minimum | Koreksi |
|---|---|---|
| Pendaftaran | `draft → confirmed → closed` atau `cancelled` | command perubahan beralasan |
| Kunjungan | `waiting → received → in_service → final` atau `cancelled` | reopen khusus atau amendment domain |
| Order | `draft → sent → received → processing → completed` atau `rejected/cancelled` | batal hanya sesuai state |
| Hasil | `preliminary → final → amended` atau `cancelled` | amendment menyimpan versi asal |
| Catatan RM | `draft → final → amended` | tidak silent overwrite |
| PenempatanBed | `reserved → occupied → released` atau `cancelled` | record lama ditutup, bukan ditimpa |
| Tagihan | `draft → final → partially_paid → paid` atau `void` | reversal/buka-final beralasan |
| SesiKasir | `open → closing → closed` | selisih direkonsiliasi |
| EpisodeKlaim | `draft → ready → grouped → submitted → disputed/approved → paid → reconciled` | versi/reopen terkontrol |
| Submission | `queued → sending → accepted` atau `retryable_failed/permanently_failed → reconciled` | retry idempotent |
| DokumenStok | `draft → approved → sent → received` atau `cancelled` | koreksi ledger, bukan edit saldo |

Nama state fisik boleh mengikuti konvensi codebase, tetapi makna dan transisinya tidak boleh berubah tanpa memperbarui kontrak ini.

## Aturan delete dan snapshot

| Jenis data | Aturan |
|---|---|
| master belum pernah dipakai | boleh dihapus jika tidak mempunyai relasi |
| master sudah dipakai | nonaktifkan; transaksi lama tetap menunjuk ID yang sama |
| draft teknis belum diterbitkan | boleh dihapus sesuai policy domain |
| klinis, finansial, stok, audit, klaim, submission | tidak hard-delete; batal, reversal, amendment, atau tombstone |
| nama/tarif/label dalam transaksi | snapshot eksplisit agar histori tidak berubah |

## Checklist migration fisik

Sebelum membuat migration capability:

1. tunjuk owner aggregate;
2. pilih ID dan unique constraint;
3. tulis FK, cardinality, serta aturan delete;
4. tulis state transition dan command;
5. tandai snapshot versus referensi hidup;
6. tentukan audit dan idempotency;
7. petakan tabel legacy yang menjadi bukti;
8. buat satu tes invariant yang gagal bila aturan utamanya rusak.
