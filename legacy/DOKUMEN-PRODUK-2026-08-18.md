# Dokumen Produk — SIMGOS (Sistem Informasi Manajemen Rumah Sakit)

> **ARSIP — bukan spesifikasi target.** Dokumen ini dipertahankan sebagai baseline historis per 2026-08-18. Gunakan [`../README.md`](../README.md) untuk sumber kebenaran aktif.

**Versi dokumen:** 1.0 — 2026-08-18
**Status:** Living document, sinkron dengan `RME-Frontend/src/types/modulesCatalog.ts` dan `RME-Frontend/src/routes/index.tsx` per tanggal di atas.
**Produk referensi (legacy):** SIMpel — ExtJS desktop-style app di `/var/www/html/production/webapps/application/SIMpel`. SIMGOS adalah **re-platform** SIMpel ke stack modern (React + Laravel), bukan produk baru dari nol. UX target: **sedekat mungkin dengan SIMpel**, lihat `PANDUAN-UI-UX-LAMA.md`.

---

## 1. Latar Belakang & Tujuan

SIMGOS adalah sistem informasi manajemen rumah sakit (Hospital Information System) yang mencakup seluruh alur operasional RS: pendaftaran pasien, layanan medis, rekam medis elektronik (RME), farmasi, penunjang (lab/radiologi), inventory, pembayaran/kasir, hingga pelaporan ke Kemenkes (RL SIRS) dan bridging BPJS/SatuSehat.

**Tujuan re-platform:**
- Mengganti UI ExtJS lama (berat, sulit dikembangkan) dengan SPA React modern tanpa mengubah *mental model* pengguna existing (petugas RS sudah terlatih di SIMpel selama bertahun-tahun).
- Backend modular Laravel (474 modul composer, 1:1 dengan domain bisnis) menggantikan backend PHP lama, diakses lewat REST API (Sanctum Bearer token, bukan cookie).
- Migrasi bertahap per-modul (lihat §5 Fase Pengerjaan), bukan big-bang rewrite — legacy SIMpel tetap jadi acuan kebenaran perilaku bisnis selama proses.

## 2. Ruang Lingkup Sistem

Katalog modul SIMGOS berisi **147 sub-modul** dalam **18 domain**, dibagi dua kelompok besar:

- **Operasional** (sidebar kiri) — dipakai staf loket/perawat/dokter/kasir sehari-hari: Pendaftaran, Layanan, Pembayaran, Rekam Medis, Penjualan, Pencarian, Pelayanan KPO.
- **Penunjang & Administrasi** (top drawer) — dipakai admin/manajemen: Master Data, Laporan, Dashboard, Informasi (display publik), Tempat Tidur, Inventory, Integrasi, Monitoring, Akses API, Logs, Berkas Klaim.

Lihat **Lampiran A** untuk daftar lengkap 147 modul beserta status build.

## 3. Persona Pengguna

| Persona | Kebutuhan Utama | Modul yang paling relevan |
|---|---|---|
| Petugas Loket Pendaftaran | Registrasi pasien baru/lama secepat mungkin, cetak kartu berobat | Pendaftaran (10), Pencarian (24) |
| Perawat/Dokter Poli & Ruangan | Input CPPT, diagnosis, resep, tindakan, lihat riwayat pasien | Layanan (11), Rekam Medis (13) |
| Kasir | Buat tagihan, terima pembayaran tunai/non-tunai, cetak kuitansi | Pembayaran (12) |
| Farmasi/Apotek | Racik & serahkan resep, kelola stok obat | Layanan Farmasi, Inventory (23), Master Data obat (1908/1913/1925) |
| Admin/IT RS | Kelola user, hak akses, master data referensi, profil institusi | Master Data (19), termasuk Manajemen Pengguna (1903) |
| Manajemen/Direksi | Pantau indikator kinerja RS (BOR/LOS), pendapatan, klaim BPJS | Dashboard (15), Laporan (14) |
| Petugas Rekam Medis/Klaim BPJS | Kelengkapan berkas klaim, koding ICD, resume medis | Rekam Medis (13), Berkas (30) |
| Pengunjung/Keluarga Pasien | Cek jadwal dokter, cari lokasi kamar pasien | Informasi (20) — layar display publik |

## 4. Status Implementasi Saat Ini (ringkas)

Dari 147 modul katalog, **50 sudah punya halaman dedicated** (list/form/detail React penuh, atau tab fungsional di dalam Visit Workspace), sisanya (97) memakai **`DynamicModulePage`** — layar scaffold generik yang menampilkan header modul (kode, domain, deskripsi), tombol "Tambah Data" (placeholder alert), tombol Cetak, dan tombol Kembali, tanpa CRUD nyata.

