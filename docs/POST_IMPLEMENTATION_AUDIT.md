# POST-IMPLEMENTATION AUDIT — RoadPriority (PRD 01 s.d. PRD 05)

**Tanggal Audit:** 2026-10-30 WIB  
**Auditor:** Antigravity (Sesi Audit Pasca-Implementasi PRD 01–05)  
**Cabang Git Aktif:** `prd-05-decision-engine-integration` (HEAD `1e84273`)  
**Lingkungan Aktual:** Windows, XAMPP MariaDB `10.4.32-MariaDB`, PHP `8.0.30`, Composer `2.8.12`, Laravel `9.52.22`, Node.js + React `19.3.1` + Vite `8.3.5` + Leaflet `1.9.4`

---

## 1. Ringkasan Status Modul

| Area / Modul | Status Audit | Ringkasan Bukti Aktual |
|---|---|---|
| **A. Fondasi & Database (PRD 01–02)** | **PASS (dengan catatan seeding DB dev)** | Seluruh 16 migration (`Batch 1` & `Batch 2`) berstatus `Ran` pada `roadpriority` dan `roadpriority_test`; 14 tabel domain + FK `RESTRICT`/`SET NULL` konsisten; tidak ada tabel duplikat; `.env` tidak masuk Git. Namun database dev `roadpriority` saat ini masih kosong (`0` baris) karena `DatabaseSeeder::run()` belum memanggil seeder awal. |
| **B. Autentikasi & Keamanan (PRD 03)** | **PASS (dengan 2 temuan P2)** | Login, logout, `/api/me`, rate-limit login (`5/menit`), pencabutan token akun nonaktif, pencegahan IDOR laporan/foto privat, proteksi admin terakhir, dan redaksi field rahasia di `AuditLogService` lulus 16 tes otomatis (`AuthAndAccessControlTest`). Ditemukan 2 celah *defense-in-depth* minor (`P2`) pada `RoadSegmentController` dan `DamageAssessmentController`. |
| **C. Pelaporan & WebGIS (PRD 04)** | **PASS** | Form pelaporan, validasi koordinat & isi biner foto (`getimagesize`), penyimpanan privat `storage/app/report-photos/`, *orphan file cleanup*, transisi status atomik, saran segmen terdekat & kandidat duplikat (`GeoGeometryService`), serta sinkronisasi daftar–peta Leaflet + filter `bbox` lulus seluruh pengujian (`DamageReportingAndWebGisTest`). |
| **D. Mesin Keputusan AHP-TOPSIS (PRD 05)** | **PASS** | Implementasi matematis [`AhpService`](../backend/app/Services/DecisionEngine/AhpService.php), [`TopsisService`](../backend/app/Services/DecisionEngine/TopsisService.php), [`SensitivityService`](../backend/app/Services/DecisionEngine/SensitivityService.php), dan [`SnapshotService`](../backend/app/Services/DecisionEngine/SnapshotService.php) cocok **100% (0 perbedaan)** terhadap referensi independen Python ([`ahp_topsis_reference.py`](../backend/tests/Reference/ahp_topsis_reference.py)) dan Tabel Lampiran C.4; 83 tes unit + integrasi mesin keputusan lulus. |
| **E. Integrasi End-to-End & Kesiapan Data Nyata** | **BLOCKED (oleh Kesiapan Data & Seeder Dev)** | Alur end-to-end 8 langkah terbukti berjalan penuh pada database pengujian `roadpriority_test` ([`DecisionEngineIntegrationTest`](../backend/tests/Feature/DecisionEngineIntegrationTest.php)). Namun pada aplikasi yang berjalan dengan database dev `roadpriority`, alur terhenti (*blocked*) karena: (1) tabel `roadpriority` masih berisi `0` baris (`DatabaseSeeder` kosong), (2) `DemoSimulationSeeder` hanya membuat `1` segmen padahal TOPSIS mewajibkan minimal `2` segmen, dan (3) data ruas jalan resmi wilayah studi BSD belum diimpor. |

---

## 2. Bukti Pemeriksaan dan Hasil Eksekusi Pengujian

Seluruh perintah berikut dijalankan secara aktual selama audit tanpa memodifikasi atau menghapus data pada database development `roadpriority`:

