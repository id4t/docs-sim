# Alur Kerja — Peta Alur SIMPel Lama → SIMGOS

> **ARSIP AUDIT LEGACY — bukan spesifikasi target.** Temuan di sini berguna untuk memahami SIMPel, tetapi keputusan aktif berada di [`../README.md`](../README.md) dan dokumen yang ditautkannya.

**Status:** Riset menyeluruh 18 domain / 147 modul katalog SIMGOS, hasil reverse-engineering `app.js` (ExtJS Classic, ~5.5MB minified + beberapa package terpisah `pembayaran.js`/`penjualan.js`/`rekammedis.js`/`informasi.js`), source PHP `webservice/module/*`, dan skema database production `aplikasi`/`bpjs`/`inacbg`/`inventory`/`lis`/`master`/`layanan`/`pendaftaran` (read-only).
**Tujuan:** dokumen ini adalah basis desain untuk membangun ulang seluruh alur kerja SIMGOS mengikuti pola SIMpel — bukan menebak, tapi berdasar bukti kode/DB konkret. Lengkapi `DOKUMEN-PRODUK-2026-08-18.md` (ruang lingkup), `PANDUAN-UI-UX-LAMA.md` (pola visual), `CATATAN.md` (catatan implementasi teknis).
**Metodologi:** 6 agent riset paralel, masing-masing menangani 3 domain, membaca `app.js` via Python `str.find()`/slicing (bukan regex `.{0,N}` di seluruh file — itu yang pernah bikin server OOM), cross-check ke DB production read-only, dan membandingkan ke folder `RME-Frontend/src/features` yang sudah dibangun.

---

## 1. Temuan Arsitektur Pemersatu — Hierarki Workspace Bersarang

Ini adalah temuan **paling penting** dan konsisten muncul di semua 6 laporan — wajib dipahami sebelum membaca detail per domain.

### 1.1 Mekanisme dasar: `createWorkspace` / `createTab`

```js
createWorkspace(rec, iconCls, closable, className, itemId, title, extra, listeners) {
  var cfg = {itemId, viewModel:{data:{record:rec}}, title, closable, iconCls, listeners};
  Ext.apply(cfg, extra);
  this.createTab(rec, className, cfg, callback);
}
createTab(rec, className, cfg, callback) {
  var existing = this.getView().getComponent(cfg.itemId);
  if (existing != null) { this.getView().setActiveTab(existing); return; } // fokus ulang, TIDAK dobel
  var tab = Ext.create(className, cfg);
  this.getView().add(tab);
  this.getView().setActiveTab(tab);
  if (callback) callback(tab);
}
```

Prinsip: tab MDI di-key per **itemId** (biasanya turunan ID record, mis. `psn-{NORM}`). Klik entitas yang sama dari mana pun di aplikasi akan **fokus ulang ke tab yang sudah terbuka**, bukan membuka duplikat.

### 1.2 Hierarki bersarang (ditemukan lintas 6 laporan, disatukan di sini)

```
pasien.Workspace (itemId: "psn-"+NORM)          — identitas & administrasi pasien
  └─ tab "Pendaftaran"                            — pendaftaran.tujuan.Form (RJ/IGD/RI/Lab/Rad = 1 form yg sama)
  └─ tab "History", "Alamat", "Kontak", "Keluarga"
  └─ (dari sini/dari widget dashboard) →
kunjungan.Workspace (idClassName TETAP, bukan per-record) — WORKLIST/antrean kunjungan aktif ("Kunjungan Pasien"/"My Pasien")
  └─ klik 1 baris kunjungan →
layanan.Workspace (itemId dari NOMOR kunjungan)  — "Detil Kunjungan", workspace kerja utama harian
  └─ child tab-panel layanan.Links (dinamis sesuai privilege & jenis kunjungan):
       ├─ tab "Rekam Medis" → rekammedis.Workspace (package terpisah, routeId "13", 19 sub-modul 1301-1319 sbg route internal)
       ├─ tab "Order" (Lab/Rad/Resep/Konsul — 1103)
       ├─ tab "Tindakan Medis" (1102)
       ├─ tab "Pemakaian BHP" (1111), "Bon Sisa" (1112)
       ├─ tab "Riwayat" (1106)
       └─ tab "Kpo Dokter"/"Kpo Perawat" (2901, bila relevan)
pembayaran.tagihan.Workspace (itemId per tagihan) — dibuka dari daftar tagihan
  └─ child tab-panel pembayaran.tagihan.Links (BUKAN top-level workspace lagi):
       ├─ tab "Rincian", "Deposit"(1202/1203), "Penjamin"(1212), "Piutang"(1204/1205), "Non Tunai"(1206), "Discount"(1207), "Info Pembayaran"(1213)
informasi.ruangkamartidur.Workspace                — display board + aksi CRUD ringan (2201-2203), BUKAN per-record
dashboard.*.Workspace (11 modul)                   — workspace-per-TIPE dashboard (key = nama modul, bukan ID data)
master.*.Workspace (28 modul)                       — CRUD grid/list tunggal biasa, TIDAK per-record
```

### 1.3 Aturan pola per domain (ringkasan keputusan arsitektur)

| Pola | Domain yang pakai | Ciri |
|---|---|---|
| **Workspace-per-record, MDI top-level** | Pasien (10), Kunjungan/Layanan (11) sbg entry, Tagihan (12) sbg entry | `itemId` dari ID record, klik ulang = fokus tab lama |
| **Workspace-per-record, tapi NESTED (tab di dalam tab)** | Rekam Medis (13, di dalam Layanan), sub-modul Pembayaran (12, di dalam Tagihan) | Bukan MDI top-level, child tab-panel di dalam parent workspace |
| **Worklist/antrean statis (idClassName tetap, bukan per-record)** | Penerimaan Ruangan 1101, KPO Farmasi 2901 (sisi apoteker) | Daftar yang di-refresh, klik baris → buka workspace-per-record entitas lain |
| **CRUD grid/list generik (bukan workspace-per-record sama sekali)** | Master Data (19, 28 modul) | Window/tab tunggal per modul, form dialog di atasnya |
| **Live-display board (auto-refresh, sebagian besar read-only)** | Informasi (20), Tempat Tidur (22) | Dirancang untuk TV/monitor publik, sebagian embed aksi CRUD ringan |
| **Workspace-per-dokumen (siklus draft→final)** | Inventory (23) | Field STATUS enum eksplisit (Batal/Proses/Final) |
| **Backend murni (tidak ada UI ExtJS)** | Integrasi (25), Akses API (27), sebagian Monitoring (26) | REST/cron job, status ditempel di dokumen domain asal |
| **DIRANCANG di DB tapi TIDAK PERNAH diimplementasikan sbg UI** | Laporan (14) | `master.jenis_laporan` lengkap, tapi 0 kemunculan class ExtJS di app.js — akses laporan sebenarnya lewat tombol Cetak tersebar di tiap modul, bukan menu terpusat |

**Implikasi desain paling besar untuk SIMGOS**: karena domain Layanan (16 modul, sepenuhnya belum dibangun) memakai hierarki nested terdalam (`pasien` → `kunjungan` → `layanan` → `rekammedis`/sub-modul lain), tim harus memutuskan sejak awal: **replikasi hierarki MDI bertingkat ini, atau sederhanakan jadi navigasi route standar React** — keputusan ini berdampak ke puluhan modul sekaligus, bukan hanya satu.

### 1.4 Prinsip mutabilitas data (append-only vs editable) yang ditemukan berulang

- **Gerbang "Final Kunjungan"** (`kunjungan.FINAL_HASIL=1`) mengunci **seluruh** sub-form Rekam Medis sekaligus (bukan lock per-form) — beda dari pendekatan SIMGOS saat ini yang granular per-entitas (CPPT/Diagnosis masing-masing append-only sendiri).
- **Pola "pembatalan via record beralasan"** (bukan hard delete) berulang di banyak domain: `pembatalandocument` (1318), `pendaftaran-pembatalan-final_hasil-form` (Batal Final), `TagihanPembatalan` (1208) — cocok dengan filosofi audit-trail SIMGOS, layak dijadikan pola standar.
- **CPPT counter-signing** (`VerifikasiCPPT`, fully append-only): PPA menulis catatan, DPJP memverifikasi lewat record terpisah (bukan edit status di baris yang sama) — pola paling relevan untuk direplikasi di modul RM lain.
- **Audit Log TTE (2801)**: preseden append-only paling ketat di seluruh legacy (create+fetch saja, semua mutasi 405) — cocok jadi acuan pola "system log" generik.
- **Diagnosis ICD-10 (`medicalrecord.diagnosa`)**: create-only/upsert, **tidak bisa delete via REST sama sekali** — SIMGOS saat ini ("boleh delete tapi tidak update") sedikit berbeda tapi searah.

---

## 2. Ringkasan Status Implementasi vs SIMGOS (lintas 18 domain)

| Domain | Modul | Status SIMGOS |
|---|---|---|
| [10] Pendaftaran | 1001 Pasien Baru | Sudah (`GeneralPatient`) |
| [10] Pendaftaran | 1002-1006 (RJ/IGD/RI/Lab/Rad) | Sebagian (`PendaftaranRegistration`+`PendaftaranVisit`, belum bedakan jenis ruangan & cetak) |
| [10] Pendaftaran | 1007-1014 | Belum |
| [11] Layanan | 1101 Penerimaan Ruangan | Sebagian (worklist `VisitListPage`, lihat detail §3) |
| [11] Layanan | 1102 Tindakan Medis | Sebagian (tab "Tindakan" di `VisitWorkspacePage`, lihat detail §3) |
| [11] Layanan | 1103,1104,1105,1106,1108,1109,1110,1111,1112,1114,1116 | Sebagian/Sudah (tab di `VisitWorkspacePage`, detail §3) |
| [11] Layanan | 1107,1113,1115 | Belum |
| [12] Pembayaran | 1201 Final Tagihan | Sebagian (auto-lock, tanpa guard legacy) |
| [12] Pembayaran | 1206 Non Tunai | Sebagian (`payment_method` enum ada, tanpa detail EDC/bank) |
| [12] Pembayaran | 1202-1205, 1207-1209, 1211-1215 | Belum |
| [13] Rekam Medis | 1302 Anamnesis | Sebagian (tab "Anamnesis": Keluhan Utama+Riwayat+Alergi di `VisitWorkspacePage`) |
| [13] Rekam Medis | 1304 Penilaian | Sebagian (tab "Penilaian": Skala Nyeri + Risiko Jatuh Morse) |
| [13] Rekam Medis | 1305 Diagnosis | Sebagian (`MedicalRecordDiagnosis`+`GeneralDiagnosisCode`) |
| [13] Rekam Medis | 1307 Perencanaan Medis | Sebagian (tab "Rencana", `MedicalRecordPlanAndTherapy` append-only) |
| [13] Rekam Medis | 1308 CPPT | Sudah (`MedicalRecordClinicalNote`, append-only lebih ketat dari legacy) |
| [13] Rekam Medis | 1309 Surat Medis | Sebagian (tab "Surat Medis", `MedicalRecordSickLeaveCertificate` — baru surat sakit) |
| [13] Rekam Medis | 1310 Resume Medis | Sebagian (tab "Resume Medis", `MedicalRecordDischargeSummary` append-only) |
| [13] Rekam Medis | 1312 Triage IGD | Sebagian (tab "Triage", `MedicalRecordTriage` append-only) |
| [13] Rekam Medis | 1319 Lembar Transfer | Sebagian (tab "Transfer", `MedicalRecordPatientTransferSheet`) |
| [13] Rekam Medis | 1301, 1306, 1311, 1313-1318 | Belum |
| [14] Laporan | Semua 12 modul | Belum (legacy sendiri **tidak pernah** punya UI utuh — perlu desain baru dari nol berbasis katalog `jenis_laporan`+engine Jasper) |
| [15] Dashboard | 1501 Indikator | Sudah (`DashboardPage`, ter-wire `/dashboard`) |
| [15] Dashboard | 1502-1511 | Belum |
| [19] Master Data | 1902,1903,1904,1905,1916,1917,1918(sebagian),1921 | Sudah |
| [19] Master Data | 19 modul lain | Belum |
| [20] Informasi | Semua 3 modul | Belum |
| [21] Penjualan | 2101 | Belum |
| [22] Tempat Tidur | Semua 3 modul | Belum |
| [23] Inventory | Semua 6 modul | Belum |
| [24] Pencarian | 2401 | Sebagian (`PatientPicker` lokal, bukan global search bar) |
| [25] Integrasi | Semua 2 modul | Belum |
| [26] Monitoring | Semua 10 modul | Belum |
| [27] Akses API | Semua 3 modul | N/A (bukan halaman UI di legacy — konsep API governance, bukan fitur end-user) |
| [28] Logs | 2801 | Belum |
| [29] Pelayanan KPO | 2901 | Belum |
| [30] Berkas | Semua 2 modul | Belum |

