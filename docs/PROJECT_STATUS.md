# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-29 19:25 WIB oleh sesi agen (Antigravity)

## Tahap aktif

- **PRD 01 (Bootstrap Fondasi Proyek + Audit Sumber Data & Kelayakan Master PRD v2.0):** **selesai**, menunggu konfirmasi pengguna atas usulan keputusan terbuka (`D-02`, `D-03`, `D-04`, `D-06`, `D-07`, `D-08`, `D-09`, `D-10`) di [DECISIONS.md](DECISIONS.md).
- **PRD 05 (Mesin Keputusan Inti AHP-TOPSIS — Pure Service Layer & Verifikasi Independen):** **selesai diimplementasikan dan lulus seluruh 11 kategori tes minimum** pada cabang `prd-05-ahp-topsis-engine`. Integrasi ke tabel database (`decision_runs`, `decision_run_items`) akan dilakukan setelah skema PRD 02 dibuat.

## Ringkasan kondisi

1. Fondasi monorepo (`backend/` Laravel 9.52.22, `frontend/` React 19 + Vite 8, database MariaDB 10.4.32 `roadpriority`) berjalan normal dan terhubung melalui endpoint `GET /api/health`.
2. Seluruh dokumen keluaran audit PRD 01 (`PROJECT_AUDIT.md`, `DATA_SOURCE_REGISTER.md`, `DATA_DICTIONARY.md`, `GAP_AND_RISK_REGISTER.md`, `FIELD_PROTOCOL.md`, `LITERATURE_PLAN.md`, `DECISIONS.md`) telah dibuat berdasarkan verifikasi nyata terhadap lingkungan lokal dan kebijakan sumber data.
3. Mesin perhitungan murni AHP-TOPSIS (`AhpService`, `TopsisService`, `SensitivityService`, `SnapshotService`, `RankCorrelation`, `InputHasher`) telah dibangun di `backend/app/Services/DecisionEngine/`, diverifikasi terhadap skrip referensi independen Python (`backend/tests/Reference/ahp_topsis_reference.py` $\rightarrow$ `backend/tests/Fixtures/reference.json`), dan didokumentasikan di `docs/FORMULAS.md`.

## Hasil build dan tes terakhir

