# Kesiapan Production SIMGOS

**Status:** gate wajib
**Scope awal:** satu instalasi, satu faskes, sekitar 1.000 pasien per hari

Tidak ada capability yang boleh disebut `production-ready` hanya karena berhasil di development. Checklist ini harus mempunyai bukti, owner, tanggal, dan hasil.

## Topologi minimum

```text
Pengguna
  → TLS / reverse proxy
      ├── frontend /
      ├── backend /api
      └── dokumen melalui endpoint berizin

Backend
  ├── MariaDB satu instalasi
  ├── queue worker
  ├── scheduler
  ├── file/object storage
  └── monitoring + backup
```

- Satu domain utama cukup; subdomain per modul tidak diperlukan.
- Operasional lokal harus tetap berjalan ketika internet eksternal putus.
- BPJS/SATUSEHAT yang dapat ditunda masuk queue; operasi yang membutuhkan jawaban langsung tidak boleh ditandai sukses secara palsu.
- High availability ditambahkan ketika SLA faskes menuntutnya; beban 1.000 pasien/hari sendiri bukan alasan membuat cluster.

## Target layanan

| Area | Target awal |
|---|---|
| backend sederhana | P95 ≤100 ms, di luar network/client |
| command internal umum | P95 ≤300 ms |
| halaman siap dipakai | ≤2 detik pada jaringan representatif |
| availability | 99,9% per bulan |
| recovery point objective | ≤15 menit |
| recovery time objective | ≤4 jam |
| load test | minimal 2× puncak perkiraan, bukan rata-rata harian |

Integrasi eksternal, upload besar, grouping, ekspor, dan laporan berat mengikuti SLA tersendiri atau berjalan asynchronous. Slow-query log menentukan kebutuhan index; jangan menambah index spekulatif ke semua kolom.

## Keamanan dan privasi

- [ ] TLS aktif dan konfigurasi production tidak memakai debug mode.
- [ ] Password di-hash menggunakan mekanisme framework yang berlaku.
- [ ] Login memiliki rate limit, session timeout, pencabutan sesi, dan audit.
- [ ] MFA wajib untuk superadmin/developer.
- [ ] Akun bersifat individual; tidak ada akun petugas bersama.
- [ ] Permission backend menggabungkan Role, Ruangan, Profesi, dan capability.
- [ ] Break-glass meminta alasan, mempunyai batas waktu, diaudit, dan mengirim notifikasi.
- [ ] Credential integrasi berupa ciphertext di DB; master key berada di environment.
- [ ] UI memasking secret dan audit mencatat rotasi tanpa mencatat nilai secret.
- [ ] Log, metric, trace, dan tiket tidak membocorkan data medis atau credential.
- [ ] Development/staging memakai data sintetis atau hasil anonimisasi terverifikasi.
- [ ] Matriks retensi disetujui; data medis/finansial tidak dihapus otomatis sebelum aturan resmi dikonfirmasi.

## Database dan migration

- [ ] Satu database operasional MariaDB dipakai oleh seluruh modul instalasi.
- [ ] Tidak ada control DB, facility runtime switching, atau provisioning multi-faskes dalam target aktif.
- [ ] Semua perubahan skema melalui migration; migration yang pernah dipakai tidak diedit ulang.
- [ ] Perubahan breaking dilakukan expand → migrate consumer → contract pada rilis berikutnya.
- [ ] Unique constraint melindungi nomor dokumen dan idempotency key.
- [ ] Transaksi uang, stok, finalisasi, dan nomor urut memakai transaction/locking yang sesuai.
- [ ] Migration rehearsal lulus pada ukuran data representatif.
- [ ] Backup diambil sebelum perubahan berisiko dan rollback aplikasi telah diuji.

## Backup dan disaster recovery

- [ ] Backup database otomatis, terenkripsi, dan disimpan di lokasi berbeda.
- [ ] Binlog/point-in-time recovery tersedia untuk memenuhi RPO bila infrastruktur mendukung.
- [ ] Dokumen/file storage ikut dibackup dan konsisten dengan metadata database.
- [ ] Restore database dan dokumen diuji berkala; hasil dan durasinya dicatat.
- [ ] Runbook mencakup kehilangan database, file, server, credential, dan konektivitas eksternal.
- [ ] Restore tidak dianggap sukses sebelum smoke test journey kritis lulus.

## Observability

