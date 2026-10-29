# Laporan Implementasi PRD 05.2 — GeoJSON Road Segment Importer

**Proyek:** RoadPriority — Sistem Informasi Pelaporan Jalan Rusak & SPK Prioritas Perbaikan (AHP-TOPSIS)  
**Tahap:** PRD 05.2 (GeoJSON Road Segment Importer)  
**Branch:** `prd-05-decision-engine-integration`  
**Tanggal:** 30 Oktober 2026  

---

## 1. Ringkasan Hasil Audit Sebelum Implementasi

Berdasarkan audit menyeluruh terhadap skema database, model, layanan geometri, controller, dan komponen peta frontend sebelum menulis kode:

1. **Kecukupan Tabel `road_segments`:**
   Migration `backend/database/migrations/2026_10_29_000003_create_road_segments_table.php` dan model `App\Models\RoadSegment` telah menyediakan seluruh kolom yang dibutuhkan untuk menyimpan ruas jalan resmi maupun simulasi:
   - `road_reference_code` (`VARCHAR(64)`, unik) sebagai kunci bisnis stabil untuk deduplikasi.
   - `name` (`VARCHAR(191)`), `road_function` (`arteri`, `kolektor`, `lokal`, `lingkungan`), dan `road_authority` (`nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi`).
   - `geometry_geojson` (`JSON`), `centroid_latitude` (`DECIMAL(10,7)`), `centroid_longitude` (`DECIMAL(10,7)`), `length_meters` (`DECIMAL(10,2)`), dan `nearest_facility_meters` (`DECIMAL(10,2)`, nullable).
   - `source_name`, `source_reference`, `data_origin`, `is_demo` (`BOOLEAN`), dan `is_active` (`BOOLEAN`).
   - **Kesimpulan skema:** Tidak diperlukan migration baru untuk mengimplementasikan PRD 05.2.
2. **Konsistensi Geometri di Backend dan Frontend:**
   `App\Services\GeoGeometryService::distanceToLineStringMeters()` serta `frontend/src/components/WebGisMap.jsx` mengharapkan `geometry_geojson` berbentuk `{"type": "LineString", "coordinates": [[longitude, latitude], ...]}` sesuai standar RFC 7946 (WGS 84 / EPSG:4326), kemudian `WebGisMap.jsx` mengonversinya menjadi `[latitude, longitude]` khusus saat merender `<Polyline>` Leaflet. Oleh sebab itu, importer wajib mempertahankan urutan `[longitude, latitude]` dan menolak tipe geometri selain `LineString`.

---

## 2. Daftar File yang Dibuat / Diperbarui

| File | Status | Penjelasan |
|---|---|---|
| `backend/app/Services/RoadSegmentGeoJsonImporter.php` | **Baru** | Layanan inti validasi path, parsing JSON, validasi `FeatureCollection` & `LineString`, kalkulasi Haversine `length_meters` & centroid, deduplikasi, proteksi segmen demo, dan transaksi database atomik. |
| `backend/app/Console/Commands/ImportRoadSegmentsCommand.php` | **Baru** | Perintah Artisan `php artisan roadpriority:import-segments` dengan dukungan `--dry-run`, `--update-existing`, `--allow-partial`, `--enforce-study-bbox`, `--source-name`, `--source-reference`, `--data-origin`, dan `--json`. |
| `backend/database/fixtures/synthetic_sample_segments.geojson` | **Baru** | Fixture sampel kecil (`3` ruas) khusus pengujian otomatis dan demonstrasi `--dry-run`, diberi label eksplisit `_fixture_notice` bahwa file tersebut **bukan** data wilayah studi penelitian nyata. |
| `backend/tests/Feature/RoadSegmentGeoJsonImporterTest.php` | **Baru** | Pengujian otomatis komprehensif (`10` metode uji mencakup seluruh `14` skenario wajib PRD 05.2 §5). |
| `docs/PRD_05_2_GEOJSON_IMPORTER_REPORT.md` | **Baru** | Dokumentasi spesifikasi format GeoJSON, panduan CLI, hasil pengujian, dan status ketergantungan data resmi. |
| `docs/PROJECT_STATUS.md` | **Diperbarui** | Pembaruan status proyek pasca penyelesaian PRD 05.2 (`128 passed`). |

---

## 3. Spesifikasi Format GeoJSON yang Didukung

