# Katalog Modul SIMGOS

**Status:** patokan cakupan
**Baseline:** SIMPel/SIMGOS2, source `/var/www`, dan `aplikasi.modules` legacy per 31 Agustus 2026

Katalog ini memastikan seluruh 147 modul utama dalam 18 domain legacy tetap terlacak. Node privilege yang lebih rinci tersedia di [`referensi-simpel/katalog-menu.csv`](./referensi-simpel/katalog-menu.csv). Plugin tambahan dicatat setelah katalog inti.

## Legenda

| Kode | Arti |
|---|---|
| `G0` | fondasi |
| `G1` | rawat jalan BPJS end-to-end |
| `G2` | IGD, rawat inap, penunjang, farmasi |
| `G3` | operasional, klaim, laporan, dan monitoring lengkap |
| `G4` | plugin/adapter sesuai kebutuhan instalasi |
| `I` | inti |
| `L` | lanjutan |
| `P` | plugin/opsional |
| `V` | perlu validasi SOP sebelum desain fisik |

## Profil domain

Setiap modul pada tabel berikut mewarisi kontrak domain ini. Detail entity dan mapping tabel ada di [`PETA-DATA-DAN-TABEL.md`](./PETA-DATA-DAN-TABEL.md).

| Domain | Owner | Aktor utama | Input → output | State/aturan utama | Integrasi/laporan |
|---|---|---|---|---|---|
| Pendaftaran | Orang 1 | loket, admin BPJS | Pasien/master → Pendaftaran, Coverage, permintaan Kunjungan | konfirmasi, batal, koreksi beralasan | BPJS, antrean, sensus |
| Layanan | Orang 2 | perawat, dokter, unit penunjang | Kunjungan → tindakan, order, hasil, final layanan | menunggu → diterima → dilayani → final/batal | Billing, RM, SATUSEHAT |
| Pembayaran | Orang 3 | billing, kasir | fakta layanan → Tagihan, alokasi, Pembayaran | draft → final → parsial/lunas/void | klaim, laporan kas |
| Rekam Medis | Orang 2 | dokter, perawat, petugas RM | Kunjungan → catatan klinis legal | draft → final → amended | klaim, SATUSEHAT, TTE |
| Laporan | owner sumber + Orang 3 | manajemen, regulator | view baca → laporan periodik | versi/periode laporan | SIRS/RL |
| Dashboard | owner sumber + Orang 4 | supervisor, developer | indikator baca → drill-down | bukan source of truth | monitoring |
| Master | owner domain terkait | administrator | referensi disetujui → master aktif | aktif/nonaktif; kode stabil | mapping eksternal |
| Informasi | Orang 4 | publik/petugas | proyeksi minimum → display | masking identitas | antrean/bed |
| Penjualan | Orang 2 + Orang 3 | farmasi, kasir | barang → penjualan dan tagihan | draft → final/retur | inventory, kasir |
| Tempat Tidur | Orang 2 | admisi, bangsal | kebutuhan bed → reservasi/penempatan | tersedia/dipesan/terisi/maintenance | Aplicares, sensus |
| Inventory | Orang 2 | gudang, depo | dokumen stok → ledger/pergerakan | draft → disetujui → diterima/batal | farmasi, laporan |
| Pencarian | Orang 1 | seluruh petugas | identifier → hasil berizin | baca saja | semua workspace |
| Integrasi | Orang 4 | admin integrasi | outbox → submission/rekonsiliasi | queued → sending → accepted/gagal | BPJS, LIS |
| Monitoring | owner sumber + Orang 4 | supervisor, developer | status domain → worklist masalah | terbuka → ditindak → selesai | alert/audit |
| Akses API | Orang 4 | admin/developer | policy → akses API | aktif/dicabut/kedaluwarsa | audit keamanan |
| Logs | Orang 4 | auditor/developer | event → audit append-only | tidak dapat diedit | TTE/keamanan |
| KPO | Orang 2 | dokter, farmasis | resep → kajian/intervensi | menunggu → dikaji → final | farmasi, RM |
| Berkas Klaim | Orang 3 | casemix/verifikator | dokumen sumber → bundel/readiness | draft → lengkap → final | E-Klaim |

