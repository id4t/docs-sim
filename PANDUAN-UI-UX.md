# Panduan UI/UX — SIMGOS

## Prinsip

SIMGOS mempertahankan istilah, urutan tugas, dan mental model SIMpel, tetapi tidak menyalin seluruh tampilan atau keterbatasan ExtJS. Targetnya adalah familiar bagi petugas lama sekaligus aman, terarah, dan dapat dipelajari pengguna baru.

- Gunakan Material Design 3 dan komponen `Md*` yang konsisten.
- Desktop-first untuk loket, kasir, farmasi, lab, casemix, dan admin.
- Desktop dan tablet untuk dokter/perawat.
- Ponsel hanya untuk use case khusus seperti approval atau monitoring ringkas.
- Display antrean dan ketersediaan tempat tidur adalah surface terpisah.
- Optimalkan keyboard, scan tabel, pencarian, dan input berulang.

## Information architecture

Navigasi utama berangkat dari pekerjaan, bukan katalog tabel:

- **Worklist:** antrean tugas menurut Ruangan, profesi, dan status.
- **Encounter Workspace:** satu konteks pasien/kunjungan untuk pekerjaan klinis dan administratif.
- **Task Workspace:** farmasi, lab, radiologi, kasir, coding, dan klaim memiliki worklist khusus tetapi membuka episode yang sama.
- **Monitoring:** kegagalan integrasi dan kelengkapan menampilkan tindakan pemulihan.
- **Master & Configuration:** dipisahkan dari aktivitas pelayanan harian.

Katalog modul boleh tersedia untuk administrasi dan transisi dari SIMpel, tetapi bukan struktur utama roadmap atau ukuran progres.

## Encounter Workspace

```text
┌────────────────────────────────────────────────────────────┐
│ Pasien · NRM · Ruangan · DPJP · Status · Penjamin          │
│ SEP · alergi/peringatan · waktu pelayanan                  │
├────────────────────────────────────────────────────────────┤
│ Kelengkapan: [asesmen] [diagnosis] [order] [resume] [sync] │
├───────────────┬────────────────────────────────────────────┤
│ Tugas relevan │ Area kerja                                 │
│ per role/unit │ klinis / order / farmasi / administrasi    │
├───────────────┴────────────────────────────────────────────┤
│ Timeline dan audit context                                 │
└────────────────────────────────────────────────────────────┘
```

Workspace tidak menampilkan semua tab kepada semua pengguna. Visibility dan editability ditentukan bersama oleh:

- capability profile instalasi;
- Ruangan aktif;
- profesi/role dan permission aksi;
- jenis serta state Kunjungan;
- assignment terhadap pasien;
- final/locked state record.

Menyembunyikan tombol bukan kontrol keamanan; backend tetap menolak aksi yang tidak sah.

## Form dan kepadatan informasi

- Tampilkan field wajib dan paling sering dipakai terlebih dahulu.
- Letakkan detail opsional pada bagian yang dapat dibuka tanpa menyembunyikannya permanen.
- Jangan memakai default klinis hanya untuk mempercepat input bila nilainya belum benar-benar diketahui.
- Form panjang menyimpan draft dan menampilkan indikator perubahan; draft tidak pernah dianggap final otomatis.
- Finalisasi adalah aksi sadar dengan validasi, ringkasan dampak, dan konfirmasi.

## Worklist dan pencarian

- Pencarian Pasien, NRM, nomor Pendaftaran, dan identifier penting tersedia dari entry point yang konsisten.
- Worklist memakai server-side pagination, search, dan filter tanggal, Ruangan, dokter, status, serta Penjamin sesuai domain.
- Filter penting dapat disimpan per pengguna.
- Hasil pencarian hanya menampilkan data yang diizinkan oleh permission dan Akses Ruangan.

## Aksesibilitas

- Seluruh input mempunyai label dan error yang dapat diasosiasikan oleh assistive technology.
- Navigasi keyboard, urutan fokus, kontras, serta target sentuh tablet harus dapat digunakan.
- Warna tidak menjadi satu-satunya penanda status.
- Display publik memakai layout dan masking khusus, bukan workspace klinis dengan akun berprivilege tinggi.

## State UX

Setiap layar wajib mendesain:

- loading dan refresh;
- empty state yang menjelaskan tindakan berikutnya;
- field validation dan conflict/concurrency error;
- read-only/final/locked state;
- permission denied tanpa membocorkan data;
- external service unavailable;
- queued/sending/accepted/failed/retry/reconciled;
- duplicate/idempotent response;
- correction/amendment dengan alasan;
- success yang menyebut efek bisnis, bukan hanya “data tersimpan”.

Status harus menggunakan label dan warna konsisten. Warna tidak boleh menjadi satu-satunya pembeda; sertakan teks/ikon dan informasi waktu terakhir.

## Checklist, finalisasi, dan koreksi

Checklist kelengkapan bersifat actionable: klik item membawa pengguna ke bagian yang kurang. Finalisasi menampilkan:

- precondition yang sudah dan belum terpenuhi;
- record yang akan terkunci;
- side effect seperti final Tagihan atau antrean pengiriman;
- identitas aktor dan permintaan konfirmasi.

Batal final atau amendment bukan tombol edit biasa. UI meminta alasan, menampilkan dampak downstream, dan bila perlu menandai SATUSEHAT/E-Klaim untuk rekonsiliasi.

## Integrasi dalam konteks kerja

- Petugas pendaftaran melihat eligibilitas, rujukan, SEP, dan Antrean Online di episode pasien.
- Dokter melihat status IHS/mapping yang menghalangi pengiriman tanpa harus memahami payload FHIR.
- Casemix melihat readiness coding, grouping, berkas, serta klaim dalam Episode Klaim.
- Petugas IT melihat payload ter-redaksi, error, attempt history, dan aksi retry/reconcile.

Menu “Integrasi” digunakan untuk konfigurasi dan monitoring global, bukan sebagai satu-satunya tempat melakukan workflow BPJS/SATUSEHAT.

## Definisi UI selesai

CRUD yang berjalan hanya mencapai `implemented`. UI baru dapat mencapai `workflow-integrated` bila terhubung ke tugas sebelum/sesudahnya dan menangani seluruh state relevan. Status lebih tinggi mengikuti [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md).

## Migrasi dari UI saat ini

- Pertahankan Material Design 3 dan ganti sisa komponen non-Material.
- Pertahankan familiaritas navigation/tab jika membantu petugas, tetapi ukur jumlah tab dan cognitive load.
- Ubah `VisitWorkspace` dari kumpulan tab universal menjadi area task-oriented adaptif.
- Jangan menganggap halaman scaffold sebagai modul selesai.
- Tambahkan desain permission, final/locked state, failure state, dan monitoring sebelum memperluas katalog.
