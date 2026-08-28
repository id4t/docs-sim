# UI/UX Key — SIMGOS Frontend

> **ARSIP BASELINE UI LEGACY — bukan spesifikasi UX target.** Gunakan [`../UI-UX.md`](../UI-UX.md) untuk keputusan aktif.

**Prinsip utama:** UX SIMGOS **meniru SIMpel legacy** (ExtJS desktop-style app di `/var/www/html/production/webapps/application/SIMpel`) sedekat mungkin — pengguna sudah terlatih bertahun-tahun di sana, jadi migrasi UI tidak boleh memaksa mereka belajar ulang pola kerja. Yang berubah hanya *rendering engine* (ExtJS → React), bukan *mental model* navigasi/interaksi.

**Sistem desain — WAJIB, tidak bisa dinegosiasi:** UI SIMGOS **hanya boleh pakai komponen Google Material Design** — M2 atau M3 sama-sama boleh (versinya tidak masalah), yang **tidak boleh** adalah komponen dari sistem desain lain: shadcn, Tailwind UI generik, atau custom component yang tidak mengikuti spec Material sama sekali. Ini aturan keras dari product owner — lihat §2 untuk status kepatuhan saat ini (ada gap yang harus diperbaiki).

---

## 1. Referensi Pola dari SIMpel Legacy

SIMpel dibangun di atas **Sencha ExtJS Classic** (`classic/resources/ext`, folder `ux`) — pola khas aplikasi desktop-in-browser:

| Pola ExtJS legacy | Padanan yang harus direplikasi di SIMGOS |
|---|---|
| Tree/accordion menu di sidebar, dikelompokkan per domain fungsional | `MdNavigationDrawer` (sidebar kiri) dikelompokkan per domain (Pendaftaran, Layanan, Pembayaran, dst) — **sudah diterapkan** |
| MDI (Multi-Document Interface): tiap modul dibuka sebagai **tab window** baru, bukan navigasi full-page | `MdTabBar` + `TabContext` + `useTabNavigation` — tab baru terbuka otomatis saat pindah halaman, path lama tetap "hidup" di tab lain — **sudah diterapkan**, ini adalah kesamaan paling krusial dengan SIMpel dan wajib dipertahankan konsisten di semua modul baru |
| Grid (Ext.grid.Panel) dengan toolbar atas: search box, filter, tombol Tambah/Edit/Hapus/Cetak | Pola List Page: tabel + toolbar atas (lihat §3) |
| Window/Form modal untuk create-edit, bukan halaman terpisah untuk kasus sederhana | SIMGOS saat ini pakai halaman form terpisah (`/patients/new`, dst) — **penyimpangan minor dari legacy**, dapat diterima karena form medis SIMGOS sering panjang (nested list Kontak/Keluarga) sehingga modal kurang cocok; tetap pertahankan tab-based navigation supaya kembali ke list tidak kehilangan konteks |
| Top toolbar dengan info user, switch ruangan aktif, notifikasi | `MdTopAppBar` + `MdRuanganSwitcher` — **sudah diterapkan** |
| Menu drawer besar berisi seluruh katalog modul (top menu horizontal ExtJS) | `MdTopDrawer` — berisi 147 modul katalog, termasuk yang belum dibangun (scaffold) — **sudah diterapkan** |
| Konvensi penomoran dokumen otomatis per tahun (mis. nomor RM, nomor registrasi) | `REG-{tahun}-{seq}`, `KJ-{tahun}-{seq}`, `INV-{tahun}-{seq}` — **konsisten dengan konvensi legacy** |
| Dropdown lookup dari master data (bukan free text) untuk field seperti Pendidikan/Pekerjaan/Agama/Gol. Darah | Wajib pakai `MdSelect` terhubung API referensi, jangan text input bebas — pernah jadi bug ditemukan di audit Fase 1 |

**Aturan kerja saat membangun modul baru:** sebelum menganggap satu modul "selesai", verifikasi field-by-field terhadap layar SIMpel asli (screen legacy yang setara) — jangan asumsikan struktur field seragam antar modul. Ini sudah terbukti perlu (audit Fase 1 menemukan banyak field hilang dari build pertama: Kontak, Kartu Identitas, dropdown Spesialis, dll).

