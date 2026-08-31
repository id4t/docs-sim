# Blueprint SIMGOS

**Status:** patokan aktif
**Ditetapkan:** 31 Agustus 2026
**Lingkup:** satu instalasi untuk satu faskes, modular monolith, dapat dikonfigurasi untuk klinik atau rumah sakit

Dokumen ini adalah pintu masuk untuk membangun SIMGOS dari fondasi sampai production. Baseline alur berasal dari dokumentasi resmi SIMPel/SIMGOS2, source legacy di `/var/www`, dan metadata database legacy; desain target mengikuti keputusan produk yang sudah disepakati.

## Aturan sumber

Urutan otoritas:

1. regulasi dan kontrak API resmi yang masih berlaku;
2. ADR dan keputusan produk yang diterima;
3. model domain, kontrak, dan workflow aktif dalam repository ini;
4. dokumentasi SIMPel/SIMGOS2;
5. source dan metadata database legacy;
6. implementasi aplikasi saat ini.

Setiap fakta diberi salah satu label berikut:

| Label | Arti |
|---|---|
| `TERVERIFIKASI-SUMBER` | Terlihat pada dokumentasi, source, atau metadata database legacy. |
| `INFERENSI` | Disimpulkan dari route, pemakaian tabel, atau perilaku terkait. |
| `BELUM-TERVERIFIKASI-DB` | Membutuhkan metadata, SOP, atau observasi operasional tambahan. |
| `KEPUTUSAN-PRODUK` | Aturan aplikasi baru yang telah disepakati. |

Kontrak BPJS, SATUSEHAT, E-Klaim, dan regulasi wajib mencatat versi serta tanggal verifikasi. Pemeriksaan ulang dilakukan saat adapter dikerjakan dan sebelum go-live.

## Peta dokumen aktif

| Kebutuhan | Dokumen |
|---|---|
| seluruh modul dan prioritas | [`KATALOG-MODUL-SIMGOS.md`](./KATALOG-MODUL-SIMGOS.md) |
| provenance pembedahan SIMPel | [`SUMBER-PEMBEDAHAN-SIMGOS.md`](./SUMBER-PEMBEDAHAN-SIMGOS.md) |
| istilah kanonik | [`CONTEXT.md`](./CONTEXT.md) |
| aggregate, tabel, relasi, dan ownership | [`PETA-DATA-DAN-TABEL.md`](./PETA-DATA-DAN-TABEL.md) |
| lifecycle dan handoff | [`MODEL-DOMAIN.md`](./MODEL-DOMAIN.md), [`KONTRAK-LINTAS-DOMAIN.md`](./KONTRAK-LINTAS-DOMAIN.md) |
| perjalanan operasional | [`ALUR-OPERASIONAL-SIMGOS.md`](./ALUR-OPERASIONAL-SIMGOS.md) |
| rawat jalan BPJS pertama | [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md) |
| pembagian empat orang | [`PEMBAGIAN-KERJA-TIM-4-ORANG.md`](./PEMBAGIAN-KERJA-TIM-4-ORANG.md) |
| urutan delivery | [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md) |
| UI/UX | [`PANDUAN-UI-UX.md`](./PANDUAN-UI-UX.md) |
| kesiapan operasional | [`KESIAPAN-PRODUCTION.md`](./KESIAPAN-PRODUCTION.md) |
| metadata legacy | [`referensi-simpel/README.md`](./referensi-simpel/README.md) |

## Bentuk produk

```text
Satu codebase
└── satu deployment per faskes
    ├── frontend pada domain utama
    ├── backend pada /api
    ├── satu database MariaDB
    ├── worker queue dan scheduler
    └── satu set konfigurasi integrasi terenkripsi
```

- Tidak ada membership, database switching, atau credential per faskes dalam satu runtime.
- Klinik dan rumah sakit memakai core yang sama; capability yang tidak dibutuhkan dinonaktifkan per instalasi.
- Modul tetap memiliki owner data dan invariant walaupun dirilis sebagai satu aplikasi.
- Sistem eksternal tidak menjadi sumber kebenaran data klinis atau finansial internal.

## Rantai kerja utama

```text
Pasien
  → Pendaftaran + Coverage
  → SEP/Antrean bila BPJS
  → Kunjungan unit
  → Rekam Medis + tindakan + order
  → hasil/dispensing/mutasi
  → Final Rekam Medis
  → Final Layanan
  → Tagihan + Pembayaran
  → coding/grouping + Episode Klaim
  → submission BPJS/E-Klaim/SATUSEHAT
  → monitoring + rekonsiliasi + laporan
```

Satu `Pendaftaran` dapat mempunyai beberapa `Kunjungan`. Final Rekam Medis, Final Layanan, dan Final Tagihan adalah tiga transisi berbeda. Pembatalan dan koreksi tidak menghapus histori.

## Gelombang implementasi

| Gelombang | Hasil |
|---|---|
| 0 — Fondasi | instalasi, akun/role/profesi/akses Ruangan, master minimum, audit, state machine, outbox, deployment dasar |
| 1 — Rawat jalan BPJS | pasien sampai SEP, poli, TTV, pemeriksaan, diagnosis/tindakan/resep, final, tagihan, klaim minimum, SATUSEHAT |
| 2 — Layanan rumah sakit | IGD, rawat inap, tempat tidur, lab, radiologi, farmasi, mutasi, resume |
| 3 — Operasional lengkap | inventory, klaim lengkap, laporan wajib, dashboard, document storage, TTE |
| 4 — Plugin | CSSD, laundry, gizi, PPI, mutu, risk, MSDM, operasi, dan adapter tambahan sesuai kebutuhan nyata |

## Definition of done capability

Capability belum selesai hanya karena tabel, endpoint, atau halaman sudah tersedia. Selesai berarti:

- happy path dan failure path berjalan;
- validasi dan permission backend diterapkan;
- state final, batal, koreksi, dan audit jelas;
- UI memiliki loading, empty, validation, conflict, dan error state;
- kontrak lintas domain serta integrasi terkait lulus;
- tes invariant, API/database, dan journey tersedia;
- SOP/runbook serta acceptance pengguna tersedia;
- bukti maturity dicatat di rencana pengembangan.

## Batas kelengkapan blueprint

Blueprint memetakan seluruh capability yang ditemukan, tetapi tidak mengklaim semua perilaku legacy layak ditiru. Metadata legacy adalah bukti struktur, bukan desain target. Plugin yang belum dibutuhkan cukup dipetakan; detail fisiknya dibuat saat capability memasuki gelombang aktif.