Acceptance umum setiap modul: permission backend, validasi, state terminal, pembatalan/koreksi, audit, UI state, contract test, journey test, dan dokumentasi operasional tersedia.

## 10 — Pendaftaran (14)

| Kode | Modul | Kelas | Gelombang | Ketergantungan utama | Kepastian |
|---|---|---:|---:|---|---|
| 1001 | Pasien Baru | I | G1 | identitas, NRM, wilayah | TERVERIFIKASI-SUMBER |
| 1002 | Rawat Jalan | I | G1 | Pasien, Ruangan, Coverage | TERVERIFIKASI-SUMBER |
| 1003 | Gawat Darurat | I | G2 | Pasien, IGD, triase | TERVERIFIKASI-SUMBER |
| 1004 | Rawat Inap | I | G2 | admisi, bed, DPJP | TERVERIFIKASI-SUMBER |
| 1005 | Laboratorium | L | G2 | order/kunjungan lab | TERVERIFIKASI-SUMBER |
| 1006 | Radiologi | L | G2 | order/kunjungan radiologi | TERVERIFIKASI-SUMBER |
| 1007 | Perubahan Data | I | G1 | Pasien/Pendaftaran, audit | TERVERIFIKASI-SUMBER |
| 1008 | Pencetakan | I | G1 | pasien, pendaftaran, template | TERVERIFIKASI-SUMBER |
| 1009 | History Pendaftaran | I | G1 | Pasien, Pendaftaran | TERVERIFIKASI-SUMBER |
| 1010 | Pembatalan | I | G1 | alasan, state Pendaftaran/Kunjungan | TERVERIFIKASI-SUMBER |
| 1011 | Penerimaan | I | G1 | arrival/check-in | TERVERIFIKASI-SUMBER |
| 1012 | Tindakan List | V | G2 | tindakan awal/administratif | INFERENSI |
| 1013 | Triage Pendaftaran | V | G2 | IGD, Rekam Medis | INFERENSI |
| 1014 | Pasien Meninggal/Forensik | L | G2 | Kunjungan, verifikasi kematian | TERVERIFIKASI-SUMBER |

## 11 — Layanan (16)

| Kode | Modul | Kelas | Gelombang | Ketergantungan utama | Kepastian |
|---|---|---:|---:|---|---|
| 1101 | Penerimaan Ruangan | I | G1 | Kunjungan, Ruangan, akses | TERVERIFIKASI-SUMBER |
| 1102 | Tindakan/Pemeriksaan Medis | I | G1 | Kunjungan, Tindakan, pelaksana | TERVERIFIKASI-SUMBER |
| 1103 | Pengiriman Order/Konsul/Mutasi | I | G1 | Kunjungan, unit tujuan | TERVERIFIKASI-SUMBER |
| 1104 | Feedback/Hasil Pemeriksaan | I | G2 | order diterima, parameter hasil | TERVERIFIKASI-SUMBER |
| 1105 | Pembacaan/Ekspertise | I | G2 | hasil final, verifikator | TERVERIFIKASI-SUMBER |
| 1106 | History Layanan | I | G1 | Pasien, seluruh Kunjungan | TERVERIFIKASI-SUMBER |
| 1107 | Pencetakan Hasil | L | G2 | hasil final, template | TERVERIFIKASI-SUMBER |
| 1108 | Pembatalan Layanan | I | G1 | order/tindakan belum final | TERVERIFIKASI-SUMBER |
| 1109 | Final/Selesai Pelayanan | I | G1 | rule kelengkapan, outcome | TERVERIFIKASI-SUMBER |
| 1110 | Kelahiran | L | G2 | persalinan, bayi, ibu | TERVERIFIKASI-SUMBER |
| 1111 | Pemakaian BHP | L | G2 | stok Ruangan, tindakan | TERVERIFIKASI-SUMBER |
| 1112 | Bon Sisa | L | G2 | dispensing, retur stok | TERVERIFIKASI-SUMBER |
| 1113 | Pemanggilan Antrean | L | G2 | antrean Kunjungan | TERVERIFIKASI-SUMBER |
| 1114 | Layanan Tambahan | L | G2 | O2/darah/layanan unit | TERVERIFIKASI-SUMBER |
| 1115 | KPO | L | G3 | resep, kajian farmasi | TERVERIFIKASI-SUMBER |
| 1116 | Formulir Antimikroba | L | G3 | PPRA, antibiotik, approval | TERVERIFIKASI-SUMBER |

