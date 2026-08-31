# Alur Operasional SIMGOS

**Status:** dokumen kerja tim
**Diperbarui:** 2026-08-31
**Tujuan:** menjabarkan alur operasional utama SIMGOS dalam bahasa kerja rumah sakit agar bisa dipakai bersama tim produk, backend, frontend, QA, dan operasional.

Dokumen ini merangkum pola SIMPel/SIMGOS2, hasil audit legacy, dan arah produk aktif SIMGOS saat ini. Fokusnya adalah **alur kerja**, bukan daftar tabel atau daftar endpoint.

## Cara memakai dokumen ini

- Gunakan dokumen ini saat briefing tim untuk memahami urutan kerja nyata.
- Gunakan kode modul hanya sebagai penanda domain, bukan sebagai urutan implementasi otomatis.
- Jika ada pertentangan dengan catatan lama, ikuti [`BLUEPRINT-SIMGOS.md`](./BLUEPRINT-SIMGOS.md) dan ADR aktif.

## Gambaran besar sistem

Dalam operasi harian, SIMGOS membentuk rantai kerja berikut:

`Pasien datang -> Pendaftaran -> Kunjungan/Layanan -> Rekam Medis -> Billing/Kasir -> Klaim -> Integrasi eksternal -> Monitoring`

Rantai ini tidak selalu lurus. Ada cabang untuk:

- pasien baru atau pasien lama;
- rawat jalan, IGD, rawat inap, lab, atau radiologi;
- penjamin umum, BPJS, atau penjamin lain;
- layanan sederhana atau layanan dengan order penunjang;
- kasus yang berhenti di billing lokal atau lanjut ke klaim BPJS dan SATUSEHAT.

## Prinsip umum alur

- Satu instalasi aktif melayani satu faskes.
- Satu pasien dapat memiliki banyak pendaftaran.
- Satu pendaftaran dapat melahirkan satu atau lebih kunjungan sesuai alur layanan.
- Rekam medis, billing, dan integrasi harus tetap dapat ditelusuri kembali ke kunjungan yang sama.
- Status final, batal, koreksi, dan kirim ke sistem luar harus punya jejak audit.

## 0. Fondasi, Master, dan Akses

Alur operasional hanya dapat berjalan setelah master yang relevan siap. Master Data bukan satu domain tunggal; setiap data dimiliki domain yang memakai aturan bisnisnya.

### Persiapan instalasi

1. Admin menetapkan profil Faskes dan satu PPK internal.
2. Admin membentuk hierarki Ruangan: instalasi, Unit Layanan, ruang/kamar, lalu Tempat Tidur bila digunakan.
3. Pegawai, Profesi, Pengguna, Role, permission, dan Akses Ruangan disiapkan.
4. Tindakan, kategori, pemeriksaan, barang, paket, tarif, Penjamin, rekening, serta depo farmasi disiapkan sesuai capability.
5. Referensi internal dan mapping BPJS/Kemenkes divalidasi.
6. Credential integrasi dimasukkan melalui UI, dienkripsi, diuji health check, dan diaudit.

### Kelompok master yang tetap terpetakan

- PPK, Pegawai, Non Pegawai, Pengguna, Ruangan, dan akses;
- Tindakan, Kategori, Jenis Pemeriksaan, Paket, serta Tarif;
- Penyedia, Barang, Margin Obat, depo, frekuensi resep, PPN, dan restriksi farmasi;
- Penjamin Rumah Sakit, Penjamin Ruangan, rekening, serta mapping eksternal;
- Negara, Wilayah, Referensi Umum, Template Anatomi, dan pengaturan Rekam Medis;
- BPJS, Kemenkes, RS Online, SIRS, SITB, SATUSEHAT, dan pengaturan instalasi.

Kode master stabil tidak diubah setelah dipakai. Master yang pernah direferensikan dinonaktifkan, bukan dihapus.

## 1. Pendaftaran

Pendaftaran adalah pintu masuk administrasi pasien. Di sinilah identitas pasien, tujuan layanan, penjamin, dan konteks kunjungan pertama kali dibentuk.

### Aktor

- petugas pendaftaran;
- petugas administrasi BPJS;
- pasien atau keluarga pasien.

### Alur 1001 Pasien Baru

