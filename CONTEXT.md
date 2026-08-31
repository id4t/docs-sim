# SIMGOS Domain Language

SIMGOS adalah sistem operasional fasilitas kesehatan yang menyatukan pelayanan pasien, administrasi penjamin, keuangan, klaim, dan interoperabilitas. Glosarium ini menetapkan istilah kanonik lintas backend, frontend, dokumentasi, dan percakapan produk.

## Organisasi

**Faskes**:
Klinik atau rumah sakit yang dilayani oleh satu instalasi SIMGOS dan menjadi boundary aktif untuk identitas, data operasional, konfigurasi, dan kredensial integrasi.
_Avoid_: Tenant, cabang, PPK

**PPK**:
Entitas fasilitas pemberi pelayanan yang menjadi referensi bisnis atau identitas eksternal; PPK tidak otomatis berarti boundary data terpisah.
_Avoid_: Tenant

**Ruangan**:
Simpul hierarki organisasi dan lokasi di dalam Faskes, seperti instalasi, unit layanan, ruang, atau kamar; Tempat Tidur adalah entitas anak tersendiri.
_Avoid_: Faskes, nama lokasi berupa teks transaksi

**Unit Layanan**:
Ruangan yang menerima dan mengerjakan Kunjungan, seperti poli, IGD, bangsal, laboratorium, radiologi, atau farmasi.
_Avoid_: Faskes, kamar fisik, Tempat Tidur

## Pelayanan pasien

**Pasien**:
Orang yang menerima atau akan menerima pelayanan kesehatan dan dapat mempunyai identitas lokal serta identitas eksternal.
_Avoid_: Peserta, pengunjung

**Nomor Rekam Medis**:
Identitas rekam medis pasien yang diterbitkan dalam lingkup satu instalasi Faskes.
_Avoid_: NIK, IHS number, nomor peserta

**Identifier Pasien**:
Identitas selain ID internal dan Nomor Rekam Medis, seperti NIK, nomor BPJS, paspor, atau IHS number.
_Avoid_: Primary key Pasien

**Pendaftaran**:
Catatan administratif kedatangan atau rencana pelayanan pasien yang menetapkan jenis layanan, tujuan, cara bayar, dan konteks awal episode pelayanan.
_Avoid_: Encounter, antrean

**Kunjungan**:
Episode operasional pasien pada satu unit pelayanan yang menjadi pengikat aktivitas klinis, order, hasil, billing, dan integrasi internal.
_Avoid_: Pendaftaran, antrean

**Encounter**:
Representasi klinis interoperabel dari kontak pelayanan yang dipetakan dari Kunjungan dan mempunyai lifecycle eksplisit.
_Avoid_: Pendaftaran, formulir SATUSEHAT

**Order**:
Permintaan resmi dari Unit Layanan asal kepada unit tujuan untuk pemeriksaan, konsul, resep, atau layanan lain.
_Avoid_: Hasil, Tindakan yang sudah selesai

**Hasil**:
Keluaran unit tujuan atas Order yang telah dikerjakan dan dapat berstatus awal, final, atau amended.
_Avoid_: Order, catatan bebas tanpa sumber

**Finalisasi**:
Transisi terkendali yang menutup tahap pelayanan dan mengunci perubahan yang tidak lagi sah; koreksi setelah finalisasi harus melalui mekanisme beralasan dan dapat diaudit.
_Avoid_: Mengubah status secara bebas, menghapus data

**Final Rekam Medis**:
Finalisasi isi catatan klinis yang membuat perubahan berikutnya hanya sah melalui Amendment.
_Avoid_: Final Layanan, Final Tagihan

**Final Layanan**:
Finalisasi pekerjaan Kunjungan pada Unit Layanan setelah seluruh aktivitas wajib mencapai state terminal.
_Avoid_: Final Rekam Medis, pasien sudah membayar

