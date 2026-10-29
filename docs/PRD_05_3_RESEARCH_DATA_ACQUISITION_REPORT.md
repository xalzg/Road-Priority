# Laporan Audit & Akuisisi Data Penelitian (`PRD_05_3_RESEARCH_DATA_ACQUISITION_REPORT.md`)

**Proyek:** RoadPriority — Sistem Informasi Pelaporan Jalan Rusak & SPK Prioritas Perbaikan (AHP-TOPSIS)  
**Tahap:** PRD 05.3 — Research Data Acquisition & Validation  
**Branch:** `prd-05-decision-engine-integration`  
**Tanggal:** 30 Oktober 2026  

---

## 1. Ringkasan Eksekutif

Tahap **PRD 05.3** melaksanakan audit menyeluruh atas kebutuhan data penelitian nyata untuk pemetaan dan penentuan prioritas perbaikan ruas jalan di wilayah studi BSD/Tangerang, memverifikasi sumber data beserta lisensinya, menyusun protokol operasional pengumpulan dan validasi data, serta memperketat penjagaan *provenance* pada backend agar data simulasi (`is_demo = true`), data dataset publik (`public_dataset`), data pemerintah (`official_government`), dan data survei lapangan (`field_survey`) tidak pernah tercampur.

> **Pernyataan Kejujuran Ilmiah (PRD 05.3 §1 & §9):**  
> Sistem perangkat lunak RoadPriority telah siap secara teknis dan lulus **129 pengujian otomatis**. Namun, **sistem belum berisi data penelitian wilayah studi BSD/Tangerang yang sesungguhnya**. Baris yang saat ini berada di dalam database development `roadpriority` adalah konfigurasi kandidat kriteria `K1`–`K7`, akun bootstrap lokal, dan 4 segmen simulasi berlabel `is_demo = true` (`DEMO-SEG-001` s.d. `DEMO-SEG-004`). Akuisisi GeoJSON jalan wilayah studi, survei kondisi jalan lapangan, dan kuesioner pakar AHP tetap harus dilakukan oleh peneliti sesuai protokol yang didokumentasikan pada tahap ini.

---

## 2. Klasifikasi Status Ketersediaan Data (PRD 05.3 §8)

### 2.1 Sudah Diverifikasi
1. **Skema & Relasi Database (12 Migration):** Tabel `administrative_areas`, `road_segments`, `damage_reports`, `report_photos`, `report_status_histories`, `damage_assessments`, `decision_criteria`, `criterion_weight_versions`, `criterion_weight_items`, `ahp_pairwise_comparisons`, `decision_runs`, `decision_run_items`, dan `audit_logs` telah mendukung seluruh kebutuhan atribut spasial, kriteria `K1`–`K7`, matriks AHP multi-pakar, dan metadata provenance tanpa memerlukan migration tambahan.
2. **Mesin Keputusan AHP-TOPSIS & Reproducibility (`AhpService`, `TopsisService`, `SensitivityService`, `SnapshotService`):** Terverifikasi 100% cocok dengan perhitungan referensi Python independen (`0 differences`), memblokir versi bobot dengan $\text{CR} > 0{,}10$ pada run resmi, menolak data demo (`demo_data_in_official_run`), dan menerapkan kebijakan data hilang `exclude_and_log` (`D-09`) tanpa imputasi nol/rata-rata.
3. **Importer GeoJSON (`RoadSegmentGeoJsonImporter` & `ImportRoadSegmentsCommand`):** Memvalidasi struktur `FeatureCollection`, geometri `LineString` WGS 84 (`[longitude, latitude]`), panjang Haversine otomatis, deduplikasi `road_reference_code`, idempotensi, proteksi segmen demo, dan transaksi atomik.
4. **URL & Lisensi Sumber Eksternal:**
   - Ina-Geoportal BIG (`https://tanahair.indonesia.go.id`) untuk batas wilayah resmi (`DS-01`).
   - Ekstrak OpenStreetMap Pulau Jawa Geofabrik (`https://download.geofabrik.de/asia/indonesia/java-latest.osm.pbf`) dan lisensi **ODbL 1.0** (`https://www.openstreetmap.org/copyright`) untuk jaringan jalan dan fasilitas publik (`DS-02`).
   - Pedoman Survei Kondisi Jalan Metode SDI Bina Marga (Manual No. `001-01/M/BM/2011` & Permen PU No. `13/PRT/M/2011`) untuk instrumen survei `K1` (`DS-05`).

