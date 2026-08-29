# Membership Pengguna dan Akses Unit Layanan

**Status:** pengelolaan Membership tahap pertama sudah diimplementasikan; enforcement Facility context dan Unit pada workflow operasional belum aktif.

## Bukti implementasi 29 Agustus 2026

Sudah tersedia:

- schema Membership, role assignment per Faskes, cakupan Unit per role, dan audit dasar;
- API daftar/simpan yang membatasi Admin Faskes ke Faskesnya sendiri;
- validasi role facility/unit, Unit aktif, masa berlaku, dan Admin Faskes aktif terakhir;
- penonaktifan User tanpa menghapus histori Membership;
- UI tiga langkah Faskes → masa berlaku → role dan Unit Layanan;
- penghapusan data User/Unit hardcoded pada halaman pengelolaan Membership.

Belum termasuk tahap ini:

- resolver `/f/{facility_code}` dan pemilih Faskes setelah login;
- enforcement role-per-Unit pada endpoint workflow Rawat Jalan;
- penggantian fallback Ruangan pada session frontend lama;
- Monitoring Global lintas Faskes.

Dokumen ini menetapkan lapisan kewenangan User pada deployment satu Grup yang memiliki banyak Faskes. Kontrak database dan Facility context tetap mengikuti [`multi-schema-facility.md`](./multi-schema-facility.md).

## Referensi SIMPel

Baseline SIMPel yang diverifikasi pada 29 Agustus 2026:

- [Pendaftaran Kunjungan](https://www.docs.simgos2.simpel.web.id/docs/panduan/pendaftaran/pendaftaran-kunjungan/) memilih instalasi, unit pelayanan, Ruangan tujuan, dokter, dan tempat tidur secara bertingkat.
- [Pendaftaran Penunjang](https://www.docs.simgos2.simpel.web.id/docs/panduan/pendaftaran/pendaftaran-penunjang/) membatasi unit yang tersedia berdasarkan konfigurasi rumah sakit dan hak pengguna.
- [Transfer Masuk](https://www.docs.simgos2.simpel.web.id/docs/panduan/plugins/persediaan/transfer-masuk/) dan [Transaksi Keluar](https://www.docs.simgos2.simpel.web.id/docs/panduan/plugins/persediaan/transaksi-keluar/) memakai mapping petugas untuk menentukan Ruangan transaksi yang dapat dipilih.
- [Resource SATUSEHAT](https://www.docs.simgos2.simpel.web.id/docs/panduan/kemkessatusehat/resource-satusehat/) membedakan organisasi/unit dari lokasi fisik berupa ruangan, kamar, dan tempat tidur.

SIMGOS mempertahankan maksud bisnis tersebut, tetapi tidak menyalin inkonsistensi hak akses legacy. Otorisasi wajib ditegakkan backend melalui satu model Membership.

## Tiga lapis kewenangan

```text
Faskes      = pengguna bekerja pada rumah sakit/klinik mana
Role        = pengguna boleh melakukan tindakan apa
Unit Layanan = pengguna boleh melakukan tindakan itu di bagian mana
```

Contoh:

```text
User   : dr. Andi
Faskes : RS Sehat
Role   : Dokter → Poli Anak, IGD
         Kasir  → Unit Kasir
```

Role tidak boleh digabungkan dahulu lalu diterapkan ke seluruh Unit. Pada contoh tersebut, kewenangan Dokter tidak berlaku di Unit Kasir dan kewenangan Kasir tidak berlaku di Poli Anak.

## Istilah Unit Layanan

Label UI yang dipakai adalah **Unit Layanan**. Pada model aplikasi saat ini, padanannya adalah `Ward`.

```text
Faskes: RS Sehat
├── Rawat Jalan
│   ├── Poli Anak                 ← Unit Layanan
│   └── Poli Penyakit Dalam       ← Unit Layanan
├── Rawat Darurat
│   └── IGD                       ← Unit Layanan
├── Rawat Inap
│   └── Bangsal Melati            ← Unit Layanan
│       └── Kamar 101             ← Room/kamar fisik
│           ├── Bed A
│           └── Bed B
└── Penunjang
    ├── Laboratorium              ← Unit Layanan
    ├── Radiologi                 ← Unit Layanan
    └── Farmasi                   ← Unit Layanan
```

ACL hanya diterapkan pada Unit Layanan. `Room` dan `Bed` tidak mempunyai ACL pengguna tersendiri pada fase ini. Pemberian akses ke induk tidak diwariskan ke anak; Admin memilih Unit secara eksplisit atau memakai `Semua Unit`.

Unit aktif berfungsi sebagai konteks kerja dan filter UI. Backend tetap memeriksa Unit target pada setiap aksi, sehingga manipulasi URL atau payload tidak dapat melewati otorisasi.

## Model kewenangan

### User

User berada di control DB dan dapat memiliki nol atau banyak Membership.

- User nonaktif kehilangan akses ke seluruh Faskes pada request berikutnya.
- User tanpa Membership tetap dapat login untuk melihat halaman belum memiliki akses, profil, dan mengganti kata sandi; API operasional tetap ditolak.
- User nonklinis tidak wajib terhubung ke record pegawai.
- Role klinis hanya dapat digunakan jika User terhubung ke tenaga/pegawai aktif pada Faskes terkait.
- Jika tenaga klinis menjadi nonaktif, role klinisnya tidak dapat digunakan; role nonklinis tetap mengikuti status Membership.

### Membership

Satu Membership menghubungkan tepat satu User dengan satu Faskes dan memuat:

- status aktif;
- tanggal mulai;
- toggle `Permanen`;
- tanggal selesai bila tidak permanen;
- satu atau beberapa role assignment.

Aturan lifecycle:

- Membership baru langsung aktif setelah disimpan.
- `Permanen = ON` berarti tanggal selesai kosong, bukan kebal dari penonaktifan manual.
- Membership nonaktif atau di luar masa berlaku tidak memberi akses.
- Membership yang pernah dipakai tidak dihapus permanen; penonaktifan menjaga histori.
- Penonaktifan User mengalahkan seluruh status Membership.
- Perubahan kewenangan berlaku pada request berikutnya tanpa menunggu login ulang.

### Role assignment

Role memakai katalog baku aplikasi; role kustom per Grup belum diperlukan. Satu Membership dapat memiliki beberapa role.

Setiap template role mempunyai scope:

- `facility`: berlaku pada seluruh Faskes, misalnya Admin Faskes atau peran administratif lintas unit;
- `unit`: berlaku pada Unit Layanan tertentu.

Role berscope Unit mempunyai salah satu mode:

- `selected`: satu atau beberapa Unit dipilih eksplisit;
- `all`: `Semua Unit`, termasuk Unit baru yang ditambahkan kemudian.

Mode tersebut disimpan per role assignment, bukan satu flag untuk seluruh Membership. Unit yang dinonaktifkan tidak dapat dipakai, tetapi grant tetap disimpan sebagai riwayat dan berlaku kembali jika Unit diaktifkan.

## Aktor global

Developer dan Superadmin adalah pengecualian eksplisit terhadap kewajiban Membership:

- Developer dapat mengakses seluruh fitur dan Faskes pada semua environment.
- Superadmin dapat mengakses control-plane dan data operasional seluruh Faskes.
- Keduanya tetap membuka workspace operasional melalui satu `/f/{facility_code}`; data klinis beberapa Faskes tidak dicampur dalam workspace biasa.
- Akses klinis, ekspor, dan perubahan sensitif oleh aktor global diaudit khusus. Ekspor atau tindakan berisiko tetap membutuhkan alasan.
- Admin Faskes tidak dapat membuat atau memberikan status Developer/Superadmin.
- Developer disiapkan melalui deployment; Superadmin hanya dapat dikelola oleh Superadmin lain.

## Pengelolaan Membership

Superadmin mengelola seluruh Membership dalam Grup. Admin Faskes hanya mengelola Membership pada Faskesnya sendiri.

Admin Faskes boleh mengubah Membership miliknya sendiri, termasuk role, Unit, status, dan masa berlaku, dengan batas:

- tidak dapat memberikan status Developer atau Superadmin;
- tidak dapat menonaktifkan Admin Faskes aktif terakhir;
- tidak dapat mengelola Faskes lain;
- perubahan tetap masuk audit dasar.

Audit dasar menyimpan pelaku, waktu, aksi, dan target. Sesuai keputusan produk, audit Membership belum menyimpan snapshot sebelum/sesudah dan perubahan diri sendiri tidak membutuhkan alasan.

Akses darurat lintas Unit belum disediakan. Admin menambahkan Unit pada role assignment jika kebutuhan operasional memang sah.

## Pemilihan konteks

### Setelah login

- Nol Faskes: tampilkan empty state dan jangan membuka API operasional.
- Satu Faskes: langsung buka Faskes tersebut.
- Lebih dari satu Faskes: tampilkan pemilih; pilihan terakhir hanya disorot, bukan dibuka otomatis.

Facility code berada pada URL `/f/{facility_code}` dan divalidasi terhadap Membership atau pengecualian aktor global. Pemilihan tidak boleh menjadi satu nilai session global karena dua tab dapat membuka Faskes berbeda.

### Unit aktif

- Satu Unit yang diizinkan: pilih otomatis.
- Lebih dari satu Unit: tampilkan selector Unit Layanan.
- Unit aktif tercantum pada URL/tab agar dua tab dapat bekerja pada Unit berbeda.
- Perpindahan Unit tidak mengubah grant; hanya Unit yang sudah diberikan yang dapat dipilih.
- Role facility-wide tidak membutuhkan Unit palsu.

## UI minimum

Halaman detail User memisahkan status akun dari akses per Faskes:

```text
Pengguna: dr. Andi
Status akun: [ ON ]

Akses Faskes
┌─────────────────────────────────────────────┐
│ RS Sehat                                    │
│ Status Membership: [ ON ]                   │
│ Permanen:          [ ON ]                   │
│ Mulai: 01-09-2026   Selesai: —              │
│ Dokter → Poli Anak, IGD                     │
│ Kasir  → Unit Kasir                         │
└─────────────────────────────────────────────┘
```

Jika User nonaktif, Membership tetap terlihat dengan status `Diblokir oleh status akun`. Form baru menampilkan ringkasan Faskes, role, dan Unit sebelum penyimpanan karena Membership langsung aktif.

Frontend tidak boleh memakai daftar Unit hardcoded, fallback permission, atau pilihan local-storage sebagai bukti otorisasi. Semua pilihan berasal dari API yang telah discoped backend.

## Enforcement backend

Urutan minimum request operasional:

```text
autentikasi User
  → pastikan User aktif
  → resolve Faskes dari route/allowlist
  → validasi pengecualian global atau Membership aktif dan berlaku
  → resolve role yang mengizinkan aksi
  → validasi scope Unit target
  → buka database Faskes
  → jalankan use case dan audit
```

Aturan wajib:

1. Frontend hanya membantu navigasi; backend adalah sumber keputusan izin.
2. Role dan Unit harus lolos pada aksi yang sama. Union role dan union Unit yang menghasilkan hak silang dilarang.
3. Endpoint daftar, detail, create, update, delete, export, job, dan command sensitif memakai policy yang sama.
4. Query list harus discoped sebelum pagination; jangan memuat seluruh data lalu memfilter di aplikasi.
5. Cache permission harus memuat User dan Faskes serta diinvalidasi saat User, Membership, role, tenaga, atau Unit berubah.
6. Request tanpa Facility context yang sah berhenti sebelum membuka database operasional.
7. Tidak ada fallback ke semua Unit ketika konfigurasi atau API gagal.

## Monitoring Global

Monitoring Global adalah control-plane observability, bukan workspace klinis gabungan.

- dapat diakses Developer dan Superadmin;
- mencakup kegagalan Antrean Online, BPJS/VClaim, E-Klaim, SATUSEHAT, serta error yang tampil di aplikasi;
- dapat dicari berdasarkan Faskes, nama pasien, NRM, integrasi, waktu, status, dan penyebab;
- insiden mempunyai status `baru → ditangani → selesai | diabaikan`, penanggung jawab, catatan, serta retry/reconcile bila adapter mendukung;
- Developer melihat detail teknis yang sudah diamankan; Superadmin melihat konteks operasional dan tindakan penyelesaian;
- stack trace, secret, token, dan payload klinis mentah tetap berada di server log;
- membuka detail membawa pengguna ke konteks satu Faskes dan dicatat pada audit.

Penyimpanan/proyeksi Monitoring Global dirancang bersama outbox dan integration operations; halaman ini tidak boleh melakukan join bebas lintas database pada setiap request.

## Acceptance

- User tanpa Membership dapat login tetapi tidak dapat mengakses API operasional.
- User dengan Membership Faskes A tidak dapat membaca atau menulis Faskes B.
- Role Dokter pada Poli Anak dan role Kasir pada Unit Kasir tidak saling bocor.
- `Semua Unit` pada satu role tidak memperluas role lain.
- Unit nonaktif tidak dapat dipakai dan tidak menghapus histori grant.
- User, Membership, masa berlaku, tenaga klinis, dan Unit nonaktif berlaku pada request berikutnya.
- Admin Faskes tidak dapat memberi dirinya status global atau menonaktifkan Admin terakhir.
- Dua tab dapat membuka Faskes atau Unit berbeda tanpa saling mengubah konteks.
- Manipulasi facility/unit code melalui URL atau payload ditolak backend.
- Daftar besar discoped sebelum pagination dan mempunyai indeks yang sesuai.
- Developer/Superadmin dapat menemukan insiden lintas Faskes, lalu membuka detail pada Faskes yang benar.

## Urutan implementasi

1. Tambahkan model Membership dan role assignment pada control DB.
2. Ganti mock/fallback akses Unit dengan API tepercaya.
3. Terapkan Facility context dari route dan policy role-per-Unit pada satu vertical slice Rawat Jalan.
4. Tambahkan invalidasi akses pada request berikutnya dan audit dasar.
5. Buktikan isolasi dua Faskes, dua role, dua Unit, serta dua tab.
6. Perluas policy ke modul operasional lain setelah pola pertama lolos.
7. Implementasikan Monitoring Global bersama outbox/integration operations, bukan sebagai pembacaan log mentah.

Stop condition tahap awal: satu journey Rawat Jalan membuktikan bahwa User hanya dapat menjalankan aksi role-nya pada Unit dan Faskes yang diberikan. Jangan menambah role builder, ACL Room/Bed, approval berlapis, atau akses darurat sebelum kebutuhan operasionalnya terbukti.
