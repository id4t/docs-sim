---
status: accepted
---

# One deployment per Grup

SIMGOS menggunakan satu repository dan build produk, tetapi setiap Grup pelanggan menjalankan deployment/server sendiri. Keputusan ini menghindari fork kode per pelanggan sekaligus mencegah semua Grup berbagi failure domain dan data plane yang sama; multi-Grup SaaS dalam satu runtime bukan target saat ini.