## 2. Design Tokens — Material Design (M2/M3, bebas versi) — TIDAK BOLEH ADA SISTEM LAIN

**Status saat ini sebagian menyalahi aturan.** Kode `RME-Frontend` punya *dua* lapis desain:
1. **Custom Material Design 3** (`src/components/material/Md*.tsx`, token `--md-sys-color-*`, `--md-ref-typeface-*` di `index.css`) — dipakai di hampir semua halaman yang sudah dibangun (`MdNavigationDrawer`, `MdTopAppBar`, `MdTabBar`, `MdButton`, dst). **Ini sah** — M3 termasuk Material Design dari Google, tidak perlu diturunkan ke M2.
2. Sisa `shadcn`/Tailwind dari fase awal (`src/components/ui/`, disebut di `CLAUDE.md`) — **ini yang melanggar aturan**, shadcn bukan Material Design, harus dihapus/diganti komponen `Md*` yang setara.

**Aturan yang berlaku:** versi Material Design (M2 atau M3) tidak masalah dan tidak perlu diseragamkan secara paksa — yang mutlak dilarang adalah **komponen dari sistem desain non-Material** (shadcn, Tailwind UI generik, atau custom component yang tidak mengikuti spec Material sama sekali). Jadi:
- **Tidak perlu** migrasi M3 → M2. Token `--md-sys-color-*` dan komponen `Md*.tsx` yang ada sekarang boleh dipertahankan dan dikembangkan terus.
- **Wajib**: audit & hapus sisa `src/components/ui/` (shadcn) — ganti semua pemakaiannya dengan komponen `Md*` yang sepadan (Button, Card, TextField, Select, Dialog, dst sudah ada di `src/components/material/`).
- Semua warna lewat token terpusat `--md-sys-color-*` (bukan hardcode hex di komponen) — ini sudah konsisten dengan aturan, pertahankan.
- Palet warna existing (`#006877` teal medis, `#4a6268` slate cyan, `#525e7d` indigo, `#ba1a1a` error) tetap dipakai apa adanya, sudah dipetakan ke token M3 dengan benar.

**Aturan ke depan untuk semua kerja UI baru maupun refactor:**
- Semua komponen UI baru **harus** dari `src/components/material/Md*.tsx` (atau tambahan baru yang konsisten mengikuti spec Material), tidak pernah dari `src/components/ui/` atau library non-Material lain (jangan import MUI/Ant Design/Chakra/dll juga, meski itu bukan shadcn — prinsipnya sama: harus Material dari Google, bukan sistem lain).
- Kalau ada komponen yang belum ada versi `Md*`-nya dan masih dipakai dari `src/components/ui/`, buat versi M3-nya dulu di `src/components/material/` sebelum dipakai, jangan pertahankan shadcn "untuk sementara".
- Lihat `NOTES.md` §12 untuk daftar kerja bersih-bersih shadcn ini.

## 3. Pola Halaman (Page Patterns)

### 3.1 List Page
Dipakai di semua modul `*ListPage.tsx` (Employee, Patient, Ward, Registration, Visit, Invoice, dst).
- Toolbar atas: judul modul + tombol aksi utama (Tambah Data) di kanan.
- Tabel data dengan kolom relevan (nomor otomatis, nama/identitas utama, status, aksi baris).
- Aksi per baris: Edit, Detail/Lihat, aksi khusus modul (mis. ikon stetoskop → Diagnosis di VisitListPage, ikon assignment → WardAssignmentsPage).
- Search/filter di toolbar bila daftar berpotensi panjang (Pasien, Pegawai).
- **Konsisten dengan grid ExtJS legacy**: user harus bisa scan cepat & langsung klik baris untuk aksi, bukan drill-down berlapis.

