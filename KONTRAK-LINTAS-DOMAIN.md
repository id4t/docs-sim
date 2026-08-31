# Kontrak Lintas Domain

**Status:** patokan tim
**Diperbarui:** 2026-08-31
**Tujuan:** menjaga konsistensi nama, kepemilikan data, dan titik serah antar domain agar backend, frontend, QA, dan dokumentasi tidak memakai istilah berbeda untuk hal yang sama.

## Aturan inti

1. Satu konsep bisnis hanya punya satu nama kanonik.
2. Satu data hanya punya satu domain pemilik.
3. Domain lain boleh membaca data itu, tetapi tidak boleh diam-diam menjadi sumber kebenaran baru.
4. Semua relasi lintas domain harus memakai ID dan istilah kanonik yang sama.

## Nama kanonik yang wajib dipakai

| Istilah kanonik | Jangan diganti dengan |
|---|---|
| `Pasien` | peserta, customer, orang |
| `Nomor Rekam Medis` | nomor pasien, nomor BPJS |
| `Pendaftaran` | registrasi klinis, encounter |
| `Kunjungan` | antrean, visit header, transaksi layanan |
| `Coverage` | penjamin, SEP |
| `SEP` | nomor antrean, nomor kontrol |
| `Ruangan` | poli aktif, unit aktif, departemen tanpa definisi |
| `Tagihan` | invoice pembayaran, kuitansi |
| `Pembayaran` | tagihan lunas |
| `Episode Klaim` | SEP, grouping, berkas |
| `Submission` | log integrasi |

## Pemilik data per domain

| Data | Domain pemilik | Domain pembaca utama |
|---|---|---|
| identitas pasien | Pendaftaran | Rekam Medis, Billing, Klaim, Integrasi |
| nomor rekam medis | Pendaftaran | semua domain operasional |
| pendaftaran | Pendaftaran | Rekam Medis, Billing, Integrasi |
| kunjungan | Layanan | Rekam Medis, Billing, Klaim, Integrasi |
| coverage dan penjamin awal | Pendaftaran | BPJS, Billing, Klaim |
| SEP | BPJS/VClaim | Pendaftaran, Billing, Klaim |
| asesmen, anamnesis, diagnosis, CPPT | Rekam Medis | Klaim, SATUSEHAT |
| tindakan, order, hasil | Layanan | Billing, Klaim, SATUSEHAT |
| item tagihan dan status tagihan | Billing | Klaim |
| pembayaran | Kasir | Billing, audit |
| coding dan grouping | Klaim/Casemix | Billing, Monitoring |
| berkas klaim | Klaim | Monitoring |
| submission BPJS/SATUSEHAT | Integrasi | Monitoring, audit |

Diagnosis klinis dimiliki Rekam Medis. Coding ICD untuk klaim dimiliki Klaim/Casemix dan tidak boleh mengganti catatan dokter. Core Episode Klaim dimiliki Klaim/Casemix; adapter E-Klaim, retry, serta rekonsiliasi teknis dimiliki Integrasi.

## Contoh penting: Rekam Medis membaca data dari Pendaftaran

Rekam Medis memang memakai data dari Pendaftaran, tetapi batasnya harus jelas.

### Rekam Medis boleh membaca

- identitas pasien;
- nomor rekam medis;
- nomor pendaftaran;
- nomor kunjungan;
- ruangan tujuan atau ruangan aktif;
- penjamin atau coverage yang relevan untuk konteks klinis;
- dokter pengirim atau tujuan layanan bila dibutuhkan workflow.

### Rekam Medis tidak boleh menjadi pemilik

- mengganti identitas pasien;
- mengganti nomor rekam medis;
- memindah penjamin utama tanpa command resmi domain asal;
- membuat nomor pendaftaran baru;
- mengubah SEP langsung dari form rekam medis umum.

## Contract minimal antar domain

### Pendaftaran/Layanan -> Rekam Medis

Pendaftaran menyerahkan:

- `patient_id`
- `medical_record_number`
- `registration_id`
- `visit_type`
- `payer_type` atau `coverage_id` bila sudah ada

Layanan menyerahkan:

- `visit_id`
- `ward_id`
- status dan waktu Kunjungan

Rekam Medis menambahkan:

- asesmen;
- diagnosis;
- CPPT;
- rencana terapi;
- resume;
- final clinical state

### Rekam Medis/Layanan -> Billing

Layanan dan Rekam Medis menyerahkan:

- `visit_id`
- tindakan yang sah
- order yang selesai
- hasil yang relevan
- status final pelayanan

Billing menambahkan:

- item tagihan
- alokasi penjamin
- status final tagihan

### Billing/Klinis -> Klaim

Klaim membaca:

- `visit_id`
- `registration_id`
- `coverage_id`
- `sep_id`
- diagnosis dan procedure coding
- item tagihan yang relevan
- resume dan dokumen pendukung

Klaim menambahkan:

- `claim_episode_id`
- hasil grouping
- status pengajuan
- status rekonsiliasi

### Domain internal -> Integrasi

Integrasi membaca:

- identitas pasien yang sudah sah
- data pelayanan final
- status billing atau klaim bila memang dibutuhkan

Integrasi menambahkan:

- `submission_id`
- remote identifier
- status kirim
- error dan retry history

## Aturan field bersama

- Selalu pakai suffix `_id` untuk relasi ke entitas kanonik.
- Jangan buat field baru yang maknanya sama dengan field lama tetapi beda nama.
- Jika perlu menampilkan data salinan, beri status jelas bahwa itu data turunan atau snapshot.
- Nomor eksternal seperti SEP, nomor kontrol, atau ID SATUSEHAT tidak boleh menggantikan ID internal.

## Aturan untuk tim frontend

- Form hanya boleh mengedit data yang memang dimiliki domain tersebut.
- Jika satu halaman menampilkan data lintas domain, bedakan bloknya menjadi:
  - data referensi dari domain lain;
  - data yang sedang diedit domain aktif.
- Jangan kirim payload besar berisi semua data pasien bila yang dibutuhkan hanya `visit_id` atau `registration_id`.

## Aturan untuk tim backend

- Satu command mutasi hanya boleh memfinalkan satu aggregate utama.
- Jika butuh efek ke domain lain, pakai service, event, atau outbox; jangan controller silang yang saling update tabel liar.
- Larang update state final melalui endpoint generik yang menerima string bebas.

## Aturan untuk QA

- Uji apakah data yang tampil di Rekam Medis benar-benar berasal dari Pendaftaran yang sama.
- Uji apakah perubahan identitas pasien di Pendaftaran langsung tercermin di tampilan baca domain lain.
- Uji apakah domain lain gagal bila mencoba mengubah data yang bukan miliknya.

## Checklist saat menambah fitur baru

1. Nama bisnisnya apa?
2. Domain pemiliknya siapa?
3. Domain lain hanya baca atau ikut mutasi?
4. ID kanonik apa yang dipakai?
5. Status akhirnya apa?
6. Siapa yang boleh mengubahnya?
7. Kalau final atau batal, jejak auditnya di mana?

## Rekomendasi pemakaian

- Jadikan dokumen ini patokan review PR.
- Jika ada field atau istilah baru, perbarui dokumen ini dan `CONTEXT.md` di saat yang sama.
- Jika ada developer menulis nama berbeda untuk konsep yang sama, anggap itu bug desain, bukan sekadar preferensi nama.
