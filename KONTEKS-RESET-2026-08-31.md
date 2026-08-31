# Konteks Reset — 31 Agustus 2026

Dokumen ini merekam reset arah produk setelah eksplorasi multi-faskes. Blueprint aktif berada di [`BLUEPRINT-SIMGOS.md`](./BLUEPRINT-SIMGOS.md).

## Keputusan aktif

- Arsitektur target kembali ke `single faskes` per instalasi.
- Baseline kode acuan bisnis adalah branch senior `upstream/main`.
- Alur yang wajib dipertahankan adalah workflow BPJS dan SATUSEHAT.
- Eksplorasi multi-faskes, membership per faskes, credential per faskes, dan database per faskes tidak lagi menjadi target aktif.

## Implikasi desain

- `1 aplikasi = 1 faskes`.
- Kredensial BPJS dan SATUSEHAT kembali menjadi kredensial instalasi tunggal, bukan per faskes.
- Akses pengguna memakai Role, Profesi, Akses Ruangan, dan permission aksi; istilah Membership tidak dipakai pada model aktif.
- Route, middleware, provisioning, dan database switching berbasis `facility_code` tidak boleh dijadikan dasar desain baru.

## Cara membaca dokumen lama

- Dokumen workflow, integrasi BPJS, integrasi SATUSEHAT, UI/UX, dan domain masih relevan sebagai bahan lanjut karena inti alur bisnisnya tetap dipakai.
- Dokumen yang berisi asumsi multi-faskes harus dianggap draf historis sampai ditulis ulang.
- Jika ada pertentangan antara dokumen lama dan keputusan di file ini, ikuti file ini.

## Dokumen historis

- seluruh eksplorasi tersebut telah dipindahkan ke `docs/legacy/multi-faskes/`;
- ADR pengganti aktif adalah [`adr/0005-satu-deployment-satu-faskes.md`](./adr/0005-satu-deployment-satu-faskes.md).

## Tujuan penulisan ulang docs

- Menyelaraskan seluruh dokumentasi ke baseline `single faskes`.
- Memisahkan dengan tegas mana yang merupakan baseline SIMPel/SIMGOS2, mana yang keputusan produk baru, dan mana yang hanya catatan historis.
- Menjaga alur BPJS dan SATUSEHAT tetap menjadi vertical slice inti.
- Menyediakan konteks singkat yang cukup agar AI atau developer baru bisa lanjut tanpa membaca seluruh riwayat chat.

## Hasil reset

- blueprint, katalog 147 modul, peta data, pembagian empat orang, dan gate production telah disusun;
- metadata legacy tersedia di `docs/referensi-simpel/`;
- implementasi berikutnya harus memakai baseline branch senior dan memulai dari Gelombang 0 serta Rawat Jalan BPJS.