1. Petugas mencari apakah pasien sudah pernah terdaftar.
2. Jika belum ada, petugas membuat data pasien baru.
3. Petugas mengisi identitas utama: nama, tanggal lahir, jenis kelamin, alamat, kontak, keluarga, identitas resmi, dan demografi.
4. Sistem menerbitkan nomor rekam medis.
5. Jika perlu, sistem mencetak kartu, barcode, atau identitas lain.

**Output:** pasien resmi terdaftar dan siap dipakai pada pendaftaran kunjungan.

### Alur 1002-1006 Pendaftaran Kunjungan

1. Petugas memilih pasien lama atau pasien yang baru dibuat.
2. Petugas menentukan tujuan kunjungan: rawat jalan, IGD, rawat inap, laboratorium, atau radiologi.
3. Petugas memilih ruangan/poli tujuan.
4. Petugas memilih penjamin atau cara bayar.
5. Untuk pasien BPJS, petugas memeriksa eligibilitas, rujukan, kontrol, dan kebutuhan SEP.
6. Sistem membuat nomor pendaftaran.
7. Setelah Pendaftaran confirmed, orchestration meminta domain Layanan membuat Kunjungan pada unit tujuan.
8. Jika perlu, sistem mencetak bukti pendaftaran, gelang, atau dokumen antrean.

**Output:** pendaftaran dan kunjungan terbentuk, pasien siap diproses di unit tujuan.

### Alur administrasi pendaftaran lain

- `1007 Perubahan Data`: koreksi data pasien, tujuan, tanggal, status, foto, consent, atau penjamin.
- `1008 Pencetakan`: cetak ulang kartu, barcode, bukti pendaftaran, gelang.
- `1009 History Pendaftaran`: melihat riwayat pendaftaran pasien.
- `1010 Pembatalan`: membatalkan pendaftaran atau kunjungan yang belum dilayani.
- `1011 Penerimaan`: konfirmasi pasien benar-benar datang di loket.
- `1014 Pasien Meninggal/Forensik`: alur khusus kasus kematian.

### Keputusan penting domain pendaftaran

- Pendaftaran bukan sekadar membuat baris data, tetapi membentuk hubungan pasien, penjamin, tujuan layanan, dan kunjungan.
- Untuk BPJS, nomor antrean, coverage, dan SEP tidak boleh tercampur perannya.
- Nomor pendaftaran dan nomor kunjungan harus aman terhadap request ganda.

## 2. Layanan

Layanan adalah area kerja unit operasional setelah pasien resmi masuk ke kunjungan. Di sini ruangan, dokter, perawat, farmasi, dan penunjang bekerja pada kunjungan yang sama.

### Aktor

- perawat;
- dokter;
- petugas lab/radiologi;
- farmasi;
- petugas ruangan.

### Alur 1101 Penerimaan Ruangan

1. Unit layanan membuka worklist kunjungan aktif.
2. Petugas menyaring daftar berdasarkan ruangan, status, dan identitas pasien.
3. Petugas memilih pasien yang akan ditangani.
4. Sistem membuka workspace kunjungan pasien itu.

**Output:** pasien aktif diterima di ruangan dan siap dilayani.

### Alur 1102 Penginputan Tindakan Medis

1. Dokter atau perawat memilih tindakan yang dilakukan.
2. Sistem mencatat waktu, petugas pelaksana, dan status tindakan.
3. Jika tindakan dibatalkan, pembatalan dicatat sebagai perubahan status, bukan hilang tanpa jejak.

**Output:** daftar tindakan medis per kunjungan terbentuk.

### Alur 1103 Pengiriman atau order

1. Dokter membuat order resep, laboratorium, radiologi, atau konsul.
2. Order dikirim ke unit tujuan.
3. Unit tujuan menerima order dan memprosesnya.

**Output:** ada antrean kerja lanjutan untuk farmasi atau unit penunjang.

### Alur 1104-1105 hasil penunjang

1. Unit lab atau radiologi mengerjakan order.
2. Hasil dimasukkan per parameter atau per pemeriksaan.
3. Hasil diberi status, lalu tersedia bagi DPJP atau dokter pengirim.

**Output:** hasil pemeriksaan menjadi bagian dari kunjungan.

### Alur 1106 riwayat layanan

1. Petugas membuka riwayat layanan pasien.
2. Sistem menampilkan kunjungan lama atau layanan lama pasien.
3. Dokter memakai riwayat ini untuk konteks klinis.

**Output:** keputusan klinis tidak buta terhadap kunjungan sebelumnya.

### Alur 1108 pembatalan layanan