| Domain | Total Modul | Sudah Dibangun |
|---|---|---|
| [10] Pendaftaran | 14 | 2 (Pasien Baru, Rawat Jalan) |
| [11] Layanan | 16 | 13 (Penerimaan Ruangan — worklist filter ward/status + identitas pasien sesuai grid legacy `kunjungan.List`; Tindakan Medis — tab di Visit Workspace, `LayananMedicalProcedure`; Pengiriman/Resep; Pemakaian BHP Ruangan — ledger stok per ruangan; Feedback/Hasil Pemeriksaan — order+hasil lab, tab "Lab"; Pembacaan Ekspertise — order+hasil radiologi, tab "Radiologi"; History Layanan Pasien — daftar kunjungan lain pasien lintas registrasi, tab "Riwayat"; Pembatalan Layanan — aksi batalkan status per tindakan/order di tab Tindakan/Lab/Radiologi; Final/Selesai Pelayanan — tombol Final Pelayanan di header Visit Workspace, pilih Cara Keluar dari Master Referensi; Kelahiran (VK Bersalin) — tab "Kelahiran"; Bon Sisa Obat/BHP — tab "Bon Sisa", voucher retur sisa obat/BHP + item dari katalog inventori; Menu Layanan Tambahan — tab "Layanan Tambahan", Pemakaian O₂ (laju+metode+mulai/selesai); Formulir Antimikroba — tab "Antimikroba" (draft/ajukan/setujui PPRA) di Visit Workspace) |
| [12] Pembayaran | 14 | 1 (Transaksi Kasir) |
| [13] Rekam Medis | 19 | 11 (Anamnesis; Diagnosis ICD-10; Perencanaan Medis; Triage IGD; CPPT; Surat Medis — surat sakit; Resume Medis Pulang; Lembar Transfer Pasien; Penilaian — Nyeri+Morse; TTV & Fisik — vital lengkap + checklist per region tubuh) |
| [19] Master Data | 28 | 20 (Pegawai, Pengguna, Ruangan, Referensi — halaman generik 328 kategori/3973 nilai diimpor dari legacy, Tindakan, Profil RS, PPK, Kategori Barang, Penyedia/Supplier, Paket Layanan, Layanan Lainnya, Template Anatomi, Rekening RS, Negara, Wilayah — browser Provinsi/Kab-Kota/Kecamatan/Kelurahan read-only, Master Barang — katalog obat/alkes/BHP + aksi sesuaikan stok, Group/Jenis Pemeriksaan, Master Tarif — tarif per tindakan+kelas rawat dengan riwayat SK, Paket Tindakan Farmasi, Master Mapping — pemetaan Group Pemeriksaan ke kode eksternal) |
| [15] Dashboard | 11 | 1 (Indikator Efisiensi RS) |
| [23] Inventory | 6 | 1 (Rekanan/Supplier — entitas sama dengan 1907 Master Penyedia, cukup ditautkan ke `/suppliers`) |
| 11 domain lain (14, 20, 21, 22, 24, 25, 27, 28, 29, 30) | 39 | 0 |
| **Total** | **147** | **50** |

Detail per halaman ada di `CATATAN.md` §2. Urutan pengerjaan modul berikutnya mengikuti urutan histori commit backend (lihat `CATATAN.md` §4).

## 5. Fase Pengerjaan

- **Fase 0 — Auth**: selesai. Login, session persist, protected route.
- **Fase 1 — Master Data RS**: selesai (audit ulang penuh vs legacy lewat Playwright, semua gap field ditemukan & diperbaiki — lihat `CATATAN.md` §5).
- **Fase 2 — Pelayanan Pasien (sedang berjalan)**: Pendaftaran (Registration+Visit) ✅, Pembayaran (Invoice/InvoiceItem/Payment) ✅, Rekam Medis (ClinicalNote, DiagnosisCode) ✅, selanjutnya: Prescription/Item, InventoryItem, PendaftaranGuarantor, LayananLabOrder/LabResult, dst — urutan persis mengikuti `git log` modul backend.
- **Fase 3+ (belum dimulai)**: sisa ~130 modul scaffold di atas, termasuk seluruh domain Laporan, Dashboard granular, Monitoring, Integrasi BPJS/SatuSehat, Inventory penuh.

## 6. Functional Requirements per Domain

### 6.1 Pendaftaran (domain 10)
- Registrasi pasien baru dengan identitas lengkap (NIK, kontak multi-nomor, kartu identitas multi-jenis, data keluarga bersarang).
- Registrasi kunjungan (rawat jalan/IGD/rawat inap) dengan nomor otomatis `REG-{tahun}-{seq}` dan `KJ-{tahun}-{seq}`.
- Cascade pemilihan Ward → Room → Bed saat admisi.
- Pembatalan pendaftaran/antrean, riwayat pendaftaran, pencetakan kartu berobat/wristband/barcode.
- **Belum**: IGD, rawat inap penuh (admisi formal), pendaftaran lab/radiologi langsung, triage loket, forensik/jenazah.

### 6.2 Layanan (domain 11)
- Penerimaan pasien di ruangan aktif dengan antrean (worklist `/visits`, filter Ruangan+Status, kolom identitas pasien Nama+No.RM — diverifikasi field-by-field vs grid legacy `kunjungan.List`, lihat `ALUR-KERJA.md` §3 domain 11).
- Input tindakan medis per kunjungan (tab "Tindakan" di Visit Workspace `/visits/:id`) — pilih Tindakan (master `GeneralService`) + Petugas Pelaksana, status `completed`/`cancelled` (append-only: tidak bisa diedit/dihapus, cuma dibatalkan via status).
- Order resep obat/lab/radiologi/konsul dari satu titik entry.
- **Belum**: search by No.RM/No.Pendaftaran/Nama & filter DPJP/Jenis Kunjungan/Penjamin/rentang tanggal di worklist 1101, kolom Penjamin, badge titipan/paket/iterasi resep, info kamar/tempat-tidur/kelas rawat inap, guard lock tindakan medis saat kunjungan final, daftar petugas pelaksana multi-orang per tindakan (`MedicalProcedureStaff` backend sudah ada, frontend belum pakai) — plus penerimaan hasil lab/radiologi (feedback loop), ekspertise, kelahiran/VK, pemakaian BHP ruangan, KPO, panggilan antrean.