**Total real (per 2026-08-23, update terakhir)**: **50 dari 147 modul** katalog yang sudah punya implementasi kerja nyata di SIMGOS, sinkron dengan `DOKUMEN-PRODUK-2026-08-18.md` §4. Bertambah 1303 Pemeriksaan TTV & Fisik (tab "TTV & Fisik" di `VisitWorkspacePage`, `GeneralExamination`+`PhysicalExamination` append-only: TTV lengkap + checklist 9 region tubuh). Sebelum itu bertambah 1304 Penilaian / Assessment (tab "Penilaian" — Skala Nyeri+Morse). Sebelum itu bertambah 1309 Penerbitan Surat Medis (tab "Surat Medis", Surat Keterangan Sakit). Sebelum itu bertambah 1312 Triage Medis IGD (tab "Triage"), 1319 Lembar Transfer Pasien (tab "Transfer"), 1307 Perencanaan Medis (tab "Rencana"), 1310 Resume Medis Pulang (tab "Resume Medis"), 1302 Anamnesis Pasien (tab "Anamnesis") — semua di domain 13, plus di domain 11 dilengkapi 1103 Konsul, 1114 Layanan Tambahan (O₂), 1112 Bon Sisa. Total 12 modul baru + konversi tab Diagnosis (1305) shadcn→Material + pengelompokan 2-tingkat 21 tab (Klinis/Order/Farmasi/Administrasi) di sesi 2026-08-23. Masing-masing plus perbaikan filter index backend yang sebelumnya hilang. Sebelum itu bertambah 1116 Formulir Antimikroba (tab "Antimikroba" baru di `VisitWorkspacePage`, plus halaman baru `/antibiotic-restrictions` untuk Master Restriksi Antibiotik) di update terbaru — lihat §3 domain 11 dan koreksi §6.4 di bawah. Sebelum itu bertambah 1110 Kelahiran (VK Bersalin) — tab "Kelahiran" baru di `VisitWorkspacePage`, lihat §3 domain 11 untuk detail. Sebelum itu bertambah 2: 1108 Pembatalan Layanan (sudah otomatis tercakup lewat aksi ubah-status "Batalkan"/dropdown status yang sudah ada di tab Tindakan/Lab/Radiologi — tidak perlu kode baru) dan 1109 Final/Selesai Pelayanan (tombol "Final Pelayanan" baru di header `VisitWorkspacePage`, pilih Cara Keluar dari Master Referensi, memicu `discharged_at`/`final_outcome`/`status` sekaligus). Sebelum itu bertambah 1106 History Layanan Pasien (tab "Riwayat" di `VisitWorkspacePage`) — lihat §3 domain 11 untuk detail scope. Sebelum itu bertambah 1105 Pembacaan Ekspertise (tab "Radiologi" di `VisitWorkspacePage`) — lihat §6.9 untuk detail field-parity. Sebelum itu bertambah 1104 Feedback/Hasil Pemeriksaan (tab "Lab" di `VisitWorkspacePage`) — lihat §6.9 untuk detail field-parity. Sebelum itu bertambah 1111 Pemakaian BHP Ruangan (`/ward-stock-usage`) — lihat §6.9 untuk detail & kontras dengan modul yang mismatch. Sebelumnya bertambah 2 dari 29: 2305 Rekanan/Supplier (tautan ke `/suppliers`, entitas sama dengan 1907) dan 1303 Pemeriksaan TTV & Fisik (sebagian — tab "Tanda Vital" baru di `VisitWorkspacePage`, lihat detail di §3 domain 13). Sebelum itu, bertambah dari 15 di sesi ini dengan batch Master Data "penunjang" (backend semua sudah matang/pre-existing, tinggal dibangun frontend-nya + verifikasi langsung lewat REST API ke backend nyata, bukan mock): 1901 Master PPK (`/ppks`, form dedicated 18 field karena kompleks — identitas/alamat/kontak/masa berlaku/BPJS), 1906 Master Kategori (`/item-categories`), 1907 Master Penyedia (`/suppliers`), 1909 Master Paket (`/packages`), 1923 Master Template Anatomi (`/anatomy-templates`), 1924 Rekening RS (`/bank-accounts`), 1927 Layanan Lainnya (`/other-services`) — enam yang terakhir pakai komponen generik baru `src/shared/components/SimpleCrudPage.tsx` (list+dialog CRUD table-driven, dipakai untuk modul flat dengan field sederhana). Sisanya berkisar dari "belum tersentuh sama sekali" hingga "legacy-nya sendiri tidak pernah selesai dibangun" (khusus domain 14).

**Catatan arsitektur — `SimpleCrudPage` (baru)**: komponen generik `src/shared/components/SimpleCrudPage.tsx` menerima `api` (list/create/update/remove), daftar `fields` (untuk dialog form) dan `columns` (untuk tabel), lalu merender halaman CRUD lengkap (tabel + paginasi + dialog tambah/ubah + hapus) tanpa perlu file page terpisah per modul. Dipakai untuk 6 dari 7 modul batch ini (semua kecuali PPK, yang dapat form dedicated karena 18 field terlalu banyak untuk dialog generik). **Catatan penting**: tiap modul backend punya bentuk respons paginasi yang BEDA (raw Eloquent `paginate()` vs `Resource::collection` — lihat pola yang sama di §1 catatan sebelumnya), jadi tiap `api.ts` modul WAJIB menormalisasi `list()` ke bentuk `{data, currentPage, lastPage}` sebelum diteruskan ke `SimpleCrudPage` — jangan asumsikan semua modul backend seragam, selalu cek controller-nya dulu.

**Tambahan (setelah batch di atas)**: 1916 Master Negara (`/countries`, reuse `SimpleCrudPage` + `api.ts`/`types.ts` yang sudah ada sebelumnya dari picker Patient form, tinggal ditambah halaman) dan 1917 Master Wilayah (`/regions`, browser 4-kolom cascading Provinsi→Kabupaten/Kota→Kecamatan→Kelurahan, **read-only** karena datanya berasal dari paket `laravolt/indonesia` — bukan tabel yang dimaksud untuk diedit user, beda pola dari modul master lain). **Temuan penting saat verifikasi**: tabel region (`indonesia_provinces`/`cities`/`districts`/`villages`, prefixed sesuai `config('laravolt.indonesia.table_prefix')`) sudah ter-migrate tapi **kosong total** (0 baris) — bukan bug kode, tapi data referensi baku yang belum di-seed di environment ini. Diperbaiki dengan menjalankan `php artisan laravolt:indonesia:seed` (command bawaan paket) → 38 provinsi, 514 kab/kota, 7285 kecamatan, 83762 kelurahan/desa. Ini **bukan** pola seeder cross-schema seperti §13 `CATATAN.md` (bukan dari DB legacy SIMpel), tapi seeder resmi paket third-party — dicatat di sini karena jenis gap yang sama (data kosong padahal tabel/kode sudah siap) bisa saja ditemukan lagi di modul master data lain yang belum disentuh.

