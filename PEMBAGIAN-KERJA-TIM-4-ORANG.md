# Pembagian Kerja Tim 4 Orang

**Status:** patokan ownership dan delivery
**Prinsip:** setiap orang memegang vertical slice backend, frontend, migration, tes, dan dokumentasi pada domainnya

## Ringkasan ownership

| Orang | Domain utama | Hasil yang dimiliki |
|---|---|---|
| 1 | Pendaftaran dan Administrasi Pasien | Pasien, identifier, Pendaftaran, Coverage, master organisasi/pendaftaran |
| 2 | Layanan, Rekam Medis, Farmasi, Inventory | Kunjungan, catatan klinis, tindakan, order/hasil, obat, bed, stok |
| 3 | Billing, Kasir, Coding, Klaim | tarif, Tagihan, Pembayaran, coding/grouping, Episode dan Berkas Klaim |
| 4 | Platform, Akses, Integrasi, Monitoring | pengguna/role/akses, audit, credential, outbox/submission, adapter dan observability |

Owner menjaga invariant dan mereview perubahan pada tabelnya. Developer lain boleh membantu implementasi, tetapi tidak menulis langsung ke tabel domain lain tanpa kontrak owner.

## Orang 1 — Pendaftaran dan Administrasi Pasien

### Memiliki

- Pasien, Nomor Rekam Medis, identifier, alamat, kontak, keluarga, dan penggabungan duplikat;
- Pendaftaran, perubahan, pembatalan, riwayat, check-in, dan cetakan administratif;
- Coverage dan pemilihan Penjamin awal;
- profil Faskes, PPK, hierarki Ruangan, wilayah, serta master yang dipakai pendaftaran;
- pencarian pasien dan pegawai dari sisi entry point.

### Tidak memiliki

- lifecycle Kunjungan setelah dibuat;
- SEP dan komunikasi VClaim;
- isi Rekam Medis;
- alokasi nominal akhir pada Tagihan.

### Kontrak keluar

```text
patient_id
medical_record_number
registration_id
coverage_id
visit_type
destination_room_id
arrival/check-in state
```

Permintaan pembuatan Kunjungan diserahkan kepada Orang 2. Permintaan pemeriksaan BPJS/SEP diserahkan kepada Orang 4.

## Orang 2 — Layanan dan Klinis

### Memiliki

- Kunjungan, penerimaan Unit Layanan, mutasi, penempatan bed, dan final layanan;
- anamnesis, alergi, TTV, pemeriksaan, assessment, diagnosis klinis, CPPT, rencana, resume, serta amendment;
- tindakan dan pelaksana;
- order, item order, penerimaan, hasil, expertise, dan koreksi hasil;
- resep, telaah, dispensing, retur, KPO, dan antimikroba;
- barang, lokasi stok, ledger, dokumen stok, inventory, serta tempat tidur;
- master tindakan, barang, pemeriksaan, farmasi, profesi klinis, dan template anatomi.

### Tidak memiliki

- identitas/Pendaftaran pasien;
- coding klaim yang menggantikan diagnosis klinis;
- ItemTagihan dan perhitungan uang;
- pengiriman FHIR atau adapter LIS vendor.

### Kontrak keluar

```text
visit_id + lifecycle
procedure/service facts
orders + results
prescription + dispensing facts
clinical record + final state
discharge disposition
inventory movements
```

Fakta layanan dikirim kepada Orang 3 untuk menghasilkan ItemTagihan. Data klinis final dikirim kepada Orang 4 melalui outbox untuk SATUSEHAT.

## Orang 3 — Finansial dan Klaim

### Memiliki

- tarif effective-dated dan snapshot ItemTagihan;
- Tagihan, alokasi Penjamin, final/batal-final, diskon, deposit, piutang, dan gabung tagihan;
- Sesi Kasir, Pembayaran, refund, reversal, serta rekonsiliasi;
- diagnosis/procedure coding untuk klaim, grouping, dan Episode Klaim;
- readiness, Berkas Klaim, versi, dispute, approval, dan rekonsiliasi pembayaran;
- rekening rumah sakit serta laporan finansial/regulasi yang relevan.

### Tidak memiliki

- perubahan catatan diagnosis dokter;
- status klinis Kunjungan;
- komunikasi teknis E-Klaim;
- tabel tindakan, resep, atau stok.

### Kontrak keluar

```text
invoice_id + financial state
payment/reversal facts
claim_episode_id
coding/grouping version
claim readiness and submission request
```

Core klaim tetap dapat bekerja ketika jaringan mati. Orang 4 memiliki adapter E-Klaim, retry, dan rekonsiliasi teknis.

## Orang 4 — Platform, Integrasi, dan Monitoring

### Memiliki

- Pengguna, Role, permission, Akses Ruangan, MFA, session, dan break-glass;
- audit event, correlation ID, health check, metric, log, alert, dan runbook platform;
- credential integrasi terenkripsi serta rotasi melalui UI;
- transactional outbox, Submission, attempt history, retry, dead letter, dan rekonsiliasi;
- adapter Antrean Online, VClaim, Aplicares, I-Care, E-Klaim, SATUSEHAT, LIS, serta integrasi Kemenkes lain;
- monitoring teknis dan display publik yang memakai read model aman;
- API governance dan TTE dari sisi transport/audit.

