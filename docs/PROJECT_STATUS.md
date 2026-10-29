# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-30 02:20 WIB oleh sesi agen (Antigravity — Penyelesaian PRD 06.1 UI/UX Audit & Frontend Performance Assessment)

## Tahap aktif & Status Modul

- **PRD 01 (Foundation & Project Bootstrap + Audit Sumber Data):** **PASS / Selesai**.
- **PRD 02 (Database Design & API Contract):** **PASS / Selesai**.
- **PRD 03 (Backend API, Authentication & Access Control):** **PASS / Selesai**.
- **PRD 04 (Road Damage Reporting & WebGIS):** **PASS / Selesai**.
- **PRD 05 (Decision Engine AHP-TOPSIS):** **PASS / Selesai**.
- **PRD 05.1 (Bug Fix & Development Readiness):** **PASS / Selesai dan Terverifikasi** ([`docs/PRD_05_1_BUG_FIX_REPORT.md`](PRD_05_1_BUG_FIX_REPORT.md)).
- **PRD 05.2 (GeoJSON Road Segment Importer):** **PASS / Selesai dan Terverifikasi** ([`docs/PRD_05_2_GEOJSON_IMPORTER_REPORT.md`](PRD_05_2_GEOJSON_IMPORTER_REPORT.md)).
- **PRD 05.3 (Research Data Acquisition & Validation):** **PASS / Selesai dan Terverifikasi** ([`docs/PRD_05_3_RESEARCH_DATA_ACQUISITION_REPORT.md`](PRD_05_3_RESEARCH_DATA_ACQUISITION_REPORT.md)).
- **PRD 05.4 (Manual End-to-End Verification):** **PASS / Selesai dan Terverifikasi di Browser** ([`docs/PRD_05_4_MANUAL_E2E_VERIFICATION_REPORT.md`](PRD_05_4_MANUAL_E2E_VERIFICATION_REPORT.md)).
- **PRD 06.1 (UI/UX Audit & Frontend Performance Assessment):** **SELESAI / Audit Murni Observasi** ([`docs/PRD_06_1_UI_UX_AUDIT_REPORT.md`](PRD_06_1_UI_UX_AUDIT_REPORT.md)). **0 file `frontend/` atau `backend/` diubah.** 12 temuan terdokumentasi (5×P1, 6×P2, 1×P3).

## Ringkasan Verifikasi PRD 05.4 (Manual End-to-End Verification)

1. **Verifikasi End-to-End Nyata di Browser (Google Chrome via CDP) & CLI ([`docs/PRD_05_4_MANUAL_E2E_VERIFICATION_REPORT.md`](PRD_05_4_MANUAL_E2E_VERIFICATION_REPORT.md)):**
   - **Skenario A (Autentikasi & RBAC — `PASS`):** Login/logout di UI untuk `reporter`, `officer`, `expert`, dan `admin`; penolakan akun nonaktif (`inactive@roadpriority.local` menampilkan `"Account is inactive."`); pembatasan tab UI sesuai peran; serta penolakan `403` pada endpoint lintas peran.
   - **Skenario B (GeoJSON Importer & WebGIS — `PASS`):** Verifikasi `--dry-run` pada `roadpriority` (0 baris ditulis), penolakan geometri `Polygon` dan proteksi segmen demo `DEMO-SEG-001`, deteksi konflik vs `--update-existing`, serta render 3 polyline `<path class="leaflet-interactive">` pada peta Leaflet publik tanpa membocorkan segmen demo.
   - **Skenario C (Pelaporan & Asesmen Kondisi Jalan — `PASS`):** Validasi form pelaporan di browser, pengiriman 2 laporan dengan unggah foto PNG asli (`RPT-20261029-H0ZKZL` & `RPT-20261029-GXP7MF`), penolakan transisi lompat `submitted -> resolved` (`409 Conflict`), verifikasi berjenjang petugas (`submitted -> under_review -> verified`) di modal UI hingga tampil di peta publik, pencatatan asesmen lapangan `K1`–`K4` (`SDI-ADAPTED-1.0`), serta penolakan pencampuran asesmen resmi pada segmen demo (`422`).
   - **Skenario D (Kesiapan Data Keputusan — `PASS`):** Verifikasi `GET /api/decision-data-readiness` dan tabel UI Sub-tab 1: `SYNTH-SEG-001` dan `SYNTH-SEG-003` masuk `eligible_segments` (`Layak`), sedangkan `SYNTH-SEG-002` (`nearest_facility_meters = null` dan tanpa asesmen) masuk `incomplete_segments` dengan alasan `missing_or_invalid:K1, K2, K4, K5`, lengkap dengan tampilan sumber data dan protokol asesmen di UI.
   - **Skenario E (Mesin Keputusan AHP-TOPSIS — `PASS`):** Penolakan aktivasi matriks AHP tidak konsisten ($\text{CR} = 0{,}3123 > 0{,}10$ ditolak `422`), persetujuan matriks konsisten ($\text{CR} = 0{,}0064 \le 0{,}10 \to$ `APPROVED`), eksekusi TOPSIS resmi (`RUN-20261029-QCA0LQ`) yang mengeksklusi `SYNTH-SEG-002` dan mengisolasi `DEMO-SEG-*`, verifikasi mode simulasi (`6` ruas diperingkat), serta pembuktian bahwa pembaruan asesmen menghasilkan `input_hash` baru (`RUN-20261029-FRLIXW`) tanpa mengubah snapshot lama (`reproducibility_verified = true`).
