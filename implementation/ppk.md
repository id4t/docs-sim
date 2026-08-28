# Master PPK — Spesifikasi Implementasi

Gunakan dokumen ini saat mengubah `GeneralPpk`, relasi identitas Faskes–PPK, atau UI Master PPK. Baseline operasional berasal dari [panduan PPK SIMGos V2](https://docs.simgos2.simpel.web.id/docs/panduan/master/ppk/); keputusan target mengikuti dokumen aktif di direktori `docs/`.

## Outcome

Admin Faskes dapat mencari dan memelihara satu direktori Pemberi Pelayanan Kesehatan (PPK) bersama dalam Grup tanpa kehilangan kompatibilitas kode SIMPel. PPK milik Grup dan PPK eksternal memakai master yang sama; PPK bukan tenant boundary.

## Kontrak domain

- PPK merepresentasikan rumah sakit, klinik, puskesmas, apotek, laboratorium, atau pemberi pelayanan lain.
- `ppks.id` adalah identitas teknis immutable. Semua relasi menyimpan `ppk_id`.
- `code` adalah Kode Faskes manual, unik, editable seperti SIMPel, dan seluruh perubahannya diaudit.
- `bpjs_code` adalah identitas eksternal opsional dan unik jika diisi. Verifikasi format/daring BPJS berada di fase integrasi.
- `type` dan `ownership` menyimpan kode numerik referensi SIMPel. Label dibaca dari Master Referensi; frontend tidak menyimpan daftar label sendiri.
- `jpk` mengikuti kontrak SIMPel: `1` Umum dan `2` Khusus.
- Wilayah berhenti pada Kabupaten/Kota. `region_code` menyimpan kode kota/kabupaten; `region_name` adalah hasil lookup, bukan input bebas.
- Record dinonaktifkan melalui `is_active`; PPK tidak dihapus.
- Semua Admin Faskes dalam Grup boleh menulis. Update memakai optimistic locking dan menolak versi usang dengan HTTP 409.
- Semua perubahan menyimpan aktor, waktu, nilai sebelum, dan nilai sesudah.
- Kelak setiap Facility menunjuk tepat satu PPK melalui `facility.ppk_id` unik. PPK tanpa Facility adalah eksternal. Implementasikan relasi ini hanya setelah aggregate Facility tersedia; lihat `gate-0.md` bagian Facility isolation.

## Requiredness

- Selalu wajib: `code`, `name`, `type`, `region_code`.
- `class` wajib hanya untuk jenis Rumah Sakit.
- Opsional: `bpjs_code`, `ownership`, `jpk`, alamat, RT/RW, kode pos, telepon, fax, tanggal mulai, tanggal akhir.
- `bpjs_code`, bila ada, harus unik. Jangan menambahkan pemeriksaan format selain batas kolom sebelum kontrak BPJS dibuktikan.
- Kode klasifikasi yang tidak aktif/tidak ditemukan dalam kategori referensi yang benar ditolak.

## UI

Daftar utama hanya menampilkan Kode Faskes, Kode BPJS, Nama, Jenis, Kabupaten/Kota, Status, dan Aksi. Sediakan:

- pencarian kode/nama;
- filter cepat Semua, Milik Grup, Eksternal, Aktif, Nonaktif;
- panel filter lanjutan untuk Kepemilikan, Tipe/JPK, Kelas, Provinsi, dan Kabupaten/Kota;
- form bertahap: Identitas, Lokasi, lalu Detail Tambahan yang dapat dilipat;
- pesan konflik 409 yang meminta pengguna memuat ulang data terbaru.

Filter Milik Grup/Eksternal baru aktif setelah relasi Facility–PPK tersedia. Sebelum itu, sembunyikan filter tersebut; jangan menebak status internal.

## Urutan implementasi

1. **Buktikan referensi.** Jenis memakai kategori legacy `11`, Kepemilikan memakai `28`, dan JPK memakai kode tetap `1/2`. Ambil pasangan kode-label Jenis/Kepemilikan dari hasil impor legacy; jangan membuat label pengganti ketika impor belum tersedia.
2. **Expand backend.** Tambahkan kolom/constraint baru melalui migrasi ekspansi. Pertahankan pembacaan dan payload lama selama transisi. Selesai ketika migrasi forward/rollback berjalan dan record lama tetap terbaca.
3. **Kunci invariant.** Validasi referensi, requiredness kondisional, non-delete, optimistic locking, dan audit berada di backend. Selesai ketika request langsung tidak dapat melewati aturan tersebut.
4. **Bangun UI.** Gunakan API Reference dan Region yang sudah ada; tampilkan daftar ringkas dan form bertahap. Selesai ketika create, edit, deactivate, filter, serta konflik 409 dapat dijalankan tanpa label hard-coded.
5. **Verifikasi dan commit.** Jalankan tes PPK/Reference/Region serta type-check frontend. Commit backend dan frontend terpisah dengan pesan Bahasa Indonesia.

## Verifikasi sumber

Terbukti dari screenshot resmi SIMPel, diverifikasi 2026-08-28:

- daftar menampilkan Kode, Kode BPJS, Jenis, Kepemilikan, Tipe, Nama, Kelas, Alamat, Kota/Kab., dan Status;
- form edit menampilkan Kode Faskes sebagai input editable dan Kode Faskes BPJS sebagai field terpisah;
- form memakai dropdown untuk Jenis, Kepemilikan, Tipe, Kelas, serta Kota/Kabupaten.
- konfigurasi resmi menyatakan Jenis berasal dari `master.referensi` kategori `11`, dengan nilai yang dipublikasikan `1` Rumah Sakit, `2` Puskesmas, dan `3` Klinik;
- Kepemilikan berasal dari kategori legacy `28`, tetapi daftar kode-label lengkap tidak dipublikasikan;
- JPK bukan Master Referensi: `1` Umum dan `2` Khusus;
- `GeneralReferenceTypeDatabaseSeeder` menyimpan ID kategori legacy pada `reference_types.abbreviation`, sehingga kategori `11/28` dapat ditemukan tanpa menebak namanya;
- wilayah PPK memakai kode dan nama Kabupaten/Kota.

Masih harus dibuktikan sebelum kontraknya diperketat:

- seluruh pasangan kode-label Jenis dan Kepemilikan dari data impor legacy;
- pasangan kode-label Kelas;
- kondisi required asli dan format resmi Kode Faskes/Kode BPJS;
- apakah endpoint legacy mengizinkan delete walaupun UI dokumentasi hanya menunjukkan tambah/edit.

## Non-goals slice ini

- membuat aggregate Grup/Facility atau menyelesaikan Facility isolation Gate 0;
- memanggil API BPJS untuk memverifikasi PPK;
- sinkronisasi massal direktori fasilitas eksternal;
- workflow usulan/persetujuan perubahan PPK;
- filter Milik Grup/Eksternal sebelum relasi Facility–PPK tersedia.

## Acceptance

- Admin terautentikasi dapat membuat PPK dengan kode referensi valid dan Kabupaten/Kota valid.
- Payload teks untuk `type`, `ownership`, atau `jpk` ditolak; label tidak disimpan di kolom kode.
- Kode Faskes dan Kode BPJS unik sesuai kontraknya.
- Kelas wajib hanya untuk Rumah Sakit.
- Update versi lama menghasilkan 409 dan tidak menimpa data baru.
- Perubahan sukses menghasilkan audit before/after.
- Delete endpoint menghasilkan 405; deactivate tetap bekerja.
- Daftar dapat dicari dan difilter server-side tanpa fetch-all.
- Tes backend fokus hijau dan `tsc --noEmit` hijau; build frontend tidak dijalankan saat dev server aktif.
