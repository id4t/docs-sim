---
status: accepted
---

# Isolate operational data per Faskes

Setiap Faskes dalam satu Grup memiliki satu database operasional MariaDB yang terisolasi pada instance Grup yang sama. Satu database `simgos_control` menyimpan katalog Faskes, PPK, user, Membership, referensi bersama, status provisioning, dan credential integrasi terenkripsi. Data klinis, finansial, stok, transaksi integrasi, dan audit operasional berada pada database Faskes.

Database bersama dengan `tenant_id` di setiap tabel ditolak untuk fase awal karena risiko kebocoran data dan kompleksitas pembuktian isolasi. Satu server/database instance per Faskes juga ditolak karena satu Grup diperkirakan jarang menambah Faskes dan beban operasionalnya belum dibutuhkan.

Faskes dipilih melalui prefix route, lalu backend memvalidasi Membership dan me-resolve nama database dari control DB. Runtime web tidak memiliki privilege membuat database. Admin membuat draft lewat UI dan operator menjalankan provisioning idempotent melalui CLI.

Kontrak lengkap berada di [`../implementation/multi-schema-facility.md`](../implementation/multi-schema-facility.md) dan [`../implementation/facility-provisioning.md`](../implementation/facility-provisioning.md).
