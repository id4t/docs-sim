# Referensi Metadata SIMPel Legacy

Direktori ini menyimpan metadata struktur SIMPel yang diekspor secara read-only pada 31 Agustus 2026. Tidak ada data pasien, transaksi, credential, atau isi stored routine di dalam lampiran.

## Isi

| File | Isi |
|---|---|
| [`ringkasan-schema.csv`](./ringkasan-schema.csv) | jumlah tabel/view per schema |
| [`katalog-menu.csv`](./katalog-menu.csv) | seluruh node menu dan privilege legacy |
| [`katalog-tabel.csv`](./katalog-tabel.csv) | schema, tabel, jenis, dan engine |
| [`katalog-kolom.csv`](./katalog-kolom.csv) | kolom, tipe, nullable, key, dan atribut |
| [`katalog-index.csv`](./katalog-index.csv) | index per tabel |
| [`relasi-foreign-key.csv`](./relasi-foreign-key.csv) | 572 relasi FK yang terlihat oleh akun metadata |
| [`ketergantungan-view.csv`](./ketergantungan-view.csv) | dependency view yang dapat dibaca; kosong bila akun legacy tidak memiliki metadata view |
| [`katalog-routine.csv`](./katalog-routine.csv) | nama dan jenis 792 stored routine, tanpa body |

Metadata mencakup 31 schema aplikasi dan sekitar 1.299 tabel/view. Gunakan pencarian teks berdasarkan `nama_schema,nama_tabel`; jangan membuka source legacy hanya untuk mencari nama kolom yang sudah ada di katalog ini.

## Cara membaca

- Fakta di CSV diberi status `TERVERIFIKASI-SUMBER`.
- Keberadaan tabel tidak membuktikan workflow atau aturan bisnisnya benar.
- FK yang tidak tercatat bukan bukti bahwa relasi tidak ada; legacy juga memakai relasi tanpa constraint dan stored routine.
- Desain aplikasi baru berada di [`../PETA-DATA-DAN-TABEL.md`](../PETA-DATA-DAN-TABEL.md), bukan di CSV ini.

## Memperbarui snapshot

Jalankan dari root workspace pada server yang mempunyai instalasi legacy:

```bash
php alat/ekspor-metadata-simpel.php
```

Script membaca konfigurasi lokal SIMPel, melakukan query hanya ke `information_schema` dan `aplikasi.modules`, lalu menimpa lampiran CSV tanpa menyimpan credential.
