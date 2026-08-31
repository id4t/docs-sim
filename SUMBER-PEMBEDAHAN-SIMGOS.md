# Sumber Pembedahan SIMPel/SIMGOS2

**Tanggal audit:** 31 Agustus 2026
**Tujuan:** merekam fakta sumber yang sudah diperiksa agar tim tidak mengulang penelusuran baseline tanpa pertanyaan baru

## Sumber yang diperiksa

| Sumber | Lokasi | Hasil utama |
|---|---|---|
| dokumentasi resmi SIMGOS2 | [docs.simgos2.simpel.web.id](https://docs.simgos2.simpel.web.id/docs/) | arsitektur, menu, panduan operasional, integrasi, plugin |
| backend legacy | `/var/www/html/production/webapps/webservice` | module, route, service, entity, adapter, scheduler |
| frontend legacy | `/var/www/html/production/webapps/application/SIMpel` | bundle ExtJS dan nama workspace/form |
| script legacy | `/var/www/html/production/webapps/scripts` | scheduler BPJS/Kemenkes/LIS dan mode RS/klinik |
| database legacy | metadata `information_schema` read-only | schema, tabel, kolom, index, FK, routine, menu |
| audit terdahulu | [`legacy/ALUR-KERJA.md`](./legacy/ALUR-KERJA.md) | detail layar dan gap implementasi lama |

## Fakta arsitektur legacy

- Dokumentasi menjelaskan frontend SPA/RIA yang meminta data kepada banyak web service backend.
- Backend memakai Laminas API Tools dan memuat modul domain seperti Pendaftaran, Layanan, MedicalRecord, Pembayaran, Inventory, BPJS, INACBG, Kemkes/IHS, SIRS, RS Online, LIS, Berkas Klaim, TTE, Monitoring, serta Dashboard.
- Frontend yang tersedia merupakan bundle compiled; struktur menu dan privilege utama berasal dari row database `aplikasi.modules`.
- Banyak aturan finansial dan operasional legacy berada di stored routine; source utama DDL/migration tidak tersedia di `/var/www`.
- Script `mode_rs.sh` dan `mode_klinik.sh` adalah switch mode legacy, bukan bukti bahwa database baru memiliki migration lengkap.

## Ukuran sumber legacy

Snapshot metadata mencatat:

- 31 schema aplikasi;
- sekitar 1.299 tabel/view;
- 12.664 definisi kolom;
- 4.517 baris metadata index;
- 572 foreign key yang dapat terlihat;
- 792 stored routine tanpa body dalam snapshot;
- 562 node menu/privilege;
- 147 modul utama dalam 18 domain.

Rincian tersedia di [`referensi-simpel/`](./referensi-simpel/).

## Graph data yang terverifikasi

```text
master.pasien.NORM
  → pendaftaran.pendaftaran.NOMOR/NORM
      → pendaftaran.kunjungan.NOMOR/NOPEN/RUANGAN/DPJP
          → layanan.*
          → medicalrecord.*
          → pembayaran.tagihan_pendaftaran/tagihan/rincian_tagihan
          → inacbg.* dan berkas_klaim.*
          → bpjs.* dan kemkes-ihs.*
```

`Kunjungan` terbukti bukan sinonim `Pendaftaran`: ia membawa unit/Ruangan, DPJP, bed, status, mutasi, pulang, pembatalan, dan aktivitas pelayanan. Aplikasi baru mempertahankan perbedaan tersebut, tetapi ownership dipertegas: Pendaftaran dimiliki domain Pendaftaran, Kunjungan dimiliki Layanan.

## Fakta penting per domain

### Pendaftaran dan akses

- `PendaftaranService` legacy mengorkestrasi pasien, tujuan, rujukan, Penjamin, Kunjungan, tindakan awal, INACBG, berkas klaim, dan IHS Encounter.
- Akses menu berasal dari `aplikasi.pengguna_akses → group_pengguna_akses_module → aplikasi.modules`.
- Akses Ruangan legacy diturunkan dari profesi/penempatan dan juga memiliki tabel `aplikasi.pengguna_akses_ruangan`; aplikasi baru memakai satu aturan efektif Role + Ruangan + Profesi.

### Layanan dan Rekam Medis

- Order lab/radiologi/resep mempunyai header, item, penerimaan, status, dan hasil.
- Medical Record legacy memiliki ratusan route dan tabel untuk anamnesis, TTV, assessment, diagnosis, CPPT, keperawatan, resume, transfer, dan dokumen khusus.
- Diagnosis klinis (`medicalrecord.diagnosis`) dan coding (`medicalrecord.diagnosa`) memang berbeda.
- CPPT dan verifikasi mempunyai jejak terpisah; ini mendukung pola append-only/counter-sign.

### Billing dan klaim

- Billing mencakup Tagihan, rincian, penjamin, kasir, pembayaran, non-tunai, deposit/refund, piutang, diskon, gabung/batal, subsidi, dan klaim.
- Legacy banyak bergantung pada stored procedure untuk restore/recalculate/lock/distribusi tarif; aplikasi baru harus menulis invariant secara eksplisit dan mengujinya.
- Episode klaim JKN legacy ditelusuri melalui `tagihan_pendaftaran`, Pendaftaran, Pasien, tujuan/Ruangan, Penjamin, pulang, grouping, Tagihan, dan resource IHS.

### BPJS

- VClaim mengelola peserta, rujukan, SEP, kontrol, update pulang, pembatalan, dan approval.
- SEP sukses disimpan pada `bpjs.kunjungan` dengan nomor, referensi transaksi, error mapping, dan status.
- Antrean Online memerlukan mapping poli/gedung/POS, jadwal, kuota, task ID, check-in, batal, serta antrean farmasi.

### SATUSEHAT

- Master resource: Organization, Location, Practitioner, Medication, Patient.
- Resource transaksi yang ditemukan: Encounter, Condition, AllergyIntolerance, Observation, Procedure, ServiceRequest, Specimen, DiagnosticReport, ImagingStudy, Composition, MedicationRequest, MedicationDispense, CarePlan, dan resource terkait.
- Banyak staging legacy menghubungkan resource ke `pendaftaran.pendaftaran.NOMOR` melalui `refId/nopen`.
- Scheduler legacy mengirim resource berurutan, tetapi observability retry dan beberapa resource masih tidak lengkap. Pola tersebut tidak disalin mentah; aplikasi baru memakai Outbox dan Submission.

## Katalog capability di luar 147 modul

Dokumentasi/source juga menunjukkan Antrean Mobile/Web, Apotek Online, Penjadwalan Operasi, MSDM, CSSD, Laundry, Gizi, LIS, Mutu, PPI, Distribusi Berkas RM, Risk Register, Eplanning, Indikator Kinerja, APM, RS Online, SIRS, SITB, Sisrute, Dukcapil, Pusdatin, TTE, WA gateway, dan text-to-speech.

Capability tersebut tetap tercatat di katalog, tetapi berstatus plugin/adapter sampai ada kebutuhan instalasi atau kewajiban regulasi.

## Hal yang tidak boleh disimpulkan

- Keberadaan menu tidak membuktikan fitur aktif atau matang pada setiap rumah sakit.
- Keberadaan tabel tidak membuktikan state machine, SOP, atau validasinya benar.
- FK yang tidak tercatat tidak membuktikan ketiadaan relasi karena legacy sering memakai stored routine dan join tanpa constraint.
- Dokumentasi SIMPel tidak menggantikan kontrak resmi BPJS/SATUSEHAT/E-Klaim terkini.
- Source legacy tidak mempunyai test domain yang memadai; perilakunya bukan otomatis acceptance aplikasi baru.
- Sample data pada SQL bridging LIS tidak boleh dijadikan seed production.

## Kapan sumber perlu dibuka kembali

Sumber legacy hanya dibuka kembali bila:

1. capability memasuki implementasi dan fakta berlabel `INFERENSI` atau `BELUM-TERVERIFIKASI-DB` belum cukup;
2. SOP faskes berbeda dari baseline;
3. metadata snapshot tidak memuat body routine yang menentukan aturan penting;
4. ditemukan kontradiksi antara blueprint dan perilaku yang diwajibkan;
5. kontrak eksternal atau regulasi berubah.

Penelusuran baru wajib memperbarui dokumen terkait, label kepastian, tanggal, dan sumber; jangan menyimpan hasilnya hanya di chat.