### 6.3 Pembayaran (domain 12)
- Buat invoice dari kunjungan, tambah item (subtotal dihitung server), terima pembayaran (create-only, tanpa edit/delete — sengaja, demi audit trail keuangan).
- Auto-lock invoice ke status `paid` saat total dibayar ≥ total tagihan; form tambah item/bayar otomatis hilang dari UI setelah lunas.
- **Belum**: deposit uang muka, piutang, diskon, penjamin BPJS/asuransi, pembayaran non-tunai granular (EDC/QRIS), kuitansi cetak formal, kasir apotek.

### 6.4 Rekam Medis (domain 13)
- CPPT (SOAP) append-only — tidak ada edit/delete, sesuai prinsip legal medical record.
- Diagnosis ICD-10 append-only, satu diagnosis primer per kunjungan (enforced backend).
- **Belum**: anamnesis, TTV/pemeriksaan fisik, assessment (skala nyeri/risiko jatuh), penandaan anatomi, resume medis pulang, rekonsiliasi obat, pemantauan kritis (ICU), SatuSehat registry.

### 6.5 Master Data (domain 19)
- Pegawai (dengan kontak, kartu identitas, dropdown spesialis), Pengguna (akun+role+akses ruangan), Ruangan (Ward/Room/Bed + penugasan Dokter/Spesialis/Paramedis/Staff/Tindakan per ward), Profil Institusi.
- Referensi: 8 kategori demografi pasien (Agama/Jenis Kelamin/Pendidikan/dll) sebagai FK dedicated (`/genders`, `/professions`, `/religions`, dst — dipakai Patient/Employee), **plus** Master Referensi generik (`/references`, halaman Master-Detail: kiri 328 kategori + kanan nilai per kategori) menutupi sisa ~320 kategori referensi lintas modul (skala penilaian Rekam Medis, jenis tagihan, kategori tindakan, dll) — data diimpor langsung dari DB legacy (3973 nilai), lihat `ALUR-KERJA.md` §3 domain 19 & `CATATAN.md` untuk detail seeder cross-schema.
- Tindakan (`/services`, CRUD Kode/Nama/Kategori/Tarif/Status) — kategori dropdown-nya konsumsi langsung Master Referensi (`category=Kategori Tindakan`, 12 nilai dari legacy), tabel `services` ini juga yang dipakai 1102 Tindakan Medis di Visit Workspace.
- **Belum**: 21 dari 28 sub-master (Barang/obat, Tarif, PPK, BPJS config, Wilayah, dst) — plus di Master Referensi generik sendiri: UI kelola kategori (`ReferenceType`) dari frontend (sekarang cuma via DB/seeder), relasi FK proper `ReferenceType`↔`Reference`; di Master Tindakan: field `type_id`/`GeneralServiceType` belum dipakai (tidak ada data existing yang mengisinya), tarif (`GeneralServiceTariff`) belum ada UI kelola sendiri (cuma tampil read-only via `current_price`).

### 6.6 Dashboard & Laporan (domain 14, 15)
- Satu dashboard indikator (BOR/LOS/TOI/BTO/GDR/NDR) sudah ada.
- **Belum**: seluruh 12 modul laporan (RL SIRS, laporan kunjungan/layanan/keuangan) dan 10 dashboard granular lain — ini murni scaffold saat ini.

### 6.7 Domain lain (20–30)
Informasi publik, Tempat Tidur (occupancy board), Inventory, Integrasi BPJS/LIS, Monitoring, Akses API, Logs, Berkas Klaim — **seluruhnya masih scaffold**, backend maupun frontend belum ada implementasi nyata kecuali kebutuhan data yang sudah tersedia secara tidak langsung (mis. `bed_id`/`ward_id` di Visit untuk occupancy board, tapi endpoint filter belum ada).

## 7. Non-Functional Requirements

- **Auth**: Laravel Sanctum, Bearer token di header, persist di `localStorage` key `simgos_token` (bukan cookie session).
- **Konsistensi data legacy**: setiap modul baru wajib diverifikasi field-by-field terhadap layar SIMpel asli sebelum dianggap selesai — jangan asumsi struktur "seragam" antar modul (format response API bisa beda per modul: `{data: ...}` wrapping vs custom).
- **Audit trail**: modul rekam medis & finansial harus append-only di titik yang legal-sensitive (CPPT, Diagnosis, Payment) — tidak boleh ditambah tombol edit/delete meskipun terasa tidak lengkap dari sisi CRUD generik.
- **Skala**: 474 modul backend total, jadi arsitektur frontend harus tetap scalable — makanya ada `DynamicModulePage` sebagai fallback generik alih-alih blank 404 untuk modul yang belum dibangun.
- **Printing**: banyak modul punya kebutuhan cetak (kartu, wristband, kuitansi, lembar RM) — perlu strategi cetak konsisten (lihat `PANDUAN-UI-UX-LAMA.md` §6).

## 8. Out of Scope (untuk dokumen ini)

- Detail skema database (lihat `simgos_dump.sql` / migrasi Laravel langsung).
- Kontrak API per endpoint (lihat kode `Modules/*` di `RME-Backend`).
- Keputusan desain untuk gap yang sengaja dilewatkan (tab "Barang" di Ruangan legacy, dll — didaftar di `CATATAN.md` §6).

