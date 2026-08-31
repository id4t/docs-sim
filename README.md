# Dokumentasi SIMGOS

Direktori ini adalah sumber kebenaran produk dan arsitektur SIMGOS. Baca dokumen sesuai urutan berikut sebelum merancang atau mengimplementasikan fitur.

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
- **Keputusan produk SIMGOS:** deployment per Grup, isolasi per Faskes, modular monolith, capability profile, dan prioritas vertical slice.
- **Engineering safety:** transaction, row lock, idempotency, outbox, audit provenance, retry, serta observability. Mekanisme ini menjaga workflow tetap benar; bukan klaim bahwa implementasi internal SIMPel menggunakan teknik yang sama.

Dokumen workflow mencantumkan tautan baseline atau kontrak yang relevan. ADR mencatat keputusan produk/arsitektur yang tidak berasal langsung dari sumber eksternal.

## Peta dokumen

- [`PRODUK.md`](./PRODUK.md): tujuan produk, pengguna, scope, dan kriteria keberhasilan.
- [`ARSITEKTUR.md`](./ARSITEKTUR.md): deployment per Grup, isolasi Faskes, modular monolith, dan prinsip integrasi.
- [`MODEL-DOMAIN.md`](./MODEL-DOMAIN.md): relasi dan invariant domain utama.
- [`PANDUAN-UI-UX.md`](./PANDUAN-UI-UX.md): strategi UX, encounter workspace, permission, perangkat, dan state UI.
- [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md): urutan vertical slice dan satu-satunya sumber status delivery.
- [`implementation/fondasi-awal.md`](./implementation/fondasi-awal.md): backlog engineering fondasi dan progres teknisnya.
- [`implementation/ppk.md`](./implementation/ppk.md): kontrak Master PPK, referensi SIMPel, acceptance, dan urutan implementasi.
- [`implementation/referensi-umum.md`](./implementation/referensi-umum.md): katalog referensi bersama, ownership, seed, API, dan konsolidasi tabel duplikat.
- [`implementation/akun-demo.md`](./implementation/akun-demo.md): akun lokal per role, kata sandi awal, dan aturan keamanan bootstrap.
- [`implementation/keanggotaan-dan-akses.md`](./implementation/keanggotaan-dan-akses.md): lapisan kewenangan Faskes–role–Unit Layanan, lifecycle Membership, pemilihan konteks, dan Monitoring Global.
- [`implementation/multi-skema-faskes.md`](./implementation/multi-skema-faskes.md): keputusan database-per-Faskes, Facility context, ownership data, performa, migrasi, dan acceptance.
- [`implementation/penyiapan-faskes.md`](./implementation/penyiapan-faskes.md): kontrak draft UI, provisioning/retry CLI, keamanan, status, dan aktivasi Faskes.
- [`implementation/integrasi-bpjs-vclaim-per-faskes.md`](./implementation/integrasi-bpjs-vclaim-per-faskes.md): credential terenkripsi, route, role, kegagalan, dan rollout VClaim per Faskes.
- [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md): journey operasional pertama beserta failure path.
- [`integrations/bpjs.md`](./integrations/bpjs.md): batas Antrean Online, VClaim, Aplicares, dan ICare.
- [`integrations/eklaim.md`](./integrations/eklaim.md): coding, grouping, pengajuan, dispute, dan rekonsiliasi klaim.
- [`integrations/satusehat.md`](./integrations/satusehat.md): mapping FHIR, dependency, outbox, retry, dan monitoring.
- [`adr/`](./adr/): keputusan arsitektur yang mahal untuk dibalik.
- [`legacy/`](./legacy/): audit dan catatan historis; bukan spesifikasi target.
- [`../CONTEXT.md`](../CONTEXT.md): glosarium kanonik tanpa detail implementasi.

## Aturan pemeliharaan

- Status fitur hanya dicatat di `RENCANA-PENGEMBANGAN.md`; jangan menyalin angka progres ke dokumen lain.
- Workflow ditulis sebagai perjalanan lintas domain, bukan daftar halaman atau tabel.
- Setiap transisi final, batal, koreksi, pembayaran, dan pengiriman eksternal harus mencantumkan aktor, precondition, postcondition, serta audit event.
- Tandai fakta eksternal dengan tautan sumber dan tanggal verifikasi.
- Bedakan `implemented`, `workflow-integrated`, `integration-tested`, `operationally-validated`, dan `production-ready`.
