# Referensi Umum

**Status:** desain diterima; implementasi berjalan.

Dokumen ini menetapkan katalog referensi instalasi berdasarkan
`master.jenis_referensi` dan `master.referensi` SIMPel. Database legacy hanya
menjadi sumber snapshot awal; runtime SIMGOS tidak bergantung kepadanya.

## Batas domain

`GeneralReference` menjadi source of truth untuk kategori yang memang berasal
dari `master.referensi`, termasuk gender, agama, pendidikan, pekerjaan, status
perkawinan, golongan darah, bahasa, suku, dan profesi. Sembilan tabel/modul
khusus tersebut dihapus sebelum production karena belum ada data existing.

Negara, wilayah, PPK, diagnosis, tindakan, barang, pegawai, serta mapping
integrasi tetap memakai modul khusus. `GeneralReferenceMap` tetap terpisah
karena lifecycle mapping sistem eksternal berbeda dari katalog internal.

## Ownership dan schema

Katalog disimpan pada database operasional instalasi. Tidak ada control DB,
override lintas Faskes, atau tabel bayangan kedua.

`reference_types` minimum menyimpan:

- primary key internal;
- `legacy_id`, nama, singkatan, dan penanda aplikasi SIMPel;
- `management_scope`: `system` atau `installation`;
- status aktif dan version untuk optimistic locking.

`reference_entries` minimum menyimpan:

- primary key internal dan `legacy_table_id` nullable/unik;
- `reference_type_id` dan kode bisnis unik per jenis;
- nama, `ref_id`, teks, config JSON, scoring, status, dan version.

Nama jenis bukan identifier karena sumber mempunyai nama duplikat. Consumer
operasional mempertahankan nama field domain seperti `gender_id`, tetapi ID
tersebut menunjuk `reference_entries` tanpa FK lintas database. Request wajib
memastikan entry berada pada jenis yang benar dan aktif untuk input baru.

## Seed dan pembaruan

Snapshot JSON versioned memuat seluruh kategori dan entry valid dari SIMPel.
Snapshot awal yang diaudit pada 28 Agustus 2026 berisi 337 jenis dan 4.065
entry; tujuh entry tanpa jenis masuk laporan karantina, bukan data aktif.

Seeder wajib idempotent serta memvalidasi schema, uniqueness, jumlah, dan
checksum. Deployment boleh meng-upsert kategori `system`; data `installation` tidak
ditimpa. Pembaruan sumber hanya melalui command yang menghasilkan diff untuk
review Developer, bukan sinkronisasi runtime.

## API dan lifecycle

- Semua pengguna terautentikasi boleh membaca entry aktif.
- List memakai server-side search dan pagination yang mengembalikan `total`,
  `per_page`, `current_page`, dan `last_page`.
- Lookup ID tetap dapat membaca entry nonaktif untuk dokumen lama.
- Tidak ada hard delete untuk jenis, entry, atau mapping; gunakan nonaktif.
- Update memakai optimistic locking dan mengembalikan `409` saat version basi.
- Perubahan diaudit dan menginvalidasi cache kategori terkait.
- `CONFIG` disimpan utuh, tetapi consumer hanya boleh membaca key yang dikenal;
  isinya tidak boleh mengeksekusi kode, SQL, class, atau template.
- Dokumen klinis/tagihan final menyimpan snapshot kode dan nama referensi.

## Authorization

- `developer` dapat mengakses seluruh fitur instalasi; akses data klinis wajib
  diaudit.
- `superadmin` dapat mengelola fitur bisnis instalasi, tetapi tidak dapat
  mengubah kategori `system` atau memakai fungsi khusus Developer.
- Jenis referensi hanya dibuat Developer. Superadmin mengelola entry kategori
  `installation` yang tercantum dalam allowlist versioned.
- Akun local/demo menggunakan kredensial seed baku dan wajib mengganti password
  pada login pertama. Production tetap membutuhkan secret environment.

## UI dan pembagian kerja

Halaman Master Referensi mengikuti SIMPel: jenis di kiri, entry di kanan,
pencarian, status, dan pagination lengkap. Metadata lanjutan hanya berada pada
panel Developer. Implementasi halaman tersebut menjadi tanggung jawab tim UI
junior; pekerjaan utama hanya menyediakan kontrak API serta menyesuaikan form
consumer pasien, pegawai, tenaga medis, keluarga pasien, kelahiran, dan
transfusi darah.

## Acceptance

- Fresh database menghasilkan snapshot referensi tervalidasi tanpa koneksi ke
  database SIMPel.
- Sembilan tabel katalog duplikat tidak ada.
- Seluruh consumer menolak entry dari jenis yang salah atau nonaktif.
- Form existing tidak memanggil endpoint katalog khusus yang dihapus.
- Developer dan Superadmin mengikuti scope yang ditetapkan.
- Password seed wajib diganti sebelum akun membuka fitur lain.
- tidak ada tabel katalog duplikat pada database instalasi.