## 12 — Pembayaran (14)

| Kode | Modul | Kelas | Gelombang | Ketergantungan utama | Kepastian |
|---|---|---:|---:|---|---|
| 1201 | Final Tagihan | I | G1 | layanan final, sesi kasir | TERVERIFIKASI-SUMBER |
| 1202 | Penerimaan Uang Muka | L | G2 | Tagihan, sesi kasir | TERVERIFIKASI-SUMBER |
| 1203 | Pengembalian Uang Muka | L | G2 | deposit, otorisasi | TERVERIFIKASI-SUMBER |
| 1204 | Piutang | L | G3 | Tagihan, debitur | TERVERIFIKASI-SUMBER |
| 1205 | Pelunasan Piutang | L | G3 | piutang, Pembayaran | TERVERIFIKASI-SUMBER |
| 1206 | Pembayaran Non Tunai | I | G1 | bank/EDC/transfer | TERVERIFIKASI-SUMBER |
| 1207 | Diskon | L | G3 | Tagihan, approval | TERVERIFIKASI-SUMBER |
| 1208 | Pembatalan Tagihan Final | I | G1 | reversal, alasan, audit | TERVERIFIKASI-SUMBER |
| 1209 | Transaksi/Sesi Kasir | I | G1 | pengguna kasir, shift | TERVERIFIKASI-SUMBER |
| 1211 | Batal Gabung Tagihan | L | G3 | gabung Tagihan | TERVERIFIKASI-SUMBER |
| 1212 | Penjamin Tagihan | I | G1 | Coverage, alokasi nominal | TERVERIFIKASI-SUMBER |
| 1213 | Pencetakan Kuitansi | I | G1 | Tagihan final, Pembayaran | TERVERIFIKASI-SUMBER |
| 1214 | Transaksi Penjualan Apotek | L | G2 | penjualan OTC, kasir | TERVERIFIKASI-SUMBER |
| 1215 | Pembatalan Permintaan Belum Diterima | I | G1 | order pending, final tagihan | TERVERIFIKASI-SUMBER |

## 13 — Rekam Medis (19)

| Kode | Modul | Kelas | Gelombang | Ketergantungan utama | Kepastian |
|---|---|---:|---:|---|---|
| 1301 | Pencetakan/Lembaran RM | I | G2 | catatan final, template | TERVERIFIKASI-SUMBER |
| 1302 | Anamnesis | I | G1 | Kunjungan, terminologi | TERVERIFIKASI-SUMBER |
| 1303 | TTV dan Pemeriksaan | I | G1 | Kunjungan, pelaksana | TERVERIFIKASI-SUMBER |
| 1304 | Penilaian/Assessment | I | G2 | skala klinis, profesi | TERVERIFIKASI-SUMBER |
| 1305 | ICD, Coding, Grouping | I | G1 | diagnosis klinis, coder | TERVERIFIKASI-SUMBER |
| 1306 | Penandaan Gambar | L | G2 | template anatomi | TERVERIFIKASI-SUMBER |
| 1307 | Perencanaan Medis | I | G1 | diagnosis, DPJP | TERVERIFIKASI-SUMBER |
| 1308 | CPPT | I | G1 | PPA, verifikasi DPJP | TERVERIFIKASI-SUMBER |
| 1309 | Penerbitan Surat | L | G2 | dokter, template, TTE | TERVERIFIKASI-SUMBER |
| 1310 | Resume Medis | I | G1 | data klinis final | TERVERIFIKASI-SUMBER |
| 1311 | Keperawatan/Kebidanan | I | G2 | SDKI/SLKI/SIKI, profesi | TERVERIFIKASI-SUMBER |
| 1312 | Triage IGD | I | G2 | Kunjungan IGD | TERVERIFIKASI-SUMBER |
| 1313 | Rekonsiliasi Obat | I | G2 | admisi/transfer/discharge | TERVERIFIKASI-SUMBER |
| 1314 | Pemantauan Kritis | V | G4 | ICU/HD, tren observasi | INFERENSI |
| 1315 | Tindakan/Terapi Khusus | L | G4 | operasi/anestesi/transfusi | TERVERIFIKASI-SUMBER |
| 1316 | Hasil MCU | V | G4 | paket MCU | INFERENSI |
| 1317 | Form Registry SATUSEHAT | V | G4 | registry/terminologi | INFERENSI |
| 1318 | Upload Dokumen | I | G2 | document storage, audit | TERVERIFIKASI-SUMBER |
| 1319 | Lembar Transfer Pasien | I | G2 | mutasi, serah terima | TERVERIFIKASI-SUMBER |

