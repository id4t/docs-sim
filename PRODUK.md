# Definisi Produk — SIMGOS

**Status:** authoritative  
**Ditetapkan:** 2026-08-24  
**Target awal:** rumah sakit/FKRTL, rawat jalan BPJS

## Tujuan

SIMGOS adalah produk modular monolith untuk menjalankan workflow klinik dan rumah sakit dari kedatangan pasien sampai pelayanan, pembayaran, klaim, dan interoperabilitas. Produk menggunakan satu codebase; setiap Grup menjalankan deployment sendiri dan setiap Faskes di dalamnya memiliki batas data serta konfigurasi sendiri.

SIMGOS bukan sekadar katalog CRUD dan tidak dinilai dari jumlah modul atau halaman. Nilai produk dibuktikan ketika satu perjalanan pasien dapat diselesaikan secara aman, dapat diaudit, dan dapat dipulihkan saat integrasi eksternal gagal.

## Strategi produk

- Satu repo dan satu build produk; tidak ada fork kode per pelanggan.
- Satu deployment/server per Grup; Grup berbeda tidak berbagi runtime atau database.
- Data klinis dan finansial diisolasi per Faskes.
- Core domain dapat melayani klinik dan rumah sakit, tetapi integrasi serta workflow diaktifkan melalui capability profile per jenis Faskes.
- Implementasi pertama berfokus pada RS/FKRTL Rawat Jalan BPJS.
- FKTP/PCare, rawat inap, IGD kompleks, operasi, dan workflow lanjutan masuk slice setelah core stabil.

## Persona utama milestone pertama

- Petugas pendaftaran dan administrasi BPJS.
- Dokter dan perawat rawat jalan.
- Farmasi serta unit penunjang sederhana.
- Kasir dan petugas billing.
- Coder/casemix dan petugas klaim.
- Petugas integrasi/IT Faskes.
- Auditor atau supervisor yang menangani koreksi dan pembatalan.

## Milestone pertama

Rawat Jalan BPJS/FKRTL end-to-end:

`pasien → antrean/kedatangan → eligibilitas/rujukan → SEP → pendaftaran → kunjungan → pelayanan → finalisasi → billing → coding/grouping → berkas klaim → SATUSEHAT → monitoring`

Rincian dan exception path berada di [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md).

## Prinsip penerimaan

Sebuah fitur tidak dianggap selesai hanya karena endpoint dan form tersedia. Status delivery mengikuti definisi di [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md), dan `production-ready` membutuhkan:

- invariant bisnis ditegakkan backend;
- otorisasi berdasarkan Faskes, profesi, Ruangan, dan aksi;
- transaksi finansial serta nomor dokumen aman terhadap concurrency;
- integrasi memiliki idempotency, retry, audit, dan rekonsiliasi;
- skenario end-to-end serta failure path teruji;
- error operasional dapat diselesaikan petugas melalui monitoring;
- validasi operasional oleh perwakilan pengguna terkait.

Selama belum ada validator operasional, fitur hanya dapat mencapai `integration-tested`, bukan `operationally-validated` atau `production-ready`.

## Non-goals milestone pertama

- Multi-Grup dalam satu deployment.
- Berbagi tabel klinis atau finansial lintas Faskes.
- Fork repository per pelanggan.
- Mendukung FKTP/PCare dan FKRTL secara bersamaan.
- Menyelesaikan seluruh katalog modul sebelum menguji journey pertama.
- Menganggap dokumentasi atau perilaku SIMPel sebagai pengganti kontrak resmi terkini.