- [ ] Health check membedakan aplikasi, database, queue, scheduler, storage, dan adapter eksternal.
- [ ] Structured log memakai correlation ID dari UI sampai worker/submission.
- [ ] Metric minimum: error rate, latency, throughput, slow query, queue depth/age, retry, dead letter, disk, CPU, memory, backup age.
- [ ] Dashboard error dapat ditelusuri ke pasien/episode melalui ID aman dan akses berizin.
- [ ] Error teknis dibedakan dari error validasi bisnis.
- [ ] Alert mempunyai owner, ambang, kanal, dan runbook; alert yang tidak dapat ditindak dihapus.
- [ ] Audit perubahan klinis, finansial, akses, konfigurasi, finalisasi, pembatalan, dan integrasi tersedia.

## Pengujian

| Jenis bukti | Minimum |
|---|---|
| invariant domain | satu tes untuk aturan paling berisiko tiap aggregate |
| kontrak API | request/response, status, permission, dan error |
| integrasi database | constraint, transaction, locking, migration |
| adapter eksternal | sandbox/mock, timeout, duplicate, retry, reconciliation |
| journey | satu alur end-to-end per capability utama |
| keamanan | authn, authz, IDOR, rate limit, secret redaction |
| performa | 2× beban puncak dan query plan jalur utama |
| recovery | restore dan rollback rehearsal |

Journey wajib pertama: pasien baru/lama → rawat jalan BPJS → SEP → penerimaan poli → TTV/pemeriksaan → diagnosis/tindakan/resep → final RM → final layanan → tagihan/pembayaran → klaim minimum → SATUSEHAT.

## Lingkungan dan rilis

- [ ] Tersedia development, staging, dan production.
- [ ] Artifact yang sama dipromosikan; hanya konfigurasi yang berbeda.
- [ ] `main` selalu siap dirilis; branch pekerjaan pendek dan direview.
- [ ] Release captain memastikan backup, migration, worker, scheduler, smoke test, dan rollback.
- [ ] Rollback memakai artifact sebelumnya, bukan `git reset` di server.
- [ ] Submission eksternal yang telah diterima tidak dihapus saat rollback; lakukan rekonsiliasi.
- [ ] Version dan tanggal kontrak BPJS/SATUSEHAT/E-Klaim diverifikasi saat adapter dibuat dan sebelum go-live.

## UAT dan cutover

- [ ] UAT dilakukan oleh pendaftaran, perawat, dokter, penunjang, farmasi, kasir, coder/casemix, admin, dan integrasi.
- [ ] Data awal hanya profil faskes, pengguna, Ruangan, tarif, tindakan, barang, referensi, mapping, dan konfigurasi yang disetujui.
- [ ] Import master menyediakan preview, validasi, laporan error, dan transaksi atomik.
- [ ] Tidak ada pasien atau akun demo pada production.
- [ ] Pilot dimulai pada unit rawat jalan terbatas; perluasan dilakukan setelah stabil.
- [ ] Periode paralel, bila diperlukan, mempunyai batas dan prosedur rekonsiliasi.
- [ ] Video pelatihan tersedia, tetapi tidak menggantikan UAT.

## Dukungan dan insiden

Jalur dukungan:

`petugas → superuser unit → admin aplikasi → developer/integrasi`

Tiket memuat waktu, gejala, langkah, ID aman pasien/kunjungan, screenshot yang sudah diperiksa, dan correlation ID. Runbook minimum:

- aplikasi tidak dapat diakses;
- database lambat atau koneksi habis;
- queue/scheduler berhenti atau menumpuk;
- BPJS/SATUSEHAT/E-Klaim gagal;
- backup gagal atau restore dibutuhkan;
- disk/storage penuh;
- credential bocor;
- dugaan akses tidak sah;
- kesalahan klinis atau finansial setelah final.

## Gate go-live

Production hanya boleh dibuka bila seluruh kondisi berikut terpenuhi:

1. UAT journey target ditandatangani.
2. Security review tidak menyisakan risiko kritis terbuka.
3. Load test dan target latency lulus.
4. Backup/restore serta rollback terbukti.
5. Monitoring, alert, audit, queue, dan runbook aktif.
6. Credential dan kontrak eksternal production tervalidasi.
7. Migration rehearsal dan smoke test lulus.
8. Owner dukungan dan release captain ditetapkan.
9. Daftar known issue beserta mitigasi disetujui.
10. Keputusan go-live dicatat dengan tanggal dan pihak yang menyetujui.