**Tambahan lagi (lanjutan sesi yang sama)**: 1908 Master Barang (`/items`, dedicated list+form karena 10 field + aksi khusus "Sesuaikan Stok" — backend sengaja memisahkan endpoint `POST /items/{id}/adjust-stock` dari `PUT /items/{id}` biar perubahan kuantitas selalu lewat jalur yang bisa diaudit, bukan numpang di update metadata biasa) dan 1911 Group/Jenis Pemeriksaan (`/examination-groups`, `SimpleCrudPage`). **Konfirmasi arsitektur penting**: dicek juga apakah 1922 "Penjamin Rumah Sakit" (dideskripsikan sebagai "Master perusahaan asuransi, instansi rekanan, dan BPJS") punya tabel master terpisah — TIDAK ADA. Satu-satunya tabel `guarantors` (`PendaftaranGuarantor`) FK ke `registration_id`, field `payer_type` cuma string enum (`self_pay` dll), bukan FK ke entitas perusahaan. Jadi 1913/1914/**1922** bertiga sama-sama butuh migrasi+modul backend baru (tabel master penjamin/asuransi berdiri sendiri) sebelum bisa dibangun frontend-nya dengan benar — bukan modul yang bisa diselesaikan di frontend saja, beda dari kebanyakan modul lain di batch ini yang backend-nya sudah matang duluan.

**Tambahan lagi (ronde ketiga)**: 1912 Master Tarif (`/service-tariffs`, dedicated list+dialog — bukan `SimpleCrudPage` murni karena butuh dua picker relasi: `MdSelect` Tindakan/Layanan dari `GeneralService` dan Kelas Rawat dari `GeneralRoomClass`, plus riwayat tarif per tanggal efektif + nomor SK sesuai field backend `ServiceTariff`) dan 1910 Paket Tindakan Farmasi (`/pharmacy-packages`, `SimpleCrudPage`, field `category` sengaja dibiarkan text bebas bukan dropdown terbatas — backend membatasi ke `obat`/`alkes`/`campuran` via validasi, frontend belum menegakkan constraint yang sama, dicatat sebagai gap kecil kalau nanti ada waktu untuk upgrade `SimpleCrudPage` mendukung field bertipe select). Total modul real sekarang 28 dari 147 (naik dari 15 di awal sesi ini).

**Tambahan lagi (ronde keempat)**: 1920 Master Mapping (`/examination-group-mappings`, dedicated list+dialog dengan `MdSelect` Group Pemeriksaan dari `GeneralExaminationGroupMapping`, memetakan `examination_group_id` ke `mapping_category`+`external_code` bebas teks — untuk keperluan klaim/pelaporan ke sistem luar). Dicek juga **1919 Master Kemenkes** dan **1915 Master BPJS**: tidak ditemukan modul backend yang cocok — yang ada untuk BPJS adalah rangkaian modul integrasi (`BpjsVClaim`, `BpjsAntreanRs`, `BpjsPCare`, dst, ~9 modul) yang sifatnya bridging API eksternal, bukan tabel config CRUD sederhana; dan tidak ada modul `Kemkes*` selain `KemkesBloodType` yang sudah dipakai di form Pasien. Kedua ini **tidak dibangun** di sesi ini — beda kelas masalah dari modul master data biasa (butuh desain integrasi API, bukan sekadar CRUD). Total modul real sekarang 29 dari 147.

**Deferred (belum dibangun, butuh keputusan desain)**: 1913 Master Margin Obat, 1914 Penjamin Ruangan, dan 1922 Penjamin Rumah Sakit — ketiganya FK/bergantung ke tabel `guarantors` yang scoped ke registrasi (`PendaftaranGuarantor`, field `registration_id`/`payer_type`/`member_number`), bukan tabel master perusahaan penjamin/asuransi yang berdiri sendiri. Sebelum dibangun frontend-nya, perlu diputuskan apakah backend perlu tabel master penjamin terpisah, atau modul ini memang dimaksud sebagai konfigurasi per-registrasi (bukan benar-benar "master data").

**Temuan sesi 2026-08-20 — hasil cek legacy SIMpel sebelum lanjut membangun (sesuai kebiasaan tetap: selalu cek layar asli dulu)**:

- **1925 Master Farmasi** — di legacy `master.farmasi.*` ternyata bukan satu layar, tapi **11 submodul terpisah**: `DepoLayanan` (cocok ke `GeneralPharmacyDepot`/`GeneralPharmacyServiceRoom` yang sudah ada backend-nya, belum dibangun frontend), `MappingFrekuensiKategori`, `MappingGolonganPenjamin`, `MappingRetriksiFormularium`, `MappingRetriksiUnitAsal`, `PpnPenjualan`, `RetriksiAntibiotik`, `RetriksiDiagnosa`, `RetriksiDpjp`, `RetriksiHari`, `RetriksiJumlah`, `frekuensiaturanresep`. Selain `DepoLayanan`, **tidak ada satupun tabel backend yang cocok** untuk retriksi/mapping ini di `RME-Backend` saat ini — perlu migrasi+modul baru dulu, bukan sekadar kerjaan frontend. **Belum dibangun.**
- **1926 Master Non Pegawai** — dicari ke seluruh `app.js` (varian pegawai/karyawan/staff/mitra/konsultan/pihak-ketiga) dan **tidak ada layar legacy yang cocok sama sekali**. Kemungkinan entri katalog SIMGOS ini tidak punya padanan di SIMpel (fitur baru/aspirational, bukan re-platform dari yang sudah ada). **Belum dibangun**, butuh klarifikasi dulu apakah ini benar-benar dibutuhkan atau bisa dihapus dari katalog.
- **1928 Pengaturan Rekam Medis** — sama, dicari varian "rekammedis"/"pengaturan" dan **tidak ketemu layar legacy yang cocok**. **Belum dibangun**, alasan sama seperti 1926.

**Temuan penting — `PendaftaranGuarantor` (Penjamin), modul berikutnya yang direncanakan setelah batch Master Data**: dicek `pendaftaran.penjamin.Form` di legacy (`app.js`, class `Ext.cmd.derive("pendaftaran.penjamin.Form", com.Form, ...)`), dan ternyata jauh lebih kompleks dari backend `guarantors` saat ini:
- Form legacy punya field level-atas: `JENIS` (referensi-combo kategori 10 = Jenis Penjamin — Umum/BPJS/Asuransi/dll), `PEGAWAI_JENIS` (referensi-combo kategori 322, untuk tarif pegawai RS sendiri), `PEGAWAI_NIP`, `KENAKAN_TARIF` (referensi-combo kategori 323), `NOPEN`, `NORM`.
- Lalu ada **sub-form yang berganti secara dinamis tergantung `JENIS` terpilih** (container `container-penjamin`), khusus BPJS mencakup seluruh siklus SEP: `buatSep`/`batalkanSep`/`updateSep`/`updateTglPulangSep`/`cetakSep`, `rujukan`/`perpanjanganRujukan`/`cetakRujukan`, `rencanaKontrol`/`cetakRencanaKontrol`, `spri`/`cetakSPRI`, `showHistorySep`, plus `getJadwalDokter`.
- Backend `guarantors` (`PendaftaranGuarantor`) saat ini cuma punya: `registration_id`, `payer_type` (string bebas), `member_number`, `room_class_id`, `reference_letter_number`, `notes`, `status` — jauh dari cukup untuk merepresentasikan field-field BPJS SEP di atas.
- **Kesimpulan**: membangun frontend Guarantor di atas backend saat ini TIDAK akan field-parity dengan legacy untuk kasus BPJS (kasus paling umum/penting di RS Indonesia). Ini butuh keputusan/rework backend dulu (tabel/field SEP terpisah, atau JSON kolom fleksibel per jenis penjamin) sebelum frontend BPJS-nya bisa benar. **Belum dibangun** — didiskusikan dengan user 2026-08-20, keputusan: **jeda dulu, lanjut sesi berikutnya** (belum diputuskan built-simple-dulu vs rework-backend-dulu vs skip-ke-domain-lain).

---

## 3. Detail per Domain

### [10] Pendaftaran (14 modul)

Sumber menu/privilege modul 10 & 11 **tidak** punya `CLASS` terisi di tabel `aplikasi.modules` — ID privilege ini dipakai murni untuk cek hak akses (`xpriv`), bukan pemicu workspace langsung. Form Pendaftaran tertanam sbg tab di dalam `pasien.Workspace`.

**1001 Pasien Baru** — Tombol "Pasien Baru" → `pasien.Workspace` tanpa record → form `pasien-form` kosong. Isi identitas (`pasien.FormIdentitas`: NAMA, GELAR_DEPAN/BELAKANG, TANGGAL_LAHIR, TEMPAT_LAHIR, JENIS_KELAMIN, KEWARGANEGARAAN) → simpan → auto-cetak kartu+barcode (opsional) → lanjut tab Alamat/Kontak/Keluarga di workspace yang sama. Setelah simpan sukses, `createWorkspace` dipanggil ulang dengan `itemId` final `psn-`+NORM baru. Status SIMGOS: **Sudah** (`GeneralPatient`), tapi perlu verifikasi cetak-otomatis & sub-form Kontak/Keluarga lengkap.

**1002-1006 Rawat Jalan/IGD/Rawat Inap/Laboratorium/Radiologi** — **berbagi SATU form**: `pendaftaran.tujuan.Form`, dibedakan lewat kombo `ruangan-selection` (filter `notJenisKunjungan`). Alur: pilih ruangan tujuan → `NOPEN` auto → opsi `RESERVASI`, `IKUT_IBU`/`KUNJUNGAN_IBU` (bayi baru lahir) → simpan → sistem buat `Kunjungan` baru (`RUANGAN` terpilih, `BARU=1`). Setelah simpan: opsi cetak bukti/barcode/tracert/gelang (mendukung 1008). Status SIMGOS: **Sebagian** — belum bedakan jenis ruangan seperti `ruangan-selection`, belum ada cetak otomatis.

**1007 Perubahan Data** — Sub-node: Update Status/Photo/Lock-Unlock Pasien, Penjamin/Cara Bayar, Tujuan, Paket, Tanggal Pendaftaran (dengan histori perubahan tercatat via `PerubahanTanggalKunjungan`), Hapus SEP, Upload General Consent. Status SIMGOS: **Belum**.

**1008 Pencetakan** — Bukan modul berdiri sendiri, aksi cetak menempel di form Pendaftaran/Kunjungan/Pasien, dibedakan cetak BARU vs ULANG × 5 varian dokumen. Status SIMGOS: **Belum**.

**1009 History Pendaftaran** — Kemungkinan tab "History" di `pasien.Workspace` (pola sama seperti tab riwayat tagihan). Status SIMGOS: **Belum**.

**1010 Pembatalan** — Dari list kunjungan aktif (segmented filter Aktif/Selesai/Batal), form alasan batal → `STATUS=0`. Status SIMGOS: **Belum**.

**1011 Penerimaan** — Konfirmasi kedatangan pasien di loket sebelum diproses ke ruangan tujuan (beda dari 1101 "Penerimaan Ruangan" di domain Layanan). Status SIMGOS: **Belum**.

**1012 Tindakan List** — Tidak ditemukan bukti kuat berdiri sendiri; kemungkinan menaungi form administrasi lain (Kecelakaan, Rujukan). Tidak dapat dinilai gap-nya.

**1013 Triage Pendaftaran** — Tidak ditemukan bukti konkret sbg form terpisah di domain Pendaftaran; data triase kemungkinan dicatat via modul RM (Anamnesis). Tidak dapat dipastikan gap-nya.

**1014 Pasien Telah Meninggal (Forensik)** — Bukti kuat: `kunjungan.pasienmeninggal.Form` (namespace `kunjungan.*`, bukan `pendaftaran.*`) — formulir sertifikat kematian lengkap (penyakit penyebab, rudapaksa, kelahiran mati) dengan alur verifikasi (`STATUS_VERIFIKASI`+`VERIFIKATOR`). Status SIMGOS: **Belum**.

### [11] Layanan (16 modul)

**Baru 1101 yang mulai dibangun (2026-08-19), 15 modul lain masih belum ada.** Domain paling kompleks secara arsitektur (nested workspace terdalam).

**Arsitektur `VisitWorkspacePage` (hub modul besar, per 2026-08-23)** — hub kunjungan tunggal `/visits/:id` yang menampung 19 tab lintas domain 11 & 13 (Tanda Vital, Triage, Anamnesis, Diagnosis, Rencana, Tindakan, Lab, Radiologi, CPPT, Resume Medis, Surat Medis, Resep, Konsul, Bon Sisa, Riwayat, Transfer, Kelahiran, Antimikroba, Layanan Tambahan). Karena 19 tab datar terlalu padat, tab dikelompokkan **dua tingkat**: baris grup primer (`MdTabs` primary: **Klinis** / **Order & Penunjang** / **Farmasi** / **Administrasi & Luaran**) + baris sub-tab sekunder (`MdTabs` secondary) yang hanya menampilkan tab milik grup terpilih. Memilih grup otomatis pindah ke tab pertama grup itu; state `activeGroup`+`activeTab` terpisah, blok konten tetap dikunci pada `activeTab` (tidak berubah). Struktur `TAB_GROUPS` (array grup berisi `tabs`) memudahkan menambah tab baru ke grup yang tepat. Pola ini meniru kepadatan workspace RM legacy (`layanan.Workspace` ~19 sub-modul) tapi dengan navigasi yang lebih terkelola.

**1101 Penerimaan Ruangan** — Entry point aktual: widget dashboard Home "Kunjungan Pasien"/"My Pasien" → `kunjungan.Workspace` (idClassName TETAP, worklist bukan per-record) — daftar kunjungan aktif (`STATUS:1`) di ruangan petugas, search by NORM/nama. Klik baris → buka `pasien.Workspace`.

**Field grid asli** (`kunjungan.List extends com.Grid`, class definition penuh ditemukan terpisah dari widget dashboard di atas): Kolom **No** (rownumberer) · **Nomor** (grup: Kunjungan `NOMOR` + Pendaftaran `NOPEN`) · **Pasien** (template column satu sel: No. RM diformat `xx.xx.xx` + Nama + Alamat) · **Ruangan** (template column: nama ruangan bold + info kamar/tempat-tidur/kelas khusus rawat inap + badge Masuk/Keluar berwarna + badge titipan/paket/status + info iterasi resep) · **Penjamin** (hanya di `gridMode`: nama penjamin + no kartu + kode diagnosa utama + petugas). Toolbar: search by No. RM/No. Pendaftaran/Nama Pasien (3 search-field terpisah) · segmented button filter status **Aktif/Selesai/Batal** (bukan dropdown) · filter menu: rentang Tanggal, combo DPJP, combo Ruangan, combo Jenis Kunjungan, combo Penjamin.

**Status SIMGOS: Sebagian.** `VisitListPage` (`RME-Frontend/src/features/PendaftaranVisit`) jadi worklist dgn: ✅ kolom Pasien (Nama+No.RM, resolve Visit→Registration→Patient, minus Alamat) ✅ kolom Ruangan (resolve nama dari `GeneralWard`) ✅ filter Ruangan (dropdown) & Status (dropdown Aktif/Pulang/Semua — legacy segmented button 3 state Aktif/Selesai/Batal, model Visit SIMGOS baru punya status `active`/`discharged`, belum ada "Batal") ✅ backend `VisitController::index` sekarang terima `?ward_id=`&`?status=` (sebelumnya cuma `registration_id`). ❌ belum ada: search No.RM/No.Pendaftaran/Nama, filter DPJP/Jenis Kunjungan/Penjamin/rentang tanggal, kolom Penjamin, badge titipan/paket/iterasi resep, info kamar/tempat-tidur/kelas rawat inap. Diverifikasi end-to-end lewat `RME-Backend` asli (PHP 8.4, data `simgos_dump.sql`), bukan mock.

**1102 Penginputan Tindakan Medis** — `layanan.tindakanmedis.Form`: TANGGAL (dibatasi thd status final), kombo TINDAKAN (wajib). Terkunci jika kunjungan final (`isFinalKunjungan`). Privilege verifikasi terpisah (110201/110202). Sub-form terpisah `layanan.tindakanmedis.tenagamedis.FormList` — daftar petugas pelaksana per tindakan (bisa lebih dari satu, dengan peran masing-masing).

**Status SIMGOS: Sebagian (2026-08-19).** Backend (`Modules/LayananMedicalProcedure`+`LayananMedicalProcedureStaff`) ternyata **sudah ada duluan** sebelum sesi ini — field-nya cukup dekat dengan legacy: `visit_id`, `service_id` (=TINDAKAN, lookup ke `GeneralService`/master tindakan), `performed_at` (=TANGGAL), `performed_by` (=OLEH/petugas utama), `notes`, `status` (`completed`/`cancelled` — padanan STATUS 1/0, TIDAK ada delete endpoint, cuma update status "cancelled" — mengikuti prinsip "apa yang dilakukan & kapan tidak bisa diubah", cocok filosofi audit-trail). `MedicalProcedureStaff` (petugas pelaksana tambahan, field `employee_id`+`role`+`notes`) juga sudah ada di backend tapi **belum dipakai di frontend**.
Frontend baru: tab "Tindakan" di `VisitWorkspacePage` (`src/features/LayananMedicalProcedure`) — pilih Tindakan (dropdown `GeneralService`) + Petugas Pelaksana (dropdown `GeneralEmployee`) → Tambah → list tampil dgn badge status, aksi "Batalkan" (update status, bukan delete). Diverifikasi end-to-end lewat `RME-Backend` asli.
❌ Belum ada: field TANGGAL manual/editable (selalu `now()` otomatis), guard lock saat kunjungan final (`isFinalKunjungan`), privilege verifikasi terpisah (110201/110202), daftar petugas pelaksana multi-orang per tindakan (`MedicalProcedureStaff` belum dipakai).

**1103 Pengiriman (Order Penunjang/Konsul)** — 4 form seragam pola (`layanan.resep.order.Form`, `layanan.laboratorium.order.Form`, `layanan.radiologi.order.Form`, `pendaftaran.konsul.konsul.Form`): TANGGAL/WAKTU, TUJUAN (wajib), DOKTER_ASAL/DPJP (wajib), ALASAN/diagnosa (wajib). Order masuk antrean penerimaan unit tujuan (dikonsumsi 1104).

**Status SIMGOS: Sudah (2026-08-23).** Keempat jenis order legacy kini punya padanan tab di `VisitWorkspacePage`: **Resep** (`LayananPrescription`, tab "Resep"), **Lab** (`LayananLabOrder`, tab "Lab" — lihat 1104), **Radiologi** (`LayananRadiologyOrder`, tab "Radiologi" — lihat 1105), dan sekarang **Konsul** (tab "Konsul" baru). Konsul dibangun di atas backend `consultations`+`consultation_answers` (`PendaftaranConsultation`/`PendaftaranConsultationAnswer`, sudah ada duluan, keduanya sudah punya filter `?visit_id=`/`?consultation_id=` — tidak perlu perbaikan): buat konsul (Departemen Perujuk → Departemen Dituju via picker `GeneralMedicalDepartment`, wajib beda, + Pertanyaan) → unit dituju menjawab (picker `GeneralEmployee` + teks jawaban, bisa banyak jawaban per konsul) → badge "Terjawab"/"Menunggu Jawaban" (derived dari ada/tidaknya jawaban, backend tidak punya kolom `status`). `requested_at`/`answered_at` diisi server otomatis (`now()`). Frontend `src/features/PendaftaranConsultation` + `PendaftaranConsultationAnswer`. **Gap backend diperbaiki**: `MedicalDepartmentController::index()` hardcode `paginate(15)` (dropdown departemen bakal kepotong di 15) → diganti terima `?per_page=`, dan `GeneralMedicalDepartment/api.ts` list() ditambah param `per_page`. ❌ Belum ada: DOKTER_ASAL/DPJP eksplisit di sisi order (legacy punya, backend `consultations` cuma punya departemen perujuk/dituju — dokter perujuk tersirat dari konteks kunjungan), TANGGAL/WAKTU manual (selalu `now()`).

**1104 Feedback/Hasil Pemeriksaan & 1105 Pembacaan Ekspertise** — Unit penerima (Lab/Rad) isi hasil sesuai jenis pemeriksaan (form dinamis, termasuk mikrobiologi kultur/mikroskopik/PCR terpisah, Patologi Anatomi) → tandai status → tersedia untuk dibaca/ditandatangani DPJP (1105).

**Status SIMGOS 1104: Sebagian (2026-08-20).** Cek field-level ke `layanan.laboratorium.order.Form` (order: TUJUAN/DOKTER_ASAL/ALASAN/KETERANGAN/TANGGAL/STATUS_PUASA_PASIEN/Prioritas) dan `data.model.HasilLab` (hasil per parameter: TINDAKAN_MEDIS+PARAMETER_TINDAKAN/HASIL/NILAI_NORMAL/SATUAN/KETERANGAN/DOKTER/OLEH/STATUS) — **cocok bersih** ke backend `lab_orders`→`lab_order_items`→`lab_results` (`LayananLabOrder`/`LayananLabOrderItem`/`LayananLabResult`, sudah ada duluan sebelum sesi ini). Dibangun sebagai tab "Lab" baru di `VisitWorkspacePage` (`src/features/LayananLabOrder`) — buat order (tujuan/dokter perujuk/alasan/catatan/cito), tambah item pemeriksaan diminta, catat hasil per parameter, ubah status order (Menunggu/Diproses/Selesai/Dibatalkan). Diverifikasi end-to-end lewat backend nyata. Detail teknis (2 gap kecil + 1 bug backend ditemukan: field puasa & dokter-pemverifikasi tidak ada di backend; `LabOrderController::store()` kurang `->refresh()`) ada di §6.9. Cakupan ini **hanya laboratorium umum** — belum termasuk varian mikrobiologi/PA/molekuler yang jauh lebih spesifik di legacy.

**Status SIMGOS 1105: Sebagian (2026-08-20).** Cek field-level ke `layanan.radiologi.hasil.Form` (KRITIS/KLINIS/KESAN/USUL/HASIL/BTK/DOKTER/TANGGAL/WAKTU/STATUS) vs backend `radiology_orders`→`radiology_order_items`→`radiology_results` (`LayananRadiologyOrder`/`LayananRadiologyOrderItem`/`LayananRadiologyResult`, sudah ada duluan) — **cocok cukup baik**: `findings`≈HASIL, `impression`≈KESAN, `radiologist_id`≈DOKTER, `examined_at`≈TANGGAL+WAKTU, `status`≈STATUS (dipetakan ke `pending`/`final`, tombol "Simpan Draft"/"Final & Tandatangani" mengikuti pola legacy "Simpan" vs status final terkunci). Gap kecil: `KLINIS` (redundan dengan `clinical_notes` di order, jadi tidak hilang total), `USUL` (saran tindak lanjut) dan `KRITIS`/`BTK` **tidak ada** kolom setara di backend — belum diimplementasikan. Dibangun sebagai tab "Radiologi" baru di `VisitWorkspacePage` (`src/features/LayananRadiologyOrder`), pola sama seperti tab Lab. Diverifikasi end-to-end lewat backend nyata (order→item→hasil final→ubah status order, semua sukses).

**1106 History Layanan Pasien** — Tab "Riwayat" per jenis layanan (resep/lab/rad/konsul/mutasi), readonly list, dari `pasien.Workspace`/`kunjungan.Workspace`.

**Status SIMGOS: Sebagian (2026-08-20).** Cek field-level ke `layanan.resep.riwayat.Workspace` (query by `NORM` pasien + `HISTORY:1`, master-detail list+detil per order) — konsep intinya benar (riwayat lintas kunjungan per pasien, bukan cuma kunjungan aktif), tapi **scope dipersempit** untuk build pertama: dibangun sebagai tab "Riwayat" baru di `VisitWorkspacePage` (`src/features/PendaftaranVisit/components/VisitHistoryList.tsx`) yang menampilkan **daftar kunjungan lain pasien yang sama** (query `registrations?patient_id=` → `visits?registration_id=` per registrasi, gabung+urutkan, exclude kunjungan aktif) sebagai link ke masing-masing `VisitWorkspacePage` — klik kunjungan lama untuk lihat tab Lab/Radiologi/Resep/dst kunjungan tsb. **Belum** meniru pola legacy yang asli: rollup gabungan lintas-jenis-layanan dalam SATU list (resep+lab+rad+konsul+mutasi tercampur, sortable by tanggal) — versi SIMGOS saat ini mengharuskan buka tiap kunjungan lama satu-satu. Diverifikasi end-to-end: buat registrasi+kunjungan kedua utk pasien yang sama, konfirmasi query chain mengembalikan kunjungan kedua sebagai riwayat, lalu data QA dihapus (kedua modul ini `apiResource` penuh, delete didukung).

**1107 Pencetakan Hasil Layanan** — Aksi cetak menempel di masing-masing form hasil, bukan modul berdiri sendiri (>10 varian cetak berbeda).

**1108 Pembatalan Layanan** — Hanya order/tindakan yang belum final bisa dibatalkan (`STATUS=0`).

**Status SIMGOS: Sudah (2026-08-20).** Dicek ke legacy — tidak ada form/modul berdiri sendiri untuk ini, "pembatalan" selalu embedded sebagai toggle status di masing-masing form order (sama seperti temuan §6.9 sebelumnya). Sudah tercakup penuh lewat aksi "Batalkan" (`MedicalProcedureList`, status→`cancelled`) dan dropdown status (`LabOrderList`/`RadiologyOrderList`, opsi "Dibatalkan") yang sudah dibangun di tab Tindakan/Lab/Radiologi — tidak ada kode baru yang perlu ditambah, cuma pengakuan cakupan yang sudah ada.

**1109 Final/Selesai Pelayanan** — Field `FINAL_HASIL`/`FINAL_HASIL_OLEH`/`FINAL_HASIL_TANGGAL` di `Kunjungan` — mengunci input tindakan baru, biasanya trigger alur billing. Cara keluar (`CARA_KELUAR`) legacy adalah referensi-combo terintegrasi BPJS (`plugins.bpjs.referensi.carakeluar`).

**Status SIMGOS: Sebagian (2026-08-20).** Backend `visits` (`PendaftaranVisit`) **sudah punya** kolom persis serupa duluan: `final_outcome`≈FINAL_HASIL, `final_outcome_by`≈FINAL_HASIL_OLEH, `final_outcome_at`≈FINAL_HASIL_TANGGAL — bahkan `VisitController::update()` sudah punya logic auto-fill (set `discharged_at` → otomatis isi `final_outcome_by`/`final_outcome_at`/`status='discharged'` di server, tidak perlu dikirim manual dari frontend). Untuk `final_outcome` sendiri, dicek Master Referensi generik (1918) yang sudah diimpor dari legacy — kategori **"Cara Keluar" (id 44) sudah ada** dengan isi asli (Diijinkan Pulang/Pulang Paksa+Alasan/Dirujukan Ke RS Lain/dst), jadi dipakai langsung sebagai sumber dropdown, bukan input bebas. Dibangun sebagai tombol "Final Pelayanan" baru di header `VisitWorkspacePage` (`src/features/PendaftaranVisit/components/FinalizeVisitButton.tsx`) — dialog pilih Cara Keluar → `PUT /visits/{id}` dengan `discharged_at`+`final_outcome`+`status`. Diverifikasi end-to-end via kunjungan sekali-pakai (dibuat→difinalisasi→dihapus). ❌ Belum ada: penguncian input tindakan baru setelah final (guard `isFinalKunjungan` seperti di 1102), trigger otomatis alur billing.

**Update 2026-08-27 (disposisi pulang terstruktur — SUDAH, hijau).** Ditambah *discharge disposition* terstruktur di atas fondasi 1109: kolom `discharge_disposition_code`/`_text`/`_recorded_by`/`_recorded_at` di `visits` (migrasi `2026_08_26_043000_add_discharge_disposition_to_visits.php`) plus **DB trigger INSERT/UPDATE** yang menegakkan tuple valid — visit ber-status `discharged` **wajib** punya disposisi lengkap (kode ∈ `home/aadvice/other-hcf/oth/exp-lt48h/exp-gt48h`), dan aturan durasi khusus kematian (`exp-lt48h`/`exp-gt48h`). Validasi request di `FinalizeVisitRequest` (tahan tipe non-string), integrity check snapshot di `EpisodeSyncPlanner`/`EpisodeClosureIntegrity` (tolak snapshot yang di-tamper via checksum), frontend `FinalizeVisitButton.tsx` (tampilkan pesan error server) + rapikan header `VisitWorkspacePage`. Waktu disposisi diselaraskan ke UTC. **Tes lulus 76/76**: `VisitControllerTest` (27), `EpisodeClosureSyncTest`, `EpisodeClosureTest`. Factory `VisitFactory::discharged()` sudah mengisi disposisi valid agar tidak melanggar trigger.

**Update 2026-08-28 (refactor tarif — SELESAI).** Tiga tes Invoice yang sebelumnya merah sudah diselaraskan dengan komponen tarif deterministik (`285aa54`), komponen tarif tindakan selesai dengan kompatibilitas payload `price` lama dan backfill data (`969eb3b`), lalu KPTL tindakan (`1bb563c`) serta tarif penunjang Administrasi/O₂/Kamar/Farmasi (`548d414`) dipisahkan ke migrasi ekspansi yang reversible. Tes terkait hijau: Invoice+ServiceTariff+Service 40/40 dan empat tarif penunjang 16/16. Pengelolaan Pegawai/Non Pegawai juga selesai (`8924a00` backend, `524542d` frontend; 9/9 tes). **Sisa perubahan belum siap:** PPK masih memakai teks untuk kolom `type`/`ownership`/`jpk` yang bertipe angka dan UI referensinya masih placeholder; jangan commit sebelum mapping Master Referensi dipastikan.

**1110 Kelahiran (VK Bersalin)** — `layanan.kelahiran.Form/Workspace`: NAMA bayi, JENIS_KELAMIN, BERAT, PANJANG. Berlanjut ke `pendaftaran.bayi.Form` dengan opsi IKUT_IBU/KUNJUNGAN_IBU.

**Status SIMGOS: Sebagian (2026-08-20).** Cek field-level lebih dalam: `layanan.kelahiran.Form` ternyata cuma textarea narasi bebas ("Deskripsi Anamnesis"), field terstruktur (JAM_LAHIR/BERAT_BAYI/PANJANG_BAYI) sebenarnya ada di form terpisah `pendaftaran.bayi.Form` — alur legacy aslinya **3 langkah**: (1) narasi kelahiran, (2) vitals bayi, (3) registrasi bayi sebagai pasien baru (nama/jenis kelamin/dst via form Pasien standar, opsi IKUT_IBU/KUNJUNGAN_IBU). Backend `birth_records` (`LayananBirthRecord`, sudah ada duluan) **menggabungkan ketiganya jadi satu record**: `baby_name`/`gender_id`/`birth_date`/`birth_weight_grams`/`birth_length_cm`/`delivery_method`/`attending_doctor_id`/`notes`/`mother_patient_id` — ini konsolidasi desain yang wajar (bukan kehilangan field, cuma disederhanakan jadi satu langkah), field-nya justru mencakup semua yang dibutuhkan dari ketiga form legacy sekaligus. Dibangun sebagai tab "Kelahiran" baru di `VisitWorkspacePage` (`src/features/LayananBirthRecord`). Diverifikasi end-to-end lewat backend nyata. ❌ Belum ada: bayi sungguhan terdaftar sebagai record `Patient` baru (opsi IKUT_IBU/KUNJUNGAN_IBU) — `birth_records` cuma catatan medis event kelahiran, bukan alur pendaftaran pasien baru untuk si bayi.

**1111 Pemakaian BHP Ruangan** — Tambah item bahan+jumlah berulang, terhubung inventori ruangan.

**1112 Bon Sisa Pemakaian Obat/BHP** — TANGGAL+WAKTU retur → "Final & Layani" → "Cetak Bon Sisa". Terkait retur farmasi.

**Status SIMGOS: Sebagian (2026-08-23).** Backend `leftover_medication_vouchers`+`leftover_medication_voucher_items` (`LayananLeftoverMedicationVoucher`/`...Item`, sudah ada duluan) — voucher: `voucher_number` (unik, wajib, TIDAK auto-generate di server jadi frontend yang bikin nomor `BON-{visitId}-{timestamp}`), `visit_id`, `patient_id`, `prescription_id` (opsional), `status` (pending/redeemed/expired), `issued_at` (=TANGGAL+WAKTU), `redeemed_at`, `notes`; item: `item_id` (FK `InventoryItem`), `quantity`, `unit`. Dibangun sebagai tab "Bon Sisa" baru di `VisitWorkspacePage` (`src/features/LayananLeftoverMedicationVoucher/components/BonSisaList.tsx`) — buat bon (nomor auto + tanggal terbit + resep terkait opsional + catatan), tiap bon punya list item obat/BHP sisa dengan picker `InventoryItem` (search server-side via `?name=`) + jumlah + satuan (auto dari item), dan dropdown status untuk menandai "Sudah Ditebus" (mengisi `redeemed_at` otomatis, padanan aksi legacy "Final & Layani") / "Kedaluwarsa". **Gap backend ditemukan & diperbaiki** (sesuai arahan user "bila ada perbaikan di backend perbaiki saja"): `LeftoverMedicationVoucherController::index()` dan `LeftoverMedicationVoucherItemController::index()` sama-sama TIDAK punya filter apa pun (mengembalikan semua baris global lintas kunjungan) — ditambah filter `?visit_id=`/`?patient_id=`/`?status=` di voucher dan `?leftover_medication_voucher_id=` di item (pola gap yang sama berulang seperti Visit/Reference sebelumnya). ❌ Belum ada: aksi "Cetak Bon Sisa" (printing lintas-modul, ditunda seperti 1107/1008), guard lock saat kunjungan final, integrasi otomatis ke retur stok inventori (`InventoryGoodsReturn`).

**1113 Pemanggilan Antrean Kunjungan** — Model `PanggilanAntrianRuangan` — panggilan nomor antrean suara/visual poli.

**1114 Menu Layanan Tambahan** — Container konfigurasi menu spesifik unit (mis. Pemakaian O2, Permintaan Darah — khas ICU/kamar operasi/VK).

**Status SIMGOS: Sebagian (2026-08-23).** Konsep legacy = container layanan spesifik unit; dibangun sebagai tab "Layanan Tambahan" baru di `VisitWorkspacePage` yang saat ini menampung **Pemakaian Oksigen (O₂)**. Backend `oxygen_usages` (`LayananOxygenUsage`, sudah ada duluan) — `visit_id`, `flow_rate_lpm` (decimal:1 = laju L/menit), `method` (string bebas = metode pemberian: Nasal Kanul/NRM/HFNC/dst, frontend sediakan daftar preset tapi tidak mengunci), `started_at` (=mulai), `ended_at` (=selesai, nullable → badge "Berlangsung" + tombol "Akhiri" yang mengisi `ended_at=now`), `recorded_by` (nullable `users.id`, belum diisi frontend — belum ada picker user). Dibangun frontend `src/features/LayananOxygenUsage/components/OxygenUsageList.tsx`, CRUD `index/store/show/update` (tanpa delete). **Gap backend diperbaiki**: `OxygenUsageController::index()` tidak punya filter → ditambah `?visit_id=` (pola gap berulang yang sama). ❌ Belum ada: **Permintaan Darah** — backend cuma punya `LayananBloodRequestItem` (tabel item) **tanpa modul parent `LayananBloodRequest`** (orphan), jadi tidak bisa dibangun dengan benar sebelum backend parent-nya diadakan; recorded_by belum di-wire; tarif O2 otomatis (`GeneralOxygenTariff` ada di backend, belum diintegrasikan ke billing).

**1115 KPO (Kajian Penggunaan Obat)** — Lihat detail lengkap di §domain [29] di bawah (2901 adalah representasi UI utama fitur ini).

**1116 Formulir Antimikroba (PPRA)** — Form komposit 9 sub-resource (Riwayat, Keadaan, Pemeriksaan Umum+TTV, Hasil Lab Mikro/Lab/Rad/Penunjang Lain) → kirim → dokter PPRA monitoring & approve rencana terapi antibiotik.

**Status SIMGOS: Sebagian (2026-08-20).** Backend `antimicrobial_stewardship_forms` (`LayananAntimicrobialStewardshipForm`, form inti) sudah ada duluan bersama **8 modul sub-resource lain** (`...Approval`/`...FormItem`/`...GeneralExamination`/`...LabResult`/`...MicrobiologyResult`/`...OtherSupportResult`/`...PriorHistory`/`...RadiologyResult`) — cakupan sub-resource-nya sudah dicek sesuai daftar 9-bagian di atas. Dibangun: **hanya form inti** — tab "Antimikroba" baru di `VisitWorkspacePage` (`src/features/LayananAntimicrobialStewardshipForm`), alur: pilih dokter pengaju + antibiotik (dari `/antibiotic-restrictions`, master baru) + indikasi klinis → simpan draft → "Ajukan ke PPRA" (status `submitted`) → dokter PPRA "Setujui"/"Tolak" (`approved`/`rejected`). Field inti (`requesting_doctor_id`/`antibiotic_restriction_id`/`indication`/`status`/`submitted_at`) sudah field-parity dengan konsep legacy "kirim→approve". Diverifikasi end-to-end lewat backend nyata (draft→submitted→approved). ❌ Belum ada: 8 sub-resource detail (riwayat penyakit, pemeriksaan umum+TTV composite, hasil lab/mikrobiologi/radiologi/penunjang-lain, item resep antibiotik per form) — form saat ini cuma tangkap indikasi+antibiotik+status, bukan seluruh data klinis pendukung yang legacy minta sebelum PPRA bisa menyetujui secara sah. Signifikan lebih kecil scope-nya dari legacy asli, tapi alur inti (ajuan→approval) sudah benar dan bisa diperluas modul demi modul nanti.

### [12] Pembayaran (14 modul)

**Temuan arsitektur kunci**: setelah Tagihan dibuka (`pembayaran.tagihan.Workspace`), hampir semua sub-modul (Deposit, Piutang, Non Tunai, Diskon, Penjamin) adalah **tab internal** di `pembayaran.tagihan.Links` — bukan workspace top-level terpisah. Untuk SIMGOS: halaman detail Invoice sebaiknya satu halaman dengan tab internal (sudah konsisten dengan `InvoiceDetailPage.tsx` yang ada).

**1201 Final Tagihan** — Guard berlapis sebelum bisa final: privilege → sesi kasir terbuka (1209) → kunci tagihan (jika config aktif) → semua kunjungan terkait sudah "pulang" → (jika priv 1215 aktif) tidak ada order layanan pending. Final = create record pembayaran tunai `STATUS:2`, mengunci tagihan. **SIMGOS saat ini (auto-lock saat totalPaid≥total) adalah penyederhanaan besar** — belum menangkap guard kasir/kunjungan/order-pending, dan tidak ada jalur pembatalan (1208).

**1202/1203 Deposit & Pengembalian Uang Muka** — Dua form (JENIS 1/2) di tab "Deposit", aktif hanya saat tagihan belum final.

**1204/1205 Piutang & Pelunasan** — Piutang perorangan/perusahaan, cicilan (`JENIS_PEMBAYARAN` Sekaligus/Angsuran), status Belum/Lunas.

**1206 Pembayaran Non Tunai** — Sub-workspace EDC & Transfer Bank terpisah. QRIS tidak ditemukan sbg kelas terpisah (kemungkinan opsi field, bukan bukti kuat).

**1207 Diskon Tagihan** — Dua sub-tab: Sarana (diskon tarif RS) & Dokter (diskon jasa dokter per dokter terpilih).

**1208 Pembatalan Tagihan Final** — Form alasan (`TagihanPembatalan`) → status kembali ke belum-final. **Belum ada padanan sama sekali di SIMGOS** — invoice yang sudah `paid` tidak punya jalur unlock.

**1209 Transaksi Kasir** — **Bukan workspace, toggle status global** di header (buka/tutup sesi kasir per shift), disimpan di local storage. **Precondition wajib** untuk 1201. **Konsep ini hilang total di SIMGOS** — perlu entitas baru `CashierSession` sebelum implementasi penuh finalisasi tagihan.

**1211 Batal Gabung Tagihan** — Undo dari operasi "Gabung Tagihan" (menggabung tagihan pasien+keluarga jadi satu invoice, model `GabungTagihan{DARI,KE}`).

**1212 Penjamin Tagihan** — Bagian **paling kompleks secara bisnis** di domain ini: perhitungan porsi BPJS/Asuransi/Iur Bayar otomatis mengikuti tarif INA-CBG vs tarif RS vs kelas VIP (skenario naik kelas), banyak rumus kebijakan RS tersimpan sbg property config.

**1213 Pencetakan Kuitansi** — Terintegrasi di panel Info tagihan, 4 format cetak rincian berbeda (Word/PDF).

**1214 Transaksi Penjualan Apotek** — Grid dedicated kasir apotek (JENIS:4, terpisah dari tagihan rawat), workspace `tagihan-penjualan-Workspace`. Pembatalan langsung ubah status (**beda pola** dari 1208 yang wajib form alasan — inkonsistensi legacy). Upstream: modul 2101.

**1215 Pembatalan Permintaan Layanan** — Gerbang wajib sebelum Final Tagihan (cek order pending), aksi konkretnya ada di domain Pendaftaran, di luar cakupan riset detail sesi ini.

### [13] Rekam Medis (19 modul)

**Temuan arsitektur kunci**: RM **bukan** bagian dari `pasien.Workspace`, melainkan **tab di dalam `layanan.Workspace`** (per-kunjungan/encounter, bukan per-pasien) — dibuka dari file package terpisah `rekammedis.js` (1.9MB), route internal `13/xx` untuk 19 sub-modul. Header workspace RM: tombol ICare (BPJS), SSRM (link SatuSehat Mobile), Final/Batal Final, Observasi Komprehensif, Growth Chart, menu Cetak. **Gerbang "Final Kunjungan" mengunci SEMUA sub-form RM sekaligus.**

**1301 Pencetakan Lembaran RM** — Aksi cetak di header workspace RM (Resume Medis, CPPT, Asuhan Keperawatan, dll), generate PDF + auto-simpan ke document-storage.

**1302 Anamnesis Pasien** — Sub-tab: Keluhan Utama, Riwayat Penyakit/Keluarga/Alergi/Perawatan/Obat, Edukasi — banyak field terintegrasi SNOMED CT.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Anamnesis" baru di `VisitWorkspacePage` yang menggabungkan 3 backend module (semua sudah ada duluan, matang): **Keluhan Utama** (`MedicalRecordChiefComplaint`: `complaint`/`onset`/`duration`, one-per-kunjungan editable — load latest lalu create/update), **Riwayat** (`MedicalRecordAnamnesis`: 5 narasi `present_illness_history`/`past_medical_history`/`family_medical_history`/`allergy_history`/`social_history`, one-per-kunjungan editable), **Alergi** (`MedicalRecordAllergy`: patient-scoped, `category` obat/makanan/lingkungan + `allergen` + `severity` ringan/sedang/berat + `reaction`, tambah/nonaktifkan via `is_active`, `created_by` auto dari user login — ditampilkan paling atas dengan panel merah keselamatan pasien). Satu picker "Petugas Pencatat" (`GeneralEmployee`) dipakai bersama ketiga bagian (`recorded_by`). **Gap backend diperbaiki**: `ChiefComplaintController::index()` dan `AnamnesisController::index()` tidak punya filter → ditambah `?visit_id=` (Allergy sudah punya `?patient_id=`+`?active_only=`). ❌ Belum ada: integrasi SNOMED CT, sub-bagian Edukasi & Riwayat Obat/Perawatan terstruktur (legacy jauh lebih granular), penguncian saat kunjungan final.

**1303 Pemeriksaan TTV & Fisik** — Tanda vital + ~40 sub-tabel pemeriksaan fisik per region tubuh + penunjang khusus (EKG/EEG/EMG). "Observasi Komprehensif"/"Growth Chart" = grafik tren dari data ini. Legacy: tanda vital muncul embedded di dalam form gabungan `rekammedis-anamnesis-umum...` (`anamnesistore`/`pemeriksaanfisikstore`/`alergistore`/`tandavitalstore`), bukan layar berdiri sendiri — tapi field-nya (suhu/nadi/napas/TD sistolik-diastolik/SpO2/skala nyeri) universal & sudah persis cocok dengan backend `MedicalRecordVitalSign` (`vital_signs`: append-only, `visit_id`+`recorded_at`+`recorded_by` FK Employee). Status SIMGOS: **Sudah (2026-08-23).** Tab "TTV & Fisik" baru di `VisitWorkspacePage` (`MedicalRecordGeneralExamination` + `MedicalRecordPhysicalExamination`) menggabungkan tanda vital lengkap (BB/TB/suhu/nadi/napas/TD/LiLA/SpO2) dengan checklist pemeriksaan fisik per 9 region tubuh (Kepala, Leher, Tulang Belakang, Dada, Perut, Ekstremitas Kulit/Gerak, Genitalia, Anus). Both append-only. ❌ Belum ada: EKG/EEG/EMG, grafik tren.

**1304 Penilaian/Assessment** — >10 skala terstruktur: nyeri, risiko jatuh (Humpty Dumpty/Morse), Barthel Index (ADL), skrining gizi, dekubitus, dll — entri berkala per shift.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Penilaian" di `VisitWorkspacePage` (grup Klinis). Build pertama mencakup 2 skala akreditasi-kritis dari backend yang sudah ada duluan: **Skala Nyeri** (`MedicalRecordPainScoreAssessment`, append-only, filter `?visit_id=` sudah ada, tidak perlu perbaikan) — `scale_type` NRS/Wong-Baker/FLACC/CRIES + `score` 0-10 + `location`/`character`/`notes`, badge warna-koding hijau/kuning/merah. **Risiko Jatuh Morse** (`MedicalRecordMorseFallScaleAssessment`, append-only, filter `?visit_id=` sudah ada, tidak perlu perbaikan) — 6 sub-score dengan nilai baku (riwayat_jatuh 0/25, diagnosis_sekunder 0/15, alat_bantu 0/15/30, infus 0/20, gaya_jalan 0/10/20, status_mental 0/15), `total_score` auto-sum frontend (0-125), `risk_level` auto LOW/MODERATE/HIGH (batas <25/25-44/≥45). Panel memakai 1 picker "Petugas Penilai" (`GeneralEmployee`) dipakai bersama dua skala. ❌ Belum ada: Humpty Dumpty (anak), Barthel Index (ADL), skrining gizi (MST), dekubitus (Braden/Norton), skala get-up-and-go — modul backend sudah ada, tinggal dibangun frontend-nya (pola sama dengan Morse).

**1305 Diagnosis ICD-10 & 9CM** — **Dua tabel berbeda**: `medicalrecord.diagnosa` (koding ICD aktual, create-only/upsert, tidak bisa delete via REST sama sekali) vs `medicalrecord.diagnosis` (working diagnosis naratif, satu baris per kunjungan, hanya PPA boleh create/update). Status SIMGOS: **Sebagian** (`MedicalRecordDiagnosis`+`GeneralDiagnosisCode`).

**1306 Penandaan Gambar Anatomi** — Marking koordinat luka/lesi pada template, punya field `FINAL` independen dari final kunjungan.

**1307 Perencanaan Medis** — Rencana/terapi, sumber untuk instruksi CPPT.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Rencana" di `VisitWorkspacePage` (`MedicalRecordPlanAndTherapy`, sudah ada duluan, **append-only** — index/store/show, filter `?visit_id=` sudah ada, tidak perlu perbaikan backend). Field: `assessment_summary`, `plan_description` (wajib), `therapy_type`, `target_date`, `status` (active/completed/revised — revisi = entri baru, bukan edit), `ordered_by` (=DPJP, picker `GeneralDoctor` — perhatikan FK ke tabel `doctors`, BUKAN `employees`, beda dari kebanyakan modul RM lain yang pakai `recorded_by`→`employees`), `ordered_at`/`created_by` auto server. ❌ Belum ada: auto-feed ke instruksi CPPT, penguncian pasca-final.

**1308 CPPT (SOAP Terintegrasi)** — `medicalrecord.cppt`: SOAP + INSTRUKSI + STATUS_TBAK_SBAR (komunikasi verbal via telepon). Delete diblok 405; **counter-signing**: modul terpisah `VerifikasiCPPT` (fully append-only) untuk DPJP memverifikasi catatan PPA lain via record baru, bukan edit. Status SIMGOS: **Sudah** (`MedicalRecordClinicalNote`, bahkan lebih ketat dari legacy — legacy hanya larang delete, SIMGOS juga larang update).

**1309 Penerbitan Surat Medis** — Surat Sakit, Opname, Kelahiran, HD — terkait TTE untuk tanda tangan dokter.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Surat Medis" di `VisitWorkspacePage` — untuk build pertama menampung **Surat Keterangan Sakit** (`MedicalRecordSickLeaveCertificate`, full CRUD, filter `?visit_id=`/`?patient_id=`/`?doctor_id=` sudah ada, tidak perlu perbaikan backend). Field: `letter_number` (unik, wajib, di-generate frontend `SKS-{visitId}-{timestamp}` karena backend tidak auto-gen), `doctor_id` (picker `GeneralDoctor` — FK `doctors`), `issue_date`/`start_date`/`end_date` (wajib, `end_date >= start_date` divalidasi backend), `duration_days` (dihitung otomatis inklusif dari rentang tanggal di frontend), `diagnosis`/`remarks` (opsional), `created_by` auto. List + buat + hapus. ❌ Belum ada: jenis surat lain (Opname/`HospitalizationCertificate`, Kelahiran/`BirthCertificateLetter`, HD/`HemodialysisLetter`, Sehat/`HealthCertificate` — semua modul backend terpisah sudah ada, belum dibangun frontend), TTE tanda tangan digital dokter, cetak PDF.

**1310 Resume Medis Pulang** — Diagnosa masuk/keluar, tindakan, keadaan pulang — bergantung RENCANA_PULANG dari CPPT, jadi bahan utama Berkas Klaim (30).

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Resume Medis" di `VisitWorkspacePage` (`MedicalRecordDischargeSummary`, sudah ada duluan, **append-only** — index/store/show, sengaja tanpa update/delete: "koreksi = resume baru", sama filosofi CPPT; filter `?visit_id=` sudah ada, tidak perlu perbaikan backend). Field: `admission_diagnosis_id`/`discharge_diagnosis_id` (FK ke `diagnoses` kunjungan ini — picker diisi dari diagnosis yang sudah tercatat di tab Diagnosis, di-resolve ke kode ICD via `GeneralDiagnosisCode`), `treatment_summary`, `condition_at_discharge`, `follow_up_plan`, `discharge_medication`, `authored_by` (=DPJP, picker `GeneralEmployee`), `authored_at`/`created_by` auto server. Resume tersimpan tampil read-only (riwayat), form buat resume baru terpisah. ❌ Belum ada: auto-tarik RENCANA_PULANG dari CPPT, aksi cetak PDF (1301), penguncian pasca-final.

**1311 Keperawatan & Kebidanan** — Struktur mirip SDKI-SLKI-SIKI (Diagnosa Keperawatan → Indikator/Luaran → Intervensi → Evaluasi), plus modul obstetri/kebidanan terpisah.

**1312 Triage Medis IGD** — Skoring ATS/ESI, khusus kunjungan IGD, satu entri per kedatangan.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Triage" di `VisitWorkspacePage` (`MedicalRecordTriage`, sudah ada duluan, **append-only** — index/store/show, filter `?visit_id=` sudah ada, tidak perlu perbaikan backend). Field: `level` (1-5 skala ATS/ESI, warna-koding badge Merah/Merah/Kuning/Hijau/Putih), `chief_complaint`, `assessed_by` (picker `GeneralEmployee`), `notes`, `assessed_at`/`created_by` auto server. Penilaian ulang = entri baru (riwayat tersimpan). ❌ Belum ada: tanda vital terintegrasi di form triase, algoritma bantu penentuan level, penanda khusus tampil IGD-only (saat ini tab muncul di semua jenis kunjungan).

**1313 Rekonsiliasi Obat 3-Tahap** — 3 endpoint eksplisit terpisah: Admisi, Transfer, Discharge — masing-masing dengan daftar obat + keputusan lanjut/stop/ganti.

**1314 Pemantauan Kritis** — Bukti lemah untuk modul ICU/ICCU/NICU berdiri sendiri; hanya ditemukan HD Intradialitik + fitur generik "Observasi Komprehensif" (grafik tren TTV). Kemungkinan perlu didesain baru di SIMGOS, bukan replikasi 1:1.

**1315 Tindakan/Terapi Khusus** — Operasi, Anastesi, Transfusi Darah, Fibroscan — laporan operasi jadi dokumen wajib klaim.

**1316 Hasil MCU** — Bukti moderat/tidak lengkap, endpoint PHP ada (`HasilMCU`) tapi UI eksplisit tidak ditemukan.

**1317 Form Registry SatuSehat** — Bukti lemah untuk modul berdiri sendiri; yang ada tersebar (SNOMED/LOINC/KFA mapping, tombol SSRM untuk share akses RME ke pasien). Kemungkinan perlu didesain baru di SIMGOS.

**1318 Upload Dokumen Eksternal** — Layanan document-storage generik, pembatalan via record beralasan (bukan hard delete) — dipakai lintas modul.

**1319 Lembar Transfer Pasien** — Lampiran wajib form Konsul untuk kunjungan rawat inap, status berubah "terkirim" mengunci form.

**Status SIMGOS: Sebagian (2026-08-23).** Dibangun tab "Transfer" di `VisitWorkspacePage` (`MedicalRecordPatientTransferSheet`, sudah ada duluan, full CRUD, filter `?visit_id=`+`?patient_id=` sudah ada — tidak perlu perbaikan backend). Field: `from_ward_id`/`to_ward_id` (picker `GeneralWard`), `transfer_reason`, `patient_condition`, `transferred_by` (picker `GeneralEmployee`), `transferred_at` auto server. List + buat + hapus. ❌ Belum ada: status "terkirim" yang mengunci form (tidak ada kolom status di backend), keterkaitan wajib ke form Konsul, SBAR terstruktur (backend cuma 2 textarea bebas).

### [14] Laporan (12 modul)

**Temuan paling signifikan di seluruh riset**: domain ini **dirancang lengkap di database** (`master.jenis_laporan`, 24 baris, `MODULE` field cocok 1:1 ke katalog 1401-1412) tapi **TIDAK PERNAH diimplementasikan sebagai UI ExtJS** — 0 kemunculan `laporan.Workspace` maupun ke-12 xtype yang dituju di seluruh `app.js`. Menu "Laporan" kemungkinan tampil di sidebar tapi kosong/gagal saat diklik di produksi.

**Bagaimana laporan sebenarnya diakses**: lewat tombol "Cetak" tersebar di masing-masing modul domain asal data, memanggil endpoint generic `GET /report` yang me-render **200+ file Jasper `.jrxml`** terorganisir per folder domain (`reports/{rl,pendaftaran,layanan,pembayaran,jasa,inventory,rekammedis,pendapatan,kegiatan,monitoring}/`) — mesin backend-nya matang, hanya frontend generic report browser yang belum pernah dibangun.

**Kasus khusus 1401 (RL SIRS)** — punya backend dedicated (`Kemkes/SIRS`) dengan alur bolak-balik: tarik data lokal → agregasi otomatis per tahun → **kirim resmi ke SIRS Online Kemenkes** via REST → terima balikan status. Bukan sekadar cetak laporan, tapi integrasi pelaporan nasional.

**Status SIMGOS**: `GeneralReports/{ReportRLPage,ReportVisitorsPage}.tsx` **terkonfirmasi orphan** (tidak di `routes/index.tsx`), masih placeholder scaffold. **Tim harus rancang UI generic report browser dari nol** berbasis katalog `jenis_laporan`+engine Jasper — bukan port dari ExtJS existing (karena tidak ada yang bisa di-port).

### [15] Dashboard (11 modul)

**Domain paling matang & lengkap di legacy** — 1:1 terimplementasi. Pola: workspace-per-tipe (bukan per-record), filter tahun/bulan/ruangan → card KPI + chart + grid ranking. Semua 11 folder backend Rpc (`Indikator`, `Pengunjung`, `Kunjungan`, `RawatInap`, `Laboratorium`, `Radiologi`, `KasusDiagnosa`(+inacbg), `Pendapatan`, `KlaimInacbg`, `WaktuLayananRawatDarurat`) ditemukan lengkap tanpa yang hilang.

Status SIMGOS: hanya 1501 (Indikator BOR/LOS) yang sudah dibangun (`DashboardPage`, ter-wire `/dashboard`); 1502-1511 belum.

### [19] Master Data (28 modul)

**Tidak** memakai pola workspace-per-record — CRUD grid/list tunggal per modul, 3 sub-pola generik: (A) Single Grid/List Workspace (~90% modul), (B) Master-Detail Split View (Referensi, Wilayah, Ruangan — tree berjenjang), (C) Multi-Tab Workspace payung (Farmasi 12 sub-modul, Tarif).

Detail penting: **1902 Pegawai dan 1926 Non Pegawai memakai CLASS ExtJS yang sama** (`master.pegawai.Workspace`), dibedakan lewat parameter `NON_PEGAWAI`. **1903 Manajemen Pengguna**: reset password = edit ulang field password di form yang sama (bukan aksi terpisah), kunci akun = field STATUS record, hak akses = check-tree cascade ke `group_pengguna_akses_module`. **1928 Pengaturan RM** ternyata isinya konfigurasi asuhan keperawatan (mapping Diagnosa-Indikator-Intervensi), bukan murni penomoran RM/retensi seperti deskripsi katalog — perlu digali lanjut bila detail penomoran RM dibutuhkan.

**1918 Master Referensi — riset lanjutan (2026-08-19), skala aslinya jauh lebih besar dari dugaan awal.** Cek langsung ke DB legacy (`master.jenis_referensi`+`master.referensi`) nemuin **328 kategori, 3973+ nilai** (bukan cuma 8 kategori demografi pasien) — dropdown `referensi-combo` param `JENIS:<id>` dipakai di **ratusan tempat lintas SEMUA domain** (skala Barthel/Morse/Humpty Dumpty di Rekam Medis, jenis tagihan di Pembayaran, kategori tindakan, dst), bukan cuma form pasien. Pola legacy: **Master-Detail Split View** (`master.referensi.Workspace` — west: grid Jenis Referensi, center: grid Nilai per jenis terpilih).
`RME-Backend` ternyata **sudah punya** arsitektur generik yang cocok (`Modules/GeneralReferenceType`→`reference_types`, `Modules/GeneralReference`→`reference_entries`, unique `[category,code]`, TIDAK berelasi FK satu sama lain — `category` cuma string yang harus cocok manual ke `ReferenceType.name`) tapi seeder-nya kosong total dan endpoint `GeneralReference::index()` tidak filter `?category=` sebelum diperbaiki sesi ini.
**Status SIMGOS: Sebagian (2026-08-19).** Halaman baru `ReferenceMasterPage` (`src/features/GeneralReference`, route `/references`) — Master-Detail persis pola legacy: kiri list+search 328 Jenis Referensi, kanan tabel Nilai (Kode/Nama/Status/Aksi) + dialog tambah/ubah. Data 328 kategori + 3973 nilai **diimpor langsung dari DB legacy** (seeder `INSERT...SELECT` cross-schema, one-time migration — lihat `CATATAN.md`). Diverifikasi end-to-end: kategori "Agama" tampil persis 8 nilai sesuai legacy (Islam/Kristen/Katholik/Hindu/Budha/Konghuchu/Kepercayaan/Lain-lain), tambah+hapus nilai baru terkonfirmasi via API.
❌ Belum ada: relasi FK proper antara `ReferenceType`↔`Reference` (masih string-matching by convention), UI buat kelola `ReferenceType` sendiri (cuma dipakai read-only sebagai filter kiri, belum ada tambah/ubah/hapus kategori dari UI — cuma lewat DB/seeder), field `REF_ID`/`TEKS`/`CONFIG`/`SCORING` dari legacy `referensi` yang tidak ikut diimpor (dianggap tidak relevan buat kebutuhan sekarang).

**1905 Master Tindakan — riset lanjutan (2026-08-19).** Legacy `master.tindakan`: ID, JENIS (selalu 0 di data sampel, tidak dipakai berarti), NAMA, PRIVACY, KPTL_NO/KPTL_STATUS (kode kredensialing BPJS), KATEGORI (FK ke `jenis_referensi` "Kategori Tindakan", 12 nilai: Prosedur Non Bedah/Bedah, Konsultasi, Tenaga Ahli, Penunjang, Radiologi, Laboratorium, Bank Darah, Non Kategori, Rehabilitasi, Keperawatan, Sewa Alat), STATUS. Tarif tindakan **terpisah** di tabel `master.tarif` (join by tindakan ID), bukan kolom langsung.
Backend `RME-Backend` punya **dua module yang tumpang tindih secara konsep**: `GeneralProcedure`(`procedures`: code+name+is_active doang, **orphan** — tidak ada module lain yang FK ke tabel ini) vs `GeneralService`(`services`: code+name+category(string)+type_id+is_active+tariffs relation, **dipakai luas** — FK dari `ServiceTariff`,`WardService`,`LayananMedicalProcedure`,`PembayaranInvoiceItem`). `GeneralService` yang jadi padanan real 1905, bukan `GeneralProcedure`.
**Status SIMGOS: Sebagian.** `ServiceListPage`+`ServiceFormPage` (`src/features/GeneralService`, route `/services`) — field Kode/Nama/Kategori (dropdown, **konsumsi langsung Master Referensi** `category=Kategori Tindakan` yang baru diimpor, integrasi rapi antar dua fitur baru sesi ini)/Status/Tarif(read-only dari `current_price`). Diverifikasi end-to-end via API nyata. 20 data seed existing (Fisioterapi, Ganti Verban, dll) sudah pakai `category` string yang cocok persis ke 12 nilai Kategori Tindakan.
**Update (2026-08-26) — sinkronisasi struktur data tindakan + tarif ke simgos og (Fase Tindakan).** Ditambahkan padanan `KPTL_NO`/`KPTL_STATUS` legacy: migrasi kolom `services.kptl_no` (string nullable) + `services.kptl_status` (tinyint nullable; 1=Kecil/2=Sedang/3=Besar/4=Khusus) → model `$fillable`, `StoreServiceRequest`/`UpdateServiceRequest` (`kptl_status` divalidasi `in:1,2,3,4`), `ServiceResource`. Frontend: `ServiceFormPage` sekarang punya switch **"Status KPTL"** yang saat dicentang memunculkan Nomor KPTL + dropdown Jenis KPTL (kalau tak dicentang dikirim `null`). `ServiceListPage` dirombak jadi kolom **No/ID/KPTL (`123.45 (Besar)`)/Nama Tindakan/Kategori Tindakan/Status (toggle `MdSwitch` yang langsung PATCH `is_active` real-time)/Aksi** — plus tombol baru **"Input Tarif"** (ikon `sell`) yang membuka `MdDialog` kelola tarif per kelas rawat (komponen `GeneralServiceTariff/components/ServiceTariffDialog.tsx`). **Tarif tindakan** kini punya 9 komponen (`administrasi`,`sarana`,`bhp`,`dokter_operator`,`dokter_anastesi`,`dokter_lainnya`,`penata_anastesi`,`paramedis`,`non_medis`, semua `decimal(15,2)` default 0) di `service_tariffs`; kolom `price` **dihitung otomatis** di model (`saving` hook = jumlah 9 komponen), `price` di request jadi nullable/optional. Dialog memuat tarif via `/service-tariffs?service_id=` + kelas rawat via `/room-classes`, total per-baris dihitung client-side, simpan/hapus per baris. Diverifikasi: 7 feature test (`GeneralService`+`GeneralServiceTariff`) hijau + tinker (9 komponen → price 45000, update → 43000). Halaman lama `/service-tariffs` (1912) sengaja dibiarkan apa adanya. Tarif penunjang lain (Administrasi/O2/Kamar/Farmasi) ditunda ke fase berikutnya.
❌ Belum ada: field `type_id`/`GeneralServiceType` (FK terpisah yang ternyata tidak dipakai data existing — `service_types` masih 0 baris, jadi sengaja tidak diekspos di form dulu), field `PRIVACY` legacy (belum ada padanan). ✅ `KPTL_NO`/`KPTL_STATUS` + UI kelola Tarif (dialog per-tindakan) sudah ada per update 2026-08-26 di atas.

Status SIMGOS domain 19 keseluruhan: 1902, 1903, 1904, 1905, 1916, 1917, 1918(sebagian — lihat detail di atas), 1921 sudah dibangun; 20 modul lain (dari 28 total) belum.

### [20] Informasi (3 modul)

**Live-display board** murni untuk TV/monitor publik, bukan CRUD. Entry: `informasi.Workspace` — 2 tile: Informasi Pengunjung → `informasi.pengunjung.Workspace` (antrean dgn text-to-speech panggilan nomor, marquee, privilege sembunyikan identitas pasien untuk privasi), Informasi Tempat Tidur → `informasi.tempattidur.Workspace` (board kamar+bed, auto-rotate). Modul 2003 (Informasi Pasien Rawat Inap) tidak ditemukan sebagai tile terpisah — kemungkinan berbagi workspace dengan domain 22.

Status SIMGOS: **belum ada padanan sama sekali** (tidak ada konsep "display board" di frontend saat ini).

### [21] Penjualan (1 modul)

**2101 Penjualan Obat Bebas (OTC)** — `penjualan.Workspace`, 3 tab: Penjualan/History/Retur. Alur: pilih ruangan asal → jenis (resep/non-resep, wajib dokter jika resep) → tambah barang berulang (margin & PPN auto-apply) → simpan → auto-cetak Bukti Penjualan+Etiket. Setiap transaksi cipta Tagihan `JENIS:4` yang lanjut ke **1214** untuk pembayaran — rantai wajib create-then-pay sama seperti Pendaftaran→Tagihan rawat inap.

Status SIMGOS: belum ada.

### [22] Tempat Tidur (3 modul)

**Satu workspace interaktif** `informasi.ruangkamartidur.Workspace` (display board + aksi CRUD ringan) menaungi ketiganya — 2201/2202/2203 hanyalah flag privilege yang menyalakan/mematikan tombol di workspace yang sama, bukan class terpisah. Filter status Kosong/Terpesan/Terisi/Rencana Pulang, klik kartu bed → aksi kontekstual.

**2201 Reservasi Kamar** — klik kamar kosong → form reservasi (`data.model.Reservasi`, nomor auto-generate) → status Terpesan.
**2202 Identitas Pasien Kamar** — flag privilege show/hide NORM+nama di kartu bed (compliance privasi).
**2203 Antrean Tempat Tidur** — pasien yang butuh kamar tapi penuh → antrean dengan prioritas + ruangan/kelas alternatif, field `RESERVASI_NOMOR` menghubungkan ke Reservasi saat diproses.

Status SIMGOS: master data dasar (`GeneralBed`/`GeneralRoom`) ada, tapi workspace interaktif reservasi/antrean/display board belum ada. **Dicek field-level 2026-08-20** (`pendaftaran.antrian.tempattidur.Form` dan `pendaftaran.reservasi.Form` di legacy) vs backend `PendaftaranBedQueue`/`PendaftaranReservation` yang sudah ada — **mismatch signifikan**, dirinci di §6 (Laporan Gap Backend) di bawah. Kesimpulan: jangan dibangun dulu tanpa rework backend.

### [23] Inventory (6 modul)

**Pola workspace-per-dokumen**, siklus procurement penuh: **2301 Permintaan** (ajukan→terima, 2 tahap) → **2302 Penerimaan Supplier** (per faktur PBF, dengan pembatalan via record terpisah — audit trail) → **2303 Pengiriman** (surat jalan, selalu berasal dari suatu Permintaan) → **2304 Stok Opname** (status enum Batal/Proses/Final, rekonsiliasi ke level batch/expired: `AWAL`/`SISTEM`/`MANUAL`/`EXD`). **2305 Rekanan/Supplier** = master data CRUD sederhana (namespace `master.*`, bukan `inventory.*`). **2306 Distribusi Barang** = mirip Pengiriman tapi untuk logistik non-klinis (ATK unit administrasi), dengan tombol "Final Distribusi" mengunci dokumen.

Status SIMGOS: belum ada, **kecuali 2305 Rekanan/Supplier** — dibangun 2026-08-20, ternyata entitas yang sama persis dengan 1907 Master Penyedia (`InventorySupplier`/`suppliers` table), cukup ditautkan ke `/suppliers` yang sudah ada, tidak perlu kode baru.

### [24] Pencarian (1 modul)

**2401 Pencarian Terpadu Pasien** — Bukan halaman terpisah, melainkan kotak pencarian **di header aplikasi** (`SIMpel.view.modules.Pencarian`, selalu terlihat). Kombobox jenis pencarian: **Pasien, Pegawai, Berkas Klaim, Berkas Klaim Obat**. Klik hasil → memakai mekanisme `createWorkspace` yang sama (fokus/buka `pasien.Workspace`).

Status SIMGOS: hanya `PatientPicker` (search-by-name kecil, tertanam **di dalam** form lain) — **gap arsitektural signifikan**: belum ada global search bar top-nav multi-entitas dengan efek buka-workspace seperti legacy.

### [25] Integrasi (2 modul)

Murni backend-to-backend, tidak ada workspace dokumen sendiri.

**2501 Bridging BPJS (VClaim & MJKN)** — SEP (`InsertSEP`, payload+response terenkripsi AES), Rencana Kontrol/SPRI (`bpjs.rencana_kontrol`, dibedakan field `jnsKontrol`), status klaim (`bpjs.klaim`, biaya pengajuan vs setujui vs tarif gruper), plus **Aplicares** (sinkron ketersediaan kamar/tempat tidur ke BPJS via cron).

**2502 Bridging LIS Laboratorium** — Multi-vendor (winacom/novanet/vanslab), bridging via **shared database** (bukan REST), job scheduler cron periodik untuk pull hasil & push status order.

Status SIMGOS: belum ada.

### [26] Monitoring (10 modul)

Hanya **2690 (Database Session)** punya backend module `Monitoring` sungguhan (`information_schema.PROCESSLIST` — tooling admin DB, bukan alur bisnis pasien, prioritas rendah untuk direplikasi). Sisanya adalah **live-grid read-model** di atas data domain asal:

- **2601 Nilai Kritis Lab** — read-back verbal (pelapor→penerima) untuk hasil kritis lab/rad.
- **2602/2603 Monitoring Hasil Lab/Rad** — tracking tahapan terima→periksa→verifikasi→kirim.
- **2604 Konsul MPP** — tidak ada bukti kuat modul MPP terpisah dari mekanisme Konsul umum.
- **2605/2606 Jadwal Kontrol & SPRI** — **ternyata SATU modul UI BPJS yang sama**, dibedakan field `jnsKontrol` — rekomendasi gabung jadi satu modul di SIMGOS.

**Status SIMGOS 2605: Sebagian (2026-08-23).** Dibangun tab "Jadwal Kontrol" di `VisitWorkspacePage` (grup Administrasi) menggunakan `MedicalRecordControlSchedule` (`control-schedules`, full CRUD, **gap backend diperbaiki**: `ControlScheduleController::index()` tidak punya filter → ditambah `?visit_id=`+`?patient_id=`). Field: `scheduled_date` (wajib date), `medical_department_id` (picker `GeneralMedicalDepartment`), `purpose`, `notes`, `scheduled_by` (picker `GeneralEmployee`), `status` (`scheduled`/`completed`/`cancelled`, dropdown ubah-status). List + buat + ubah status + hapus. ❌ Belum ada: integrasi VClaim BPJS (pembuatan nomor surat kontrol resmi BPJS `noSuratKontrol` — terpisah di modul `BpjsVClaim`), cetak surat kontrol PDF.
- **2607 SIKEPO** — istilah tidak ditemukan literal; bukti mengarah ke fitur BPJS **Aplicares** (ketersediaan kamar).
- **2608 Pasien Meninggal** — form sertifikat kematian lengkap dengan alur verifikasi 2-tahap (bukan monitoring pasif).
- **2609 Status Klaim BPJS** — siklus pengajuan→grouping→approval/dispute→top-up.

Status SIMGOS: belum ada.

### [27] Akses API (3 modul)

**Bukan halaman UI sama sekali** — `CLASS=NULL` di DB untuk semua node, 0 kemunculan terkait di app.js. Endpoint backend aktif ada (Pegawai, Pengguna, Group Jenis Pemeriksaan) tapi tanpa UI governance token. Untuk SIMGOS ini **peluang perbaikan**, bukan porting: endpoint REST terdokumentasi + opsional halaman admin manajemen API key (legacy tidak punya sama sekali).

### [28] Logs (1 modul)

**2801 Audit Log TTE** — Integrasi ke Balai Sertifikasi Elektronik (BSrE/BSSN), log otomatis tiap pemanggilan sign dokumen (Resume Medis, Surat Medis). **Fully append-only** (create+fetch saja) — pola paling ketat di seluruh riset, cocok jadi acuan pola audit log generik SIMGOS.

Status SIMGOS: belum ada.

### [29] Pelayanan KPO (1 modul)

**2901** — Dua sisi kerja berbeda arsitektur: **sisi Dokter/Perawat** (embed di workspace kunjungan, order resep+checklist puasa/pulang/CITO) dan **sisi Farmasi/Apoteker** (`layanan.kpo.farmasi.Workspace`, worklist queue bukan per-record — daftar pasien kiri, detail+telaah resep kanan, kajian wajib per-variabel jika config aktif). Terhubung erat ke 1115 (KPO di domain Layanan — representasi UI yang sama).

Status SIMGOS: belum ada.

### [30] Berkas (2 modul)

**3001 Manajemen Berkas Klaim** — Agregator lintas-domain paling luas di seluruh sistem: kumpulkan Resume Medis+hasil Lab/Rad/PA+farmasi+tagihan+dokumen pendukung → **gabung jadi satu PDF** (TCPDF/TCPDI) → simpan sbg berkas+detail urutan dokumen.

**3002 Monitoring Kelengkapan Berkas Klaim** — Filter wajib Penjamin+Status+Jenis Kunjungan+Periode (validasi backend menolak request tanpa ini) → grid status kelengkapan per kategori dokumen, dengan komentar koordinasi antar-unit (append-only, tidak mengubah data sumber).

Status SIMGOS: belum ada.

---

## 4. Rekomendasi Prioritas Berikutnya

Berdasarkan seluruh riset, urutan yang masuk akal untuk fase pembangunan selanjutnya (melengkapi §5 Fase Pengerjaan di `DOKUMEN-PRODUK-2026-08-18.md`):

1. **Layanan (11)** — domain besar yang sepenuhnya kosong, prasyarat untuk alur "pasien sampai diperiksa dokter" yang jadi pemicu riset ini. Mulai dari 1101 (worklist kunjungan aktif) → 1102 (tindakan medis) → 1103 (order lab/rad/resep/konsul), karena ini fondasi yang paling sering dipakai harian.
2. **`CashierSession` (1209)** — entitas baru, prasyarat realistis untuk 1201 Final Tagihan yang benar (guard kasir-terbuka). Tanpa ini, alur pembayaran SIMGOS akan terus jadi versi terlalu sederhana dari kebutuhan riil.
3. **Global search bar (2401)** — gap arsitektural yang berdampak ke seluruh UX aplikasi (pola "cari lalu buka/fokus workspace" dipakai di mana-mana di legacy), bukan cuma satu modul kecil.
4. **Rekam Medis lanjutan (13)** — 1302 Anamnesis, 1303 TTV, 1310 Resume Pulang — melengkapi CPPT+Diagnosis yang sudah ada, karena RM adalah tab di dalam workspace kunjungan yang sama dengan yang dibangun di prioritas #1.
5. **Laporan (14)** — perlu keputusan desain besar dulu (generic report browser dari nol) sebelum eksekusi, karena legacy sendiri tidak punya referensi UI yang bisa di-port.

## 5. Keputusan Arsitektur yang Masih Terbuka

Daftar ini perlu diputuskan bersama sebelum implementasi besar dimulai, karena dampaknya lintas puluhan modul:

- ~~**Nested MDI workspace vs route standar React**~~ — **DIPUTUSKAN (2026-08-19): pola hybrid.** Level atas (pasien/kunjungan/tagihan sbg entitas utama) tetap route + tab MDI (`MdTabBar`/`TabContext`/`useTabNavigation` yang sudah ada — dedup otomatis per route, ini yang paling terasa user, wajib dipertahankan). Untuk nested sub-modul di dalam satu entitas (19 modul RM di dalam 1 kunjungan, 7 sub-tab Tagihan di dalam 1 invoice) — **jangan** dipaksa jadi MDI tab terpisah lagi, cukup **in-page tabs** (satu route/halaman, tab internal biasa, mis. `MdTabs`). Dapat manfaat routing standar (URL shareable, gampang maintain, tidak perlu reimplementasi `createWorkspace`/`createTab`) tanpa kehilangan mental model "satu workspace = semua hal tentang entitas ini". Implementasi pertama pola ini: `VisitWorkspacePage` (`/visits/:id`) — lihat `CATATAN.md` untuk status.
- **Lock granular per-entitas (pola SIMGOS sekarang) vs lock global per-kunjungan (pola legacy Final Hasil)** — pertahankan append-only per CPPT/Diagnosis saja, atau ikuti legacy yang mengunci semua sub-form RM sekaligus saat kunjungan final?
- **Sesi kasir sebagai entitas eksplisit** — perlu `CashierSession` model baru di backend, memengaruhi desain endpoint Invoice/Payment.
- **Modul yang legacy-nya sendiri tidak jelas/tidak lengkap** (1012, 1013, 1314, 1316, 1317, 2604) — desain baru berdasar kebutuhan regulasi/bisnis terkini, bukan replikasi 1:1, karena tidak ada yang bisa direplikasi.
- **Domain 14 (Laporan)** — bangun generic report browser (mengikuti desain DB `jenis_laporan` yang sudah ada) atau report per-modul yang menempel di masing-masing fitur (mengikuti pola nyata legacy: tombol cetak tersebar)?

## 6. Laporan Gap Backend (per 2026-08-20) — Modul yang Butuh Kerjaan Backend Sebelum Frontend Bisa Dibangun

Konsolidasi seluruh temuan "sudah dicek ke legacy, TAPI backend saat ini tidak cukup/tidak cocok" dari sesi 2026-08-19 s/d 2026-08-20, supaya bisa direncanakan sebagai kerjaan backend terpisah. Semua ini **sudah dicek ke layar asli SIMpel dulu** sesuai kebiasaan tetap — bukan tebakan.

### 6.1 Kelompok "Penjamin" — butuh tabel master perusahaan penjamin/asuransi berdiri sendiri
Tiga modul katalog SIMGOS bergantung ke tabel `guarantors` (`PendaftaranGuarantor`) yang **scoped ke registrasi** (`registration_id`, `payer_type` string bebas, `member_number`, `room_class_id`, `reference_letter_number`, `notes`, `status`) — bukan tabel master perusahaan penjamin yang berdiri sendiri:
- **1913 Master Margin Obat** — konfigurasi margin harga jual obat per penjamin.
- **1914 Penjamin Ruangan** — mapping penjamin yang diterima per ruangan/poliklinik.
- **1922 Penjamin Rumah Sakit** — master perusahaan asuransi/instansi rekanan/BPJS itu sendiri.

**Rekomendasi backend**: tabel master baru (mis. `payers`/`insurers`) dengan `name`, `type` (BPJS/Asuransi/Instansi/dll), `code`, kontak, `is_active` — lalu `guarantors.payer_type` diganti FK ke tabel ini (breaking change kecil, perlu migrasi data).

### 6.2 `PendaftaranGuarantor` (Penjamin per-kunjungan) — field BPJS SEP tidak ada
Legacy `pendaftaran.penjamin.Form`: field level-atas `JENIS` (referensi kategori 10), `PEGAWAI_JENIS`/`PEGAWAI_NIP`/`KENAKAN_TARIF` (tarif pegawai RS sendiri), plus **sub-form dinamis per jenis penjamin** — khusus BPJS mencakup seluruh siklus SEP (`buatSep`/`batalkanSep`/`updateSep`/`updateTglPulangSep`/`cetakSep`), rujukan (`rujukan`/`perpanjanganRujukan`/`cetakRujukan`), rencana kontrol, SPRI, riwayat SEP. Backend `guarantors` saat ini cuma 7 kolom generik, tidak ada satupun field SEP.

**Rekomendasi backend**: tabel `guarantors` perlu kolom tambahan khusus BPJS (nomor SEP, tanggal SEP, faskes perujuk, kelas hak, dst — idealnya kolom JSON fleksibel per `payer_type` daripada puluhan kolom nullable) + endpoint aksi terpisah (buat/batal/update SEP) yang pada akhirnya juga butuh integrasi ke BPJS VClaim (lihat `Modules/BpjsVClaim` — sudah ada modul integrasinya di backend, belum pernah dicek kesiapannya).

### 6.3 Domain 22 (Tempat Tidur) — model data antrean/reservasi tidak cocok
- **`PendaftaranBedQueue`** (`bed_queues`: `bed_id` wajib + `patient_id` wajib + `queue_number`) vs legacy `pendaftaran.antrian.tempattidur.Form` yang antre berdasar **ward+kelas pilihan (dengan alternatif)**, belum tentu ada bed spesifik, plus field `DIAGNOSA`/`PRIORITAS`/`DPJP` yang tidak ada di backend sama sekali.
- **`PendaftaranReservation`** (`reservations`: `patient_id` wajib FK + `ward_id`) vs legacy `pendaftaran.reservasi.Form` yang mengizinkan **nama+kontak bebas (bukan pasien terdaftar)**, terikat ke **bed spesifik** (bukan cuma ward), dan tertaut ke entry antrean tempat tidur (`REFERENSI.ANTRIAN_TEMPAT_TIDUR`).

**Rekomendasi backend**: `bed_queues` perlu `ward_id`+`room_class_id` (bukan `bed_id` wajib) + `diagnosis`+`priority`+`dpjp_id`+kolom alternatif ward/kelas; `reservations` perlu `patient_id` nullable + kolom nama/kontak walk-in + `bed_id`+FK opsional ke `bed_queues`.

### 6.4 1925 Master Farmasi — 11 layar legacy, awalnya dikira cuma 1 yang ada backend-nya
`master.farmasi.*` di legacy: `DepoLayanan` (✅ ada backend, `GeneralPharmacyDepot`/`GeneralPharmacyServiceRoom`, belum dibangun frontend-nya — bisa langsung dikerjakan tanpa backend baru), `MappingFrekuensiKategori`, `MappingGolonganPenjamin`, `MappingRetriksiFormularium`, `MappingRetriksiUnitAsal`, `PpnPenjualan`, `RetriksiAntibiotik`, `RetriksiDiagnosa`, `RetriksiDpjp`, `RetriksiHari`, `RetriksiJumlah`, `frekuensiaturanresep`.

**Koreksi 2026-08-20**: klaim awal "10 dari 11 tidak ada tabel backend sama sekali" **keliru** — waktu menelusuri 1116 Formulir Antimikroba, ditemukan `GeneralAntibioticRestriction` (`antibiotic_restrictions`: `antibiotic_name`/`aware_category` WHO AWaRe/`requires_pra_approval`/`restriction_condition`/`is_active`) yang **persis cocok** dengan `RetriksiAntibiotik`. Berarti setidaknya **2 dari 11** (`DepoLayanan` + `RetriksiAntibiotik`) punya backend siap pakai — kemungkinan modul retriksi lain (`RetriksiDiagnosa`/`RetriksiDpjp`/`RetriksiHari`/`RetriksiJumlah`/`MappingRetriksiFormularium`/`MappingRetriksiUnitAsal`) juga sudah ada tapi belum ditelusuri satu-satu (nama modul `Modules/General*Restriction`/`Modules/General*Mapping` belum di-grep lengkap). `RetriksiAntibiotik` sudah dibangun jadi halaman `/antibiotic-restrictions` (dipakai sebagai sumber dropdown di tab "Antimikroba" `VisitWorkspacePage`, lihat §3 domain 11 untuk 1116).

**Rekomendasi**: sebelum menyimpulkan modul lain di 1925 "tidak ada backend", cek dulu satu-satu (`ls Modules | grep -iE "Restriction|Diagnosis.*Restrict|Dpjp|Duration|Formulary|Quantity|PrescriptionOrigin"`) — pola penamaan modul ternyata tidak selalu `Farmasi*`, banyak yang masuk grup `General*Restriction` yang mudah terlewat saat grep awal.

### 6.5 1915 Master BPJS / 1919 Master Kemenkes — bukan soal CRUD, tapi integrasi API eksternal
Tidak ada tabel config sederhana untuk ini. BPJS punya ~9 modul integrasi terpisah di backend (`BpjsVClaim`, `BpjsPCare`, `BpjsAntreanRs`, `BpjsAntreanFktp`, `BpjsAplicares`, `BpjsApotek`, `BpjsICare`, `BpjsRekamMedis`, `BpjsSmartClaim`) yang belum pernah dicek kesiapannya satu-satu. Kemenkes tidak ada modul `Kemkes*` selain `KemkesBloodType` (sudah dipakai). **Beda kelas pekerjaan** dari modul master data biasa — butuh keputusan desain integrasi (API key management, webhook, dll), bukan form CRUD.

### 6.6 1926 Master Non Pegawai / 1928 Pengaturan Rekam Medis — tidak ada padanan legacy
Dicari ke seluruh `app.js` (variasi kata kunci pegawai/karyawan/staff/mitra/konsultan/pihak-ketiga untuk 1926; rekammedis/pengaturan untuk 1928) — **nihil**. Kemungkinan entri katalog SIMGOS ini aspirational/fitur baru yang tidak ada di SIMpel, perlu klarifikasi kebutuhan bisnis dulu sebelum didesain (tidak ada yang bisa "di-port").

### 6.7 Domain 13 Rekam Medis — Anamnesis & Riwayat Alergi kehilangan integrasi terminologi SatuSehat/Kemkes
Legacy `rekammedis.js` (package terpisah, bukan `app.js`) untuk **1302 Anamnesis Pasien** ternyata **jauh lebih besar** dari asumsi awal — bukan satu form, tapi puluhan sub-workspace: `anamnesis.umum`, `anamnesis.diperoleh` (sumber anamnesis — cocok ke `MedicalRecordAnamnesisSource`), `anamnesis.riwayat.alergi`, `anamnesis.batuk` (skrining TB), `anamnesis.edukasi.emergency`/`.endoflife`, dst.

- **`rekammedis.anamnesis.umum.Form`** (anamnesis utama per kunjungan): cuma **2 field** — `SNOMED_CT_ID` (combo kode SNOMED CT via `kemkes-satusehat-pengaturan-snomedct-combo`) + `DESKRIPSI` (textarea bebas). Auto-create satu record per kunjungan, autosave on-blur. **Backend `anamneses` (`MedicalRecordAnamnesis`) punya bentuk yang sama sekali berbeda**: 5 kolom teks panjang terpisah (`present_illness_history`/`past_medical_history`/`family_medical_history`/`allergy_history`/`social_history`) ala format EMR Barat, tidak ada satupun kolom untuk kode SNOMED.
- **`rekammedis.anamnesis.riwayat.alergi.Form`**: `JENIS` (referensi kategori 180) + `KODE_SNOMED` **atau** `KODE_BZA` (kode obat KFA, mutually exclusive) + `DESKRIPSI`. Backend `allergies` (`MedicalRecordAllergy`) punya `category`+`allergen`+`reaction`+`severity`+`is_active` — mirip secara konsep (ada "kategori") tapi **tidak ada kolom kode SNOMED/BZA sama sekali**, dan sebaliknya backend punya `reaction`/`severity` yang tidak muncul di form legacy ini.

**Akar masalah bersama**: legacy SIMpel sudah terintegrasi dalam ke ekosistem terminologi standar Kemkes SatuSehat (SNOMED CT untuk diagnosis/anamnesis, KFA/BZA untuk obat) — ada modul backend `Kemkes*SatuSehat*` pendukungnya di legacy. SIMGOS backend saat ini **tidak punya tabel referensi SNOMED CT/KFA sama sekali** (beda dari 328 kategori Master Referensi biasa yang sudah diimpor — itu referensi internal RS, bukan terminologi kesehatan standar nasional/internasional). **Rekomendasi**: sebelum membangun modul Rekam Medis lanjutan manapun (1302 dst), perlu diputuskan apakah SIMGOS akan mengimpor subset SNOMED CT/KFA (datasetnya besar, ribuan-jutaan entri) atau pakai pendekatan lain (mis. free-text search ke API SatuSehat live, bukan tabel lokal) — ini keputusan arsitektur, bukan sekadar tambah kolom.

### 6.8 Domain 12 Pembayaran — 1206 Non Tunai (EDC) kehilangan detail transaksi kartu
`/invoices` (Payment) sudah punya selector metode (Tunai/Debit/Kredit/Transfer) yang secara fungsional jalan, TAPI dicek ke legacy `pembayaran.tagihan.nontunai.edc.Form`: form aslinya menangkap jauh lebih detail — `JENIS_KARTU_ID` (Master Card/Visa), `NO_ID` (nomor kartu), `NAMA` (nama pemilik kartu), `BANK_ID` (referensi bank penerbit, kategori 16), `REF` (kode approve/approval code transaksi), plus `TANGGAL`. Backend `payments` (`PembayaranPayment`) cuma punya `payment_method` (string bebas) + `amount` + `admin_fee` — **tidak ada kolom untuk nomor kartu/bank/kode approve sama sekali**.

**Rekomendasi**: ini gap yang relatif kecil dibanding 6.7 (bukan soal integrasi eksternal, cuma nambah kolom) — cukup tambah `card_number`, `card_holder_name`, `issuing_bank`, `approval_code` (nullable, hanya relevan utk `payment_method` in [debit, credit]) ke tabel `payments`, lalu frontend `PaymentList.tsx` tinggal expose field tambahan saat metode non-tunai dipilih. Bisa dikerjakan sebagai peningkatan kecil kapan saja tanpa menunggu keputusan arsitektur besar.

### 6.9 Konfirmasi POLA — kontras dengan modul yang BERHASIL dibangun tanpa hambatan
Sebagai pembanding: **1111 Pemakaian BHP Ruangan** dicek ke legacy `layanan.bhp.Form` (field: `BARANG` combo + `JUMLAH` angka) dan **cocok bersih** dengan backend `ward_stock_transactions`/`ward_item_stocks` (`InventoryWardStockTransaction`/`InventoryWardItemStock`) yang sudah punya logic ledger+saldo otomatis (increment/decrement stok, validasi stok cukup untuk transaksi keluar) — dibangun 2026-08-20 di `/ward-stock-usage`, tanpa perlu perubahan backend. Ini membuktikan pola bukan "semua modul operasional rusak", tapi spesifik ke area yang **butuh integrasi eksternal/terminologi kesehatan berat** (BPJS, SatuSehat/SNOMED, transaksi kartu) — modul operasional yang scope-nya "cuma" pergerakan data internal RS (stok, ledger, master data) cenderung tetap solid.

**Update 2026-08-20 (lanjutan)**: **1104 Feedback/Hasil Pemeriksaan** dicek field-level dan **cocok bersih**, dibangun sebagai tab "Lab" baru di `VisitWorkspacePage`:
- Legacy `layanan.laboratorium.order.Form`: `TUJUAN` (ruangan tujuan lab), `DOKTER_ASAL` (dokter perujuk), `ALASAN` (diagnosa), `KETERANGAN` (catatan), `TANGGAL` (waktu order), `STATUS_PUASA_PASIEN` (checkbox puasa — **tidak ada di backend**, gap kecil), Prioritas/Cito (checkbox). Cocok ke backend `lab_orders` (`LayananLabOrder`): `destination`≈TUJUAN, `ordered_by`≈DOKTER_ASAL, `reason`≈ALASAN, `notes`≈KETERANGAN, `ordered_at`≈TANGGAL, `is_emergency`≈Prioritas.
- Legacy `data.model.HasilLab` (model hasil per parameter, `serviceName:"layanan/hasillab"`): `TINDAKAN_MEDIS`+`PARAMETER_TINDAKAN` (nama tes+parameter), `HASIL` (nilai), `NILAI_NORMAL`, `SATUAN`, `KETERANGAN`, `DOKTER` (dokter pemverifikasi — **tidak ada di backend**, gap kecil), `OLEH`, `STATUS`. Cocok ke backend `lab_results` (`LayananLabResult`): `test_name`≈TINDAKAN_MEDIS+PARAMETER_TINDAKAN (digabung jadi satu field, legacy pisah dua — simplifikasi kecil), `result_value`≈HASIL, `normal_range`≈NILAI_NORMAL, `unit`≈SATUAN, `notes`≈KETERANGAN, `recorded_at`≈TANGGAL, `recorded_by`≈OLEH, `status`≈STATUS.
- **Catatan implementasi**: `LayananLabOrderItem` (`lab_order_items`, baris pemeriksaan yang diminta) **endpoint index-nya tidak punya filter `?lab_order_id=`** — frontend mengambil `per_page=200` lalu filter di client. Perlu ditambahkan filter di backend kalau jumlah item lintas semua order sudah banyak (lihat `Modules/LayananLabOrderItem/app/Http/Controllers/LabOrderItemController.php`).
- **Bug backend kecil ditemukan**: `LabOrderController::store()` tidak memanggil `->refresh()` setelah `create()`, jadi response awal setelah simpan order menunjukkan `status: null` walau migrasi punya `default('pending')` — baru benar setelah reload/index ulang. Tidak menghalangi (frontend sudah reload otomatis setelah create), tapi seharusnya perbaikan satu baris (`$order->refresh()`) di backend.
- Ini mengonfirmasi ulang pola §6.9: modul yang scope-nya "internal RS" (order+catat hasil, bukan integrasi eksternal) tetap solid meski struktur datanya beda granularitas kecil dari legacy.

**Update 2026-08-20 (lanjutan lagi)**: **1105 Pembacaan Ekspertise** juga cocok, dibangun sebagai tab "Radiologi" baru (`src/features/LayananRadiologyOrder`), pola identik dengan tab Lab (order→item→hasil→status). Field-parity lebih detail ada di §3 domain 11.
- **Gap endpoint index lebih parah dari Lab**: `LayananRadiologyOrder`/`LayananRadiologyOrderItem`/`LayananRadiologyResult` — **ketiga-tiganya** endpoint index **tidak punya filter sama sekali** (bahkan `radiology-orders` tidak terima `?visit_id=`, padahal `lab-orders` setidaknya terima `?visit_id=`). Frontend mengatasi dengan fetch `per_page=200` untuk ketiganya lalu filter penuh di client (termasuk filter visit_id untuk order, yang mestinya jadi query parameter backend). Ini akan jadi masalah performa nyata kalau jumlah order/hasil radiologi di seluruh RS sudah besar — prioritas tinggi untuk ditambah filter di backend (`?visit_id=` di `RadiologyOrderController::index()`, `?radiology_order_id=` di dua controller item/result-nya) dibanding gap serupa di Lab.
- **Ketidakkonsistenan desain kecil**: `StoreRadiologyOrderRequest`/`StoreRadiologyResultRequest` menandai `status` sebagai `required`, padahal `RadiologyOrderController::store()`/`RadiologyResultController::store()` punya fallback `$data['status'] ?? 'pending'` yang tidak akan pernah tereksekusi karena validasi sudah menolak request tanpa `status` duluan (dead code). Frontend mengirim `status` eksplisit untuk menghindari 422, tapi baiknya validasi dibikin `sometimes` biar fallback-nya benar-benar jalan, konsisten dengan pola `LabOrder`.