| Perintah Pemeriksaan | Hasil Aktual | Status |
|---|---|---|
| `git status --short` & `git ls-files \| Select-String "\.env$"` | Tidak ada file `.env`, kunci privat, atau kredensial yang terlacak di Git. Terdapat 16 file dengan perbedaan *trailing newline* CRLF di working tree yang dipertahankan sesuai aturan audit. | **PASS** |
| `php artisan migrate:status` | 16 migration terdaftar dan seluruhnya berstatus `[1] Ran` / `[2] Ran` tanpa duplikasi tabel. | **PASS** |
| `php artisan route:list --path=api` | 29 rute API terdaftar (`health`, `login`, `logout`, `me`, `users`, `audit-logs`, `reports`, `reports/{id}/status`, `reports/{id}/photos/{photo}`, `road-segments`, `assessments`, `criteria`, `criterion-weight-versions`, `decision-data-readiness`, `decision-runs`). | **PASS** |
| `php artisan test` (menggunakan `roadpriority_test`) | **`113 passed` (0 failed)** dalam `5.77s` (`AhpServiceTest`: 32, `TopsisServiceTest`: 32, `SensitivityServiceTest`: 7, `SnapshotServiceTest`: 5, `ExampleTest`: 2, `AuthAndAccessControlTest`: 16, `DamageReportingAndWebGisTest`: 6, `DatabaseSchemaIntegrityTest`: 8, `DecisionEngineIntegrationTest`: 3, `HealthEndpointTest`: 2). | **PASS** |
| `python tests/Reference/ahp_topsis_reference.py` vs `tests/Fixtures/reference.json` | `REFERENCE_JSON_MATCH: EXACT (0 differences)` — hasil solver polinomial karakteristik & eliminasi Gauss di Python identik dengan `reference.json` dan cocok hingga toleransi $< 10^{-6}$ dengan power iteration PHP. | **PASS** |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors. Finished in 124ms on 14 files with 104 rules`. | **PASS** |
| `npm run build` (`frontend/`) | `✓ built in 358ms` (`dist/assets/index-6THUM4F_.js` 439.10 kB, `dist/assets/index-BK8-Xho-.css` 24.27 kB). | **PASS** |
| Query Read-Only `SELECT COUNT(*)` pada DB `roadpriority` | `users=0`, `administrative_areas=0`, `road_segments=0`, `damage_reports=0`, `damage_assessments=0`, `decision_criteria=0`, `criterion_weight_versions=0`, `decision_runs=0`. | **TEMUAN AUDIT** |

---

## 3. Audit Mendalam per Area (A s.d. E)

### Area A — Fondasi dan Database
1. **Kesiapan Aplikasi & Koneksi MySQL:** Backend Laravel 9.52.22 dan Frontend React 19.3.1 + Vite 8.3.5 terkonfigurasi dengan benar; proxy Vite `/api` mengarah ke `http://127.0.0.1:8000`; MariaDB 10.4.32 berjalan pada port `3306`.
2. **Konsistensi Migration & Foreign Key:**
   - Seluruh 14 tabel inti memiliki primary key dan foreign key eksplisit.
   - Aturan penghapusan historis (`ON DELETE RESTRICT` pada `report_status_histories.damage_report_id`, `damage_assessments.road_segment_id`, `decision_runs.criterion_weight_version_id`, `decision_run_items.road_segment_id`, dan `ON DELETE SET NULL` pada FK aktor `users`) terverifikasi oleh `DatabaseSchemaIntegrityTest::test_deletion_rules_protect_historical_records_and_preserve_audit_trail`.
3. **Kerapian Repository:** Tidak ada migration ganda atau tabel yatim. File `.env` diabaikan oleh `.gitignore`.

### Area B — Autentikasi dan Keamanan
1. **Autentikasi Sanctum & Masa Berlaku Token:** Login menerbitkan *Personal Access Token* Sanctum dengan masa berlaku `480` menit (`config/sanctum.php`), mencabut token saat logout atau saat akun dinonaktifkan (`EnsureActiveUser` + `UserAccountService`).
2. **Otorisasi & Pencegahan IDOR:**
   - Pelapor tidak dapat memverifikasi laporannya sendiri (`403 Forbidden` pada `PATCH /api/reports/{id}/status`).
   - Pelapor tidak dapat membaca laporan `submitted` milik pelapor lain (`403 Forbidden`) maupun mengunduh foto non-publik milik pelapor lain (`403 Forbidden` pada `GET /api/reports/{id}/photos/{photo}`).
   - Pengunjung publik hanya menerima laporan berstatus `verified`/`resolved` dengan `is_demo = false`, dan `reporter_id`, `verified_by`, serta `verification_notes` disembunyikan (`null`).
