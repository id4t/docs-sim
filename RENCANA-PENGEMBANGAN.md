# Rencana Pengembangan — SIMGOS

Dokumen ini adalah satu-satunya sumber status delivery. Jangan menyalin jumlah modul selesai ke PRD, workflow, atau catatan lain.

## Status maturity

| Status | Arti |
|---|---|
| `scaffolded` | Route, tabel, atau placeholder tersedia; belum menyelesaikan tugas pengguna. |
| `implemented` | Fungsi lokal berjalan dengan validasi dasar. |
| `workflow-integrated` | Terhubung ke langkah sebelum/sesudah, state, permission, dan audit. |
| `integration-tested` | Happy path dan failure path lintas domain lolos pada environment representatif. |
| `operationally-validated` | Petugas terkait telah memvalidasi SOP dan usability. |
| `production-ready` | Security, observability, recovery, performance, runbook, dan acceptance go-live terpenuhi. |

Maturity dicatat per capability/journey, bukan per folder backend atau halaman frontend.

## Gate 0 — Fondasi arsitektur

**Status:** `scaffolded`

- [ ] Facility context tepercaya dan isolasi database per Faskes.
- [ ] Membership, role/profesi, Ruangan, dan policy aksi backend.
- [ ] Sequence dokumen concurrency-safe.
- [ ] State machine Kunjungan, hasil, Tagihan, klaim, dan Submission.
- [ ] Finalisasi serta amendment/cancellation service lintas modul.
- [ ] Transactional outbox, idempotency, retry, dead-letter, monitoring.
- [ ] Audit event yang konsisten dan redaction data sensitif.
- [ ] Test harness journey lintas modul.

## Slice 1 — Rawat Jalan BPJS/FKRTL

**Status:** `scaffolded`

Acceptance journey: [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md).

- [ ] Pasien, NRM, appointment/Antrean Online, dan arrival.
- [ ] Eligibilitas, rujukan, Coverage, SEP, dan traceability ke Kunjungan.
- [ ] Encounter Workspace adaptif untuk dokter/perawat.
- [ ] Asesmen, diagnosis, tindakan, order sederhana, resep, dan dispensing.
- [ ] Final RM/Kunjungan dengan server-side lock dan amendment.
- [ ] Tagihan, alokasi Penjamin, final tagihan, sesi kasir, dan Pembayaran.
- [ ] Coding ICD-10/ICD-9, grouping, Episode Klaim, dan berkas minimum.
- [ ] SATUSEHAT minimum rawat jalan dengan mapping, dependency, dan monitoring.
- [ ] End-to-end tests termasuk timeout, duplikasi, koreksi, dan retry.
- [ ] Validasi petugas pendaftaran, klinis, farmasi, kasir, dan casemix.

## Slice 2 — Penunjang diagnostik

**Status:** `scaffolded`

Lab/radiologi dari order sampai hasil final/amended, terminology mapping, ServiceRequest/Specimen/Observation/DiagnosticReport/ImagingStudy, billing, dan klaim.

## Slice 3 — Rawat inap BPJS

**Status:** `scaffolded`

Admisi, bed queue/reservation, mutasi, DPJP, medication administration, discharge planning, resume, LOS, SEP rawat inap, Aplicares, grouping, dan klaim.

## Slice 4 — IGD

**Status:** `scaffolded`

Arrival darurat, triase, penanganan tanpa menunggu administrasi normal, kasus kecelakaan, transfer/pulang/admisi, dan rekonsiliasi eligibility.

## Slice 5 — FKTP/klinik

**Status:** `scaffolded`

Capability profile FKTP, PCare, workflow klinik, rujukan ke FKRTL, serta subset SATUSEHAT yang berlaku. Core dipakai ulang tanpa memaksakan konsep SEP/E-Klaim FKRTL.

## Pekerjaan ditunda

- Menambah halaman berdasarkan urutan commit modul backend.
- Menghitung folder/controller sebagai progres produk.
- Multi-Grup dalam satu deployment.
- Master Patient Index lintas Faskes.
- Fitur granular yang tidak dibutuhkan slice aktif, kecuali untuk keamanan atau fondasi.

## Cara memperbarui

Perubahan status membutuhkan bukti: test, environment, kontrak eksternal, validator, dan tanggal. Gunakan catatan singkat di bawah item terkait; jangan mengubah status berdasarkan estimasi subjektif.
