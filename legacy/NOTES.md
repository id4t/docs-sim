# Notes — SIMGOS

> **ARSIP PROGRESS LOG — bukan sumber status delivery.** Status aktif hanya dicatat di [`../ROADMAP.md`](../ROADMAP.md).

Catatan teknis & operasional yang melengkapi `PRD.md` dan `UI-UX-KEY.md`. Sumber: eksplorasi kode `RME-Frontend` (progress log `CLAUDE.md`), `routes/index.tsx`, `modulesCatalog.ts`, dan struktur legacy `SIMpel` di `/var/www`.

## 1. Struktur Proyek

```
/home/simgos/inisimgos/
├── RME-Backend/     Laravel modular (474 modul, 1 folder Modules/<Nama> per domain)
├── RME-Frontend/    React 19 + Vite + TS SPA, konsumsi REST API backend
├── simgos_dump.sql  Dump database
├── service.sh, services/  systemd unit untuk jalanin backend+frontend sebagai service
```

Frontend: `src/features/<Modul>` 1:1 mapping ke `RME-Backend/Modules/<Modul>`. Tiap fitur folder berisi `api.ts`, `types.ts`, `pages/`.

Base path deploy frontend: `/apps/inisimgos` (lihat `basename` di `routes/index.tsx`) — bukan root domain, ini production path riil di `/var/www/html/production/webapps/application/inisimgos`.

## 2. Halaman yang Sudah Dibangun (13 modul, per routes/index.tsx)

| Fitur | List | Form | Detail/Lain |
|---|---|---|---|
| Auth | — | `LoginPage` | — |
| Dashboard | `DashboardPage` | — | — |
| GeneralEmployee | `EmployeeListPage` | `EmployeeFormPage` | — |
| GeneralPatient | `PatientListPage` | `PatientFormPage` | — |
| GeneralGender/Profession/Religion | `*ListPage` (lookup CRUD) | — | — |
| GeneralWard | `WardListPage` | — | `WardAssignmentsPage` (`/wards/:id/assignments`) |
| GeneralRoom / GeneralBed | `RoomListPage`, `BedListPage` | — | — |
| UserManagement | `UserListPage` | `UserFormPage` | `UserRoomAccessPage`, `RoleMatrixPage`, `AuditLogPage` |
| GeneralInstitution | — | `InstitutionProfilePage` (single-record settings) | — |
| PendaftaranRegistration | `RegistrationListPage` | `RegistrationFormPage` | — |
| PendaftaranVisit | `VisitListPage` | `VisitFormPage` | `VisitDiagnosesPage` (`/visits/:id/diagnoses`) |
| PembayaranInvoice | `InvoiceListPage` | `InvoiceFormPage` | `InvoiceDetailPage` (nested Item+Payment) |
| MedicalRecordClinicalNote | `ClinicalNoteListPage` | `ClinicalNoteFormPage` (create-only) | — |
| GeneralDiagnosisCode | `DiagnosisCodeListPage` | — | — |
| LayananPrescription | `PrescriptionListPage` | `PrescriptionFormPage` | `PrescriptionDetailPage` |

**Catatan orphan**: `src/features/GeneralReports/pages/ReportRLPage.tsx` dan `ReportVisitorsPage.tsx` **ada di kode tapi tidak terdaftar di `routes/index.tsx`** — kemungkinan work-in-progress belum di-wire, atau sengaja diakses lewat jalur lain. Perlu klarifikasi sebelum dianggap "selesai" — jangan asumsikan dua halaman ini aktif.

## 3. Sisa 134 Modul (Scaffold)

Semua diakses lewat rute generik `/module/:moduleId` → `DynamicModulePage`, yang membaca `SIMGOS_ALL_MODULES` (`src/types/modulesCatalog.ts`) untuk render header + deskripsi + tombol placeholder. Ini **bukan implementasi fungsional**, murni penanda "modul ini ada di katalog, belum dibangun". Lihat `PRD.md` Lampiran A untuk daftar lengkap per domain.

## 4. Urutan Pengerjaan Berikutnya

Ikuti urutan histori commit backend (`git log --diff-filter=A --name-only --pretty=format:"%h|%ad" --date=short --reverse -- "Modules/*/composer.json"` di repo `RME-Backend`). Urutan yang sudah diketahui dari progress log:

1. ✅ Ward/Room/Bed
2. ✅ PendaftaranRegistration/Visit
3. ✅ GeneralCountry/Education/Ethnicity/Language/MaritalStatus/Occupation/KemkesBloodType
4. ✅ PembayaranInvoice/InvoiceItem/Payment
5. ✅ GeneralService/ServiceTariff (Service saja)
6. ✅ MedicalRecordClinicalNote
7. ✅ GeneralDiagnosisCode/MedicalRecordDiagnosis
8. **Next**: LayananPrescription/Item (List/Form/Detail sudah ada — cek status backend match)
9. Lalu: InventoryItem → PendaftaranGuarantor → LayananLabOrder/LabResult → dst (474 modul total, urutan lengkap ada di git log backend).

## 5. Temuan Audit Fase 1 (penting untuk pola verifikasi modul baru)

Audit dilakukan lewat Playwright langsung ke app legacy asli (`http://192.168.56.101/apps/SIMpel/`, kredensial di memory sesi sebelumnya). Ditemukan gap signifikan dari build awal:
- Form Employee/Patient kehilangan sub-struktur: Kontak (list multi-nomor), Kartu Identitas (list multi-jenis), `smf_id` Employee harusnya dropdown Spesialis bukan text.
- Patient: 7 field yang seharusnya dropdown referensi (Pendidikan/Pekerjaan/Status Kawin/Gol Darah/Kewarganegaraan/Suku/Bahasa) awalnya text bebas.
- Ward/Room kehilangan dropdown Jenis Ruangan/Jenis Kunjungan/Kelas walau field-nya sudah ada di backend.
- Data referensi (Agama/Pendidikan/dst) di-generate bebas oleh Faker awalnya — harus diseeding **persis** sama dengan layar "Master > Referensi" legacy (koreksi Spesialis 19→36 item, dst).

**Implikasi**: setiap modul baru **wajib** dibandingkan field-by-field ke layar SIMpel asli sebelum dianggap selesai. Jangan percaya deskripsi/nama field di backend saja — banyak yang gak lengkap di percobaan pertama.

## 6. Gap yang Sengaja Dilewatkan (bukan lupa — didokumentasikan)

- **Tab "Barang" di Ruangan** (legacy) — belum ketemu modul backend yang cocok, butuh keputusan desain dulu sebelum dibangun.
- ~~**Menu "Tempat Tidur" (papan okupansi live)**~~ — **SELESAI (2026-08-19)**: `VisitController::index` sekarang support filter `?ward_id=`/`?status=` (backend `Modules/PendaftaranVisit`), dipakai `VisitListPage` sebagai worklist 1101 "Penerimaan Ruangan" (filter Ruangan + Status, default `status=active`). Terverifikasi end-to-end lewat backend asli (PHP 8.4, database `simgos` hasil import `simgos_dump.sql`). Papan okupansi tempat tidur (domain 22) sendiri masih belum dibangun, tapi endpoint filter yang jadi prasyaratnya sudah ada.
- **Registration form**: field `admission_diagnosis_id`/`referral_id`/`package_id` dilewatkan — modul `GeneralDiagnosisCode`/`PendaftaranReferral`/`GeneralPackage` belum ada picker-nya di frontend.
- **Doctor/Nurse/StaffMember**: data masih dummy Faker (nama random), belum di-link ke pegawai asli RS.

## 7. Business Rules Kritis (jangan dilanggar saat menambah fitur)

- **Invoice auto-lock**: `recalculateTotals()` di backend, status berubah `paid` otomatis saat `totalPaid >= total_amount` — form tambah item/bayar harus hilang dari UI begitu ini terjadi.
- **Payment**: create-only, sengaja tanpa edit/delete — bagian dari desain audit trail keuangan, bukan kekurangan.
- **ClinicalNote (CPPT)**: append-only (index/store/show saja) — "legal medical record", tidak ada update/delete.
- **Diagnosis**: append-only (index/store/show/destroy — destroy ada tapi bukan update), satu diagnosis primer per kunjungan di-enforce backend.

## 8. Konvensi Frontend