### 3.2 Form Page (Create/Edit)
- Halaman terpisah (bukan modal), route `.../new` dan `.../:id/edit` memakai komponen form yang sama.
- Nested list component untuk data 1-ke-banyak dalam satu form: `ContactList`, `IdentityCardList`, `FamilyList` (Family berisi nested Kontak+Kartu Identitas per anggota).
- **Bug historis penting**: nested `<form>` di dalam `<form>` menyebabkan submit nyasar ke form induk. Semua shared list component sudah diganti jadi `div` + button biasa (bukan `<form>` bersarang) — **pola ini wajib diikuti untuk komponen list baru**, jangan pernah taruh elemen `<form>` di dalam form lain.
- Field lookup/referensi (Pendidikan, Pekerjaan, Status Kawin, Golongan Darah, Kewarganegaraan, Suku, Bahasa, Spesialis, dll) **wajib dropdown terhubung API**, bukan text bebas.
- Field turunan otomatis (umur dari tanggal lahir) dihitung client-side, autocomplete untuk field seperti tempat lahir.
- Cascading select: Ward → Room → Bed (pola dependent dropdown, opsi anak hilang/reset saat induk berubah).
- Picker search-select untuk relasi ke entitas besar: `PatientPicker` (cari pasien), picker dokter dari Employee — bukan dropdown panjang biasa, karena datanya bisa ribuan baris.

### 3.3 Detail Page + Nested Sub-list
Dipakai untuk entitas induk yang punya anak dinamis, contoh utama: `InvoiceDetailPage`.
- Header: info entitas induk (nomor invoice, status, total).
- Sub-list nested: `InvoiceItem` (tambah item → subtotal auto-recalculate server-side), `Payment` (create-only, tanpa edit/delete — sengaja).
- **Auto-lock UI**: begitu kondisi bisnis terpenuhi (mis. `totalPaid >= total_amount`), form tambah item/bayar otomatis disembunyikan dari UI, bukan cuma di-disable — mencerminkan status finalized yang tidak reversibel dari sisi UI.

### 3.4 Append-Only Record Page
Dipakai untuk data legal/rekam medis: `ClinicalNoteListPage`+`ClinicalNoteFormPage` (CPPT), `VisitDiagnosesPage` (Diagnosis).
- Hanya ada index/create/(kadang delete untuk diagnosis), **tidak ada tombol Edit** — sesuai prinsip legal medical record, riwayat tidak boleh diubah setelah tercatat, hanya ditambah entri baru.
- List menampilkan versi terpotong (truncated assessment) dengan opsi lihat detail penuh.
- Form input terstruktur sesuai kerangka klinis standar (SOAP: Subjective/Objective/Assessment/Planning/Instruksi untuk CPPT).

### 3.5 Scaffold Page (module belum dibangun)
`DynamicModulePage` — dipakai untuk 134 dari 147 modul yang belum punya implementasi CRUD nyata.
- Header dengan kode modul, domain, badge scope (Menu Pelayanan / Menu Penunjang), deskripsi.
- Tombol "Tambah Data" (jika user punya permission `C`) saat ini **placeholder `alert()`** — bukan implementasi nyata, harus diganti saat modul dibangun.
- Tombol Cetak (`window.print()`) dan Kembali.
- **Penting**: scaffold ini bukan halaman final — desain apapun untuk modul yang masih scaffold harus dianggap sementara dan divalidasi ulang terhadap layar SIMpel asli begitu modul benar-benar dibangun.

## 4. Navigasi & Information Architecture

- **Sidebar kiri** (`MdNavigationDrawer`): domain operasional — dipakai harian oleh petugas front-line.
- **Top drawer** (`MdTopDrawer`): katalog penuh 147 modul, termasuk penunjang/admin — pola "menu lengkap" ala ExtJS classic top menu.
- **Tab bar** (`MdTabBar`): MDI-style, tiap modul yang dibuka jadi tab baru, mempertahankan state halaman lain — **ciri khas paling penting yang direplikasi dari SIMpel**, jangan hilangkan demi "kesederhanaan React Router biasa".
- **Ruangan switcher** (`MdRuanganSwitcher`): konteks ruangan aktif memengaruhi data yang tampil (mis. daftar bed/visit) — analog dengan konsep "unit kerja aktif" di SIMpel legacy.
- Root path `/` redirect ke `/visits` (Layanan → Penerimaan Ruangan) sebagai halaman kerja utama harian, bukan dashboard — mencerminkan prioritas kerja operasional dulu.

### 4.1 Landing page setelah login — TIDAK berbeda per role (dikonfirmasi vs legacy)