1. Petugas membatalkan tindakan atau order yang belum final.
2. Sistem mengubah status menjadi batal.
3. Audit pembatalan tetap tersimpan.

### Alur 1109 final pelayanan

1. Dokter atau petugas berwenang memeriksa apakah layanan sudah lengkap.
2. Sistem memeriksa diagnosis, hasil, resep, dan data pulang yang wajib.
3. Petugas memilih cara keluar atau outcome.
4. Kunjungan ditutup/final.

**Output:** pekerjaan unit selesai. Final Rekam Medis tetap transisi terpisah sebelum data klinis dianggap terkunci untuk billing, klaim, dan sinkronisasi.

### Alur layanan lain

- `1110 Kelahiran`: mencatat persalinan dan data bayi baru lahir.
- `1111 Pemakaian BHP`: mencatat barang habis pakai yang dipakai ruangan.
- `1112 Bon Sisa`: mengelola sisa obat atau BHP.
- `1113 Pemanggilan Antrean`: memanggil pasien secara suara/visual.
- `1114 Layanan Tambahan`: misalnya pemakaian O2 atau layanan unit khusus.
- `1115 KPO`: kajian penggunaan obat.
- `1116 Formulir Antimikroba`: pengajuan dan persetujuan penggunaan antibiotik terbatas.

## 3. Rekam Medis

Rekam medis adalah detail klinis dari kunjungan. Ia hidup berdampingan dengan domain layanan, tetapi kepemilikan datanya berbeda karena sifat legal dan klinisnya.

### Aktor

- dokter;
- perawat;
- petugas rekam medis;
- farmasi klinik;
- tim mutu/komite tertentu bila relevan.

### Alur utama rekam medis

1. Petugas klinis membuka kunjungan aktif.
2. Mereka mengisi data klinis sesuai tahap layanan.
3. Sistem menyimpan data sebagai catatan medis yang tetap dapat ditelusuri.
4. Saat kunjungan difinalkan, input tertentu harus terkunci atau hanya bisa dikoreksi lewat jalur amendment.

### Sub-alur rekam medis

- `1302 Anamnesis`: keluhan utama, riwayat penyakit, riwayat alergi, riwayat lain.
- `1303 TTV & Fisik`: suhu, nadi, napas, tekanan darah, SpO2, berat badan, tinggi badan, pemeriksaan fisik.
- `1304 Penilaian`: skala nyeri, risiko jatuh, penilaian keperawatan lain.
- `1305 Diagnosis`: diagnosis klinis, koding ICD, dan diagnosis utama.
- `1307 Rencana Medis`: rencana terapi dan tindak lanjut.
- `1308 CPPT`: catatan perkembangan pasien terintegrasi.
- `1309 Surat Medis`: surat sakit, surat opname, surat lain.
- `1310 Resume Medis`: ringkasan kondisi pasien saat selesai dirawat.
- `1312 Triage IGD`: penilaian awal kegawatan pasien IGD.
- `1313 Rekonsiliasi Obat`: membandingkan obat sebelumnya dengan terapi saat ini.
- `1319 Transfer Pasien`: catatan serah terima antar ruangan.

### Keputusan penting domain rekam medis

- Tidak semua data medis layak diperlakukan seperti CRUD biasa.
- Catatan medis sensitif sebaiknya append-only atau punya pola koreksi yang jelas.
- Final rekam medis memengaruhi klaim, resume, surat, dan SATUSEHAT.

## 4. Pembayaran dan Kasir

Setelah layanan berjalan atau selesai, aktivitas finansial mulai terbentuk. Domain ini mengubah tindakan dan produk menjadi tagihan yang bisa ditagih dan dibayar.

### Aktor

- petugas billing;
- kasir;
- penjamin internal;
- pasien atau keluarga;
- petugas klaim/casemix untuk kasus BPJS.

### Alur umum billing

1. Sistem mengumpulkan sumber biaya dari kunjungan: tindakan, obat, BHP, penunjang, kamar, dan layanan lain.
2. Billing membentuk item tagihan.
3. Jika ada penjamin, porsi penjamin dan porsi pasien dihitung.
4. Tagihan diperiksa sebelum difinalkan.

### Alur 1201 final tagihan

1. Petugas billing memastikan kunjungan sudah siap ditagih.
2. Sistem memeriksa guard yang dibutuhkan.
3. Tagihan dikunci/final.

**Output:** tagihan resmi siap dibayar atau diklaim.

