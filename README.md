# Dokumentasi SIMGOS

Direktori ini adalah sumber kebenaran produk dan arsitektur SIMGOS. Mulai dari [`BLUEPRINT-SIMGOS.md`](./BLUEPRINT-SIMGOS.md); dokumen itu mengarahkan pembaca ke detail yang dibutuhkan tanpa mengharuskan membuka source legacy.

## Catatan reset

Per 31 Agustus 2026, arah produk di-reset ke `single faskes`. Eksplorasi multi-faskes telah dipindahkan menjadi histori dan bukan bagian arsitektur aktif.

## Sumber kebenaran

Jika sumber saling bertentangan, gunakan urutan otoritas berikut:

1. Regulasi, terminologi, playbook, dan kontrak API resmi yang berlaku.
2. Keputusan yang diterima di [`adr/`](./adr/).
3. Spesifikasi produk, arsitektur, domain, workflow, dan integrasi di direktori ini.
4. Dokumentasi SIMGOS2/SIMPel untuk baseline perilaku operasional.
5. Kode legacy untuk detail perilaku yang belum terdokumentasi.
6. Implementasi SIMGOS saat ini; kode yang ada bukan otomatis perilaku yang benar.

Dokumen resmi eksternal dapat berubah. Versi endpoint, kredensial, payload, dan kesiapan production wajib diverifikasi kembali saat implementasi dan sebelum go-live.

## Cara membaca asal keputusan

Setiap spesifikasi target harus dapat dibedakan ke salah satu kategori berikut:

- **Baseline SIMPel/SIMGOS2:** istilah petugas, urutan kerja, precondition operasional, dan mental model yang sudah dikenal rumah sakit.
- **Kontrak resmi eksternal:** payload, status, kode, serta aturan pertukaran data BPJS, E-Klaim, dan SATUSEHAT. Kontrak resmi selalu mengalahkan perilaku SIMPel yang sudah usang.
- **Keputusan produk SIMGOS:** bentuk deployment aktif, boundary data yang dipakai saat ini, modular monolith, capability profile, dan prioritas vertical slice.
- **Engineering safety:** transaction, row lock, idempotency, outbox, audit provenance, retry, serta observability. Mekanisme ini menjaga workflow tetap benar; bukan klaim bahwa implementasi internal SIMPel menggunakan teknik yang sama.

Dokumen workflow mencantumkan tautan baseline atau kontrak yang relevan. ADR mencatat keputusan produk/arsitektur yang tidak berasal langsung dari sumber eksternal.

## Peta dokumen

- [`BLUEPRINT-SIMGOS.md`](./BLUEPRINT-SIMGOS.md): pintu masuk, sumber, bentuk produk, rantai kerja, dan gelombang implementasi.
- [`KATALOG-MODUL-SIMGOS.md`](./KATALOG-MODUL-SIMGOS.md): 147 modul utama, plugin tambahan, owner, dependensi, klasifikasi, dan gelombang.
- [`PETA-DATA-DAN-TABEL.md`](./PETA-DATA-DAN-TABEL.md): entity kanonik, cardinality, constraint, lifecycle, serta mapping tabel legacy.
- [`SUMBER-PEMBEDAHAN-SIMGOS.md`](./SUMBER-PEMBEDAHAN-SIMGOS.md): provenance audit situs, source, database, fakta terverifikasi, dan batasnya.
- [`PRODUK.md`](./PRODUK.md): tujuan produk, pengguna, scope, dan kriteria keberhasilan.
- [`ARSITEKTUR.md`](./ARSITEKTUR.md): satu deployment per Faskes, modular monolith, dan prinsip integrasi.
- [`MODEL-DOMAIN.md`](./MODEL-DOMAIN.md): relasi dan invariant domain utama.
- [`KONTRAK-LINTAS-DOMAIN.md`](./KONTRAK-LINTAS-DOMAIN.md): patokan istilah kanonik, pemilik data, dan titik serah antar domain.
- [`ALUR-OPERASIONAL-SIMGOS.md`](./ALUR-OPERASIONAL-SIMGOS.md): peta alur kerja lintas domain dalam bahasa operasional rumah sakit.
- [`PEMBAGIAN-KERJA-TIM-4-ORANG.md`](./PEMBAGIAN-KERJA-TIM-4-ORANG.md): pembagian stream kerja paralel untuk tim kecil.
- [`PANDUAN-UI-UX.md`](./PANDUAN-UI-UX.md): strategi UX, encounter workspace, permission, perangkat, dan state UI.
- [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md): urutan vertical slice dan satu-satunya sumber status delivery.
- [`KESIAPAN-PRODUCTION.md`](./KESIAPAN-PRODUCTION.md): keamanan, performa, backup, observability, release, UAT, dan gate go-live.
- [`referensi-simpel/`](./referensi-simpel/): snapshot metadata legacy tanpa data pasien atau credential.
- [`KONTEKS-RESET-2026-08-31.md`](./KONTEKS-RESET-2026-08-31.md): keputusan reset terbaru, ruang lingkup aktif, dan cara membaca dokumen lama.
- [`implementation/fondasi-awal.md`](./implementation/fondasi-awal.md): backlog engineering fondasi dan progres teknisnya.
- [`implementation/ppk.md`](./implementation/ppk.md): kontrak Master PPK, referensi SIMPel, acceptance, dan urutan implementasi.
- [`implementation/referensi-umum.md`](./implementation/referensi-umum.md): katalog referensi bersama, ownership, seed, API, dan konsolidasi tabel duplikat.
- [`implementation/akun-demo.md`](./implementation/akun-demo.md): akun lokal per role, kata sandi awal, dan aturan keamanan bootstrap.
- [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md): journey operasional pertama beserta failure path.
- [`integrations/bpjs.md`](./integrations/bpjs.md): batas Antrean Online, VClaim, Aplicares, dan ICare.
- [`integrations/eklaim.md`](./integrations/eklaim.md): coding, grouping, pengajuan, dispute, dan rekonsiliasi klaim.
- [`integrations/satusehat.md`](./integrations/satusehat.md): mapping FHIR, dependency, outbox, retry, dan monitoring.
- [`adr/`](./adr/): keputusan arsitektur yang mahal untuk dibalik.
- [`legacy/`](./legacy/): audit dan catatan historis; bukan spesifikasi target.
- [`CONTEXT.md`](./CONTEXT.md): glosarium kanonik tanpa detail implementasi.

Dokumen multi-faskes berada di [`legacy/multi-faskes/`](./legacy/multi-faskes/) dan hanya dipakai untuk memahami histori keputusan.

## Aturan pemeliharaan

- Status fitur hanya dicatat di `RENCANA-PENGEMBANGAN.md`; jangan menyalin angka progres ke dokumen lain.
- Workflow ditulis sebagai perjalanan lintas domain, bukan daftar halaman atau tabel.
- Setiap transisi final, batal, koreksi, pembayaran, dan pengiriman eksternal harus mencantumkan aktor, precondition, postcondition, serta audit event.
- Tandai fakta eksternal dengan tautan sumber dan tanggal verifikasi.
- Bedakan `implemented`, `workflow-integrated`, `integration-tested`, `operationally-validated`, dan `production-ready`.