2. **Perbaikan Bug cakupan PRD 05.4 (`BUG-P2-05`, `BUG-P2-06`, `BUG-P3-02`):**
   - Menampilkan metadata `source_name`, `assessment_source`, dan `assessment_protocol` pada tabel kesiapan data serta menambahkan pemilih `assessment_source` pada form asesmen di [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) (`BUG-P2-05`).
   - Menambahkan pemeriksaan pencabutan token akun nonaktif pada [`ReportAccessController::showPhoto()`](../backend/app/Http/Controllers/Api/ReportAccessController.php) beserta tes regresi otomatis di [`Prd051BugFixRegressionTest.php`](../backend/tests/Feature/Prd051BugFixRegressionTest.php) (`BUG-P2-06`).
   - Merapikan notasi matematika di [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) menggunakan elemen `<sub>`/`<sup>` dan simbol Unicode sehingga tidak menampilkan karakter `$` mentah di browser (`BUG-P3-02`).

## Ringkasan Implementasi PRD 05.3 (Research Data Acquisition & Validation)

1. **Audit Kebutuhan Data & Kamus Data Penelitian ([`docs/RESEARCH_DATA_DICTIONARY.md`](RESEARCH_DATA_DICTIONARY.md)):** Memetakan seluruh kebutuhan data (batas administratif `D-02`, geometri `LineString` & identitas ruas `D-01`/`D-03`, kewenangan jalan `D-04`, kriteria kandidat `K1`–`K7`, matriks AHP pakar `D-07`, dan metadata provenance) terhadap kolom database, aturan validasi, ketersediaan aktual, dan blocker (`BLK-01` s.d. `BLK-06`).
2. **Verifikasi Sumber Data & Lisensi ([`docs/RESEARCH_DATA_SOURCES_AND_LICENSES.md`](RESEARCH_DATA_SOURCES_AND_LICENSES.md)):** Mendokumentasikan sumber resmi pemerintah (BIG Ina-Geoportal, Bina Marga SDI `001-01/M/BM/2011`, SK Jalan Daerah) dan membedakannya secara tegas dari dataset kolaboratif terbuka (OpenStreetMap di bawah lisensi ODbL 1.0 via Geofabrik `java-latest.osm.pbf`).
3. **Protokol Operasional Pengumpulan & Validasi Data ([`docs/RESEARCH_DATA_COLLECTION_PROTOCOL.md`](RESEARCH_DATA_COLLECTION_PROTOCOL.md)):** Menyusun prosedur operasional langkah demi langkah (Prosedur A s.d. F) untuk pemotongan poligon wilayah studi di QGIS, ekstraksi `LineString` jalan, pengukuran jarak fasilitas publik (`K5`) pada proyeksi metrik `EPSG:32748`, pelaksanaan survei lapangan `SDI-ADAPTED-1.0`, elisitasi kuesioner AHP pakar ($\text{CR} \le 0{,}10$), serta pemisahan laporan warga dari asesmen teknis.
4. **Pengetatan Provenance & Isolasi Data pada Backend:**
   - Menyelaraskan `DamageAssessment::ASSESSMENT_SOURCES` (`field_survey`, `field_inspection`, `report_verification`, `official_secondary`, `public_dataset`, `demo_fixture`, `demo`) dan default `protocol_version = 'SDI-ADAPTED-1.0'` pada [`DamageAssessmentController`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php), serta memblokir pencampuran asesmen resmi pada segmen demo dan sebaliknya (`422`).
   - Memperkuat validasi `RoadSegment::NON_DEMO_DATA_ORIGINS` per-feature pada [`RoadSegmentGeoJsonImporter`](../backend/app/Services/RoadSegmentGeoJsonImporter.php) dan menyertakan metadata provenance lengkap pada output [`DecisionDataProvider::buildReadinessReport()`](../backend/app/Services/DecisionEngine/DecisionDataProvider.php).