## 14 — Laporan (12)

| Kode | Modul | Kelas | Gelombang | Ketergantungan utama |
|---|---|---:|---:|---|
| 1401 | Rekapitulasi Laporan RL | I | G3 | data regulasi, versi format |
| 1402 | Pengunjung | I | G3 | Pasien/Pendaftaran |
| 1403 | Kunjungan | I | G3 | Kunjungan/Ruangan |
| 1404 | Layanan | I | G3 | tindakan/order/hasil |
| 1405 | Penerimaan Kasir | I | G3 | sesi kasir/Pembayaran |
| 1406 | Jasa Pelayanan | L | G3 | tarif dan distribusi jasa |
| 1407 | Inventory | I | G3 | ledger stok |
| 1408 | Rekam Medis | I | G3 | diagnosis/ICD |
| 1409 | Pendapatan | I | G3 | Tagihan/Pembayaran |
| 1410 | Kegiatan Rawat Inap dan Darurat | I | G3 | admisi/pulang/kematian |
| 1411 | Monitoring Pelayanan | I | G3 | state Kunjungan |
| 1412 | Kinerja Dokter dan Perawat | L | G3 | pelaksana/tindakan |

Semua modul laporan berstatus `TERVERIFIKASI-SUMBER`; format regulasi tetap harus diverifikasi sebelum go-live.

## 15 — Dashboard (11)

| Kode | Modul | Kelas | Gelombang | Sumber data utama |
|---|---|---:|---:|---|
| 1501 | Indikator | L | G3 | indikator lintas domain |
| 1502 | Pengunjung | L | G3 | Pendaftaran |
| 1503 | Kunjungan | L | G3 | Layanan |
| 1504 | Rawat Inap | L | G3 | bed/admisi |
| 1505 | Laboratorium | L | G3 | order/hasil lab |
| 1506 | Radiologi | L | G3 | order/hasil radiologi |
| 1507 | 10 Kasus Diagnosis Terbesar | L | G3 | diagnosis klinis/coding |
| 1508 | 10 Kasus INA-CBG Terbesar | L | G3 | grouping |
| 1509 | Pendapatan | L | G3 | Billing |
| 1510 | Klaim INA-CBG | L | G3 | Episode Klaim |
| 1511 | Waktu Layanan Rawat Darurat | L | G3 | event IGD |

Semua modul dashboard berstatus `TERVERIFIKASI-SUMBER` dan hanya merupakan read model.

## 19 — Master (28)