Ini poin penting yang sempat disangka sebaliknya — sudah diverifikasi langsung ke database production legacy (`aplikasi` schema, kredensial di `webservice/config/autoload/local.php`) dan bundle `app.js`:

- Tabel `pengguna`, `pengguna_akses`, `group_pengguna_akses_module` **tidak punya kolom** "default module"/"home screen" per user atau per group akses. Jadi role/grup akses tidak pernah di-map ke route awal yang berbeda di level data.
- Kolom `MENU_HOME` di tabel `aplikasi.modules` (`SELECT ... WHERE MENU_HOME=1` → domain 14/15/19/20/21/22/23/26/28/29) hanya menandai node top-level mana yang auto-expand di menu drawer (bagian "penunjang"), **bukan** logic routing landing page.
- Tidak ada percabangan `JENIS`/tipe-user di `app.js` untuk memilih initial view berbeda. Ada satu class `pengunjung.Workspace` / `pengunjung.WorkspaceController` — **satu workspace tunggal "Pengunjung"** yang jadi main view untuk *semua* user setelah login, apapun rolenya.

**Kesimpulan yang harus dipegang**: SIMpel legacy tidak punya "halaman pertama beda per role". Semua user (dokter, perawat, kasir, admin) mendarat di workspace "Pengunjung" yang sama. Yang membedakan pengalaman per role bukan *route*-nya, tapi **konten & aksi di dalam workspace itu**, yang beradaptasi terhadap:
- `activeRuangan.category` (ruangan aktif yang dipilih user — rawat_jalan/gawat_darurat/rawat_inap/laboratorium/radiologi/farmasi/kasir/penunjang)
- `hasPermission(moduleId, action)` (hak akses user di modul terkait)

Ini **persis** yang sudah diterapkan SIMGOS: `{ index: true, element: <Navigate to="/visits" replace /> }` untuk semua user tanpa kecuali. **Jangan bangun redirect landing page berbeda per `roleType`** (mis. kasir → `/invoices`, admin → `/dashboard`) — itu menyimpang dari pola asli. Yang perlu dikerjakan justru: pastikan konten/aksi di `/visits` benar-benar berubah mengikuti `activeRuangan` dan permission, bukan menambah route tujuan baru. Dashboard tetap murni modul yang diakses manual lewat tab, tidak pernah jadi default.

## 5. Permission & Role

- `hasPermission(moduleId, 'C'/...)` per modul — tombol create/edit/delete harus dicek permission dulu, bukan ditampilkan universal.
- `RoleMatrixPage`, `AuditLogPage`, `UserRoomAccessPage` — kontrol akses granular per ruangan (`activeRuangan`) dan per modul, bukan role global saja. Ini mencerminkan pola RS nyata: user bisa punya akses beda per poli/bangsal.

## 6. Cetak (Printing)

Banyak modul (Pencetakan kartu, wristband, kuitansi, lembar RM) punya kebutuhan cetak fisik — kebutuhan nyata di RS (bukan sekadar "export PDF"). Pola sementara: `window.print()` di scaffold page. **Catatan desain terbuka**: begitu modul cetak nyata dibangun, perlu keputusan apakah pakai print-CSS langsung atau generate PDF terpisah (server-side) — belum diputuskan, evaluasi per kebutuhan (label kecil seperti wristband vs dokumen A4 seperti kuitansi kemungkinan butuh pendekatan berbeda).

## 7. Hal yang Harus Dihindari

- Jangan buat modal generik untuk semua create/edit — form medis SIMGOS sering kompleks/nested, ikuti pola halaman terpisah yang sudah established.
- Jangan hilangkan tab-based MDI navigation demi navigasi single-page biasa — ini elemen UX paling dikenali user existing dari SIMpel.
- Jangan biarkan field lookup jadi text bebas — selalu dropdown dari API referensi.
- Jangan taruh `<form>` di dalam `<form>` di komponen shared list.
- Jangan tambahkan tombol Edit/Delete di modul append-only (CPPT, Diagnosis, Payment) — itu pelanggaran prinsip legal record, bukan kekurangan fitur.
- Jangan asumsikan format response API seragam antar modul — selalu cek dulu (`{data: ...}` wrapping vs custom).