### Alur 1209 transaksi kasir

1. Kasir membuka sesi kerja.
2. Kasir menerima pembayaran pasien.
3. Sistem mencatat pembayaran append-only.
4. Jika jumlah sudah cukup, status tagihan berubah sesuai aturan.
5. Kuitansi dicetak.

**Output:** penerimaan kas tercatat dan bisa diaudit.

### Sub-alur finansial lain

- `1202-1203 Deposit`: uang muka dan pengembaliannya.
- `1204-1205 Piutang`: tagihan tertunda dan pelunasannya.
- `1206 Non Tunai`: pembayaran kartu, transfer, QRIS, dan sejenisnya.
- `1207 Diskon`: potongan tarif atau jasa.
- `1208 Pembatalan Tagihan Final`: membuka kembali tagihan final dengan alasan.
- `1211 Batal Gabung Tagihan`: memisahkan tagihan yang pernah digabung.
- `1212 Penjamin Tagihan`: membagi beban antara BPJS, penjamin lain, dan pasien.
- `1213 Pencetakan Kuitansi`: bukti pembayaran resmi.
- `1214 Transaksi Penjualan Apotek`: pembayaran penjualan obat bebas atau resep luar.
- `1215 Pembatalan Permintaan Layanan`: guard terhadap order yang belum selesai.

## 5. Penjualan Obat Bebas

Domain ini berbeda dari farmasi per kunjungan. Ia menangani transaksi apotek yang berdiri sendiri.

### Alur 2101 penjualan OTC

1. Petugas apotek memilih jenis penjualan.
2. Petugas menambahkan barang yang dijual.
3. Sistem menghitung harga, margin, pajak, dan total.
4. Transaksi penjualan dibuat.
5. Tagihan penjualan dibayar di kasir apotek.
6. Bukti penjualan dan etiket dicetak jika perlu.

**Output:** penjualan obat bebas tercatat sebagai transaksi finansial mandiri.

## 6. Tempat Tidur dan Rawat Inap

Domain ini penting untuk rawat inap, meski sebagian instalasi mungkin belum mengaktifkannya penuh pada tahap awal.

### Alur utama

- `2201 Reservasi Kamar`: memesan kamar/tempat tidur sebelum pasien masuk.
- `2202 Identitas Pasien Kamar`: menampilkan data pasien di bed board sesuai kebijakan privasi.
- `2203 Antrean Tempat Tidur`: mengelola antrean pasien bila tempat tidur penuh.

### Bentuk alur kerja

1. Petugas melihat status tempat tidur.
2. Jika ada tempat, pasien ditempatkan atau direservasi.
3. Jika penuh, pasien masuk antrean.
4. Saat tempat tersedia, antrean diproses ke reservasi atau admisi.

## 7. Inventory dan Logistik

Inventory mengurus barang yang bergerak di dalam rumah sakit, baik medis maupun non-medis.

### Aktor

- gudang;
- farmasi;
- unit ruangan;
- purchasing/logistik.

### Alur utama inventory

- `2301 Permintaan`: unit meminta barang.
- `2302 Penerimaan Supplier`: barang datang dari supplier.
- `2303 Pengiriman`: gudang mengirim ke unit.
- `2304 Stok Opname`: mencocokkan stok sistem dengan stok nyata.
- `2305 Rekanan/Supplier`: master supplier.
- `2306 Distribusi Barang`: distribusi logistik non-klinis.

### Bentuk alur kerja

1. Unit membuat permintaan.
2. Gudang atau purchasing memproses permintaan.
3. Barang diterima dari supplier atau dipindahkan dari gudang.
4. Saldo stok berubah dan harus tetap bisa diaudit.
5. Bila ada selisih, stok opname atau penyesuaian dilakukan lewat jalur resmi.

## 8. Pencarian Terpadu

Pencarian adalah pintu cepat menuju entitas penting, terutama pasien.

### Alur 2401 pencarian

1. User memilih jenis pencarian.
2. User memasukkan nomor RM, nama, NIK, atau kata kunci lain.
3. Sistem menampilkan hasil yang relevan.
4. User membuka workspace pasien, berkas klaim, atau entitas lain dari hasil itu.

**Output:** navigasi cepat tanpa harus masuk menu domain satu per satu.

## 8A. Informasi Publik

Modul Informasi menampilkan pengunjung, tempat tidur kosong, atau pasien rawat inap melalui read model minimum.

