# Model Domain — SIMGOS

Glosarium kanonik berada di [`../CONTEXT.md`](../CONTEXT.md). Dokumen ini menjelaskan relasi, lifecycle, dan invariant; nama tabel atau class implementasi dapat berbeda.

## Model organisasi

```text
Grup 1 ── * Faskes 1 ── * Unit Layanan
                    ├── 1 PPK internal
                    ├── 1 database operasional
                    └── * Membership * ── 1 User
```

- Satu deployment hanya melayani satu Grup.
- Semua aggregate operasional dimiliki tepat satu Faskes.
- User berada pada level Grup; kewenangan operasionalnya berasal dari Membership pada Faskes terkait, kecuali Developer dan Superadmin sebagai aktor global yang diaudit khusus.
- Membership menghubungkan User dengan Faskes, status, masa aktif, serta satu atau beberapa role dengan cakupan Unit Layanan masing-masing.
- Unit Layanan adalah lokasi/unit kerja operasional (`Ward`), bukan `Room` kamar fisik atau `Bed`; kontrak lengkap berada di [`implementation/keanggotaan-dan-akses.md`](./implementation/keanggotaan-dan-akses.md).
- Setiap Faskes menunjuk tepat satu PPK internal sebagai sumber identitas bisnis. Satu PPK internal tidak boleh dipakai dua Faskes aktif dalam Grup yang sama.
- PPK lain dapat merepresentasikan fasilitas eksternal/rujukan tanpa menjadi Faskes; PPK bukan isolation boundary.
- Setiap Faskes memiliki tepat satu database operasional. ID lokal hanya bermakna dalam scope Faskes tersebut.

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

- Pendaftaran, Coverage, SEP, Kunjungan, dan Encounter harus berada pada Faskes dan Pasien yang sama.
- SEP FKRTL merujuk satu episode pelayanan yang dapat ditelusuri hingga Coverage, Kunjungan, Tagihan, dan Episode Klaim.
- Encounter tidak dapat menjadi `finished` sebelum data minimum sesuai use case terpenuhi.
- Finalisasi Kunjungan menolak penambahan atau perubahan record yang terkunci.
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

- Satu operation identity mempunyai idempotency key unik dalam scope Faskes dan sistem tujuan.
- Parent dependency harus `accepted/reconciled` sebelum child dikirim.
- Worker crash tidak boleh kehilangan event atau membuat duplikasi tanpa deteksi.
- Payload dan response sensitif disimpan dengan redaction, akses terbatas, serta retention policy.

## Transisi yang tidak boleh berupa string bebas

- Pendaftaran: draft/confirmed/cancelled sesuai workflow.
- Kunjungan: planned/arrived/in-progress/finished/cancelled dengan aturan koreksi.
- Hasil klinis: preliminary/final/amended/cancelled.
- Tagihan: draft/final/partially-paid/paid/void dengan command eksplisit.
- Episode Klaim: draft/ready/grouped/submitted/pending-or-disputed/approved/paid/reconciled.
- Submission: lifecycle antrean pengiriman di atas.

Nama state final harus dikonfirmasi terhadap kontrak domain masing-masing; yang wajib adalah transisi eksplisit dan tervalidasi, bukan nilai string bebas.