### 2.2 Sudah Tersedia Secara Lokal
1. **Konfigurasi Kandidat Kriteria `K1`–`K7`** di tabel `decision_criteria` (melalui [`CandidateCriteriaSeeder.php`](../backend/database/seeders/CandidateCriteriaSeeder.php)).
2. **Kotak Pembatas Referensi Wilayah Studi BSD/Tangerang** di [`backend/config/reporting.php`](../backend/config/reporting.php#L42-L49) (`min_lat: -6.38`, `max_lat: -6.22`, `min_lng: 106.58`, `max_lng: 106.75`).
3. **Fixture Simulasi Terisolasi (`is_demo = true`)**: 4 segmen demo (`DEMO-SEG-001`..`004`) pada [`DemoSimulationSeeder.php`](../backend/database/seeders/DemoSimulationSeeder.php) dan 3 segmen contoh uji importer pada [`synthetic_sample_segments.geojson`](../backend/database/fixtures/synthetic_sample_segments.geojson).
4. **Dokumen Protokol & Kamus Data Penelitian:**
   - [`docs/RESEARCH_DATA_DICTIONARY.md`](RESEARCH_DATA_DICTIONARY.md)
   - [`docs/RESEARCH_DATA_SOURCES_AND_LICENSES.md`](RESEARCH_DATA_SOURCES_AND_LICENSES.md)
   - [`docs/RESEARCH_DATA_COLLECTION_PROTOCOL.md`](RESEARCH_DATA_COLLECTION_PROTOCOL.md)
   - [`docs/FIELD_PROTOCOL.md`](FIELD_PROTOCOL.md)

### 2.3 Perlu Diperoleh dari Pihak Eksternal
1. **Poligon Batas Administratif Resmi Wilayah Studi (`DS-01` / `BLK-01`):** Unduhan Shapefile/GeoJSON `BATAS_DESAKEL_AR` atau `BATAS_KECAMATAN_AR` dari portal Ina-Geoportal BIG setelah peneliti login menggunakan akun pribadinya.
2. **Dataset GeoJSON Ruas Jalan & Titik Fasilitas Publik Wilayah Studi (`DS-02` / `BLK-02`):** Ekstrak jalan `LineString` dan titik POI fasilitas publik dari OpenStreetMap (Geofabrik/QGIS) yang telah dipotong sesuai poligon wilayah studi dan dihitung jarak terdekatnya (`nearest_facility_meters` untuk `K5`) di QGIS pada proyeksi metrik `EPSG:32748`.

### 2.4 Perlu Dikumpulkan Melalui Survei
1. **Data Asesmen Kondisi Fisik & Kontekstual Ruas Jalan Sampel (`DS-05` / `BLK-04`):** Pengamatan lapangan pada ruas jalan sampel menggunakan protokol `SDI-ADAPTED-1.0` untuk memperoleh nilai nyata `K1` (`severity_level`), `K2` (`safety_risk_score`), `K3` (`road_function_score`), dan `K4` (`impact_score`, bila Opsi K4-a dipilih) beserta parameter fisik `measurements`.
2. **Matriks Perbandingan Berpasangan AHP Pakar (`DS-07` / `BLK-06`):** Pengisian kuesioner skala Saaty ($1\text{–}9$) oleh pakar/responden penelitian hingga memenuhi syarat konsistensi $\text{CR} \le 0{,}10$.

### 2.5 Belum Dapat Dipastikan
1. **Batas Eksak Kelurahan/Kecamatan Wilayah Studi (`D-02` / `BLK-01`):** Apakah wilayah studi mencakup 1 kecamatan penuh (mis. Kecamatan Serpong, Kota Tangerang Selatan) atau gabungan kelurahan lintas Kota Tangsel & Kab. Tangerang.
2. **Ketersediaan Dokumen SK Jalan Daerah & Status Serah Terima PSU BSD (`D-04` / `BLK-03`):** Sejauh mana daftar resmi ruas jalan Kota Tangsel/Kab. Tangerang vs jalan kawasan pengembang BSD (Sinar Mas Land) dapat diakses oleh peneliti; ruas yang belum terverifikasi wajib memakai `road_authority = 'belum_terverifikasi'`.
3. **Konfigurasi Himpunan Kriteria Aktif Final (`D-06` / `BLK-05`):** Keputusan bersama dosen pembimbing mengenai apakah `K4` memakai observasi lapangan (Opsi K4-a) atau dinonaktifkan (Opsi K4-b), serta apakah `K6` dan `K7` diaktifkan atau dinonaktifkan apabila penelitian berfokus pada survei lapangan potong-lintang.

---

## 3. Penyesuaian Teknis Kecil Hasil Audit Kode (PRD 05.3 §6)

Audit terhadap integrasi importer PRD 05.2, controller asesmen, dan endpoint kesiapan data menemukan tiga celah konsistensi metadata provenance yang dapat diselesaikan langsung tanpa mengubah skema database atau rumus AHP-TOPSIS:

1. **Keselarasan Nilai `assessment_source` dan `protocol_version` ([`DamageAssessment.php`](../backend/app/Models/DamageAssessment.php) & [`DamageAssessmentController.php`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php)):**
   - *Temuan:* Migration `2026_10_29_000007_create_damage_assessments_table.php` dan `DATA_DICTIONARY.md` menetapkan default `assessment_source = 'field_survey'`, `report_verification`, dan `protocol_version = 'SDI-ADAPTED-1.0'`, tetapi `DamageAssessmentController::store` sebelumnya hanya mengizinkan `['field_inspection', 'official_secondary', 'demo_fixture']`.
   - *Perbaikan:* Menambahkan konstanta `DamageAssessment::ASSESSMENT_SOURCES` yang mendukung seluruh kode provenance sah (`field_survey`, `field_inspection`, `report_verification`, `official_secondary`, `public_dataset`, `demo_fixture`, `demo`), menyelaraskan default `protocol_version` ke `'SDI-ADAPTED-1.0'`, serta memblokir pencampuran asesmen resmi (`is_demo = false`) pada segmen demo (`is_demo = true`) maupun sebaliknya dengan status `422`.
2. **Validasi Provenance `data_origin` Per-Feature & Deteksi Perubahan Metadata pada Importer GeoJSON ([`RoadSegment.php`](../backend/app/Models/RoadSegment.php) & [`RoadSegmentGeoJsonImporter.php`](../backend/app/Services/RoadSegmentGeoJsonImporter.php)):**
   - *Temuan:* `RoadSegmentGeoJsonImporter` sebelumnya hanya memeriksa `data_origin !== 'demo'` pada opsi CLI dan belum membandingkan perubahan `source_name`, `source_reference`, `data_origin`, serta `administrative_area_id` saat `--update-existing` dijalankan.
   - *Perbaikan:* Menambahkan konstanta `RoadSegment::NON_DEMO_DATA_ORIGINS` (`public_dataset`, `official_government`, `field_survey`, `citizen_report`), memvalidasi `data_origin` baik di level CLI maupun per-feature GeoJSON, dan memperbarui `isRecordEquivalentToSegment()` agar pembaruan atribusi sumber/provenance dengan `--update-existing` tercatat secara akurat.
3. **Keterlacakan Provenance pada Endpoint Kesiapan Data ([`DecisionDataProvider.php`](../backend/app/Services/DecisionEngine/DecisionDataProvider.php)):**
   - *Perbaikan:* Menambahkan atribut `road_function`, `road_authority`, `source_name`, `source_reference`, `assessment_source`, dan `assessment_protocol` pada setiap entri `eligible_segments` dan `incomplete_segments` di `GET /api/decision-data-readiness` tanpa mengubah hash input kanonik TOPSIS.
4. **Koreksi Dokumentasi Enum `road_authority` ([`docs/PRD_05_2_GEOJSON_IMPORTER_REPORT.md`](PRD_05_2_GEOJSON_IMPORTER_REPORT.md)):**
   - Menyelaraskan daftar nilai `road_authority` pada dokumentasi agar persis mencerminkan `RoadSegment::AUTHORITIES` (`nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi`).

---

## 4. Perintah Verifikasi & Hasil Pengujian Aktual

### 4.1 Perintah yang Dijalankan
```powershell
# 1. Pengujian otomatis backend pada database uji terisolasi (roadpriority_test)
cd c:\Users\MyBook Hype AMD\OneDrive\Documents\ML\RoadPriority\backend
C:\xampp\php\php.exe artisan test

# 2. Linter & build produksi frontend
cd c:\Users\MyBook Hype AMD\OneDrive\Documents\ML\RoadPriority\frontend
npm run lint
npm run build
```

### 4.2 Hasil Pengujian Aktual
- **`php artisan test`:** **13 test suites lulus (`129 passed`, `0 failed` dalam `4.60s`)**, termasuk pengujian baru `test_prd_05_3_provenance_isolation_and_assessment_source_alignment` pada [`RoadSegmentGeoJsonImporterTest.php`](../backend/tests/Feature/RoadSegmentGeoJsonImporterTest.php#L696-L772).
- **`npm run lint`:** **PASS (`Found 0 warnings and 0 errors` pada 14 berkas dalam `105ms`)**.
- **`npm run build`:** **PASS (`✓ built in 301ms`)**.

---

## 5. Daftar Kendala (*Blocking Issues*) & Langkah Lanjutan Konkret

| Kode Blocker | Deskripsi Kendala | Langkah Pengadaan & Penyelesaian Konkret oleh Peneliti |
|---|---|---|
| **`BLK-01` (`D-02`)** | Daftar definitif kecamatan/kelurahan wilayah studi BSD dan poligon batas resmi BIG belum dimuat. | 1. Sepakati cakupan wilayah studi bersama dosen pembimbing (mis. Kecamatan Serpong, Kota Tangsel).<br>2. Unduh shapefile `BATAS_DESAKEL_AR` dari https://tanahair.indonesia.go.id dan potong poligon wilayah studi di QGIS (`EPSG:4326`). |
| **`BLK-02` (`D-03` & `K5`)** | File GeoJSON ruas jalan resmi dan nilai pengukuran jarak fasilitas publik (`nearest_facility_meters`) belum tersedia di lokal. | 1. Unduh `java-latest.osm.pbf` dari https://download.geofabrik.de/asia/indonesia/java.html.<br>2. Ikuti **Prosedur B & D** pada [`docs/RESEARCH_DATA_COLLECTION_PROTOCOL.md`](RESEARCH_DATA_COLLECTION_PROTOCOL.md) di QGIS untuk memotong jalan, menghitung jarak fasilitas publik terdekat dalam meter (`EPSG:32748`), dan mengekspor GeoJSON.<br>3. Jalankan `php artisan roadpriority:import-segments <file.geojson> --dry-run` lalu impor tanpa `--dry-run`. |
| **`BLK-03` (`D-04`)** | Status kewenangan jalan Pemda vs pengembang BSD City belum seluruhnya terverifikasi dokumen SK. | Gunakan nilai `'belum_terverifikasi'` secara jujur untuk ruas yang belum memiliki bukti SK, atau `'kawasan_pengembang'` / `'kota'` / `'kabupaten'` hanya untuk ruas yang telah terverifikasi sumbernya. |
| **`BLK-04` (`D-08`)** | Data asesmen kondisi fisik jalan (`K1`, `K2`, `K4`) untuk ruas jalan nyata belum dikumpulkan. | Laksanakan survei lapangan pada minimal 15–30 ruas sampel mengikuti [`docs/FIELD_PROTOCOL.md`](FIELD_PROTOCOL.md) dan catat hasilnya melalui `POST /api/assessments` dengan `assessment_source = 'field_survey'`. |
| **`BLK-05` (`D-06`)** | Penetapan himpunan kriteria aktif final (`K4`, `K6`, `K7`) belum dikunci. | Konfirmasikan dengan dosen pembimbing apakah menggunakan 7 kriteria penuh, 6 kriteria (tanpa `K4`), atau 5 kriteria teknis lapangan (`K1`–`K5`), lalu sesuaikan status `is_active` kriteria sebelum kuesioner AHP disebarkan. |
| **`BLK-06` (`D-07`)** | Matriks perbandingan berpasangan AHP dari pakar nyata belum tersedia. | Setelah `BLK-05` selesai, kumpulkan kuesioner perbandingan berpasangan dari pakar/responden, hitung bobot via `POST /api/criterion-weight-versions/{id}/calculate`, pastikan $\text{CR} \le 0{,}10$, dan aktifkan versi bobot untuk eksekusi TOPSIS resmi. |