1. Domain sumber menerbitkan proyeksi yang aman.
2. Identitas dimasking sesuai layar dan permission.
3. Display tidak memakai akun superadmin dan tidak dapat membuka Rekam Medis.
4. Perubahan pada display tidak pernah mengubah data sumber.

## 9. Integrasi Eksternal

Integrasi menghubungkan SIMGOS dengan sistem nasional atau sistem vendor lain.

### Alur 2501 BPJS

1. Sistem memeriksa data peserta, rujukan, kontrol, dan SEP.
2. Sistem membuat atau memperbarui data yang diperlukan ke BPJS.
3. Sistem menyimpan ID eksternal, payload penting, status, dan error bila ada.
4. Untuk alur lanjut, data ini dipakai lagi pada billing, klaim, kontrol, atau monitoring.

### Alur 2502 LIS laboratorium

1. Order lab dari SIMGOS dikirim ke sistem LIS.
2. LIS mengerjakan pemeriksaan.
3. Hasil ditarik kembali ke SIMGOS.
4. Sistem menjaga status order dan hasil tetap sinkron.

### Integrasi lain yang terkait

- Antrean Online BPJS/Mobile JKN;
- VClaim;
- E-Klaim/INA-CBG;
- Aplicares;
- ICare;
- SATUSEHAT.

## 10. Monitoring

Monitoring bukan hanya log teknis. Ia adalah tampilan operasional agar masalah bisa cepat ditemukan dan ditindak.

### Contoh alur monitoring

- `2601 Nilai Kritis Lab`: hasil kritis yang butuh tindak lanjut.
- `2602-2603 Monitoring Hasil Lab/Rad`: memantau order yang belum selesai atau belum diverifikasi.
- `2605-2606 Jadwal Kontrol & SPRI`: memantau kontrol BPJS.
- `2607 Ketersediaan Tempat Tidur/Aplicares`: memantau sinkronisasi kapasitas.
- `2608 Pasien Meninggal`: memantau kasus kematian dan verifikasinya.
- `2609 Status Klaim BPJS`: memantau status grouping, approval, dispute, atau pending.
- `2690 Database Session`: monitoring teknis sesi database.

### Bentuk alur monitoring

1. Sistem membentuk read-model atau worklist.
2. Petugas monitoring melihat item yang macet, gagal, atau butuh tindakan.
3. Petugas masuk ke domain asal untuk memperbaiki data atau melanjutkan proses.

## 11. Akses API

Domain ini bukan alur user klinis harian, tetapi penting untuk tata kelola sistem.

### Isi domain

- pengelolaan token atau akses integrasi;
- pembatasan hak akses API;
- kontrol terhadap siapa boleh memanggil endpoint tertentu.

Tujuannya adalah menjaga agar integrasi eksternal dan internal tetap aman dan terkelola.

## 12. Logs dan Audit

Logs menyimpan jejak peristiwa penting yang tidak boleh hilang.

### Alur 2801 audit log TTE

1. Dokumen tertentu dikirim untuk ditandatangani elektronik.
2. Sistem mencatat siapa memicu, kapan, dokumen apa, dan hasilnya.
3. Log tidak boleh diubah sembarangan.

### Pola audit yang harus berlaku luas

- create, final, batal, koreksi, kirim ke sistem luar, dan retry harus bisa ditelusuri;
- error integrasi harus menyimpan konteks yang cukup untuk investigasi;
- data sensitif tetap perlu redaction bila ditampilkan di monitor umum.

## 13. Pelayanan KPO

KPO adalah area farmasi klinik yang mengkaji penggunaan obat pada pasien.

### Bentuk alur

1. Dokter membuat order atau terapi obat.
2. Farmasi melakukan telaah.
3. Jika ada masalah, farmasi memberi catatan atau tindak lanjut.
4. Hasil kajian menjadi bagian dari proses pelayanan obat pasien.

KPO bisa muncul sebagai alur embedded di kunjungan dan juga sebagai worklist farmasi.

## 14. Coding, Klaim, dan Berkas

Berkas klaim adalah pengikat antara rekam medis, billing, coding, dan BPJS.

### Coding dan Episode Klaim