| Kode | Modul | Owner | Kelas | Gelombang | Ketergantungan utama |
|---|---|---|---:|---:|---|
| 1901 | PPK | O1 | I | G0 | profil faskes/rujukan |
| 1902 | Pegawai | O1/O2 | I | G0 | profesi, penempatan |
| 1903 | Manajemen Pengguna | O4 | I | G0 | akun, role, Ruangan |
| 1904 | Ruangan | O1 | I | G0 | hierarki, kelas, bed |
| 1905 | Tindakan | O2 | I | G0 | kategori, tarif |
| 1906 | Kategori | sesuai domain | I | G0 | referensi |
| 1907 | Penyedia | O2 | L | G2 | inventory |
| 1908 | Barang | O2 | I | G2 | farmasi/inventory |
| 1909 | Paket | O1/O3 | L | G2 | layanan/tarif |
| 1910 | Paket Tindakan Farmasi | O2 | L | G2 | resep/barang |
| 1911 | Group/Jenis Pemeriksaan | O2 | I | G2 | lab/radiologi |
| 1912 | Tarif | O3 | I | G1 | tindakan, kelas, penjamin |
| 1913 | Margin Obat | O2/O3 | L | G2 | barang, penjamin |
| 1914 | Penjamin Ruangan | O1 | I | G1 | Ruangan, Penjamin |
| 1915 | BPJS | O4 | I | G1 | mapping referensi BPJS |
| 1916 | Negara | O1 | I | G0 | identitas/alamat |
| 1917 | Wilayah | O1 | I | G0 | alamat pasien |
| 1918 | Referensi | sesuai domain | I | G0 | kode stabil/label |
| 1919 | Kemenkes | O4 | I | G1–G3 | RS Online/SITB/SIRS/SATUSEHAT |
| 1920 | Mapping | sesuai domain | I | G0+ | sistem eksternal |
| 1921 | Pengaturan | O4 | I | G0 | konfigurasi instalasi |
| 1922 | Penjamin Rumah Sakit | O1/O3 | I | G1 | Coverage/tagihan |
| 1923 | Template Anatomi | O2 | L | G2 | penandaan gambar |
| 1924 | Rekening Rumah Sakit | O3 | I | G1 | pembayaran non-tunai |
| 1925 | Farmasi | O2 | I | G2 | depo, aturan, restriksi |
| 1926 | Non Pegawai | O1 | V | G4 | konsultan/pihak luar |
| 1927 | Layanan Lainnya | O2 | L | G2 | konfigurasi layanan |
| 1928 | Pengaturan Rekam Medis | O2 | L | G2 | keperawatan dan mapping |

Seluruh nama menu berstatus `TERVERIFIKASI-SUMBER`; kebutuhan 1926 tetap perlu SOP (`V`).

## 20–30 — Domain operasional lain (33)

