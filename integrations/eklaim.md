# Integration — E-Klaim dan Episode Klaim BPJS

**Status:** target architecture  
**Referensi eksternal terakhir diaudit:** 2026-08-24

## Tujuan

E-Klaim menghubungkan coding/casemix dengan grouping INA-CBG/Non-INA-CBG. Ia bukan VClaim, bukan Tagihan internal, dan bukan sekadar generator PDF.

## Episode Klaim

Episode Klaim mengagregasi referensi terhadap:

- Faskes, Pasien, Pendaftaran, dan Kunjungan;
- Coverage dan SEP;
- tanggal masuk/keluar, jenis rawat, kelas, serta LOS;
- diagnosis/procedure klinis dan pilihan kode yang dikirim ke grouper;
- Tagihan serta alokasi Penjamin;
- versi grouper, stage, tarif, dan hasil grouping;
- dokumen wajib, kelengkapan, serta Berkas Klaim;
- Submission, pending/dispute, revisi, approval, pembayaran, dan rekonsiliasi.

Data sumber tetap dimiliki context asal. Episode Klaim menyimpan snapshot/version yang diperlukan untuk audit dan tidak silently overwrite submission lama.

## Lifecycle target

```text
draft
  → ready
  → grouped
  → submitted
  → pending_or_disputed → corrected → regrouped → resubmitted
  → approved
  → paid
  → reconciled
```

Transisi dapat disesuaikan dengan kontrak aktual, tetapi harus berupa command eksplisit dan tidak boleh melompat bebas melalui update CRUD.

## Separation of duties

- Klinisi bertanggung jawab pada diagnosis/procedure klinis dan amendment medis.
- Coder/casemix memilih kode yang ikut grouping dengan provenance.
- Petugas klaim menyusun berkas dan mengajukan episode.
- Supervisor menyetujui koreksi sensitif sesuai policy.
- Keuangan merekonsiliasi nilai disetujui/dibayar terhadap piutang Penjamin.

Permission harus ditegakkan backend. Semua perubahan menyimpan actor, reason, before/after, dan relasi ke versi submission.

## Kelengkapan dan berkas

Readiness bukan boolean manual tunggal. Sistem menghitung checklist berdasarkan jenis layanan/claim profile, misalnya:

- identitas dan SEP;
- resume dan diagnosis/procedure;
- hasil lab/radiologi/PA bila relevan;
- operasi/anestesi/transfusi bila relevan;
- resep/dispensing;
- rincian Tagihan;
- dokumen pendukung dan signature.

Berkas agregat adalah output ter-versioning. Urutan halaman, hash, sumber, waktu generate, dan actor harus dapat diaudit.

## Failure dan reconciliation

- Respons ambigu/timeout menggunakan reconciliation sebelum resubmit.
- Perubahan medis atau Tagihan setelah grouping menandai episode `stale/needs_regroup`.
- Pending/dispute membuat task koreksi dengan reason partner dan owner internal.
- Approval tidak otomatis berarti pembayaran telah diterima.
- Pembayaran BPJS direkonsiliasi ke Episode Klaim dan piutang, termasuk selisih/top-up bila berlaku.

## Readiness checklist

- [ ] Kontrak E-Klaim dan versi grouper untuk environment target tervalidasi.
- [ ] Data klinis, coding grouper, dan Tagihan mempunyai provenance terpisah.
- [ ] State transition serta separation of duties diuji.
- [ ] Payload/result setiap grouping dan submission ter-versioning.
- [ ] Workflow pending/dispute, regroup, resubmit, approval, dan reconciliation tersedia.
- [ ] Claim batch dan remittance, bila diwajibkan kontrak, dimodelkan eksplisit.

## Referensi

- [SIMGOS2 — E-Klaim](https://docs.simgos2.simpel.web.id/docs/integrasi/kemenkes/e-klaim/)
- [SIMGOS2 — ICD-10](https://docs.simgos2.simpel.web.id/docs/panduan/koding/diagnosa-10/)
- [SIMGOS2 — ICD-9](https://docs.simgos2.simpel.web.id/docs/panduan/koding/diagnosa-9/)
- [SATUSEHAT — Klaim BPJS](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/klaim-bpjs/)