- Auth: Sanctum Bearer token, disimpan di `localStorage` key `simgos_token`. Axios instance di `src/api/client.ts`.
- Format response API **tidak seragam** antar modul — selalu cek per modul apakah pakai `JsonResource` wrapping `{data: ...}` atau custom. Konvensi versioning route (`v1` dst) juga perlu dicek per modul, jangan asumsi.
- Nomor dokumen otomatis per tahun: `REG-{tahun}-{seq}` (Registration), `KJ-{tahun}-{seq}` (Visit), `INV-{tahun}-{seq}` (Invoice).
- Komponen shared penting: `PatientPicker` (search-select pasien), `ContactList`/`IdentityCardList`/`FamilyList` (nested list, sudah diperbaiki dari bug nested-form), `WardAssignmentList` (generic, dipakai di tab Dokter/Spesialis/Paramedis/Staff/Tindakan).
- 6 modul (`GeneralDoctor`/`Nurse`/`StaffMember` + 3 `WardAssignment`) dipindah dari unauthenticated ke `auth:sanctum`+`v1` di sesi yang sama saat fitur Kelola Penugasan Ruangan dibangun — kalau ada modul lain yang masih unauthenticated, itu tanda belum di-hardening.

## 9. Ketidaksesuaian Dokumentasi yang Perlu Diperbaiki

- `CLAUDE.md` (progress log lama) menyebut stack UI "Tailwind v4 + shadcn-style components (`src/components/ui/`)", tapi kode saat ini sudah pakai custom Material Design 3 token system (`src/components/material/Md*.tsx`, `--md-sys-color-*`). Kemungkinan terjadi migrasi UI yang belum di-update di dokumentasi — perlu dikonfirmasi ke tim/commit history mana yang jadi arah final, lalu update `CLAUDE.md`.
- Katalog modul menyebut "146 sub-modul" di beberapa tempat (komentar kode `DynamicModulePage`), tapi hasil parsing `modulesCatalog.ts` aktual menghasilkan **147 baris entri** — selisih kecil, kemungkinan penghitungan lama sebelum modul terakhir ditambahkan. Tidak signifikan tapi sebaiknya disamakan.

## 10. Referensi Legacy untuk Perbandingan UX

- App legacy: `/var/www/html/production/webapps/application/SIMpel` (ExtJS Classic — folder `classic/resources/ext`, `ux`).
- Webservice pendukung legacy: `/var/www/html/production/webapps/webservice` — berisi modul PHP (BPJS, Kemkes, INACBG, Dashboard, dll) yang kemungkinan jadi acuan kontrak data untuk modul-modul yang belum dibangun di SIMGOS (terutama domain Integrasi/25, Laporan/14).
- Environment legacy untuk audit Playwright: `http://192.168.56.101/apps/SIMpel/` (kredensial tersimpan di memory sesi kerja sebelumnya, bukan di repo).
- DB production legacy (`aplikasi` schema, MySQL `127.0.0.1:3306`) bisa diakses read-only pakai kredensial di `webservice/config/autoload/local.php` (user `simgos`) untuk verifikasi perilaku — dipakai untuk konfirmasi temuan §4 di bawah.

## 11. Landing Page Setelah Login — Dikonfirmasi TIDAK Berbeda per Role

Sempat diasumsikan tiap role (dokter/perawat/kasir/admin) punya landing page sendiri-sendiri. Setelah verifikasi ke DB production legacy (`aplikasi.pengguna`, `aplikasi.pengguna_akses`, `aplikasi.group_pengguna_akses_module` — tidak ada kolom default-module/home-screen) dan bundle `app.js` (tidak ada percabangan tipe-user untuk initial view, hanya ada satu class `pengunjung.Workspace`/`WorkspaceController`), **kesimpulannya: semua role mendarat di satu workspace yang sama ("Pengunjung"), bukan route berbeda-beda.**

Yang membedakan pengalaman per role adalah **konten di dalam workspace itu**, adaptif terhadap `activeRuangan.category` dan `hasPermission(moduleId, action)` — bukan tujuan redirect setelah login. Kolom `MENU_HOME` di tabel `aplikasi.modules` cuma menandai grup top-level mana yang auto-expand di menu drawer, tidak terkait routing landing page.

**Implikasi untuk SIMGOS**: pola `{ index: true, element: <Navigate to="/visits" replace /> }` yang berlaku universal untuk semua user **sudah benar dan harus dipertahankan** — jangan buat redirect landing berbeda per `roleType`. Kerja yang justru dibutuhkan: pastikan `/visits` benar-benar adaptif secara konten/aksi terhadap `activeRuangan` dan permission (ini yang saat ini belum lengkap), bukan menambah route tujuan baru per role. Detail lengkap ada di `UI-UX-KEY.md` §4.1.

## 12. Sistem Desain — Gap: Sisa shadcn Harus Dibersihkan (bukan soal M2 vs M3)

