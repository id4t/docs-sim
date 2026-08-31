# Integration — SATUSEHAT

**Status:** target architecture
**Scope awal:** resource minimum Rawat Jalan sesuai playbook resmi yang berlaku
**Referensi eksternal terakhir diaudit:** 2026-08-24

## Prinsip

SATUSEHAT adalah pipeline interoperabilitas FHIR sepanjang lifecycle pelayanan, bukan satu Form Registry dan bukan pengganti SIMRS. Resource dibangun server-side dari record lokal serta mapping tervalidasi; frontend tidak mengirim FHIR ID bebas sebagai source of truth.

## Prerequisite identity dan mapping

Parent identity harus tersedia sebelum transaksi anak:

1. Organization Faskes dan unit organisasinya.
2. Location/Ruangan.
3. Practitioner/tenaga kesehatan.
4. Patient/IHS identity.
5. Terminology dan product mapping yang diwajibkan use case.

Mapping menyimpan local identity, remote ID, system/coding version, verification status, source, dan waktu terakhir diverifikasi. Missing mapping menghasilkan task, bukan payload tebakan.

## Dependency graph

Graf minimum bergantung use case, tetapi pola rawat jalan umumnya mencakup:

```text
Patient + Practitioner + Organization + Location
                    └── Encounter
                        ├── Condition
                        ├── Observation
                        ├── Procedure
                        ├── ServiceRequest ── Specimen/Observation/DiagnosticReport
                        ├── MedicationRequest ── MedicationDispense
                        └── Composition
```

Coverage, Account, ChargeItem, Invoice, ImagingStudy, AllergyIntolerance, CarePlan, dan resource lain ditambahkan sesuai playbook/use case resmi. Jangan membuat satu payload generik untuk semua layanan.

## Local resource projection

Builder mengambil data dari aggregate internal pada versi tertentu dan menghasilkan canonical FHIR payload. Submission menyimpan:

- Faskes, resource type, local aggregate ID/version;
- dependency dan remote resource ID;
- operation POST/PUT;
- idempotency key/fingerprint;
- payload version, attempt history, response, dan status reconciliation.

POST digunakan ketika belum ada remote identity yang tervalidasi. PUT digunakan untuk amendment/update terhadap resource yang sudah dikenali. Delete hanya bila use case dan kontrak resmi membolehkannya.

## Outbox dan worker

1. Transaksi domain menyimpan perubahan lokal dan outbox event secara atomik.
2. Projector membuat atau memperbarui Submission deterministik.
3. Worker mengklaim batch terbatas dengan lease/lock.
4. Dependency, rate limit, credential health, dan backoff diperiksa.
5. Response dipersist dan remote ID direkonsiliasi.
6. Retry berhenti pada batas; permanent failure masuk worklist manual.

Scheduler, retry command, dan manual resend tidak boleh menghasilkan resource duplikat tanpa deteksi.

## Encounter lifecycle

- Encounter dipetakan dari Kunjungan, bukan ID pendaftaran string bebas.
- Class, period, subject, participant, service provider, dan location berasal dari domain/mapping lokal.
- Encounter tidak ditutup `finished` sebelum diagnosis akhir dan data penutup minimum lengkap.
- Koreksi tanggal pulang, diagnosis, atau record final membuat update/reconciliation task.

## Monitoring operasional

Dashboard per Faskes menampilkan:

- completeness dan readiness per episode;
- queued/sending/accepted/failed/reconciled;
- missing parent/mapping;
- waktu dan attempt terakhir;
- error yang sudah diterjemahkan ke tindakan petugas;
- manual retry/rebuild/reconcile sesuai permission;
- trace dari resource ke record lokal tanpa membuka data lintas Faskes.

Payload/response sensitif hanya tersedia bagi role tertentu dan harus ter-redaksi.

## Readiness checklist

- [ ] OAuth/environment dan Organization Faskes tervalidasi.
- [ ] Location, Practitioner, Patient, terminology, dan medication mapping memiliki owner.
- [ ] Builder mengambil ID dari mapping tepercaya, bukan request frontend.
- [ ] Unique idempotency constraint, dependency, lease, backoff, max attempts, dan dead-letter diuji.
- [ ] POST/PUT serta amendment/resync diuji.
- [ ] Dashboard error dan runbook tersedia.
- [ ] Resource set diverifikasi terhadap playbook resmi untuk jenis layanan yang dirilis.

## Referensi

- [Prasyarat FHIR SATUSEHAT](https://satusehat.kemkes.go.id/platform/docs/id/fhir/prerequisites/)
- [RME Rawat Jalan](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/rme-rawat-jalan/)
- [Rawat Inap](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/rawat-inap-new/)
- [Kefarmasian](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/kefarmasian/)
- [SIMGOS2 — Resource SATUSEHAT](https://docs.simgos2.simpel.web.id/docs/panduan/kemkessatusehat/resource-satusehat/)
- [SIMGOS2 — Monitoring SATUSEHAT](https://www.docs.simgos2.simpel.web.id/docs/panduan/kemkessatusehat/monitoring-satusehat/)

Dokumentasi SATUSEHAT berkembang; resource wajib dan production readiness diperiksa ulang per rilis.