1. Dokter memfinalkan diagnosis klinis tanpa menyerahkan ownership catatannya kepada coder.
2. Coder memilih diagnosis/procedure klaim dan menyimpan hubungan dengan sumber klinis.
3. Sistem menjalankan grouping serta menyimpan versi input dan hasil.
4. Readiness memeriksa SEP, Coverage, klinis, Tagihan, coding, grouping, dan dokumen.
5. Petugas berwenang mengirim Episode Klaim melalui adapter E-Klaim.
6. Pending, dispute, approval, pembayaran, dan rekonsiliasi menjadi state episode yang sama.

Pengiriman ulang tidak membuat episode tanpa hubungan. Koreksi klinis atau finansial setelah submit menandai episode perlu direkonsiliasi.

### Alur 3001 manajemen berkas klaim

1. Sistem mengumpulkan dokumen dari banyak domain: resume, hasil penunjang, tagihan, resep, dan dokumen pendukung lain.
2. Petugas klaim memeriksa kelengkapan.
3. Dokumen digabung atau disiapkan untuk pengajuan klaim.

### Alur 3002 monitoring kelengkapan berkas

1. Petugas menyaring klaim berdasarkan periode, penjamin, dan status.
2. Sistem menampilkan bagian mana yang belum lengkap.
3. Petugas berkoordinasi dengan unit asal untuk melengkapi kekurangan.

**Output:** klaim tidak diajukan dalam keadaan buta atau setengah lengkap.

## 15. Dashboard dan Laporan

Dashboard dan laporan adalah lapisan baca untuk manajemen dan pelaporan.

### Dashboard

Dashboard menampilkan indikator seperti:

- BOR;
- LOS;
- BTO;
- TOI;
- GDR;
- NDR;
- pendapatan;
- volume layanan;
- status klaim.

### Laporan

Laporan mencakup:

- laporan kunjungan;
- laporan tindakan;
- laporan farmasi;
- laporan billing dan kasir;
- laporan RL/SIRS;
- laporan klaim;
- laporan mutu atau operasional lain.

Alur kerjanya bukan input data baru, melainkan menarik data yang sudah sah dari domain asal lalu menyusunnya menjadi keluaran yang bisa dibaca atau dikirim.

## 16. Plugin Opsional

Capability seperti CSSD, Laundry, Gizi, PPI, Mutu, Risk Register, MSDM, Penjadwalan Operasi, Distribusi Berkas RM, APM, dan LIS vendor masuk setelah kebutuhan instalasi nyata tersedia.

Setiap plugin harus:

- menunjuk owner domain dan data core yang dibaca;
- memakai API/service/event owner, bukan menulis tabel core langsung;
- mempunyai SOP, permission, state, audit, failure path, serta acceptance;
- tidak menghambat delivery alur Rawat Jalan BPJS inti.

## Peta alur ujung ke ujung

Berikut peta ringkas yang paling sering dipakai tim saat membahas satu perjalanan pasien:

1. Pasien dicari atau dibuat.
2. Pendaftaran dibuat.
3. Penjamin dipilih.
4. Jika BPJS, eligibilitas dan SEP diperiksa/dibuat.
5. Kunjungan dibentuk.
6. Ruangan menerima pasien.
7. Rekam medis dan layanan diisi.
8. Order penunjang dan farmasi diproses.
9. Hasil kembali ke kunjungan.
10. Kunjungan difinalkan.
11. Tagihan dibentuk dan difinalkan.
12. Pembayaran pasien dicatat bila ada.
13. Coding dan grouping dilakukan bila perlu.
14. Berkas klaim disiapkan.
15. SATUSEHAT dan integrasi lain dikirim.
16. Monitoring memastikan tidak ada proses yang macet.

## Catatan untuk tim implementasi

- Jangan memecah diskusi hanya berdasarkan nama modul. Banyak alur nyata selalu menyeberang domain.
- Pendaftaran, layanan, rekam medis, billing, klaim, dan integrasi harus dibahas sebagai satu rantai.
- Jika tim sedang memulai fitur baru, selalu tentukan lebih dulu: aktor, langkah sebelum, langkah sesudah, status terminal, dan jejak auditnya.

## Dokumen lanjutan yang perlu dibaca

- [`workflows/rawat-jalan-bpjs.md`](./workflows/rawat-jalan-bpjs.md)
- [`integrations/bpjs.md`](./integrations/bpjs.md)
- [`integrations/satusehat.md`](./integrations/satusehat.md)
- [`legacy/ALUR-KERJA.md`](./legacy/ALUR-KERJA.md)
- [`legacy/DOKUMEN-PRODUK-2026-08-18.md`](./legacy/DOKUMEN-PRODUK-2026-08-18.md)