## 9. Lampiran A — Katalog Modul Lengkap (147 modul / 18 domain)

Kolom **Status**: `Dibangun` = ada halaman React dedicated (list/form/detail nyata terhubung ke API). `Scaffold dinamis` = hanya rute generik `/module/:id` lewat `DynamicModulePage`, belum ada CRUD.

### [10] Pendaftaran (14 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1001 | Pasien Baru | Dibangun | `/patients` | Perekaman identitas dan demografi pasien baru |
| 1002 | Rawat Jalan | Dibangun | `/registrations` | Pendaftaran kunjungan poliklinik rawat jalan |
| 1003 | Gawat Darurat (IGD) | Scaffold dinamis | `/module/1003` | Pendaftaran pasien instalasi gawat darurat |
| 1004 | Rawat Inap | Scaffold dinamis | `/module/1004` | Admisi dan pendaftaran pasien rawat inap |
| 1005 | Laboratorium | Scaffold dinamis | `/module/1005` | Pendaftaran pemeriksaan laboratorium langsung |
| 1006 | Radiologi | Scaffold dinamis | `/module/1006` | Pendaftaran pemeriksaan radiologi langsung |
| 1007 | Perubahan Data | Scaffold dinamis | `/module/1007` | Koreksi dan pembaruan data pendaftaran kunjungan |
| 1008 | Pencetakan | Scaffold dinamis | `/module/1008` | Pencetakan kartu berobat, wristband, dan barcode |
| 1009 | History Pendaftaran | Scaffold dinamis | `/module/1009` | Riwayat pendaftaran kunjungan pasien |
| 1010 | Pembatalan | Scaffold dinamis | `/module/1010` | Pembatalan nomor antrean dan pendaftaran pasien |
| 1011 | Penerimaan | Scaffold dinamis | `/module/1011` | Konfirmasi penerimaan kedatangan pasien di loket |
| 1012 | Tindakan List | Scaffold dinamis | `/module/1012` | Daftar tindakan administrasi pendaftaran |
| 1013 | Triage Pendaftaran | Scaffold dinamis | `/module/1013` | Skrining triase awal pasien di loket gawat darurat |
| 1014 | Pasien Telah Meninggal (Forensik) | Scaffold dinamis | `/module/1014` | Pendaftaran dan administrasi jenazah / forensik |

### [11] Layanan (16 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1101 | Penerimaan Ruangan | Dibangun | `/visits` | Penerimaan pasien dan antrean di ruangan aktif |
| 1102 | Penginputan Tindakan Medis | Dibangun | `/visits` (tab "Tindakan") | Pencatatan tindakan dan pemeriksaan medis dokter/perawat |
| 1103 | Pengiriman (Order Penunjang/Konsul) | Dibangun | `/prescriptions` (+ tab Lab/Radiologi/Konsul di `/visits`) | Keempat jenis order legacy jadi tab di Visit Workspace: Resep (`LayananPrescription`), Lab (`LayananLabOrder`), Radiologi (`LayananRadiologyOrder`), Konsul antar-departemen (`PendaftaranConsultation`+`ConsultationAnswer`: perujuk→dituju + tanya/jawab) |
| 1104 | Feedback / Hasil Pemeriksaan | Dibangun | `/visits` | Penerimaan hasil laboratorium dan ekspertise radiologi |
| 1105 | Pembacaan Ekspertise | Dibangun | `/visits` | Lembar pembacaan hasil diagnostik imaging & rad |
| 1106 | History Layanan Pasien | Dibangun (sebagian) | `/visits` | Riwayat layanan medis yang pernah diterima pasien |
| 1107 | Pencetakan Hasil Layanan | Scaffold dinamis | `/module/1107` | Cetak lembar rincian tindakan dan hasil layanan |
| 1108 | Pembatalan Layanan | Dibangun | `/visits` | Pembatalan tindakan medis yang belum difinalisasi |
| 1109 | Final / Selesai Pelayanan | Dibangun | `/visits` | Penyelesaian dan penutupan status pelayanan pasien |
| 1110 | Kelahiran (VK Bersalin) | Dibangun | `/visits` | Pencatatan persalinan, partus, dan data bayi baru lahir |
| 1111 | Pemakaian BHP Ruangan | Dibangun | `/ward-stock-usage` | Penginputan pemakaian bahan medis habis pakai dan alkes |
| 1112 | Bon Sisa Pemakaian Obat/BHP | Dibangun | `/visits` (tab "Bon Sisa") | Voucher retur sisa obat/BHP per kunjungan — nomor bon auto, item dari katalog inventori (`item_id`+qty+satuan), status tebus (pending/redeemed/expired); backend `LayananLeftoverMedicationVoucher`+`...Item`, ditambah filter index `visit_id`/`patient_id`/`status`+`leftover_medication_voucher_id` |
| 1113 | Pemanggilan Antrean Kunjungan | Scaffold dinamis | `/module/1113` | Pemanggilan nomor antrean suara dan visual poli |
| 1114 | Menu Layanan Tambahan | Sebagian | `/visits` (tab "Layanan Tambahan") | Container layanan spesifik unit per kunjungan; terisi Pemakaian O₂ (`LayananOxygenUsage`: laju L/mnt + metode + mulai/selesai, tombol "Akhiri"), +filter index `visit_id`. Permintaan Darah menunggu backend parent `LayananBloodRequest` (baru ada tabel item `LayananBloodRequestItem`) |
| 1115 | KPO (Kajian Penggunaan Obat) | Scaffold dinamis | `/module/1115` | Kajian farmasi klinik dan interaksi obat pasien |
| 1116 | Formulir Antimikroba | Dibangun (sebagian) | `/visits` | Pengendalian dan evaluasi penggunaan antibiotik (PPRA) |