| Kode | Domain — Modul | Kelas | Gelombang | Ketergantungan utama | Kepastian |
|---|---|---:|---:|---|---|
| 2001 | Informasi — Pengunjung | P | G4 | proyeksi/masking Pasien | TERVERIFIKASI-SUMBER |
| 2002 | Informasi — Ruang/Bed Kosong | L | G3 | ketersediaan bed | TERVERIFIKASI-SUMBER |
| 2003 | Informasi — Pasien Rawat Inap | P | G4 | proyeksi/masking admisi | TERVERIFIKASI-SUMBER |
| 2101 | Penjualan — Barang OTC/Resep Luar | L | G2 | barang, stok, tagihan | TERVERIFIKASI-SUMBER |
| 2201 | Tempat Tidur — Reservasi | I | G2 | kamar/bed/Pasien | TERVERIFIKASI-SUMBER |
| 2202 | Tempat Tidur — Identitas Pasien | I | G2 | bed/privasi | TERVERIFIKASI-SUMBER |
| 2203 | Tempat Tidur — Antrean | I | G2 | kelas/ruang/prioritas | TERVERIFIKASI-SUMBER |
| 2301 | Inventory — Permintaan | I | G2 | stok asal/tujuan | TERVERIFIKASI-SUMBER |
| 2302 | Inventory — Penerimaan | I | G2 | permintaan/pengiriman | TERVERIFIKASI-SUMBER |
| 2303 | Inventory — Pengiriman | I | G2 | dokumen stok | TERVERIFIKASI-SUMBER |
| 2304 | Inventory — Stok Opname | I | G2 | ledger/koreksi | TERVERIFIKASI-SUMBER |
| 2305 | Inventory — Rekanan | L | G2 | penyedia/penerimaan/retur | TERVERIFIKASI-SUMBER |
| 2306 | Inventory — Distribusi Barang | I | G2 | gudang/depo/Ruangan | TERVERIFIKASI-SUMBER |
| 2401 | Pencarian — Terpadu | I | G1 | Pasien/Pegawai/Berkas + permission | TERVERIFIKASI-SUMBER |
| 2501 | Integrasi — BPJS | I | G1 | Coverage/SEP/Antrean/Aplicares | TERVERIFIKASI-SUMBER |
| 2502 | Integrasi — LIS | P | G4 | order/hasil/mapping vendor | TERVERIFIKASI-SUMBER |
| 2601 | Monitoring — Nilai Kritis Lab | I | G2 | hasil lab kritis | TERVERIFIKASI-SUMBER |
| 2602 | Monitoring — Hasil Laboratorium | I | G2 | order/hasil lab | TERVERIFIKASI-SUMBER |
| 2603 | Monitoring — Hasil Radiologi | I | G2 | order/hasil radiologi | TERVERIFIKASI-SUMBER |
| 2604 | Monitoring — Konsul MPP | V | G3 | konsul/implementasi | INFERENSI |
| 2605 | Monitoring — Jadwal Kontrol | I | G1 | VClaim/rencana kontrol | TERVERIFIKASI-SUMBER |
| 2606 | Monitoring — SPRI | I | G2 | rencana rawat inap | TERVERIFIKASI-SUMBER |
| 2607 | Monitoring — Pasien Dirawat/SIKEPO | L | G3 | bed/Aplicares | INFERENSI |
| 2608 | Monitoring — Pasien Meninggal | I | G2 | kematian/verifikasi | TERVERIFIKASI-SUMBER |
| 2609 | Monitoring — Status Klaim BPJS | I | G3 | Episode Klaim | TERVERIFIKASI-SUMBER |
| 2690 | Monitoring — Sesi Database | P | G4 | operasi database terbatas | TERVERIFIKASI-SUMBER |
| 2701 | Akses API — Pegawai | L | G3 | policy API/audit | TERVERIFIKASI-SUMBER |
| 2702 | Akses API — Pengguna | L | G3 | policy API/audit | TERVERIFIKASI-SUMBER |
| 2703 | Akses API — Group Pemeriksaan | L | G3 | policy API/audit | TERVERIFIKASI-SUMBER |
| 2801 | Logs — TTE | I | G3 | dokumen/tanda tangan | TERVERIFIKASI-SUMBER |
| 2901 | Pelayanan KPO | L | G3 | resep/kajian farmasi | TERVERIFIKASI-SUMBER |
| 3001 | Berkas — Manajemen Klaim | I | G3 | RM/hasil/tagihan/dokumen | TERVERIFIKASI-SUMBER |
| 3002 | Berkas — Monitoring Kelengkapan | I | G3 | readiness/komentar | TERVERIFIKASI-SUMBER |

## Plugin dan capability tambahan

Plugin berikut ditemukan di dokumentasi atau source legacy. Semuanya `P/G4` sampai ada kebutuhan instalasi nyata, kecuali kewajiban regulasi menyatakan lain.

| Kelompok | Capability | Dependensi core |
|---|---|---|
| BPJS | Antrean Online, Aplicares, I-Care, Apotek Online, SmartClaim, PCare/FKTP | Pasien, Coverage, SEP, Kunjungan, bed, farmasi |
| Kemenkes | RS Online, SIRS, SITB, Sisrute, SATUSEHAT lanjutan | master, pelayanan final, laporan |
| Klinis | penjadwalan operasi, anestesi, transfusi, MCU, HD, registry | Kunjungan, RM, order, dokumen |
| Penunjang | LIS multi-vendor, CSSD | order, hasil, inventory |
| Operasional | laundry, gizi, distribusi berkas RM, APM | Ruangan, Pasien, inventory |
| Mutu | PPI, mutu, risk register, manajemen risk, indikator kinerja | event/read model domain |
| SDM | MSDM, absensi visite | Pegawai, penempatan, jadwal |
| Komunikasi | WA gateway, text-to-speech antrean | notifikasi, antrean, audit |

## Aturan penambahan atau pengurangan modul

- Modul baru harus menunjuk owner, aggregate, state, permission, gelombang, dan konsumen data.
- Modul plugin tidak boleh mengubah tabel core secara liar; gunakan kontrak owner domain.
- Modul hanya boleh ditandai `tidak diadopsi` melalui keputusan produk tercatat.
- Status pengerjaan tidak dicatat di katalog ini; gunakan [`RENCANA-PENGEMBANGAN.md`](./RENCANA-PENGEMBANGAN.md).
