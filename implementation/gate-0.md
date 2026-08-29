# Gate 0 Implementation Backlog

Dokumen ini memecah Gate 0 di [`../ROADMAP.md`](../ROADMAP.md) menjadi urutan engineering. Status produk tetap hanya diperbarui di Roadmap; checklist ini adalah urutan kerja teknis.

## 1. Lifecycle Kunjungan

- [x] Sediakan module dengan interface kecil untuk finalisasi dan penulisan klinis terjaga.
- [x] Pindahkan finalisasi dari generic update ke command endpoint eksplisit.
- [x] Gunakan transaction dan row lock saat finalisasi serta penulisan klinis.
- [x] Tolak update, delete, CPPT, dan diagnosis setelah final.
- [x] Terapkan guard pada tindakan, keluhan utama, anamnesis, TTV, pemeriksaan umum/fisik, rencana terapi, resume, resep, serta pembuatan order lab/radiologi.
- [x] Tolak finalisasi jika order lab atau radiologi belum berada pada status terminal.
- [x] Lindungi item resep, item order, petugas tindakan, serta hasil lab/radiologi setelah final.
- [ ] Definisikan amendment hasil final sebagai command terpisah dengan reason dan provenance.
- [ ] Definisikan amendment/batal-final dengan reason, authorization, dan audit.

Catatan: perubahan status/hasil order lab dan radiologi belum diblok setelah final karena amendment hasil membutuhkan lifecycle tersendiri. Pembuatan order baru sudah diblok dan finalisasi kini menolak order non-terminal.

## 2. Authorization

- [x] Definisikan Membership User–Faskes–role–Unit Layanan dan profesi sesuai [`membership-access.md`](./membership-access.md).
- [ ] Tambahkan policy untuk admit, record, verify, finalize, amend, bill, code, dan submit.
- [ ] Hapus `authorize(): true` dari command sensitif.
- [ ] Tambahkan matrix test role, Ruangan, assignment, dan cross-Faskes denial.

## 3. Facility isolation

- [x] Pilih database-per-Faskes pada satu instance MariaDB Grup sesuai [`multi-schema-facility.md`](./multi-schema-facility.md).
- [x] Implementasikan katalog dan draft UI Faskes yang mengacu ke satu PPK internal.
- [x] Tambahkan validator ownership migration yang memblokir FK operasional menuju control DB.
- [ ] Hilangkan dependency lintas database yang dilaporkan `facility:schema-plan`.
- [ ] Implementasikan provisioning/retry CLI sesuai [`facility-provisioning.md`](./facility-provisioning.md).
- [x] Turunkan facility context HTTP dari route dan validasi Membership tepercaya pada vertical slice Rawat Jalan pertama; jangan memakai pilihan session global.
- [ ] Perluas scope HTTP ke seluruh modul serta scope queue, scheduler, cache key, storage path, dan sequence.
- [ ] Tambahkan test dua Faskes yang membuktikan tidak ada cross-read/write.

## 4. Transaction safety

- [x] Ganti generator nomor Payment dengan sequence tahunan atomik yang mengadopsi nomor legacy/manual.
- [ ] Ganti generator `count()+1` lain sesuai prioritas vertical slice; jangan menganggap sequence Payment menyelesaikan seluruh nomor dokumen.
- [x] Bungkus Payment, InvoiceItem, dan total recalculation dalam transaction serta row lock Tagihan.
- [x] Implementasikan lifecycle buka/tutup Sesi Kasir dengan satu sesi aktif per master Kasir.
- [x] Pindahkan penguncian Tagihan dari efek samping pembayaran ke command Final Tagihan eksplisit.
- [x] Final Tagihan memeriksa Kunjungan final, pembayaran cukup, Sesi Kasir aktif, serta mencatat aktor dan waktu secara atomik.
- [ ] Lengkapi precondition Final Tagihan SIMPel yang belum tercakup: policy kasir/supervisor, seluruh jenis order pending, serta konfigurasi privilege terkait.
- [ ] Implementasikan pembatalan Final Tagihan sebagai lifecycle reversal beralasan; jangan memakai pembatalan invoice biasa untuk membuka record final.
- [x] Serialisasikan ledger/saldo stok Ruangan dan fulfillment StockRequest dengan transaction serta row lock.
- [ ] Amankan mutasi stok gudang lain, termasuk GoodsReceipt dan adjustment langsung InventoryItem.
- [x] Wajibkan idempotency key pada command Payment dan pertahankan key yang sama saat UI melakukan retry.
- [ ] Tambahkan version/idempotency guard pada command finansial prioritas lain.
- [x] Uji rollback atomik, retry Payment, reuse key yang konflik, numbering berurutan, dan retry fulfillment stok.
- [ ] Uji concurrency nyata pada database target untuk Payment, numbering, dan stock movement; SQLite test tidak membuktikan perilaku row lock production.

## 5. Outbox dan integration operations

- [ ] Transactional outbox per Faskes.
- [ ] Operation identity/idempotency unique constraint.
- [ ] Worker lease, batch limit, backoff, max attempts, dan dead-letter.
- [ ] Attempt history, redaction, reconciliation, dan manual resolution.
- [ ] Adapter terpisah untuk Antrean Online, VClaim, E-Klaim, dan SATUSEHAT.

## Exit criteria Gate 0

- Satu automated scenario membuktikan finalisasi mengunci seluruh write klinis yang termasuk Slice 1.
- Dua Faskes tidak dapat mengakses data satu sama lain.
- Command finansial prioritas aman terhadap retry dan concurrency.
- Submission eksternal dapat dipulihkan dari timeout, duplikasi, dan worker crash.
- Error operasional tersedia sebagai worklist, bukan hanya application log.