3. **Audit Log & Penanganan Error:** `AuditLogService` menyensor kunci sensitif (`password`, `token`, `secret`, `authorization`) menjadi `[REDACTED]`. `Handler::register()` menangkap `Throwable` tak terduga pada rute `api/*` dan mengembalikan pesan generik `500` tanpa membocorkan SQL atau *stack trace*.

### Area C — Pelaporan dan WebGIS
1. **Integrasi Form & Validasi Koordinat:** [`ReportForm.jsx`](../frontend/src/components/ReportForm.jsx) mengirim `FormData` ke `POST /api/reports`. Urutan koordinat konsisten: kolom tabel menggunakan `latitude` $[-90, 90]$ dan `longitude` $[-180, 180]$, GeoJSON menggunakan `[longitude, latitude]` sesuai standar RFC 7946, dan [`WebGisMap.jsx`](../frontend/src/components/WebGisMap.jsx) membalik `[lng, lat]` menjadi `[lat, lng]` untuk Leaflet `Polyline`.
2. **Penyimpanan Foto & Transaksi Atomik:** [`DamageReportService::createReport`](../backend/app/Services/DamageReportService.php) memvalidasi isi biner gambar dengan `getimagesize()`, menyimpan ke `storage/app/report-photos/` (disk privat `local`), dan menghapus file yatim pada blok `catch (Throwable $e)` apabila transaksi database gagal.
3. **Sumber Marker Peta:** [`HomePage.jsx`](../frontend/src/pages/HomePage.jsx) mengambil marker laporan dari `GET /api/reports` dan geometri ruas dari `GET /api/road-segments` (bukan array statis di kode frontend).

### Area D — Mesin Keputusan AHP-TOPSIS
1. **Kebenaran Matematis:**
   - **AHP ([`AhpService`](../backend/app/Services/DecisionEngine/AhpService.php)):** Menggunakan *principal right eigenvector* (iterasi pangkat), menguji $\lambda_{\max}$, $CI = \frac{\lambda_{\max}-n}{n-1}$, dan $CR = \frac{CI}{RI_n}$, mendukung rata-rata geometrik multi-pakar, serta memblokir aktivasi bila $CR > 0{,}10$.
   - **TOPSIS ([`TopsisService`](../backend/app/Services/DecisionEngine/TopsisService.php)):** Menghitung normalisasi Euclidean $r_{ij}$, pembobotan $v_{ij} = w_j r_{ij}$, solusi ideal $A^+/A^-$ yang membedakan secara tepat kriteria `benefit` ($\max/\min$) dan `cost` ($\min/\max$), jarak $D_i^+/D_i^-$, dan skor preferensi $C_i = \frac{D_i^-}{D_i^+ + D_i^-}$.
2. **Kasus Batas & Data Hilang:**
   - Kolom bernilai nol seluruhnya menghasilkan $r_{ij} = 0$ tanpa pembagian nol (`zero_norm_criteria`).
   - Kolom konstan ditandai pada `flags.constant_criteria`.
   - Alternatif identik ($D_i^+ + D_i^- = 0$) menghasilkan $C_i = 0$ tanpa `NaN` (`flags.non_discriminative = true`).
   - Nilai kosong (`null`) atau di luar rentang $[x_{\min}, x_{\max}]$ tidak diimputasi dengan nol, melainkan dikeluarkan ke `excluded_items` dengan kode `missing_or_invalid:{kode}` atau `out_of_range:{kode}`.
   - Peringkat seri (*tie-breaking*) dipecahkan secara deterministik menggunakan kriteria berbobot tertinggi lalu urutan kode segmen.