| Perintah | Hasil | Tanggal |
|---|---|---|
| `python Reference\ahp_topsis_reference.py > Fixtures\reference.json` | Sukses membuat fixture referensi independen (10.287 byte, tanpa pembulatan) | 2026-10-29 19:14 WIB |
| `php artisan test` | **`80 passed` (0 failed)** dalam `0.39s` (`AhpServiceTest`, `TopsisServiceTest`, `SensitivityServiceTest`, `SnapshotServiceTest`, `HealthEndpointTest`, `ExampleTest`) | 2026-10-29 19:19 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` | 2026-10-29 19:03 WIB |
| `npm run build` (`frontend/`) | `built in 151ms` | 2026-10-29 19:03 WIB |

## Requirement dan acceptance criteria

| ID / Butir | Status | Bukti |
|---|---|---|
| PRD 01 Bootstrap (10 acceptance criteria) | **Terpenuhi** | Tag `prd-01-done`, `HealthEndpointTest`, uji headless Chrome |
| PRD 01 Master PRD (Audit repo, sumber data, kamus data, risiko, protokol lapangan, rencana literatur) | **Terpenuhi** | `docs/PROJECT_AUDIT.md`, `docs/DATA_SOURCE_REGISTER.md`, `docs/DATA_DICTIONARY.md`, `docs/GAP_AND_RISK_REGISTER.md`, `docs/FIELD_PROTOCOL.md`, `docs/LITERATURE_PLAN.md` |
| PRD 05 Tes Minimum #1 (Matriks semua 1 $\rightarrow$ bobot sama, $CI=0$) | **Terpenuhi** | `AhpServiceTest::test_all_ones_matrix_gives_equal_weights_and_zero_ci` |
| PRD 05 Tes Minimum #2 (Matriks konsisten sempurna $a_{ij}=w_i/w_j \rightarrow CR \approx 0$) | **Terpenuhi** | `AhpServiceTest::test_perfectly_consistent_matrix_has_cr_near_zero_and_recovers_weights` |
| PRD 05 Tes Minimum #3 (Contoh Lampiran C cocok dalam toleransi $\le 10^{-6}$) | **Terpenuhi** | `AhpServiceTest::test_lampiran_c_matches_independent_reference`, `TopsisServiceTest::test_lampiran_c_matches_independent_reference`, `SensitivityServiceTest::test_equal_weights_scenario_matches_lampiran_c5` |
| PRD 05 Tes Minimum #4 (Matriks inkonsisten $CR > 0{,}10$ ditandai & ditolak pada run resmi) | **Terpenuhi** | `AhpServiceTest::test_inconsistent_matrix_is_flagged`, `AhpServiceTest::test_consistency_threshold_boundary_matches_reference`, `SnapshotServiceTest::test_official_run_rejects_unapproved_or_inconsistent_weights` |
| PRD 05 Tes Minimum #5 (Kriteria benefit & cost menghasilkan solusi ideal benar) | **Terpenuhi** | `TopsisServiceTest::test_ideal_solutions_respect_benefit_and_cost` |
| PRD 05 Tes Minimum #6 (Kolom konstan, satu alternatif, matriks kosong ditangani) | **Terpenuhi** | `TopsisServiceTest::test_constant_column_is_flagged_and_does_not_break`, `test_zero_column_sets_normalized_to_zero_and_flags_criterion`, `test_single_alternative_is_rejected`, `test_empty_alternatives_and_empty_criteria_are_rejected` |
| PRD 05 Tes Minimum #7 (Tie-breaking deterministik) | **Terpenuhi** | `TopsisServiceTest::test_tie_breaking_uses_key_criterion_then_code`, `test_tie_breaking_cost_key_criterion_prefers_smaller_value` |
| PRD 05 Tes Minimum #8 (Dua run dengan input identik menghasilkan hasil & hash identik) | **Terpenuhi** | `TopsisServiceTest::test_identical_input_gives_identical_result_and_hash`, `SnapshotServiceTest::test_snapshot_freezes_inputs_and_reproduces_identically` |
| PRD 05 Tes Minimum #9 (Membuat versi bobot baru tidak mengubah hasil run lama) | **Terpenuhi** | `TopsisServiceTest::test_new_weights_do_not_alter_previous_result`, `SnapshotServiceTest::test_creating_new_weight_version_does_not_change_old_snapshot` |
| PRD 05 Tes Minimum #10 (Invarian $\sum w_j = 1$ dan $0 \le C_i \le 1$) | **Terpenuhi** | `TopsisServiceTest::test_invariants_on_seeded_random_data` (200 run acak ber-seed tetap) |
| PRD 05 Tes Minimum #11 (Dibandingkan dengan hitungan independen pada dataset acuan) | **Terpenuhi** | `backend/tests/Reference/ahp_topsis_reference.py` & `backend/tests/Fixtures/reference.json` |
| PRD 05 Persyaratan DB & API (`criterion_weight_versions`, `decision_runs`, endpoint `/api/v1/decision-runs`) | **Belum (menunggu PRD 02 & PRD 03)** | Akan dibuat setelah skema database PRD 02 disetujui |

## Perubahan database dan endpoint terakhir

Tidak ada perubahan tabel database atau route API pada langkah ini (tetap 4 tabel migration bawaan Laravel dan endpoint `GET /api/health`).

## Issue terbuka

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| ISS-01 | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (DEC-002) | `cd backend && composer audit`; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| ISS-02 | Rendah | `ST_Distance_Sphere(POINT, LINESTRING)` menghasilkan `ERROR 1815` pada MariaDB 10.4.32 (DEC-008) | Gunakan `MBRIntersects` di SQL + perhitungan jarak titik-ke-segmen dalam meter di service layer PHP pada PRD 04 |

## Keputusan menunggu persetujuan

Lihat bagian 2 pada [docs/DECISIONS.md](DECISIONS.md):
- **D-02:** Konfirmasi cakupan administratif wilayah studi BSD (mis. Kecamatan Serpong, Kota Tangerang Selatan, atau daftar kelurahan spesifik).
- **D-03 & D-04:** Konfirmasi penggunaan geometri OSM (ODbL) + pencatatan atribut `road_authority` per ruas.
- **D-06:** Pilihan perlakuan kriteria **K4** (*Opsi K4-a*: rubrik observasi kepadatan lalu lintas 1–5 di lapangan, atau *Opsi K4-b*: keluarkan K4 sehingga memakai 6 kriteria).
- **D-07, D-08, D-09, D-10:** Konfirmasi panel pakar & agregasi *geometric mean*, draf protokol lapangan `SDI-ADAPTED-1.0`, kebijakan eksklusi data hilang, dan metodologi pengembangan/penelitian.

## Langkah berikutnya yang disarankan

1. Tinjau dan setujui usulan keputusan `D-02`, `D-03`, `D-04`, `D-06`..`D-10` di `docs/DECISIONS.md`.
2. Gabungkan cabang `prd-05-ahp-topsis-engine` atau lanjutkan ke **PRD 02 (Arsitektur, Database Migration, Model, Seeder Referensi & Demo Berlabel, dan Kontrak API `/api/v1`)**.
3. Mulai aktivitas jalur non-kode secara paralel: unduh poligon batas wilayah dari Ina-Geoportal BIG & ekstrak jalan OSM wilayah studi, serta konsultasikan `docs/FIELD_PROTOCOL.md` kepada dosen pembimbing.