### [12] Pembayaran (14 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1201 | Final Tagihan | Scaffold dinamis | `/module/1201` | Penguncian seluruh biaya tagihan sebelum pembayaran kasir |
| 1202 | Penerimaan Uang Muka (Deposit) | Scaffold dinamis | `/module/1202` | Penerimaan deposit uang muka pasien rawat inap |
| 1203 | Pengembalian Uang Muka | Scaffold dinamis | `/module/1203` | Kelebihan pembayaran dan pengembalian sisa deposit |
| 1204 | Piutang Pasien | Scaffold dinamis | `/module/1204` | Pencatatan sisa piutang pasien atau penjamin perusahaan |
| 1205 | Pelunasan Piutang | Scaffold dinamis | `/module/1205` | Pembayaran dan pelunasan tagihan piutang tertunda |
| 1206 | Pembayaran Non Tunai | Scaffold dinamis | `/module/1206` | Transaksi EDC, Debit/Kredit, QRIS, dan Transfer Bank |
| 1207 | Diskon Tagihan | Scaffold dinamis | `/module/1207` | Pemberian potongan tarif RS atau diskon jasa dokter |
| 1208 | Pembatalan Tagihan Final | Scaffold dinamis | `/module/1208` | Buka kembali tagihan yang terkunci untuk penyesuaian |
| 1209 | Transaksi Kasir | Dibangun | `/invoices` | Loket penerimaan pembayaran kasir utama |
| 1211 | Batal Gabung Tagihan | Scaffold dinamis | `/module/1211` | Pemisahan invoice billing pasien dan keluarga |
| 1212 | Penjamin Tagihan | Scaffold dinamis | `/module/1212` | Penetapan porsi penjamin BPJS, Asuransi, dan Iur Bayar |
| 1213 | Pencetakan Kuitansi | Scaffold dinamis | `/module/1213` | Pencetakan kuitansi resmi lunas dan rincian biaya perawatan |
| 1214 | Transaksi Penjualan Apotek | Scaffold dinamis | `/module/1214` | Kasir pembayaran penjualan obat bebas dan resep luar |
| 1215 | Pembatalan Permintaan Layanan | Scaffold dinamis | `/module/1215` | Batal order layanan yang belum diproses |

### [13] Rekam Medis (19 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1301 | Pencetakan Lembaran RM | Scaffold dinamis | `/module/1301` | Cetak seluruh lembaran riwayat rekam medis pasien |
| 1302 | Anamnesis Pasien | Sebagian | `/visits` (tab "Anamnesis") | Keluhan Utama (`ChiefComplaint`) + Riwayat 5-narasi (`Anamnesis`) + Alergi patient-scoped (`Allergy`: kategori/derajat/reaksi, nonaktifkan) dalam satu tab; +filter `visit_id` di ChiefComplaint & Anamnesis. Belum: SNOMED CT, Edukasi/Riwayat Obat terstruktur |
| 1303 | Pemeriksaan TTV & Fisik | Dibangun | `/visits` (tab "TTV & Fisik") | TTV lengkap (BB/TB/suhu/nadi/napas/TD/LiLA/SpO2) + checklist fisik 9 region tubuh (Kepala-Leher-TulangBelakang-Dada-Perut-Ekstremitas Kulit/Gerak-Genitalia-Anus); append-only `GeneralExamination`+`PhysicalExamination` |
| 1304 | Penilaian / Assessment | Sebagian | `/visits` (tab "Penilaian") | Skala terstruktur per kunjungan: Skala Nyeri (NRS/Wong-Baker/FLACC/CRIES, skor 0-10) + Risiko Jatuh Morse (6 item, total auto, LOW/MOD/HIGH). Belum: Humpty Dumpty, Barthel, skrining gizi, dekubitus |
| 1305 | Diagnosis ICD-10 & 9CM | Dibangun | `/diagnosis-codes` | Koding diagnosis WHO ICD-10 dan prosedur medis ICD-9CM |
| 1306 | Penandaan Gambar Anatomi | Scaffold dinamis | `/module/1306` | Marking visual luka / lesi pada template diagram anatomi |
| 1307 | Perencanaan Medis | Sebagian | `/visits` (tab "Rencana") | Plan & terapi append-only (`MedicalRecordPlanAndTherapy`): asesmen, rencana (wajib), jenis terapi, target tanggal, status active/completed/revised, DPJP (`doctors`). Belum: feed ke CPPT |
| 1308 | CPPT (SOAP Terintegrasi) | Dibangun | `/clinical-notes` | Catatan Perkembangan Pasien Terintegrasi (Subjective, Objective, Assessment, Plan) |
| 1309 | Penerbitan Surat Medis | Sebagian | `/visits` (tab "Surat Medis") | Surat Keterangan Sakit (`MedicalRecordSickLeaveCertificate`): nomor+dokter+rentang istirahat (durasi auto)+diagnosis. Belum: surat Opname/Kelahiran/HD/Sehat, TTE, cetak PDF |
| 1310 | Resume Medis Pulang | Sebagian | `/visits` (tab "Resume Medis") | Discharge summary append-only (`MedicalRecordDischargeSummary`): Dx masuk/keluar (dari diagnosis kunjungan), ringkasan terapi, keadaan pulang, tindak lanjut, obat pulang, DPJP. Belum: tarik dari CPPT, cetak PDF |
| 1311 | Keperawatan & Kebidanan | Scaffold dinamis | `/module/1311` | Asuhan keperawatan (SDKI, SLKI, SIKI) dan catatan bidan |
| 1312 | Triage Medis IGD | Sebagian | `/visits` (tab "Triage") | Skoring triase 5 tingkat ATS/ESI append-only (`MedicalRecordTriage`): level + keluhan + penilai. Belum: TTV terintegrasi, tampil IGD-only |
| 1313 | Rekonsiliasi Obat 3-Tahap | Scaffold dinamis | `/module/1313` | Rekonsiliasi obat saat admisi, transfer ruangan, dan pulang |
| 1314 | Pemantauan Kritis | Scaffold dinamis | `/module/1314` | Lembar observasi pasien intensif (ICU, ICCU, NICU) |
| 1315 | Tindakan / Terapi Khusus | Scaffold dinamis | `/module/1315` | Laporan operasi, hemodialisa, kemoterapi, dan endoskopi |
| 1316 | Hasil MCU | Scaffold dinamis | `/module/1316` | Kompilasi laporan Medical Check-Up terpadu |
| 1317 | Form Registry SatuSehat | Scaffold dinamis | `/module/1317` | Pencatatan registry klinis Kemenkes SatuSehat |
| 1318 | Upload Dokumen Eksternal | Scaffold dinamis | `/module/1318` | Unggah berkas rujukan luar dan scan dokumen fisik |
| 1319 | Lembar Transfer Pasien | Sebagian | `/visits` (tab "Transfer") | Serah terima antar ruangan (`MedicalRecordPatientTransferSheet`): dari/ke ruangan, alasan, kondisi pasien, petugas. Belum: status kunci, keterkaitan Konsul, SBAR terstruktur |