3. **Immutability Snapshot Historis:** Diuji pada `DecisionEngineIntegrationTest::test_historical_snapshot_reproducibility_and_criterion_immutability`: pembaruan `DamageAssessment` setelah decision run dieksekusi tidak mengubah `input_hash`, skor $C_i$, maupun urutan peringkat historis (`reproducibility_verified = true`).

### Area E — Telusur Integrasi End-to-End (8 Langkah)

| Langkah Alur End-to-End | Status pada `roadpriority_test` (Otomatis) | Status pada DB Dev `roadpriority` (Aplikasi Berjalan) | Keterangan / Bukti |
|---|---|---|---|
| 1. Pengguna membuat laporan | **PASS** | **BLOCKED (DB kosong)** | Terbukti di `DamageReportingAndWebGisTest`; di DB dev butuh eksekusi seeder akun terlebih dahulu. |
| 2. Laporan tersimpan di MySQL | **PASS** | **PASS (Skema siap)** | Tabel `damage_reports`, `report_photos`, `report_status_histories` siap di MySQL. |
| 3. Petugas memeriksa & memverifikasi laporan | **PASS** | **BLOCKED (DB kosong)** | `PATCH /api/reports/{id}/status` + modal [`ReportDetailModal.jsx`](../frontend/src/components/ReportDetailModal.jsx) berfungsi penuh. |
| 4. Laporan yang memenuhi aturan muncul di WebGIS | **PASS** | **PASS (Kode siap)** | `GET /api/reports` menyaring `status IN ('verified','resolved')` & `is_demo = false` untuk publik. |
| 5. Data asesmen terhubung ke segmen jalan | **PASS** | **BLOCKED (0 segmen)** | `POST /api/assessments` menyimpan `DamageAssessment` ke `road_segment_id`, namun tabel `road_segments` di DB dev masih `0` baris. |
| 6. Pengguna berwenang menjalankan AHP-TOPSIS | **PASS** | **BLOCKED (< 2 segmen)** | `POST /api/decision-runs` mewajibkan minimal `2` segmen jalan layak (`TopsisService::MIN_ALTERNATIVES = 2`). |
| 7. Hasil ranking tersimpan & dapat dibuka kembali | **PASS** | **PASS (Kode siap)** | `GET /api/decision-runs/{id}` menampilkan matriks $X, R, V, A^+, A^-, D_i^+, D_i^-, C_i$ di [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx). |
| 8. Hasil lama tetap konsisten setelah data sumber berubah | **PASS** | **PASS (Terbukti)** | `SnapshotService::reproduce()` memverifikasi kesamaan `input_hash` dan seluruh skor hingga $10^{-12}$. |

---

## 4. Daftar Bug dan Temuan Audit Berdasarkan Prioritas (P0 – P3)

> **Catatan:** Tidak ditemukan bug **P0** (kerusakan rumus matematis AHP-TOPSIS, korupsi data, atau kebocoran kredensial). Namun terdapat **3 temuan P1** yang menghambat pengujian manual *end-to-end* langsung pada database development `roadpriority` serta **4 temuan P2** dan **1 temuan P3**.

### Prioritas P1 (Blocker Alur Manual End-to-End pada Lingkungan Development)