### 3.1 Struktur Dokumen
Dokumen wajib berupa JSON valid dengan root object bertipe `FeatureCollection` dan array `features` yang tidak kosong:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "road_reference_code": "KOTA-TGR-001",
        "name": "Jl. Pahlawan Seribu Segmen 1",
        "road_function": "arteri",
        "road_authority": "kota",
        "nearest_facility_meters": 150.5,
        "length_meters": null,
        "source_name": "OpenStreetMap / Dinas PUPR",
        "source_reference": "ODbL 1.0"
      },
      "geometry": {
        "type": "LineString",
        "coordinates": [
          [106.652, -6.302],
          [106.655, -6.300],
          [106.658, -6.298]
        ]
      }
    }
  ]
}
```

### 3.2 Aturan Geometri (`geometry`)
- Hanya mendukung `"type": "LineString"`. Tipe lain (`Point`, `Polygon`, `MultiLineString`, dll.) ditolak dengan pesan yang menyebutkan indeks feature `#i` dan kode ruas.
- `coordinates` wajib berisi minimal **2 titik** `[longitude, latitude]` numerik dalam rentang WGS 84:
  - `longitude` $\in [-180, 180]$
  - `latitude` $\in [-90, 90]$
- Urutan `[longitude, latitude]` dipertahankan secara konsisten di kolom `geometry_geojson`.
- Panjang segmen (`length_meters`) dihitung otomatis menggunakan rumus Haversine (`GeoGeometryService::distanceMeters()`) antar titik berurutan apabila properti `length_meters` tidak disertakan pada GeoJSON. Segmen degenerat dengan panjang $0\text{ m}$ ditolak.
- Koordinat titik tengah (`centroid_latitude`, `centroid_longitude`) dihitung otomatis dari rata-rata titik penyusun `LineString`.

### 3.3 Properti Feature (`properties`)

| Nama Properti | Status | Aturan Validasi & Perilaku |
|---|---|---|
| `road_reference_code` (atau alias `segment_code`) | **Wajib** | String/integer unik `1–64` karakter (`[A-Za-z0-9._-]+`). Tidak boleh duplikat di dalam satu file GeoJSON. |
| `name` | **Wajib** | Nama ruas jalan tidak kosong, maksimal `191` karakter. |
| `road_function` (atau tag OSM `highway`) | **Wajib** | Salah satu dari `arteri`, `kolektor`, `lokal`, `lingkungan`. Jika menggunakan hasil ekspor OSM tanpa `road_function`, tag `highway` kendaraan dipetakan secara eksplisit: `trunk`/`primary` $\rightarrow$ `arteri`, `secondary`/`tertiary` $\rightarrow$ `kolektor`, `unclassified` $\rightarrow$ `lokal`, `residential`/`living_street`/`service` $\rightarrow$ `lingkungan`. Tag non-kendaraan (`footway`, `path`, dll.) ditolak. |
| `road_authority` | Opsional | Salah satu dari `nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi`. Default: `belum_terverifikasi`. |
| `nearest_facility_meters` | Opsional | Angka $\ge 0$ (meter ke fasilitas publik terdekat untuk kriteria `K5`). **Jika tidak ada pada GeoJSON, nilai dibiarkan `null` (tidak pernah diisi angka acak/fiktif).** |
| `length_meters` | Opsional | Angka $> 0$. Jika kosong/`null`, dihitung otomatis dari panjang geometri `LineString`. |
| `administrative_area_external_code` | Opsional | Jika diisi, dicocokkan ke `administrative_areas.external_code` untuk mengisi `administrative_area_id`. |
| `source_name` & `source_reference` | Opsional | Dapat diisi per feature atau melalui argumen CLI `--source-name` dan `--source-reference`. |

---

## 4. Aturan Integritas Data, Deduplikasi, dan Perlindungan Data Demo

1. **Pemisahan Tegas Data Resmi vs Simulasi (`is_demo = false`):**
   - Seluruh ruas yang diimpor melalui `roadpriority:import-segments` ditandai `is_demo = false` dan menolak `--data-origin=demo`.
   - Apabila sebuah feature memiliki `road_reference_code` yang sama dengan segmen simulasi/demo yang sudah ada di database (`is_demo = true`), importer **menolak** feature tersebut sebagai konflik meskipun `--update-existing` diaktifkan. Data demo tidak pernah diubah menjadi data resmi secara diam-diam.
2. **Idempotensi & Deduplikasi:**
   - Mengimpor file GeoJSON yang sama berulang kali menghasilkan `created_count = 0`, `updated_count = 0`, dan `unchanged_count = N` tanpa membuat baris duplikat.
   - Apabila `road_reference_code` sudah ada sebagai segmen resmi namun atribut/geometrinya berbeda, importer menandainya sebagai konflik (`conflict_count`) kecuali flag `--update-existing` diberikan secara eksplisit.
3. **Transaksi Atomik Default:**
   - Secara default (`atomic = true`), jika terdapat satu saja feature yang gagal validasi atau mengalami konflik, seluruh transaksi dibatalkan (`rolled_back = true`) sehingga database tidak berada dalam kondisi setengah terimpor.
   - Opsi `--allow-partial` tersedia bila pengguna secara eksplisit menghendaki penyimpanan feature yang valid saja.
