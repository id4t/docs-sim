# Rencana Pengembangan SIMGOS

Dokumen ini adalah satu-satunya sumber status delivery. Katalog modul menjelaskan cakupan; file ini mencatat bukti kematangan implementasi pada baseline branch senior.

## Status maturity

| Status | Arti |
|---|---|
| `unverified` | Keberadaan kode belum diperiksa terhadap blueprint aktif. |
| `scaffolded` | Route, tabel, atau placeholder tersedia; tugas pengguna belum selesai. |
| `implemented` | Fungsi lokal berjalan dengan validasi dasar. |
| `workflow-integrated` | Terhubung ke langkah sebelum/sesudah, state, permission, dan audit. |
| `integration-tested` | Happy path dan failure path lintas domain lulus. |
| `operationally-validated` | Petugas memvalidasi SOP dan usability melalui UAT. |
| `production-ready` | Seluruh gate keamanan, recovery, performa, observability, dan go-live lulus. |

Status awal seluruh capability adalah `unverified` sampai audit baseline branch senior selesai. Pekerjaan dari branch eksperimen multi-faskes tidak dihitung otomatis.

## Gelombang 0 — Fondasi

**Status:** `unverified`

- [ ] satu profil Faskes, satu PPK internal, satu database operasional;
- [ ] hierarki Ruangan dan Tempat Tidur;
- [ ] Pengguna, Role, Profesi, Akses Ruangan, MFA, break-glass;
- [ ] state machine, command final/batal/amend/reversal;
- [ ] sequence, idempotency, transaction, dan locking;
- [ ] audit, outbox, Submission, retry, dead letter, rekonsiliasi;
- [ ] credential terenkripsi dalam database;
- [ ] observability, backup/restore, migration, dan test harness journey.

Exit criteria rinci: [`implementation/fondasi-awal.md`](./implementation/fondasi-awal.md).

## Gelombang 1 — Rawat Jalan BPJS

**Status:** `unverified`

- [ ] pasien baru/lama, duplicate warning, NRM, dan check-in;
- [ ] Pendaftaran rawat jalan, Coverage, rujukan/kontrol, SEP;
- [ ] penerimaan poli dan workspace pasien;
- [ ] TTV, anamnesis, pemeriksaan, diagnosis, tindakan, rencana, resep;
- [ ] Final Rekam Medis dan Final Layanan terpisah;
- [ ] ItemTagihan, alokasi Penjamin, Final Tagihan, Sesi Kasir, Pembayaran;
- [ ] coding/grouping, Episode Klaim, readiness dan berkas minimum;
- [ ] SATUSEHAT rawat jalan dan monitoring error;
- [ ] happy path, duplicate, timeout, koreksi, retry, dan recovery test;
- [ ] UAT pendaftaran, klinis, farmasi, kasir, serta casemix.

Acceptance journey: [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md).

## Gelombang 2 — IGD, Rawat Inap, Penunjang, dan Farmasi

**Status:** `unverified`

- [ ] triase dan alur darurat;
- [ ] admisi, antrean/reservasi bed, penempatan, mutasi, dan discharge;
- [ ] order–penerimaan–hasil lab/radiologi beserta amendment;
- [ ] resep–telaah–dispensing–retur dan rekonsiliasi obat;
- [ ] inventory dasar dan ledger stok;
- [ ] resume, transfer, kelahiran, kematian, serta dokumen klinis;
- [ ] billing/klaim IGD dan rawat inap;
- [ ] Aplicares dan SATUSEHAT resource lanjutan.

## Gelombang 3 — Operasional dan Kepatuhan Lengkap

**Status:** `unverified`

- [ ] inventory lanjutan, opname, distribusi, supplier;
- [ ] deposit, piutang, diskon, non-tunai, dan laporan kas;
- [ ] berkas/readiness klaim, dispute, approval, dan rekonsiliasi;
- [ ] RL/SIRS serta laporan operasional;
- [ ] dashboard dan drill-down tanpa source of truth baru;
- [ ] document storage, template, TTE, retensi, dan audit;
- [ ] monitoring teknis serta runbook production.

## Gelombang 4 — Plugin dan Adapter Tambahan

**Status:** `unverified`

Kandidat: LIS vendor, CSSD, laundry, gizi, PPI, mutu, risk register, MSDM, penjadwalan operasi, distribusi berkas RM, PCare/FKTP, Sisrute, SITB, dan integrasi Kemenkes lain. Capability hanya masuk sprint jika ada kebutuhan instalasi, SOP, owner, dependency, dan acceptance.

## Gate sebelum berpindah gelombang

Gelombang berikutnya boleh dimulai secara terbatas hanya jika contract dependency sudah stabil. Penyelesaian gelombang membutuhkan:

- journey utama lulus end-to-end;
- tidak ada pelanggaran ownership data;
- migration dan rollback diuji;
- error dapat ditindak melalui worklist/monitoring;
- performa pada beban target lulus;
- UAT aktor terkait selesai.

## Cara memperbarui status

Setiap perubahan maturity mencatat capability, status lama/baru, bukti test/UAT, environment, tanggal, dan reviewer. Jangan memakai persentase berdasarkan jumlah folder, endpoint, atau halaman.
