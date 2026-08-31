# Model Domain — SIMGOS

Glosarium kanonik berada di [`CONTEXT.md`](./CONTEXT.md). Dokumen ini menjelaskan relasi, lifecycle, dan invariant; nama tabel atau class implementasi dapat berbeda.

## Model organisasi

```text
Satu instalasi SIMGOS
├── 1 Faskes
├── * User
├── * Role/Profesi
├── * Ruangan hierarkis
├── * Akses Ruangan
├── 1 PPK internal
└── 1 database operasional
```

- Satu deployment aktif melayani satu Faskes.
- Semua aggregate operasional dimiliki instalasi Faskes yang sama.
- User, role, profesi, dan akses ruangan dikelola dalam satu boundary instalasi, tanpa membership lintas faskes.
- Ruangan membentuk hierarki instalasi, Unit Layanan, ruang, dan kamar. Unit Layanan adalah jenis Ruangan yang menerima Kunjungan; Tempat Tidur tetap entitas anak tersendiri.
- Faskes menunjuk tepat satu PPK internal sebagai sumber identitas bisnis instalasi.
- PPK lain dapat merepresentasikan fasilitas eksternal atau rujukan tanpa menjadi boundary data baru.
- Instalasi memiliki tepat satu database operasional. ID lokal bermakna dalam scope instalasi yang sama.

## Model pelayanan

```text
Pasien ── Pendaftaran ── Coverage ── SEP
              │
              └── Kunjungan ── Encounter
                     ├── catatan klinis dan asesmen
                     ├── diagnosis dan tindakan
                     ├── order ── hasil/fulfillment
                     ├── resep ── dispensing
                     └── resume/finalisasi
```

Invariant utama:

- Pendaftaran, Coverage, SEP, Kunjungan, dan Encounter harus berada pada instalasi dan Pasien yang sama.
- SEP FKRTL merujuk satu episode pelayanan yang dapat ditelusuri hingga Coverage, Kunjungan, Tagihan, dan Episode Klaim.
- Pendaftaran dimiliki domain Pendaftaran; Kunjungan dimiliki domain Layanan dan dapat berjumlah lebih dari satu untuk satu Pendaftaran.
- Encounter tidak dapat menjadi `finished` sebelum data minimum sesuai jenis layanan dan Ruangan terpenuhi.
- Final Rekam Medis dan Final Layanan adalah transisi berbeda; keduanya menolak perubahan yang tidak lagi sah.
- Koreksi record legal dilakukan dengan amendment/counter-entry yang menyimpan alasan, aktor, waktu, dan referensi record asal.
- Diagnosis klinis dan penandaan kode yang dikirim ke grouper adalah keputusan terkait tetapi berbeda.

## Model finansial dan klaim

```text
Kunjungan ── Tagihan ── PenjaminTagihan
                 ├── ItemTagihan
                 └── Pembayaran ── SesiKasir

Kunjungan + SEP + coding + grouping + Tagihan
                    └── EpisodeKlaim
                        ├── BerkasKlaim
                        ├── SubmissionKlaim
                        ├── hasil verifikasi/dispute
                        └── rekonsiliasi pembayaran
```

Invariant utama:

- Final tagihan hanya diizinkan setelah order dan layanan yang memengaruhi biaya berada pada state terminal yang sah.
- Pembayaran tidak mengubah atau menghapus riwayat sebelumnya; koreksi melalui void/refund beralasan.
- Total, alokasi Penjamin, dan perubahan status Tagihan dihitung atomik.
- Episode Klaim tidak dapat diajukan bila identitas episode, SEP, coding, grouping, Tagihan, atau dokumen wajib belum lengkap.
- Perubahan klinis/finansial setelah pengajuan menghasilkan status perlu-rekonsiliasi; tidak silently overwrite payload yang sudah dikirim.

## Model submission eksternal

```text
Aggregate internal ── OutboxEvent ── Submission ── Sistem eksternal
                                         ├── attempt history
                                         ├── remote ID
                                         └── reconciliation state
```

Lifecycle minimum Submission:

`queued → sending → accepted | retryable_failed | permanently_failed → reconciled`

- Satu operation identity mempunyai idempotency key unik dalam scope instalasi dan sistem tujuan.
- Parent dependency harus `accepted/reconciled` sebelum child dikirim.
- Worker crash tidak boleh kehilangan event atau membuat duplikasi tanpa deteksi.
- Payload dan response sensitif disimpan dengan redaction, akses terbatas, serta retention policy.

## Transisi yang tidak boleh berupa string bebas

- Pendaftaran: `draft/confirmed/closed/cancelled` sesuai workflow.
- Kunjungan: `waiting/received/in_service/final/cancelled` dengan aturan koreksi.
- Order: `draft/sent/received/processing/completed/rejected/cancelled`.
- Hasil klinis: `preliminary/final/amended/cancelled`.
- Tagihan: `draft/final/partially_paid/paid/void` dengan command eksplisit.
- Episode Klaim: `draft/ready/grouped/submitted/disputed/approved/paid/reconciled`.
- Submission: lifecycle antrean pengiriman di atas.

Nama state final harus dikonfirmasi terhadap kontrak domain masing-masing; yang wajib adalah transisi eksplisit dan tervalidasi, bukan nilai string bebas.