### Tidak memiliki

- state bisnis sumber hanya karena menampilkannya di dashboard;
- Coverage, Kunjungan, Tagihan, atau Episode Klaim core;
- izin mengubah data klinis saat menangani error integrasi.

### Kontrak keluar

```text
external identifiers
submission status
attempt/retry state
reconciliation result
actionable integration error
health/alert state
```

## Master data tidak menjadi domain kelima

| Kelompok master | Owner |
|---|---|
| pasien, wilayah, PPK, Ruangan, Penjamin awal | Orang 1 |
| profesi, tindakan, barang, pemeriksaan, farmasi, bed | Orang 2 |
| tarif, rekening, komponen biaya | Orang 3 |
| pengguna, role, mapping/credential eksternal | Orang 4 |
| Referensi Umum | owner kategori; platform CRUD dasar dijaga bersama |

## Titik serah resmi

| Dari | Ke | Contract minimum | Trigger |
|---|---|---|---|
| O1 | O2 | Pasien, Pendaftaran, tujuan, Coverage | Pendaftaran confirmed |
| O1 | O4 | identitas, rujukan/kontrol, Coverage | operasi BPJS diminta |
| O2 | O3 | tindakan/produk/kamar sah, final layanan | fakta billable tercatat |
| O2 | O4 | clinical aggregate final | outbox event committed |
| O3 | O4 | Episode Klaim ready + versi | submit diminta petugas |
| O4 | O1/O2/O3 | external ID/status/error | submission berubah |

Setiap contract menetapkan nama field, contoh payload, status, error, versi, dan idempotency key sebelum implementasi paralel dimulai.

## Paket kerja per gelombang

### Gelombang 0 — Fondasi

| Orang | Paket kerja |
|---|---|
| O1 | profil Faskes/PPK, Ruangan, Pasien minimum, Referensi pendaftaran |
| O2 | skeleton Kunjungan, state machine, master klinis minimum |
| O3 | Tarif minimum, skeleton Tagihan/Sesi Kasir, invariant uang |
| O4 | auth/role/Ruangan, audit, credential encrypted, outbox/submission, observability dasar |

Exit bersama: satu journey sintetis dapat melewati empat domain dengan ID dan audit yang sama.

### Gelombang 1 — Rawat Jalan BPJS

| Orang | Paket kerja |
|---|---|
| O1 | pasien baru/lama, duplicate check, rawat jalan, Coverage, check-in |
| O2 | worklist poli, TTV, anamnesis, pemeriksaan, diagnosis, tindakan, resep, final RM/layanan |
| O3 | ItemTagihan, final Tagihan, Sesi Kasir, Pembayaran, coding/grouping, Episode Klaim minimum |
| O4 | Antrean/VClaim/SEP, SATUSEHAT minimum, E-Klaim transport, monitoring error |

Exit bersama: workflow [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md) lulus termasuk failure path.

### Gelombang 2 — Layanan rumah sakit

| Orang | Paket kerja |
|---|---|
| O1 | admisi IGD/RI/penunjang, perubahan administratif, reservasi awal |
| O2 | triase, bed/mutasi, lab, radiologi, farmasi, inventory, resume/transfer |
| O3 | kamar/deposit/piutang/non-tunai, klaim RI/IGD |
| O4 | Aplicares, resource SATUSEHAT lanjutan, adapter LIS pertama bila dibutuhkan |

### Gelombang 3 — Operasional lengkap

| Orang | Paket kerja |
|---|---|
| O1 | pencarian/riwayat/cetakan administratif lengkap |
| O2 | KPO/PPRA, stok lengkap, layanan klinis lanjutan |
| O3 | berkas/readiness klaim, laporan wajib dan finansial |
| O4 | dashboard operasional, TTE, alert/runbook, integrasi pemerintah tambahan |

### Gelombang 4 — Plugin

Plugin hanya ditarik ketika kebutuhan instalasi nyata mempunyai owner, SOP, dan acceptance. Jangan membangun semua plugin untuk mengejar parity menu.

## Aturan kerja tim

- `main` selalu siap dirilis; gunakan branch pendek per capability.
- Setiap orang maksimal memegang satu capability utama yang belum selesai.
- Pembuat fitur bertanggung jawab atas backend, frontend, migration, tes, dan docs.
- Perubahan contract direview owner dan konsumen yang terdampak.
- Migration yang sudah dijalankan tidak diedit; perubahan breaking dilakukan bertahap.
- Merge kecil dilakukan harian atau dalam beberapa hari, bukan akhir bulan.
- Release captain bergilir dan memeriksa backup, migration, smoke test, worker, scheduler, serta rollback.

## Definition of ready

Pekerjaan boleh dimulai jika:

- owner, aktor, use case, dan acceptance jelas;
- input/output dan dependency tersedia atau mempunyai stub;
- entity, state, permission, dan failure path disepakati;
- perubahan contract dan migration sudah direview pihak terdampak.

## Definition of done

Gunakan definisi pada [`BLUEPRINT-SIMGOS.md`](./BLUEPRINT-SIMGOS.md) dan gate pada [`KESIAPAN-PRODUCTION.md`](./KESIAPAN-PRODUCTION.md). Jumlah halaman atau controller bukan ukuran selesai.