4. **Larangan Data Penelitian Fiktif:**
   - Importer **tidak** membuat `DamageAssessment` fiktif, **tidak** mengisi `nearest_facility_meters` dengan angka tebakan, dan **tidak** menjalankan perhitungan bobot AHP maupun peringkat TOPSIS secara otomatis.
   - Segmen hasil impor yang belum memiliki `nearest_facility_meters` atau asesmen kerusakan akan langsung terdeteksi oleh `GET /api/decision-data-readiness` pada daftar `incomplete_segments` dengan alasan eksplisit (`missing_or_invalid:K1`, `missing_or_invalid:K2`, `missing_or_invalid:K4`, `missing_or_invalid:K5`).

---

## 5. Panduan Penggunaan CLI (`roadpriority:import-segments`)

### 5.1 Menjalankan Simulasi Validasi (`--dry-run`) Tanpa Menulis ke Database
```powershell
cd backend
C:\xampp\php\php.exe artisan roadpriority:import-segments database/fixtures/synthetic_sample_segments.geojson --dry-run
```

### 5.2 Contoh Output Nyata CLI (`--dry-run`)
```text
=== RoadPriority GeoJSON Road Segment Importer (DRY-RUN (Tanpa Tulis Database)) ===
File             : C:\Users\MyBook Hype AMD\OneDrive\Documents\ML\RoadPriority\backend\database\fixtures\synthetic_sample_segments.geojson
Mode Transaksi   : Atomik (All-or-Nothing)

+-------------------+-----------------+
| Metrik Ringkasan  | Jumlah / Status |
+-------------------+-----------------+
| total_features    | 3               |
| valid_features    | 3               |
| rejected_features | 0               |
| created_count     | 3               |
| updated_count     | 0               |
| unchanged_count   | 0               |
| conflict_count    | 0               |
| rolled_back       | TIDAK           |
+-------------------+-----------------+

Mode --dry-run selesai. Tidak ada perubahan yang ditulis ke database.
```

### 5.3 Mengimpor Dataset GeoJSON Resmi Wilayah Studi (Saat File Resmi Tersedia)
```powershell
cd backend
C:\xampp\php\php.exe artisan roadpriority:import-segments storage/app/official_study_area_roads.geojson `
  --source-name="OpenStreetMap / Dinas PUPR" `
  --source-reference="ODbL 1.0" `
  --data-origin="public_dataset"
```
Gunakan tambahan `--update-existing` apabila ingin memperbarui atribut/geometri segmen resmi yang sudah pernah diimpor sebelumnya, atau `--enforce-study-bbox` untuk menolak segmen di luar kotak referensi wilayah studi.

---

## 6. Hasil Pengujian Otomatis

1. **Backend Test Suite (`php artisan test` pada `roadpriority_test`):**
   - Total suite: **13 test suites lulus (`128 passed` dalam `5.97s`)**.
   - Mencakup `10` pengujian baru pada `Tests\Feature\RoadSegmentGeoJsonImporterTest`:
     - `valid geojson imports multiple road segments accurately`
     - `corrupted json or non feature collection is rejected`
     - `unsupported geometries are rejected with feature index and reason`
     - `invalid coordinates and study bbox enforcement`
     - `missing or invalid required properties are rejected`
     - `duplicate road reference code within same file is rejected`
     - `importing same file twice is idempotent and updating requires explicit flag`
     - `mid import failure atomic rollback vs allow partial and dry run`
     - `demo segments are protected and never converted to official data`
     - `imported segments have no fictional assessments and integrate with api and readiness`
2. **Frontend Lint & Production Build:**
   - `npm run lint`: **0 warnings, 0 errors** (`14 files` dengan `oxlint`).
   - `npm run build`: **Berhasil** (`dist/assets/index-DkxKX4As.js` dibangun dalam `297ms`).

---

## 7. Ketergantungan yang Masih Menunggu Data Resmi Penelitian

Mekanisme teknis pengimporan GeoJSON telah selesai dan teruji. Untuk menghasilkan keputusan prioritas jalan resmi (`is_official_run = true`) pada laporan penelitian/skripsi, tahapan data berikut tetap bergantung pada ketersediaan data primer/sekunder resmi:

1. **`D-02` & `D-03` (Dataset GeoJSON Ruas Jalan Resmi Wilayah Studi):** Mengekspor atau memperoleh file GeoJSON `LineString` ruas jalan wilayah studi yang disetujui pembimbing/instansi, lalu mengimpornya menggunakan `php artisan roadpriority:import-segments`.
2. **`D-07` (Pengukuran Jarak Fasilitas Publik `K5` & Asesmen Teknis Lapangan `K1, K2, K4`):** Melengkapi atribut `nearest_facility_meters` serta mencatat asesmen kondisi jalan hasil survei/verifikasi petugas melalui `POST /api/assessments`.
3. **`D-08` (Matriks Perbandingan Berpasangan AHP Pakar Resmi):** Menginput penilaian skala Saaty dari pakar/responden penelitian melalui endpoint/antarmuka AHP hingga $\text{CR} \le 0{,}10$ dan menyetujuinya untuk eksekusi TOPSIS resmi.

