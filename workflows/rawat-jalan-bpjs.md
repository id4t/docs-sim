# Workflow — Rawat Jalan BPJS/FKRTL

**Status:** target specification
**Tujuan:** membuktikan satu perjalanan pasien end-to-end pada satu Faskes.
**Referensi eksternal terakhir diaudit:** 2026-08-24

## Aktor

- Pasien/peserta.
- Petugas pendaftaran dan administrasi BPJS.
- Dokter, perawat, farmasi, dan unit penunjang.
- Kasir/billing.
- Coder/casemix dan petugas klaim.
- Supervisor serta petugas integrasi.

## Preconditions Faskes

- Identitas Faskes, kode BPJS/Kemenkes, Organization dan Location SATUSEHAT valid.
- Mapping poli, DPJP, spesialisasi, kelas, tarif, tindakan, ICD, LOINC/KFA yang dibutuhkan tersedia.
- User mempunyai Role, Profesi, Akses Ruangan, dan permission yang sesuai.
- Credential serta health check Antrean Online, VClaim, E-Klaim, dan SATUSEHAT dikonfigurasi per environment.

## Happy path

### 1. Antrean dan kedatangan

1. Appointment/booking diperoleh dari kanal RS atau Mobile JKN.
2. SIMGOS menyimpan external booking ID dan status Antrean Online secara idempotent.
3. Saat pasien datang, petugas mencocokkan identitas dan melakukan check-in/arrival.
4. Pasien baru mendapat NRM dalam scope Faskes setelah duplicate check.

**Output:** pasien teridentifikasi, booking/arrival dapat ditelusuri, tidak ada Pendaftaran ganda aktif yang tidak disengaja.

### 2. Eligibilitas, Coverage, dan SEP

1. Petugas memilih Penjamin BPJS dan memeriksa peserta, rujukan/kontrol, poli, DPJP, serta kelas hak.
2. Sistem membuat Pendaftaran dan Coverage dengan referensi sumber yang tervalidasi.
3. VClaim menerbitkan atau menemukan SEP melalui operation idempotent.
4. SEP disimpan dengan remote status dan terhubung ke Pasien, Pendaftaran, Coverage, serta Kunjungan yang sama.

**Output:** episode mempunyai Coverage dan SEP yang sah; nomor antrean tidak diperlakukan sebagai SEP.

### 3. Pelayanan klinis

1. Kunjungan berpindah `waiting → received → in_service` oleh aktor berwenang.
2. Perawat mencatat asesmen awal/TTV; dokter mencatat anamnesis, pemeriksaan, diagnosis, rencana, dan order.
3. Unit tujuan menerima order dan mencatat fulfillment/hasil dengan status preliminary/final yang sesuai.
4. Farmasi melakukan telaah dan dispensing terhadap resep, bukan sekadar mengubah status resep.
5. Workspace menampilkan checklist kelengkapan dan warning mapping/integrasi.

**Output:** clinical record lengkap dan dapat ditelusuri ke Kunjungan; order mempunyai hasil atau state terminal yang sah.

### 4. Finalisasi

1. Sistem memeriksa diagnosis, order pending, hasil kritis, resep, resume, cara keluar, dan data wajib lain.
2. Aktor berwenang melakukan Final Rekam Medis lalu Final Layanan sebagai transisi berbeda.
3. Backend mengunci mutasi yang tidak sah dan merekam audit event.
4. Side effect finansial dan interoperabilitas dibuat melalui outbox.

**Output:** Kunjungan/Encounter selesai secara konsisten; koreksi hanya melalui amendment/batal-final beralasan.

### 5. Billing dan pembayaran

1. Item Tagihan dihasilkan dari layanan/produk yang sah dan direkonsiliasi.
2. Porsi BPJS, penjamin lain, subsidi, dan pasien dialokasikan eksplisit.
3. Final tagihan memeriksa semua precondition secara atomik.
4. Pembayaran pasien, jika ada, dilakukan melalui Sesi Kasir terbuka dan dicatat append-only.

**Output:** Tagihan final dapat ditelusuri ke pelayanan dan Coverage; total serta status konsisten saat request paralel.

### 6. Coding, grouping, dan klaim

1. Coder memilih diagnosis/procedure yang dikirim ke grouper tanpa mengubah diam-diam diagnosis klinis.
2. E-Klaim menerima data episode dan menjalankan grouping.
3. Episode Klaim menyimpan versi grouper, hasil, tarif, kelengkapan RME, dan Berkas Klaim.
4. Pengajuan klaim menggunakan state transition serta submission history yang eksplisit.

**Output:** SEP, Kunjungan, coding, grouping, Tagihan, dan berkas terhubung dalam satu Episode Klaim.

### 7. SATUSEHAT

1. Mapping Organization, Location, Practitioner, dan Patient tersedia lebih dahulu.
2. Encounter dan resource klinis dikirim menurut dependency graph dan use case resmi.
3. Remote ID disimpan; update menggunakan PUT saat record internal berubah secara sah.
4. Encounter baru menjadi `finished` setelah diagnosis dan data penutup lengkap.

**Output:** dashboard menunjukkan resource accepted atau item tindak lanjut yang actionable.

## Failure paths wajib

| Kondisi | Perilaku yang diharapkan |
|---|---|
| BPJS timeout setelah create SEP | Jangan create ulang buta; query/reconcile memakai operation identity. |
| Double-click pendaftaran/pembayaran | Idempotency dan unique constraint menghasilkan satu efek bisnis. |
| Rujukan/kelas/poli tidak cocok | Tolak transisi dan tampilkan data yang harus dikoreksi. |
| SATUSEHAT parent belum diterima | Child tetap queued dengan dependency reason. |
| Order belum selesai saat finalisasi | Finalisasi ditolak dengan checklist yang dapat dibuka. |
| Koreksi setelah final | Buat amendment/batal-final beralasan dan tandai downstream untuk resync/regroup. |
| E-Klaim pending/dispute | Episode masuk worklist koreksi; payload lama dan baru tetap dapat diaudit. |
| Worker crash saat mengirim | Lease kedaluwarsa dan submission dapat dilanjutkan tanpa duplikasi yang tidak terdeteksi. |
| Kredensial eksternal salah/kedaluwarsa | Circuit/health status mencegah spam retry dan memberi instruksi petugas IT. |

## Acceptance evidence

- Automated test untuk happy path dan seluruh failure path prioritas.
- Trace ID yang menghubungkan request UI, domain event, Submission, dan respons eksternal.
- Demonstrasi role/permission serta pembatasan Akses Ruangan dalam satu instalasi.
- Rekonsiliasi total Tagihan, pembayaran, dan hasil grouping.
- Validasi operasional oleh minimal petugas pendaftaran, klinis, farmasi, kasir, dan casemix.

## Referensi baseline

- [Pendaftaran Kunjungan SIMGOS2](https://docs.simgos2.simpel.web.id/docs/panduan/pendaftaran/pendaftaran-kunjungan/)
- [Final Rekam Medis SIMGOS2](https://docs.simgos2.simpel.web.id/docs/panduan/layanan/pelayanankunjungan/rekammedis/final-rekam-medis/)
- [Final Tagihan SIMGOS2](https://docs.simgos2.simpel.web.id/docs/panduan/pembayaran/final-tagihan-cetak-kwitansi/)
- [SATUSEHAT RME Rawat Jalan](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/rme-rawat-jalan/)

Baseline bukan pengganti verifikasi kontrak resmi saat implementasi.