## Ringkasan Implementasi PRD 05.2 (GeoJSON Road Segment Importer)

1. **Layanan Importer ([`RoadSegmentGeoJsonImporter`](../backend/app/Services/RoadSegmentGeoJsonImporter.php)):** Memvalidasi path file, sintaks JSON, struktur `FeatureCollection`, geometri `LineString` dengan urutan koordinat `[longitude, latitude]` dalam rentang WGS 84, kalkulasi otomatis panjang Haversine (`length_meters`) dan centroid, pemetaan eksplisit tag OSM `highway` ke `road_function`, serta pencatatan audit log.
2. **Perintah Artisan ([`ImportRoadSegmentsCommand`](../backend/app/Console/Commands/ImportRoadSegmentsCommand.php)):** Menyediakan perintah `php artisan roadpriority:import-segments {geojson_path}` dengan opsi `--dry-run`, `--update-existing`, `--allow-partial`, `--enforce-study-bbox`, `--source-name`, `--source-reference`, `--data-origin`, dan `--json`.
3. **Perlindungan Integritas Data & Isolasi Demo:**
   - Segmen hasil impor ditandai `is_demo = false` dan menolak `--data-origin=demo`.
   - Segmen demo yang sudah ada (`is_demo = true`) dilindungi penuh dan tidak pernah tertimpa atau berubah menjadi data resmi.
   - `nearest_facility_meters` (`K5`) dibiarkan `null` apabila tidak tersedia pada GeoJSON (tidak diisi angka fiktif), dan tidak ada `DamageAssessment`, bobot AHP, atau perhitungan TOPSIS otomatis yang dibuat saat impor.
   - Impor ulang file yang sama bersifat idempoten (`unchanged_count`), dan kegagalan sebagian feature membatalkan transaksi secara atomik secara default.
4. **Pengujian Otomatis ([`RoadSegmentGeoJsonImporterTest`](../backend/tests/Feature/RoadSegmentGeoJsonImporterTest.php)):** Menambahkan `11` pengujian fitur yang mencakup seluruh skenario wajib PRD 05.2 §5 dan PRD 05.3 §6, meningkatkan total pengujian backend menjadi **`129 passed`**.

## Ringkasan Perbaikan PRD 05.1

1. **`BUG-P1-01` (FIXED):** [`DatabaseSeeder::run()`](../backend/database/seeders/DatabaseSeeder.php) kini menjalankan `CandidateCriteriaSeeder` dan `LocalAdminSeeder` (non-production) secara idempoten tanpa menggandakan baris atau menimpa password admin yang sudah ada. Database development `roadpriority` telah di-seed (`1` admin, `7` kriteria `K1`–`K7`).
2. **`BUG-P1-02` (FIXED):** [`DemoSimulationSeeder`](../backend/database/seeders/DemoSimulationSeeder.php) kini menyediakan **4 segmen jalan demo** (`DEMO-SEG-001` s.d. `DEMO-SEG-004`) dengan nilai lengkap untuk `K1`–`K7`, `damage_type` sesuai enum resmi (`lubang`, `aspal_mengelupas`, `retak`, `permukaan_bergelombang`), `assessment_source = 'demo_fixture'`, serta penanda `is_demo = true` dan `data_origin = 'demo'`. Mode simulasi TOPSIS kini dapat dijalankan end-to-end tanpa error `insufficient_alternatives`, sementara mode resmi dan peta publik tetap terisolasi penuh dari data demo.
3. **`BUG-P2-01` (FIXED):** Validasi `road_function_score` (`K3`) pada [`DamageAssessmentController::store`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php) disamakan menjadi `min:1, max:4` sesuai skala resmi `K3`.
4. **`BUG-P2-02` (FIXED):** [`RoadSegmentController@index`](../backend/app/Http/Controllers/Api/RoadSegmentController.php) dan `show` kini menolak dan mencabut token akun nonaktif (`403 Account is inactive.`).
5. **`BUG-P2-03` (FIXED):** [`DamageAssessmentPolicy`](../backend/app/Policies/DamageAssessmentPolicy.php) telah dibuat dan didaftarkan di [`AuthServiceProvider`](../backend/app/Providers/AuthServiceProvider.php), serta [`DecisionCriterionPolicy::viewAny`](../backend/app/Policies/DecisionCriterionPolicy.php) diperketat untuk peran `officer`, `expert`, dan `admin` aktif.
6. **`BUG-P2-04` (FIXED):** Dropdown segmen pada [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) kini menampilkan segmen demo berlabel `[DATA DEMO]` saat mode simulasi diaktifkan tanpa menampilkannya di peta publik.

