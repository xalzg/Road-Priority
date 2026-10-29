# Laporan Perbaikan Bug & Kesiapan Development — PRD 05.1 (`PRD_05_1_BUG_FIX_REPORT.md`)

**Tanggal:** 2026-10-30 WIB  
**Cabang Git:** `prd-05-decision-engine-integration`  
**Lingkungan:** Windows, XAMPP MariaDB `10.4.32-MariaDB`, PHP `8.0.30`, Laravel `9.52.22`, React `19.3.1` + Vite `8.3.5`

---

## 1. Ringkasan Temuan yang Diperbaiki dan Status Setiap BUG-ID

| BUG-ID | Prioritas | Deskripsi Temuan Audit | Tindakan Perbaikan pada PRD 05.1 | Status Akhir |
|---|---|---|---|---|
| **`BUG-P1-01`** | **P1** | `DatabaseSeeder::run()` kosong dan database development `roadpriority` belum memiliki kriteria maupun akun admin awal. | Menghubungkan [`CandidateCriteriaSeeder`](../backend/database/seeders/CandidateCriteriaSeeder.php) dan (untuk non-production) [`LocalAdminSeeder`](../backend/database/seeders/LocalAdminSeeder.php) ke dalam [`DatabaseSeeder::run()`](../backend/database/seeders/DatabaseSeeder.php), serta membuat `LocalAdminSeeder` idempoten (tidak menggandakan akun dan tidak menimpa password admin yang sudah ada bila `INITIAL_ADMIN_PASSWORD` kosong). | **FIXED & VERIFIED** |
| **`BUG-P1-02`** | **P1** | `DemoSimulationSeeder` hanya membuat `1` segmen (`DEMO-SEG-001`) sehingga simulasi TOPSIS gagal karena syarat minimum `m >= 2`, serta memakai nilai `damage_type` dan `assessment_source` di luar enum validasi. | Memperbarui [`DemoSimulationSeeder`](../backend/database/seeders/DemoSimulationSeeder.php) agar menyediakan **4 segmen jalan demo** (`DEMO-SEG-001` s.d. `DEMO-SEG-004`) dengan nilai lengkap dan valid untuk `K1`–`K7`, memakai `DamageReport::DAMAGE_TYPES` resmi (`lubang`, `aspal_mengelupas`, `retak`, `permukaan_bergelombang`), `assessment_source = 'demo_fixture'`, serta penanda `is_demo = true` dan `data_origin = 'demo'`. | **FIXED & VERIFIED** |
| **`BUG-P1-03`** | **P1** | Belum tersedia fitur impor GeoJSON / CRUD segmen jalan resmi wilayah studi (`is_demo = false`). | Dicatat sebagai kebutuhan fitur akusisi data penelitian tersendiri (di luar cakupan perbaikan bug PRD 05.1 agar tidak menambah fitur/skema baru tanpa data sumber resmi `D-02`/`D-03`). Mode simulasi kini telah bekerja penuh dengan 4 segmen demo. | **DOCUMENTED (Menunggu Data Resmi D-02/D-03)** |
| **`BUG-P2-01`** | **P2** | `DamageAssessmentController::store` memvalidasi `road_function_score` (`K3`) dengan `min:1, max:5`, padahal definisi resmi `K3` adalah skala `1–4`. | Menyamakan aturan validasi `road_function_score` pada [`DamageAssessmentController::store`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php#L58) menjadi `['sometimes', 'nullable', 'numeric', 'min:1', 'max:4']` agar selaras dengan `CandidateCriteriaSeeder` (`K3: 1–4`), `DecisionEnginePanel.jsx`, dan `TopsisService`. | **FIXED & VERIFIED** |
| **`BUG-P2-02`** | **P2** | `RoadSegmentController@index` dan `show` tidak memeriksa `$viewer->is_active` saat mengevaluasi akses `include_demo=1`. | Menambahkan pemeriksaan `$viewer !== null && ! $viewer->is_active` pada [`RoadSegmentController::index`](../backend/app/Http/Controllers/Api/RoadSegmentController.php#L30-L38) dan [`show`](../backend/app/Http/Controllers/Api/RoadSegmentController.php#L140-L148) yang langsung mencabut token (`$viewer->tokens()->delete()`) dan mengembalikan `403 Forbidden` (`Account is inactive.`), sembari mempertahankan akses publik untuk segmen non-demo. | **FIXED & VERIFIED** |
| **`BUG-P2-03`** | **P2** | `DamageAssessmentController` belum memiliki Policy khusus dan `DecisionCriterionPolicy::viewAny` terlalu permisif (`$actor->is_active` saja). | Membuat [`DamageAssessmentPolicy`](../backend/app/Policies/DamageAssessmentPolicy.php), mendaftarkannya di [`AuthServiceProvider`](../backend/app/Providers/AuthServiceProvider.php), memanggil `$this->authorize(...)` di [`DamageAssessmentController`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php), dan memperketat [`DecisionCriterionPolicy::viewAny`](../backend/app/Policies/DecisionCriterionPolicy.php#L10-L18) & `view` menjadi `officer`, `expert`, dan `admin` aktif. | **FIXED & VERIFIED** |
| **`BUG-P2-04`** | **P2** | Dropdown segmen jalan pada form asesmen di `DecisionEnginePanel.jsx` tidak menampilkan segmen demo saat mode simulasi diaktifkan. | Memperbarui [`DecisionEnginePanel.jsx`](../backend/../../frontend/src/components/DecisionEnginePanel.jsx#L97-L112) agar menyusun opsi dropdown segmen dari hasil `readiness` (yang menghormati toggle `includeDemo`) dengan awalan label eksplisit `[DATA DEMO]`, sementara [`HomePage.jsx`](../frontend/src/pages/HomePage.jsx) tetap hanya memuat segmen resmi (`is_demo = false`) untuk peta publik. | **FIXED & VERIFIED** |
| **`BUG-P3-01`** | **P3** | Perbedaan *trailing newline* CRLF pada 16 file di working tree. | Dipertahankan sesuai aturan wajib PRD 05.1 §3.9 (tidak melakukan normalisasi line-ending massal). | **PRESERVED BY RULE** |

---

## 2. Daftar File yang Diubah atau Ditambahkan

1. [`backend/database/seeders/DatabaseSeeder.php`](../backend/database/seeders/DatabaseSeeder.php) — menghubungkan `CandidateCriteriaSeeder` dan `LocalAdminSeeder` (khusus non-production).
2. [`backend/database/seeders/LocalAdminSeeder.php`](../backend/database/seeders/LocalAdminSeeder.php) — menjaga idempotensi akun admin lokal dan mencegah penimpaan password eksisting saat dijalankan ulang tanpa `INITIAL_ADMIN_PASSWORD`.
3. [`backend/database/seeders/DemoSimulationSeeder.php`](../backend/database/seeders/DemoSimulationSeeder.php) — memperluas data simulasi menjadi 4 segmen (`DEMO-SEG-001` s.d. `DEMO-SEG-004`) dengan nilai `K1`–`K7` lengkap, `damage_type` sesuai enum resmi, dan `assessment_source = 'demo_fixture'`.
4. [`backend/app/Http/Controllers/Api/DamageAssessmentController.php`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php) — menambahkan `$this->authorize(...)` dan memperbaiki batas validasi `road_function_score` menjadi `min:1, max:4`.
5. [`backend/app/Policies/DamageAssessmentPolicy.php`](../backend/app/Policies/DamageAssessmentPolicy.php) *(baru)* — kebijakan otorisasi untuk pembacaan (`officer`, `expert`, `admin`) dan pembuatan (`officer`, `admin`) penilaian kondisi jalan.
6. [`backend/app/Policies/DecisionCriterionPolicy.php`](../backend/app/Policies/DecisionCriterionPolicy.php) — membatasi `viewAny` dan `view` hanya untuk `officer`, `expert`, dan `admin` aktif.
7. [`backend/app/Providers/AuthServiceProvider.php`](../backend/app/Providers/AuthServiceProvider.php) — mendaftarkan `DamageAssessment::class => DamageAssessmentPolicy::class`.
8. [`backend/app/Http/Controllers/Api/RoadSegmentController.php`](../backend/app/Http/Controllers/Api/RoadSegmentController.php) — menolak dan mencabut token akun nonaktif (`403 Account is inactive.`) pada `index` dan `show`.
9. [`frontend/src/components/DecisionEnginePanel.jsx`](../frontend/src/components/DecisionEnginePanel.jsx) — menyediakan opsi segmen demo berlabel `[DATA DEMO]` pada dropdown asesmen ketika mode simulasi/demo diaktifkan tanpa membocorkannya ke peta publik.
10. [`backend/tests/Feature/Prd051BugFixRegressionTest.php`](../backend/tests/Feature/Prd051BugFixRegressionTest.php) *(baru)* — 5 metode pengujian regresi terfokus untuk `BUG-P1-01`, `BUG-P1-02`, `BUG-P2-01`, `BUG-P2-02`, dan `BUG-P2-03`.

*Catatan:* Tidak ada file migration (`database/migrations/*`) maupun rumus matematis di `AhpService` / `TopsisService` yang diubah.

---

## 3. Perbandingan Tes Sebelum vs Sesudah Perubahan & Hasil Aktual

### 3.1 Konfirmasi Isolasi Database Saat Pengujian
- **Database Pengujian Otomatis (`php artisan test`):** Dikonfirmasi melalui `phpunit.xml` menggunakan `APP_ENV=testing` dan `DB_DATABASE=roadpriority_test`.
- **Database Development (`php artisan db:seed`):** Dikonfirmasi melalui bootstrap aplikasi menggunakan `APP_ENV=local` dan `DB_DATABASE=roadpriority`. Kondisi awal `roadpriority` diperiksa terlebih dahulu (`0` baris pada seluruh tabel) sebelum menjalankan seeder non-destruktif (`updateOrCreate`).

### 3.2 Tabel Perbandingan Sebelum dan Sesudah

| Metrik Pengujian | Sebelum PRD 05.1 (Baseline Audit) | Sesudah PRD 05.1 |
|---|---|---|
| Total Suite PHPUnit (`roadpriority_test`) | `113 passed` (`5.77s`) | **`118 passed` (`0 failed`, `5.36s`)** (+5 tes regresi di `Prd051BugFixRegressionTest`) |
| Kecocokan Referensi Independen Python (`ahp_topsis_reference.py`) | `EXACT (0 differences)` | **`EXACT (0 differences)`** |
| Linter Frontend (`npm run lint`) | `0 warnings and 0 errors` (14 berkas) | **`0 warnings and 0 errors` (14 berkas, `101ms`)** |
| Build Produksi Frontend (`npm run build`) | `✓ built in 358ms` | **`✓ built in 326ms`** |
| Isi Database Development `roadpriority` (setelah 2x `db:seed` idempoten) | `0` baris di semua tabel | `users=1` (admin), `decision_criteria=7` (`K1`–`K7`), `road_segments=4` (demo), `damage_reports=4` (demo), `damage_assessments=4` (demo), `official_rows=0` |

---

## 4. Langkah Manual Menjalankan Simulasi AHP-TOPSIS Melalui Browser

1. **Pastikan MariaDB (XAMPP) aktif** pada port `3306`.
2. **Siapkan akun & data simulasi pada database `roadpriority`** (aman dijalankan ulang):
   ```powershell
   cd RoadPriority\backend
   # Opsional: atur INITIAL_ADMIN_PASSWORD di backend/.env untuk menentukan password tetap lokal,
   # atau jalankan langsung untuk melihat one-time password saat akun baru dibuat:
   C:\xampp\php\php.exe artisan db:seed --class=DatabaseSeeder
   C:\xampp\php\php.exe artisan db:seed --class=DemoSimulationSeeder
   ```
3. **Jalankan server backend Laravel:**
   ```powershell
   cd RoadPriority\backend
   C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
   ```
4. **Jalankan server development frontend Vite:**
   ```powershell
   cd RoadPriority\frontend
   npm run dev
   ```
5. **Uji Alur di Browser (`http://localhost:5173`):**
   - Pada tampilan awal (**Peta & Laporan Publik**), perhatikan bahwa peta publik menampilkan **0 laporan dan 0 segmen**, membuktikan bahwa ke-4 data demo (`is_demo = true`) **tidak bocor** ke tampilan publik.
   - Klik **Masuk (Pelapor / Petugas)** dan login menggunakan akun admin lokal (`admin@roadpriority.local`).
   - Buka tab **Prioritas AHP-TOPSIS** lalu centang kotak **`Sertakan data berlabel [DATA DEMO] (khusus mode simulasi)`** di kanan atas panel.
   - Pada **Sub-tab 1 (Kriteria & Kesiapan Data)**, verifikasi bahwa keempat ruas jalan simulasi (`DEMO-SEG-001` s.d. `DEMO-SEG-004`) berstatus **Layak** untuk seluruh kriteria `K1`–`K7`, dan dropdown **Pilih Segmen Jalan** menampilkan keempat ruas dengan label `[DATA DEMO]`.
   - Buka **Sub-tab 2 (Perbandingan AHP & Versi Bobot)**, buat draft versi bobot baru, isi nilai perbandingan berpasangan Saaty $1\text{–}9$, klik **Hitung Bobot AHP & Uji Konsistensi (CR)**, dan jika $CR \le 0{,}10$ klik **Setujui & Aktifkan**.
   - Buka **Sub-tab 3 (Eksekusi & Riwayat TOPSIS)**, isi nama skenario, pilih versi bobot, centang **`Jalankan sebagai Mode Simulasi`**, lalu klik **Jalankan Perhitungan TOPSIS**.
   - Aplikasi otomatis membuka **Sub-tab 4 (Rincian Perhitungan & Penjelasan Peringkat)** yang menampilkan tabel peringkat ke-4 ruas jalan demo ($D_i^+, D_i^-, C_i$), kriteria pendorong utama, matriks tahapan $X \to R \to V$, solusi ideal $A^+/A^-$, serta status **`TERVERIFIKASI IDENTIK`** (`reproducibility_verified = true`).

---

## 5. Risiko Tersisa

1. **Data Penelitian Nyata (`BUG-P1-03` / `ISS-02`):** Poligon batas administratif resmi BIG (`D-02`), ekstrak jalan OpenStreetMap wilayah studi BSD (`D-03`), kuesioner pakar AHP nyata (`D-07`), dan survei lapangan (`D-08`) masih perlu diimpor untuk menjalankan perhitungan resmi (`is_simulation = false`).
2. **Keamanan Versi PHP/Laravel (`ISS-01` / `DEC-002`):** Lingkungan pengembangan lokal tetap menggunakan PHP `8.0.30` dan Laravel `9.52.22` sesuai aturan PRD 05.1 §3.8. Wajib di-upgrade ke PHP $\ge 8.2$ sebelum dideploy ke server publik.