### [21] Penjualan (1 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2101 | Penjualan Obat Bebas (OTC) | Scaffold dinamis | `/module/2101` | Entry kasir penjualan obat tanpa resep di apotek |

### [24] Pencarian (1 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2401 | Pencarian Terpadu Pasien | Scaffold dinamis | `/module/2401` | Pencarian nomor RM, NIK, nama, dan riwayat pasien |

### [29] Pelayanan KPO (1 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2901 | Pelayanan Kajian Obat (KPO) | Scaffold dinamis | `/module/2901` | Pelayanan telaah resep dan kajian farmasi klinik |

### [19] Master Data (28 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1901 | Master PPK | Dibangun | `/ppks` | Master Fasilitas Pelayanan Kesehatan & PPK Kemenkes |
| 1902 | Master Pegawai | Dibangun | `/employees` | Master Dokter, Perawat, Nakes, dan Staf RS |
| 1903 | Manajemen Pengguna | Dibangun | `/users` | Kelola akun user, reset password, kunci akun, dan hak akses |
| 1904 | Master Ruangan | Dibangun | `/wards` | Master instalasi, bangsal, poliklinik, dan depo |
| 1905 | Master Tindakan | Dibangun | `/services` | Master prosedur dan tindakan medis rumah sakit (kategori dari Master Referensi `Kategori Tindakan`) |
| 1906 | Master Kategori | Dibangun | `/item-categories` | Kategori barang medis, non medis, dan tindakan |
| 1907 | Master Penyedia | Dibangun | `/suppliers` | Master PBF, distributor obat, dan supplier alkes |
| 1908 | Master Barang | Dibangun | `/items` | Katalog obat, formularium RS, alat kesehatan, dan BHP |
| 1909 | Master Paket | Dibangun | `/packages` | Paket pelayanan MCU dan paket tindakan |
| 1910 | Master Paket Tindakan Farmasi | Dibangun | `/pharmacy-packages` | Paket obat dan tindakan farmasi terpadu |
| 1911 | Group / Jenis Pemeriksaan | Dibangun | `/examination-groups` | Grouping parameter lab (Hematologi, Kimia) & radiologi |
| 1912 | Master Tarif | Dibangun | `/service-tariffs` | Tarif layanan, komponen jasa medis, sarana, dan RS |
| 1913 | Master Margin Obat | Scaffold dinamis | `/module/1913` | Konfigurasi persentase margin harga jual obat apotek |
| 1914 | Penjamin Ruangan | Scaffold dinamis | `/module/1914` | Mapping penjamin yang diterima per ruangan/poliklinik |
| 1915 | Master BPJS | Scaffold dinamis | `/module/1915` | Konfigurasi parameter bridging BPJS VClaim & Antrean |
| 1916 | Master Negara | Dibangun | `/countries` | Referensi negara kewarganegaraan pasien |
| 1917 | Master Wilayah | Dibangun | `/regions` | Master Provinsi, Kabupaten/Kota, Kecamatan, Kelurahan |
| 1918 | Master Referensi | Dibangun | `/references` | Master Referensi generik — 328 kategori/3973 nilai diimpor dari legacy (skala penilaian, jenis tagihan, kategori tindakan, dll); 8 kategori demografi pasien (Agama/Jenis Kelamin/dll) tetap FK dedicated terpisah di `/genders`, `/professions`, `/religions` |
| 1919 | Master Kemenkes | Scaffold dinamis | `/module/1919` | Mapping kode standar registrasi RS Kementerian Kesehatan |
| 1920 | Master Mapping | Dibangun | `/examination-group-mappings` | Mapping kode penunjang lab, radiologi, dan sistem luar |
| 1921 | Master Pengaturan (Profil RS) | Dibangun | `/institution` | Identitas resmi faskes, kode PPK, direktur, logo, dan kop |
| 1922 | Penjamin Rumah Sakit | Scaffold dinamis | `/module/1922` | Master perusahaan asuransi, instansi rekanan, dan BPJS |
| 1923 | Master Template Anatomi | Dibangun | `/anatomy-templates` | Katalog gambar dan diagram penandaan anatomi medis |
| 1924 | Rekening RS | Dibangun | `/bank-accounts` | Master rekening bank penerimaan pembayaran rumah sakit |
| 1925 | Master Farmasi | Scaffold dinamis | `/module/1925` | Retriksi resep obat DPJP, golongan obat, dan aturan depo |
| 1926 | Master Non Pegawai | Scaffold dinamis | `/module/1926` | Data pihak ketiga, konsultan, dan mitra lepas RS |
| 1927 | Layanan Lainnya | Dibangun | `/other-services` | Tarif sewa ambulans, kamar jenazah, dan penunjang non-medis |
| 1928 | Pengaturan Rekam Medis | Scaffold dinamis | `/module/1928` | Penomoran Rekam Medis, masa retensi berkas, dan privasi EMR |