## Hasil build dan tes terakhir

| Perintah | Hasil Aktual | Tanggal |
|---|---|---|
| Verifikasi Browser E2E (Google Chrome CDP — Skenario A–E pada `roadpriority_test`) | **`B_webgis=PASS, A_auth=PASS, C_reporting_and_assessment=PASS, D_data_readiness=PASS, E_ahp_topsis=PASS`** (`0 console/page errors`) | 2026-10-30 01:47 WIB |
| `php artisan test` (pada `roadpriority_test`) | **`130 passed` (0 failed, 447 assertions)** dalam `5.07s` | 2026-10-30 01:48 WIB |
| `php artisan roadpriority:import-segments database/fixtures/synthetic_sample_segments.geojson --dry-run` | **`total_features=3, valid_features=3, rejected_features=0, created_count=3, rolled_back=TIDAK`** (0 baris ditulis ke DB `roadpriority`) | 2026-10-30 01:16 WIB |
| `python tests/Reference/ahp_topsis_reference.py` vs `tests/Fixtures/reference.json` | **`REFERENCE_JSON_MATCH: EXACT (0 differences)`** | 2026-10-30 00:25 WIB |
| `php artisan db:seed` & `DemoSimulationSeeder` (2x pada `roadpriority`) | Idempoten: `users=1`, `decision_criteria=7`, `road_segments=4` (demo), `damage_reports=4` (demo), `damage_assessments=4` (demo), `official_rows=0` | 2026-10-30 01:48 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` (14 berkas dalam `79ms`) | 2026-10-30 01:48 WIB |
| `npm run build` (`frontend/`) | `✓ built in 239ms` (`dist/assets/index-DX6Nc2jd.js` 441.61 kB, `index-BK8-Xho-.css` 24.27 kB) | 2026-10-30 01:48 WIB |

## Issue terbuka & Batasan Data

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| `ISS-01` | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (`DEC-002`) | Aman untuk pengembangan lokal; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| `ISS-02` (`BUG-P1-03`) | Rendah | File GeoJSON ruas jalan wilayah studi resmi (`D-02`, `D-03`), pengukuran lapangan (`D-07`), dan penilaian pakar AHP resmi (`D-08`) belum dimuat ke database `roadpriority` | Mekanisme importer (`php artisan roadpriority:import-segments`) telah siap dan teruji; eksekusi impor aktual tinggal dijalankan saat file GeoJSON resmi wilayah studi tersedia |

## Langkah berikutnya yang disarankan

1. Gunakan perintah `php artisan roadpriority:import-segments <path_geojson> --dry-run` terlebih dahulu saat dataset GeoJSON wilayah studi resmi sudah tersedia, kemudian jalankan tanpa `--dry-run` untuk memuat segmen resmi ke database.
2. Lanjutkan ke **PRD 06.2** (Implementasi perbaikan sistem desain, navigasi URL, konsistensi komponen, dan responsivitas berdasarkan temuan audit PRD 06.1).
3. Lanjutkan ke **PRD 06.3** (Implementasi perbaikan aksesibilitas, interaksi WebGIS choropleth, dan optimasi performa frontend).

## Dokumentasi Audit PRD 06.1 (UI/UX & Performa Frontend)

| Dokumen | Deskripsi |
| :--- | :--- |
| [`docs/PRD_06_1_UI_UX_AUDIT_REPORT.md`](PRD_06_1_UI_UX_AUDIT_REPORT.md) | Laporan utama audit: ringkasan eksekutif, ruang lingkup, distribusi temuan, keterbatasan, dan langkah selanjutnya |
| [`docs/UI_UX_AUDIT_INVENTORY.md`](UI_UX_AUDIT_INVENTORY.md) | Inventaris lengkap halaman/tab/modal, matriks akses role, komponen bersama, dan kesenjangan backend-frontend |
| [`docs/UI_UX_AUDIT_FINDINGS.md`](UI_UX_AUDIT_FINDINGS.md) | Detail setiap temuan UX-001 hingga UX-012 dengan bukti, kode sumber, dampak, dan rekomendasi |
| [`docs/UI_UX_AUDIT_PERFORMANCE.md`](UI_UX_AUDIT_PERFORMANCE.md) | Baseline performa: bundle size, Navigation Timing (N=5), Resource Timing, debounce, re-fetch, dan kontras WCAG |
| [`docs/UI_UX_AUDIT_ROADMAP.md`](UI_UX_AUDIT_ROADMAP.md) | Peta jalan perbaikan terurut dengan ketergantungan, risiko regresi, dan kriteria penerimaan terukur |
