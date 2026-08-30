# Akun Demo Lokal

**Berlaku hanya untuk environment `local`.** Production tidak membuat akun demo; bootstrap administrator production tetap membutuhkan `AUTH_BOOTSTRAP_ADMIN_PASSWORD`.

Jalankan seeder Auth lalu Authorization pada database dev yang baru:

```bash
php84 artisan module:seed Auth
php84 artisan module:seed Authorization
```

Semua akun lokal memakai kata sandi awal `Simgos123!` dan wajib menggantinya setelah login pertama.

| Username | Peran |
| --- | --- |
| `developer` | Akses seluruh fitur dan administrasi permission |
| `superadmin` | Akses seluruh fitur operasional tanpa administrasi permission developer |
| `admin` | Kompatibilitas administrator lama; akses seluruh permission |
| `pendaftaran` | Petugas pendaftaran |
| `perawat` | Perawat |
| `dokter` | Dokter |
| `kasir` | Kasir |
| `verifikator_penjamin` | Verifikator penjamin |
| `petugas_klaim` | Petugas klaim |
| `rekam_medis` | Petugas rekam medis |
| `integrasi_satusehat` | Petugas integrasi SATUSEHAT |

Seeder bersifat idempoten: akun yang sudah ada tidak ditimpa kata sandinya. Jangan memakai kredensial ini di staging atau production.
