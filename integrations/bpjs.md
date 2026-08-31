# Integration — BPJS FKRTL

**Status:** target architecture; kesiapan implementasi diverifikasi terhadap baseline branch senior
**Scope awal:** Rawat Jalan BPJS/FKRTL
**Referensi eksternal terakhir diaudit:** 2026-08-24

## Batas integrasi

BPJS bukan satu “bridging module”. Pisahkan capability berikut meskipun menggunakan shared transport/auth library:

| Capability | Tanggung jawab | Bukan |
|---|---|---|
| Antrean Online | jadwal, booking, check-in, waktu layanan, batal, antrean farmasi | SEP atau klaim |
| VClaim | peserta, rujukan/kontrol, eligibilitas, SEP, update/batal SEP | antrean atau grouper |
| Aplicares | sinkronisasi ketersediaan tempat tidur | bed management internal |
| ICare | akses riwayat pelayanan untuk dokter yang berwenang | rekam medis internal |
| PCare | workflow BPJS FKTP pada capability profile terpisah | workflow FKRTL |

E-Klaim dijelaskan terpisah di [`eklaim.md`](./eklaim.md).

## Ownership dan traceability

- Antrean eksternal terhubung ke appointment/arrival internal.
- SEP terhubung ke Coverage, Pendaftaran, Kunjungan, dan akhirnya Episode Klaim.
- External identifier tidak boleh hanya terhubung ke Pasien karena satu Pasien dapat mempunyai banyak episode.
- Data klinis dan status operasional internal tetap source of truth SIMGOS.

## Konfigurasi instalasi

- kode faskes dan environment;
- consumer ID/secret, user key, atau credential yang dipersyaratkan;
- endpoint/version, timeout, clock synchronization, dan certificate policy;
- mapping poli, DPJP/subspesialis, kelas, Ruangan, serta kode referensi;
- redaction dan retention request/response;
- health check, rate limit, serta rotasi credential.

Secret disimpan terenkripsi pada database agar dapat dirotasi melalui UI, tidak ditulis pada source code, dan tidak dikembalikan melalui API frontend. Master encryption key tetap berada pada environment server.

## Operation model

Setiap create/update/cancel eksternal mempunyai:

- instalasi dan environment;
- operation type dan aggregate internal;
- idempotency/operation identity;
- canonical request snapshot dengan data sensitif ter-redaksi untuk tampilan;
- attempt history, response code, partner timestamp, external ID;
- state `queued/sending/accepted/retryable_failed/permanently_failed/reconciled`;
- manual resolution dengan reason dan actor.

Timeout sesudah request create tidak boleh langsung diikuti create baru. Sistem melakukan query/reconciliation terlebih dahulu bila API menyediakan cara pencarian.

## Workflow UI

- Pendaftaran menampilkan rujukan/kontrol, kelas hak, poli, DPJP, Coverage, SEP, dan error resolution dalam konteks episode.
- Worklist antrean menampilkan status booking/check-in serta timestamp layanan yang harus dikirim.
- Configuration/monitoring global berada di area Integrasi.
- Petugas tidak perlu melihat detail kriptografi; petugas IT dapat melihat diagnostics yang sudah ter-redaksi.

## Readiness checklist

- [ ] Kontrak API UAT dan production diverifikasi melalui akses mitra resmi.
- [ ] Clock, signature, encryption/decryption, dan decompression diuji dengan fixture resmi.
- [ ] Mapping reference mempunyai owner dan laporan missing mapping.
- [ ] Idempotency serta reconciliation diuji untuk timeout dan respons ambigu.
- [ ] Audit membedakan siapa memulai operasi, worker yang mengirim, dan respons BPJS.
- [ ] Antrean, SEP, dan klaim tidak menggunakan satu status generik.
- [ ] Runbook credential expired, maintenance, rate limit, dan partner outage tersedia.

## Referensi

- [SIMGOS2 — VClaim](https://docs.simgos2.simpel.web.id/docs/integrasi/bpjs/vclaim/)
- [SIMGOS2 — Aplicares](https://docs.simgos2.simpel.web.id/docs/integrasi/bpjs/aplicares/)
- [SIMGOS2 — ICare](https://docs.simgos2.simpel.web.id/docs/integrasi/bpjs/icare/)
- [SIMGOS2 — Antrean Online](https://docs.simgos2.simpel.web.id/docs/integrasi/bpjs/antrian-online/)
- [Manual Mobile JKN BPJS](https://bpjs-kesehatan.go.id/user-manual-mobile-jkn/video_mjkn.html)

Versi endpoint dan aturan operasional wajib diverifikasi kembali saat implementasi.