1. **`BUG-P1-01` — [`DatabaseSeeder::run()`](../backend/database/seeders/DatabaseSeeder.php#L15-L18) Kosong dan Database Development `roadpriority` Belum Di-seed:**
   - **Lokasi:** [`backend/database/seeders/DatabaseSeeder.php`](../backend/database/seeders/DatabaseSeeder.php#L15-L18)
   - **Deskripsi:** Method `run()` pada `DatabaseSeeder` masih kosong, dan seeder belum dijalankan pada database `roadpriority`. Akibatnya tabel `users` dan `decision_criteria` pada database development `roadpriority` memiliki `0` baris sehingga pengguna belum dapat login atau melihat kriteria tanpa menjalankan perintah seeder satu per satu.
2. **`BUG-P1-02` — [`DemoSimulationSeeder`](../backend/database/seeders/DemoSimulationSeeder.php#L38-L103) Hanya Membuat 1 Segmen Jalan (`DEMO-SEG-001`) sehingga Gagal Memenuhi Syarat Minimum TOPSIS (`m >= 2`):**
   - **Lokasi:** [`backend/database/seeders/DemoSimulationSeeder.php`](../backend/database/seeders/DemoSimulationSeeder.php#L38-L103) vs [`TopsisService::MIN_ALTERNATIVES`](../backend/app/Services/DecisionEngine/TopsisService.php#L17)
   - **Deskripsi:** `TopsisService` secara tepat mewajibkan minimal `2` alternatif layak (`MIN_ALTERNATIVES = 2`). Namun `DemoSimulationSeeder` hanya membuat `1` segmen jalan (`DEMO-SEG-001`), sehingga bahkan pada **Mode Simulasi** (`is_simulation = true`), eksekusi `POST /api/decision-runs` akan gagal dengan `422 Unprocessable Entity` (`insufficient_alternatives`). Selain itu, `DemoSimulationSeeder` memakai `'damage_type' => 'pothole'` (baris 66) yang tidak termasuk dalam enum Bahasa Indonesia `DamageReport::DAMAGE_TYPES` (`'lubang'`, `'retak'`, dst.), serta `'assessment_source' => 'demo'` (baris 100) yang berbeda dari enum validasi controller (`'demo_fixture'`).
3. **`BUG-P1-03` — Belum Ada Endpoint API atau Perintah CLI Impor `road_segments` Resmi Wilayah Studi:**
   - **Lokasi:** [`backend/routes/api.php`](../backend/routes/api.php#L31-L32), [`RoadSegmentController`](../backend/app/Http/Controllers/Api/RoadSegmentController.php)
   - **Deskripsi:** Saat ini hanya tersedia `GET /api/road-segments` dan `GET /api/road-segments/{segmentIdentifier}`. Belum ada endpoint `POST /api/road-segments` / `PATCH /api/road-segments/{id}` maupun perintah Artisan pengimpor GeoJSON untuk memasukkan ruas jalan resmi (`is_demo = false`) beserta atribut `nearest_facility_meters` (`K5`) ke database development/produksi.

### Prioritas P2 (Ketidaksesuaian Validasi, Otorisasi Defense-in-Depth, & Integrasi UI)

4. **`BUG-P2-01` — Ketidaksesuaian Batas Maksimum `road_function_score` (`K3`) pada [`DamageAssessmentController::store`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php#L56):**
   - **Lokasi:** [`backend/app/Http/Controllers/Api/DamageAssessmentController.php`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php#L56) vs [`CandidateCriteriaSeeder.php`](../backend/database/seeders/CandidateCriteriaSeeder.php#L44-L55)
   - **Deskripsi:** Kriteria `K3` (*Fungsi jalan*) didefinisikan pada skala `1–4` (`minimum_value = 1, maximum_value = 4`). Namun `DamageAssessmentController::store` memvalidasi `'road_function_score' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5']`. Jika petugas mengirim nilai `5` lewat API, data tersimpan di `damage_assessments`, tetapi saat TOPSIS dijalankan segmen tersebut akan dikeluarkan dengan alasan `out_of_range:K3`.
5. **`BUG-P2-02` — [`RoadSegmentController::index`](../backend/app/Http/Controllers/Api/RoadSegmentController.php#L28-L30) dan [`show`](../backend/app/Http/Controllers/Api/RoadSegmentController.php#L125-L127) Tidak Memeriksa `$viewer->is_active` Saat Mengizinkan `include_demo=1`:**
   - **Lokasi:** [`backend/app/Http/Controllers/Api/RoadSegmentController.php`](../backend/app/Http/Controllers/Api/RoadSegmentController.php#L28-L30)
   - **Deskripsi:** Karena rute `/api/road-segments` berada di luar middleware `active.user` (agar dapat diakses publik), resolusi pengguna menggunakan `$request->user('sanctum')`. Berbeda dengan `ReportAccessController`, `RoadSegmentController` langsung memeriksa `$viewer->hasRole(...)` tanpa memeriksa `$viewer->is_active`.
6. **`BUG-P2-03` — Belum Ada `DamageAssessmentPolicy` dan `DecisionCriterionPolicy::viewAny` Terlalu Permisif:**
   - **Lokasi:** [`backend/app/Http/Controllers/Api/DamageAssessmentController.php`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php), [`backend/app/Policies/DecisionCriterionPolicy.php`](../backend/app/Policies/DecisionCriterionPolicy.php#L10-L18)
   - **Deskripsi:** Otorisasi pada `/api/assessments` dan `/api/criteria` saat ini sudah terlindungi oleh middleware rute `role:officer,expert,admin` dan `role:officer,admin` di `routes/api.php`, tetapi `DamageAssessmentController` belum memanggil `$this->authorize(...)` dengan Policy khusus, dan `DecisionCriterionPolicy::viewAny` hanya memeriksa `$actor->is_active` tanpa memeriksa peran.
7. **`BUG-P2-04` — Dropdown Segmen Jalan pada [`DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx#L439-L445) Tidak Memuat Segmen Demo Saat Mode Demo Diaktifkan:**
   - **Lokasi:** [`frontend/src/pages/HomePage.jsx`](../frontend/src/pages/HomePage.jsx#L90) & [`frontend/src/components/DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx#L439-L445)
   - **Deskripsi:** `HomePage.jsx` memanggil `fetchRoadSegments({ per_page: 100 })` tanpa `include_demo: 1`, sehingga daftar `roadSegments` yang diteruskan ke `DecisionEnginePanel.jsx` tidak menyertakan segmen berlabel `[DATA DEMO]`.

### Prioritas P3 (Perbaikan Minor / Kosmetik)

8. **`BUG-P3-01` — Perbedaan Baris Baru Akhir File (CRLF) pada 16 File di Working Tree Git:**
   - **Lokasi:** Working tree cabang `prd-05-decision-engine-integration`
   - **Deskripsi:** `git diff --ignore-space-at-eol` menunjukkan 1 line-ending tambahan pada 16 file akibat normalisasi CRLF Windows.

---

## 5. Ringkasan Temuan Keamanan

1. **Kredensial & Rahasia Lingkungan (`PASS`):** File `backend/.env` tidak masuk ke indeks Git (`git ls-files` bersih).
2. **Keamanan Paket Pihak Ketiga (`RISIKO DITERIMA — DEC-002`):** Karena proyek berjalan di PHP `8.0.30` dan Laravel `9.52.22` (keputusan pengguna pada `DEC-002`), terdapat 19 advisory keamanan historis pada `composer audit`. Aman untuk lingkungan lokal skripsi, namun **dilarang** dideploy ke internet publik sebelum upgrade ke PHP $\ge 8.2$ dan Laravel $\ge 10/11$.
3. **Kontrol Akses & Privasi Data (`PASS` dengan catatan `BUG-P2-02` & `BUG-P2-03`):** Seluruh pengujian IDOR laporan, privasi foto bukti di disk `local`, larangan verifikasi mandiri oleh pelapor, dan sanitasi log audit lulus pada `AuthAndAccessControlTest` dan `DamageReportingAndWebGisTest`.

---

## 6. Status Kesiapan Data Penelitian vs Fitur yang Masih Menggunakan Data Dummy/Fixture

| Komponen / Dataset | Status Saat Ini | Apakah Menggunakan Data Dummy/Fixture? | Keterangan Jujur |
|---|---|---|---|
| **Kriteria Keputusan (`K1`–`K7`)** | **Kandidat Siap (`CandidateCriteriaSeeder`)** | Tidak (menggunakan definisi kandidat Master PRD §5.1) | Menunggu penguncian keputusan `D-06` (apakah `K4` dipertahankan atau dinonaktifkan) dan eksekusi seeder pada DB `roadpriority`. |
| **Matriks Perbandingan AHP & Bobot Kriteria** | **Belum Ada Data Pakar Nyata (`D-07`)** | **Ya, di dalam Test (`Lampiran C`)** | Unit/Feature test menggunakan matriks ilustratif Lampiran C. Data kuesioner pakar nyata (`D-07`) belum diinput ke database `roadpriority`. |
| **Batas Wilayah Studi (`administrative_areas`)** | **Menggunakan Kotak Pembatas Referensi (`config/reporting.php`)** | **Ya (`DemoSimulationSeeder` untuk demo)** | Poligon resmi BIG (`BATAS_KECAMATAN_AR` / `BATAS_DESAKEL_AR` untuk wilayah studi BSD, `D-02`) belum diimpor ke tabel `administrative_areas`. Validasi lokasi saat ini menggunakan *bounding box* referensi BSD (`[-6.38..-6.22, 106.58..106.75]`) dengan penanda metode `reference_bbox_fallback`. |
| **Geometri Ruas Jalan (`road_segments`) & Jarak Fasilitas (`K5`)** | **Belum Diimpor ke DB `roadpriority` (`D-03`)** | **Ya (`DemoSimulationSeeder` & Test Fixture)** | Ekstrak jalan OpenStreetMap wilayah studi BSD beserta jarak ke fasilitas publik (`nearest_facility_meters`) belum diimpor ke database `roadpriority`. |
| **Laporan Kerusakan & Asesmen Kondisi Fisik (`damage_reports`, `damage_assessments`)** | **Skema & Modul Siap, Data Lapangan Kosong (`0` baris)** | **Ya (`DemoSimulationSeeder` & Test Fixture)** | Seluruh data di `DemoSimulationSeeder` dilabeli `is_demo = true` dan `data_origin = 'demo'`, serta diblokir secara otomatis dari tampilan publik maupun perhitungan TOPSIS resmi. |

---

## 7. Rekomendasi Pekerjaan Lanjutan Berdasarkan Urutan Dependensi

Sebelum masuk ke pengembangan fitur visualisasi lanjutan (PRD 06–08), kerjakan perbaikan dan penyiapan data berikut secara berurutan:

1. **Tahap 1 — Perbaikan Blocker Seeder & Konsistensi Validasi (`BUG-P1-01`, `BUG-P1-02`, `BUG-P2-01`, `BUG-P2-02`, `BUG-P2-03`, `BUG-P2-04`):**
   - Hubungkan `LocalAdminSeeder` dan `CandidateCriteriaSeeder` ke dalam [`DatabaseSeeder::run()`](../backend/database/seeders/DatabaseSeeder.php) dan jalankan pada database `roadpriority`.
   - Perbarui [`DemoSimulationSeeder`](../backend/database/seeders/DemoSimulationSeeder.php) agar menyediakan minimal `3–4` segmen jalan simulasi (`DEMO-SEG-001` s.d. `DEMO-SEG-004`) dengan nilai `damage_type` dan `assessment_source` yang sesuai enum resmi, sehingga simulasi end-to-end AHP-TOPSIS dapat langsung diuji melalui UI browser.
   - Samakan batas validasi `road_function_score` pada [`DamageAssessmentController::store`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php#L56) menjadi `min:1, max:4`, tambahkan pengecekan `$viewer->is_active` pada [`RoadSegmentController`](../backend/app/Http/Controllers/Api/RoadSegmentController.php), dan lengkapi `DamageAssessmentPolicy`.
2. **Tahap 2 — Penyediaan Mekanisme Impor / CRUD Segmen Jalan (`BUG-P1-03`):**
   - Tambahkan perintah Artisan (mis. `php artisan roadpriority:import-segments {geojson_path}`) atau endpoint admin untuk mengimpor ruas jalan wilayah studi (`road_segments`) beserta `nearest_facility_meters` (`K5`).
3. **Tahap 3 — Akuisisi & Impor Data Penelitian Nyata (`D-02`, `D-03`, `D-06`, `D-07`, `D-08`):**
   - Impor poligon batas wilayah studi resmi BIG (`administrative_areas`) dan segmen jalan OSM wilayah studi BSD (`road_segments`).
   - Masukkan hasil kuesioner perbandingan berpasangan pakar nyata (`D-07`) dan hasil survei/asesmen kondisi fisik ruas jalan (`D-08`).

---

## 8. Kesimpulan Akhir Audit

**Kesimpulan:** Proyek **harus memperbaiki blocker P1 (`BUG-P1-01`, `BUG-P1-02`, `BUG-P1-03`) dan temuan P2 terlebih dahulu** sebelum dinyatakan siap untuk demonstrasi end-to-end penuh pada database development `roadpriority` atau masuk ke tahap integrasi lanjutan (PRD 06). Secara arsitektur, keamanan inti, dan kebenaran matematis AHP-TOPSIS, fondasi kode pada PRD 01–05 **telah lulus seluruh 113 pengujian otomatis** dan siap dilanjutkan segera setelah blocker seeder/impor segmen tersebut diselesaikan.