**Perintah product owner: UI SIMGOS hanya boleh pakai komponen Google Material Design — M2 atau M3 sama-sama boleh, yang dilarang adalah komponen dari sistem desain lain (shadcn, Tailwind UI generik, dll).** Klarifikasi ini penting karena sempat disangka harus M2 secara spesifik — **tidak**, versi Material bebas, custom Material Design 3 yang sudah dibangun (`src/components/material/Md*.tsx`, token `--md-sys-color-*`) **sah dan tidak perlu diturunkan ke M2**.

Kondisi kode saat ini:
- `src/components/material/Md*.tsx` — custom M3, dipakai di hampir semua halaman yang sudah dibangun (drawer, top app bar, tab bar, button, select, text field, dialog, dst). **Ini patuh aturan**, lanjutkan dipakai/dikembangkan.
- `src/components/ui/` — sisa shadcn/Tailwind dari fase awal (`CLAUDE.md` progress log masih menyebut ini sebagai stack aktif, sudah usang). **Ini yang melanggar aturan** — shadcn bukan Material Design, harus dihapus.

**Rencana kerja yang disarankan** (belum dieksekusi):
1. Grep semua import dari `src/components/ui/` di seluruh 13 modul yang sudah dibangun untuk tahu cakupan pastinya.
2. Untuk setiap komponen shadcn yang masih dipakai, cek apakah sudah ada versi `Md*` yang setara di `src/components/material/` — kalau ada, ganti pemakaiannya langsung.
3. Kalau belum ada versi `Md*`-nya, buat dulu (mengikuti spec M3 yang sudah established di `index.css`), baru ganti pemakaian shadcn-nya.
4. Setelah semua pemakaian pindah, hapus total folder `src/components/ui/` dan dependency shadcn dari `package.json`.
5. Aturan sama berlaku ke depan untuk library UI non-Material lain (MUI, Ant Design, Chakra, dll) — jangan pernah ditambahkan, meski secara teknis bukan shadcn.

## 13. Pola Seeder Cross-Schema dari DB Legacy (dipakai pertama kali: Master Referensi 1918)

Server produksi ini punya **DB legacy SIMpel** (schema `master`, `aplikasi`, `bpjs`, dll) dan **DB `simgos`** (dipakai `RME-Backend`) di **MySQL instance yang sama** (`127.0.0.1:3306`). User `admin` (kredensial di `webservice/config/autoload/local.php`, juga dipakai di `RME-Backend/.env`) punya privilege penuh (`SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,...ON *.*`) ke SEMUA schema, termasuk `master`.

Ini memungkinkan pola seeder Laravel yang langsung `INSERT...SELECT` cross-schema tanpa perlu export/fixture file manual — contoh nyata: `Modules/GeneralReferenceType/database/seeders/GeneralReferenceTypeDatabaseSeeder.php` dan `Modules/GeneralReference/database/seeders/GeneralReferenceDatabaseSeeder.php` (import 328 kategori + 3973 nilai referensi dari `master.jenis_referensi`+`master.referensi`, lihat `WORKFLOWS.md` §3 domain 19 untuk detail).

**Catatan penting kalau mau pakai pola ini lagi**:
- Seeder ini **one-time data migration**, bukan dimaksudkan idempotent-portable — kalau `master` schema legacy nggak ada/nggak bisa diakses (mis. di environment lain, atau kalau DB legacy suatu saat di-decommission), seeder ini akan gagal total. Jangan didaftarkan ke `DatabaseSeeder` utama yang jalan otomatis tiap fresh install — jalankan manual (`php artisan db:seed --class=...`) cuma sekali di environment yang punya akses ke `master` schema.
- Pakai `ON DUPLICATE KEY UPDATE kolom = kolom` (no-op update) biar seeder aman dijalankan ulang tanpa duplikat, asalkan tabel tujuan punya unique constraint yang sesuai.
- Selalu jalankan pakai binary PHP 8.4 (`/opt/remi/php84/root/usr/bin/php artisan db:seed ...`) — PHP CLI default di server ini masih 8.2, lihat §7/§12 soal ini.
- Sebelum bikin seeder cross-schema baru, cek dulu struktur tabel sumber legacy via `mysql -uadmin -p'S!MGos2@kemkes.go.id' -h127.0.0.1 <schema> -e "DESCRIBE <table>;"` — jangan asumsi struktur kolom dari nama doang (lihat contoh `referensi` yang punya `TABEL_ID`/`ID`/`JENIS`/`REF_ID`/`TEKS`/`CONFIG`/`SCORING` — banyak kolom yang gak jelas dari nama modul aja).
