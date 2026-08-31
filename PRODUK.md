# Definisi Produk — SIMGOS

**Status:** authoritative
**Ditetapkan:** 2026-08-31
**Target awal:** rumah sakit/FKRTL, rawat jalan BPJS

## Tujuan

SIMGOS adalah produk modular monolith untuk menjalankan workflow klinik dan rumah sakit dari kedatangan pasien sampai pelayanan, pembayaran, klaim, dan interoperabilitas. Pada arah aktif saat ini, satu instalasi melayani satu faskes dan satu lingkungan operasional.

SIMGOS bukan sekadar katalog CRUD dan tidak dinilai dari jumlah modul atau halaman. Nilai produk dibuktikan ketika satu perjalanan pasien dapat diselesaikan secara aman, dapat diaudit, dan dapat dipulihkan saat integrasi eksternal gagal.

## Strategi produk

- Satu repo dan satu build produk; tidak ada fork kode per pelanggan.
- Satu deployment/server per instalasi faskes.
- Data klinis, finansial, konfigurasi, dan kredensial integrasi berada dalam boundary instalasi tunggal itu.
- Core domain tetap dirancang agar bisa dipakai klinik atau rumah sakit, tetapi milestone aktif berfokus pada rumah sakit/FKRTL.
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
- otorisasi berdasarkan role, profesi, ruangan/unit, dan aksi;
- transaksi finansial serta nomor dokumen aman terhadap concurrency;
- integrasi memiliki idempotency, retry, audit, dan rekonsiliasi;
- skenario end-to-end serta failure path teruji;
- error operasional dapat diselesaikan petugas melalui monitoring;
- validasi operasional oleh perwakilan pengguna terkait.

Selama belum ada validator operasional, fitur hanya dapat mencapai `integration-tested`, bukan `operationally-validated` atau `production-ready`.

## Non-goals milestone pertama

- Multi-faskes dalam satu runtime aktif.
- Provisioning database per faskes dari runtime yang sama.
- Fork repository per pelanggan.
- Mendukung FKTP/PCare dan FKRTL secara bersamaan.
- Menyelesaikan seluruh katalog modul sebelum menguji journey pertama.
- Menganggap dokumentasi atau perilaku SIMPel sebagai pengganti kontrak resmi terkini.
