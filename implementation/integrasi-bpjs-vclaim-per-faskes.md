# Integrasi BPJS VClaim per Faskes

**Status:** pemeriksaan peserta rawat jalan, penerbitan SEP, dan Rencana Kontrol telah memakai Facility context; belum dinyatakan production-ready sebelum UAT BPJS.

## Dasar keputusan

Implementasi mengikuti pemisahan operasi SIMPel: SEP dan Rencana Kontrol adalah transaksi VClaim berbeda dan hasilnya disimpan lokal. Acuan kode yang diperiksa:

- `BPJService/V1/Rpc/RencanaKontrol/RencanaKontrolController.php`;
- `Plugins/V2/Rpc/Bpjs/SEP.php`;
- `Plugins/V2/Rpc/Bpjs/RencanaKontrol.php`.

Lokasi sumber SIMPel pada server pengembangan adalah `/var/www/html/production/webapps/webservice/module/`. SIMGOS mempertahankan alur bisnis tersebut, lalu menambahkan batas keamanan multi-Faskes yang tidak tersedia pada instalasi SIMPel tunggal.

```text
pilih Faskes
  → periksa peserta/rujukan
  → buat Pendaftaran berstatus pending_sep
  → terbitkan atau rekonsiliasi SEP
  → aktifkan Pendaftaran dan lanjutkan Kunjungan
  → setelah pelayanan, buat Rencana Kontrol bila diperlukan
  → gunakan surat kontrol pada Pendaftaran berikutnya
```

SEP bukan Rencana Kontrol dan nomor antrean bukan SEP. Ketiganya tidak boleh digabung menjadi satu status generik.

## Kepemilikan data

| Data | Lokasi |
|---|---|
| `cons_id`, `secret_key`, `user_key` VClaim | control DB, terenkripsi per Faskes |
| status konfigurasi dan audit perubahan teredaksi | control DB |
| pemeriksaan peserta/rujukan | database Faskes |
| SEP dan status pengiriman/rekonsiliasi | database Faskes |
| Rencana Kontrol | database Faskes |

Kunci enkripsi berasal dari konfigurasi aplikasi dan tidak disimpan bersama ciphertext. Audit hanya menyimpan status konfigurasi dan petunjuk teredaksi, tidak menyimpan nilai rahasia.

## Konfigurasi

Admin Faskes, Superadmin, atau Developer membuka:

```text
/facilities/{facility_code}/integrations/bpjs-vclaim
```

Konfigurasi pertama wajib mengisi `cons_id`, `secret_key`, dan `user_key`. Pada rotasi berikutnya hanya nilai yang berubah yang perlu diisi; field kosong mempertahankan nilai lama. API yang dipakai:

```text
GET /api/v1/f/{facility_code}/integrations/bpjs-vclaim/credential
PUT /api/v1/f/{facility_code}/integrations/bpjs-vclaim/credential
```

Respons GET/PUT hanya berisi `configured`, petunjuk empat karakter terakhir `cons_id` dan `user_key`, serta waktu perubahan. Secret tidak dapat dibaca kembali dari frontend.

## Route operasional aktif

```text
POST /api/v1/f/{facility_code}/bpjs/outpatient-verifications
GET|POST /api/v1/f/{facility_code}/seps
GET /api/v1/f/{facility_code}/seps/{sep}
POST /api/v1/f/{facility_code}/seps/{sep}/reconcile
GET|POST /api/v1/f/{facility_code}/rencana-kontrols
GET /api/v1/f/{facility_code}/rencana-kontrols/{rencanaKontrol}
GET /api/v1/f/{facility_code}/rencana-kontrols-lookup/*
```

Petugas Pendaftaran menangani eligibilitas dan penerbitan SEP. Dokter dapat membaca SEP serta membuat/membaca Rencana Kontrol. Verifikator Penjamin dapat membaca, menerbitkan, dan merekonsiliasi SEP. Semua akses tetap membutuhkan Membership Faskes, kecuali aktor global yang memang ditetapkan.

## Aturan kegagalan

- Request ber-Facility context tanpa credential `bpjs_vclaim` lengkap berhenti dengan HTTP 409 sebelum membuat transaksi lokal atau menghubungi BPJS.
- Tidak ada fallback ke credential global atau credential Faskes lain.
- Timeout setelah create SEP menghasilkan status lokal `unknown`; petugas wajib menjalankan rekonsiliasi, bukan mengirim create kedua secara buta.
- Kegagalan bisnis BPJS disimpan sebagai status/error transaksi pada database Faskes terkait.
- Endpoint lama tanpa prefix Faskes masih memakai konfigurasi `.env` hanya sebagai jalur kompatibilitas. Jangan membuat pemanggil UI baru ke jalur lama.

## Bukti otomatis 31 Agustus 2026

- ciphertext control DB tidak memuat secret asli;
- API dan audit tidak mengekspos secret;
- rotasi satu field mempertahankan dua field lain;
- dua database Faskes dengan `patient.id = 1` yang sama menerima hasil masing-masing;
- header request Faskes A dan B memakai `cons_id` masing-masing;
- koneksi kembali ke control DB sesudah request;
- request tanpa credential lokal menghasilkan 409, tidak mengirim HTTP, dan tidak membuat snapshot.

## Rollout dan rollback

1. Jalankan migration control DB untuk tabel `facility_integration_credentials`.
2. Isi credential per Faskes melalui UI maintenance.
3. Uji peserta, signature, dekripsi, SEP, dan Rencana Kontrol pada UAT BPJS.
4. Aktifkan petugas per Faskes dan pantau error teredaksi.
5. Setelah semua pemanggil memakai prefix Faskes, hapus endpoint kompatibilitas dalam perubahan tersendiri.

Rollback aplikasi boleh mengembalikan pemanggil ke endpoint kompatibilitas selama credential `.env` masih valid. Jangan menjatuhkan tabel credential sampai ciphertext dicadangkan dan dipastikan tidak lagi dibutuhkan. Migration tabel dapat di-rollback setelah syarat tersebut dipenuhi.

## Yang belum dicakup

- tombol health check langsung dari UI;
- Antrean Online, Aplicares, i-Care, PCare, dan E-Klaim;
- secret manager eksternal;
- UAT/production credential BPJS dan validasi petugas.

Tambahkan kemampuan tersebut ketika slice terkait dimulai atau hasil UAT membuktikannya diperlukan; jangan menumpuknya pada konfigurasi VClaim dasar.
