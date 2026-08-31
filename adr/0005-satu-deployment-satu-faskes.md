---
status: accepted
---

# Satu deployment melayani satu Faskes

SIMGOS tetap satu modular monolith yang dapat dikonfigurasi untuk klinik atau rumah sakit, tetapi setiap deployment hanya melayani satu Faskes dengan satu database operasional dan satu set konfigurasi integrasi. Pilihan ini menggantikan eksplorasi runtime multi-faskes karena kebutuhan aktif tidak membenarkan membership, provisioning, credential, dan database switching lintas faskes; dokumen keputusan lama dipertahankan di `docs/legacy/multi-faskes/`.