### [14] Laporan (12 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1401 | Rekapitulasi Laporan (RL SIRS) | Scaffold dinamis | `/module/1401` | Formulir Laporan RL 1.1 s/d RL 5.4 Standar Kemenkes RI |
| 1402 | Laporan Pengunjung | Scaffold dinamis | `/module/1402` | Laporan demografi dan sebaran pengunjung baru/lama |
| 1403 | Laporan Kunjungan | Scaffold dinamis | `/module/1403` | Laporan kunjungan rawat jalan, rawat inap, dan IGD |
| 1404 | Laporan Layanan | Scaffold dinamis | `/module/1404` | Rekapitulasi volume tindakan medis per instalasi |
| 1405 | Laporan Penerimaan Kasir | Scaffold dinamis | `/module/1405` | Rekap setoran harian kasir dan metode pembayaran |
| 1406 | Laporan Jasa Pelayanan | Scaffold dinamis | `/module/1406` | Distribusi dan perhitungan jasa medis nakes |
| 1407 | Laporan Inventory | Scaffold dinamis | `/module/1407` | Laporan mutasi, fast moving, dan nilai perputaran obat |
| 1408 | Laporan Rekam Medis | Scaffold dinamis | `/module/1408` | Statistik mortalitas, morbiditas, dan indeks penyakit |
| 1409 | Laporan Pendapatan Keuangan | Scaffold dinamis | `/module/1409` | Laporan pendapatan RS per penjamin BPJS dan Umum |
| 1410 | Laporan Kegiatan RI & RD | Scaffold dinamis | `/module/1410` | Sensus harian rawat inap dan rawat darurat |
| 1411 | Monitoring Pelayanan | Scaffold dinamis | `/module/1411` | Monitoring durasi waktu pelayanan antrean pasien |
| 1412 | Laporan Kinerja Dokter & Perawat | Scaffold dinamis | `/module/1412` | Produktivitas jam pelayanan dan beban kerja nakes |

### [15] Dashboard (11 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 1501 | Indikator Efisiensi RS (BOR/LOS) | Dibangun | `/dashboard` | BOR, LOS, TOI, BTO, GDR, NDR indikator kinerja RS |
| 1502 | Dashboard Pengunjung | Scaffold dinamis | `/module/1502` | Grafik tren pertumbuhan pengunjung pasien baru/lama |
| 1503 | Dashboard Kunjungan | Scaffold dinamis | `/module/1503` | Grafik kunjungan poli rawat jalan, rawat inap, dan IGD |
| 1504 | Dashboard Rawat Inap | Scaffold dinamis | `/module/1504` | Grafik pasien masuk, keluar, pindah, dan meninggal |
| 1505 | Dashboard Laboratorium | Scaffold dinamis | `/module/1505` | Volume pemeriksaan laboratorium dan tren parameter |
| 1506 | Dashboard Radiologi | Scaffold dinamis | `/module/1506` | Volume pemeriksaan foto rontgen, CT-Scan, USG |
| 1507 | 10 Kasus Terbesar Diagnosa (ICD-10) | Scaffold dinamis | `/module/1507` | 10 peringkat penyakit terbanyak rumah sakit |
| 1508 | 10 Kasus Terbesar INACBG | Scaffold dinamis | `/module/1508` | 10 besar klaim paket INA-CBG BPJS tertinggi |
| 1509 | Dashboard Pendapatan | Scaffold dinamis | `/module/1509` | Monitoring realisasi pendapatan kasir dan piutang |
| 1510 | Dashboard Klaim INA-CBG | Scaffold dinamis | `/module/1510` | Status verifikasi dan pengajuan klaim BPJS Kesehatan |
| 1511 | Waktu Layanan Rawat Darurat | Scaffold dinamis | `/module/1511` | Response time emergency IGD penanganan dokter |