**Final Tagihan**:
Finalisasi perhitungan finansial yang mengunci Tagihan dan membuka jalur pembayaran atau klaim.
_Avoid_: Final Layanan, Pembayaran

**Amendment**:
Koreksi setelah finalisasi yang mempertahankan nilai asal serta mencatat nilai baru, alasan, aktor, dan waktu.
_Avoid_: Edit diam-diam, hard delete

## Penjamin dan klaim

**Penjamin**:
Pihak yang menanggung seluruh atau sebagian biaya pelayanan, termasuk pasien sendiri, BPJS, perusahaan, atau asuransi.
_Avoid_: Coverage, SEP

**Coverage**:
Catatan hak dan cakupan pembiayaan pasien untuk suatu Pendaftaran atau Kunjungan, termasuk relasinya dengan Penjamin.
_Avoid_: Penjamin, tagihan

**SEP**:
Surat Eligibilitas Peserta BPJS untuk episode pelayanan FKRTL tertentu yang harus terhubung ke Coverage dan Kunjungan terkait.
_Avoid_: Nomor antrean, klaim

**Tagihan**:
Akumulasi biaya pelayanan yang menjadi dasar penetapan kewajiban pasien dan Penjamin.
_Avoid_: Pembayaran, klaim

**Item Tagihan**:
Snapshot biaya satu fakta layanan, produk, kamar, atau komponen lain pada saat transaksi menjadi sah.
_Avoid_: Master Tarif, total Tagihan

**Pembayaran**:
Penerimaan atau pengembalian nilai finansial terhadap Tagihan melalui sesi kasir dan metode pembayaran tertentu.
_Avoid_: Tagihan, penjamin

**Sesi Kasir**:
Periode kerja kasir dari pembukaan sampai penutupan dan rekonsiliasi penerimaan.
_Avoid_: Pembayaran, login pengguna

**Episode Klaim**:
Agregat proses klaim yang menghubungkan Kunjungan, SEP, coding, grouping, Tagihan, berkas, pengajuan, hasil verifikasi, dan rekonsiliasi.
_Avoid_: SEP, invoice, berkas klaim

## Integrasi

**Antrean Online**:
Integrasi BPJS untuk jadwal, booking, check-in, pembaruan waktu layanan, pembatalan, dan antrean farmasi.
_Avoid_: VClaim, SEP

**VClaim**:
Integrasi administratif BPJS FKRTL untuk eligibilitas, rujukan, SEP, kontrol, dan tindak lanjut penjaminan.
_Avoid_: Antrean Online, E-Klaim

**E-Klaim**:
Integrasi casemix untuk data klaim dan grouping INA-CBG/Non-INA-CBG.
_Avoid_: VClaim, billing internal

**SATUSEHAT**:
Platform interoperabilitas data kesehatan berbasis FHIR yang menerima rangkaian resource sepanjang lifecycle pelayanan.
_Avoid_: Form registry, pengganti SIMRS

**Submission**:
Catatan pengiriman satu resource atau operasi eksternal yang menyimpan payload, target, status, percobaan, respons, dan identitas idempotensi.
_Avoid_: Log biasa

## Aturan penamaan lintas domain

**Pemilik Data**:
Domain yang berhak membuat, mengubah, membatalkan, atau memfinalkan data tertentu. Domain lain boleh membaca atau menyalin data turunan, tetapi tidak menjadi sumber kebenaran.
_Avoid_: Semua modul boleh update data yang sama

**Data Turunan**:
Data tampilan, ringkasan, cache, atau salinan yang dibentuk dari domain pemilik untuk kebutuhan workspace, laporan, billing, klaim, atau integrasi.
_Avoid_: Sumber kebenaran baru

**Referensi Lintas Domain**:
Relasi baca-ke-entitas lain melalui ID kanonik, misalnya Rekam Medis membaca `registration_id` dari Pendaftaran dan `visit_id` dari Layanan.
_Avoid_: Menyalin seluruh form asal tanpa kontrak