### [20] Informasi (3 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2001 | Informasi Pengunjung | Scaffold dinamis | `/module/2001` | Papan informasi publik dan jadwal praktik dokter |
| 2002 | Ruang Tempat Tidur Kosong | Scaffold dinamis | `/module/2002` | Display publik ketersediaan bed per kelas rawat inap |
| 2003 | Informasi Pasien Rawat Inap | Scaffold dinamis | `/module/2003` | Pencarian lokasi kamar pasien dirawat untuk keluarga |

### [22] Tempat Tidur (3 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2201 | Reservasi Kamar | Scaffold dinamis | `/module/2201` | Pemesanan dan booking tempat tidur rawat inap |
| 2202 | Identitas Pasien Kamar | Scaffold dinamis | `/module/2202` | Visualisasi denah bed dan identitas pasien di kamar |
| 2203 | Antrean Tempat Tidur | Scaffold dinamis | `/module/2203` | Antrean pasien menunggu kamar perawatan kosong |

### [23] Inventory (6 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2301 | Permintaan Barang Depo | Scaffold dinamis | `/module/2301` | Pengajuan mutasi obat dan BHP antar depo farmasi |
| 2302 | Penerimaan Supplier | Scaffold dinamis | `/module/2302` | Penerimaan barang masuk dari faktur distributor PBF |
| 2303 | Pengiriman Barang | Scaffold dinamis | `/module/2303` | Penerbitan surat jalan pengiriman antar unit RS |
| 2304 | Stok Opname Gudang & Depo | Scaffold dinamis | `/module/2304` | Penyesuaian stok fisik dan audit selisih persediaan |
| 2305 | Rekanan / Supplier | Dibangun | `/suppliers` | Daftar vendor penyedia obat, reagen lab, dan BHP |
| 2306 | Distribusi Barang | Scaffold dinamis | `/module/2306` | Penyaluran logistik umum dan ATK ke unit administrasi |

### [25] Integrasi (2 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2501 | Bridging BPJS (VClaim & MJKN) | Scaffold dinamis | `/module/2501` | Koneksi API VClaim, Surat Eligibilitas Peserta (SEP) |
| 2502 | Bridging LIS Laboratorium | Scaffold dinamis | `/module/2502` | Koneksi dua arah mesin analyzer laboratorium RS |

### [26] Monitoring (10 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2601 | Nilai Kritis Laboratorium | Scaffold dinamis | `/module/2601` | Alert darurat hasil laboratorium di bawah/atas batas kritis |
| 2602 | Monitoring Hasil Lab | Scaffold dinamis | `/module/2602` | Status pengerjaan spesimen darah dan cairan tubuh |
| 2603 | Monitoring Hasil Radiologi | Scaffold dinamis | `/module/2603` | Status pengerjaan foto dan ekspertise radiologi |
| 2604 | Monitoring Konsul MPP | Scaffold dinamis | `/module/2604` | Konsultasi Manajer Pelayanan Pasien (Case Manager) |
| 2605 | Jadwal Kontrol Pasien | Sebagian | `/visits` (tab "Jadwal Kontrol") | Surat rencana kontrol ulang (`MedicalRecordControlSchedule`): tanggal, poli dituju, alasan, petugas. Status scheduled/completed/cancelled. Belum: bridging VClaim BPJS, cetak PDF |
| 2606 | Surat Perencanaan Rawat Inap (SPRI) | Scaffold dinamis | `/module/2606` | Monitoring penerbitan SPRI untuk rujukan rawat inap |
| 2607 | Pasien Dirawat (SIKEPO) | Scaffold dinamis | `/module/2607` | Sistem Informasi Ketersediaan Ruangan & Pasien Rawat |
| 2608 | Pasien Meninggal | Scaffold dinamis | `/module/2608` | Pencatatan surat keterangan kematian dan administrasi |
| 2609 | Status Klaim dari BPJS | Scaffold dinamis | `/module/2609` | Monitoring approval dan dispute klaim BPJS Kesehatan |
| 2690 | Database Session | Scaffold dinamis | `/module/2690` | Monitoring koneksi aktif dan session server MySQL |

### [27] Akses API (3 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2701 | API Pegawai | Scaffold dinamis | `/module/2701` | Endpoint API data pegawai untuk sistem eksternal |
| 2702 | API Pengguna | Scaffold dinamis | `/module/2702` | Endpoint API autentikasi dan otorisasi pengguna |
| 2703 | API Group Jenis Pemeriksaan | Scaffold dinamis | `/module/2703` | Endpoint API master group pemeriksaan LIS & RIS |

### [28] Logs (1 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 2801 | Audit Log Tanda Tangan Elektronik (TTE) | Scaffold dinamis | `/module/2801` | Audit trail tanda tangan elektronik digital EMR BSrE |

### [30] Berkas (2 modul)

| Kode | Nama Modul | Status | Rute Frontend | Deskripsi |
|---|---|---|---|---|
| 3001 | Manajemen Berkas Klaim | Scaffold dinamis | `/module/3001` | Penggabungan dokumen digital rekam medis untuk klaim |
| 3002 | Monitoring Kelengkapan Berkas Klaim | Scaffold dinamis | `/module/3002` | Verifikasi kelengkapan resume, lab, rad, dan billing |
