# PROJECT_STATUS — RoadPriority

Diperbarui: 2026-10-29 19:55 WIB oleh sesi agen (Antigravity)

## Tahap aktif

- **PRD 01 (Foundation & Project Bootstrap + Audit Sumber Data):** **selesai dan disetujui**.
- **PRD 02 (Database Design & API Contract):** **selesai dan terverifikasi** (`prd-02-database-and-api`).
- **PRD 03 (Backend API, Authentication & Access Control):** **selesai diimplementasikan dan diverifikasi** pada cabang `prd-03-backend-auth-rbac` (menunggu persetujuan penutupan tahap dari pengguna).
- **PRD 05 (Mesin Keputusan Inti AHP-TOPSIS — Pure Service Layer):** **selesai diimplementasikan dan teruji unit** (`80` unit test mesin keputusan lulus).

## Ringkasan kondisi

1. **Autentikasi Sanctum & Keamanan Sesi (PRD 03):** `POST /api/login` (`throttle:login` 5 percobaan/menit per `email|ip`), `POST /api/logout`, dan `GET /api/me` diimplementasikan dengan Laravel Sanctum Personal Access Tokens bermasa berlaku terbatas (`SANCTUM_TOKEN_EXPIRATION_MINUTES=480`), pencabutan token otomatis saat logout maupun saat akun dinonaktifkan (`EnsureActiveUser`), serta konfigurasi CORS terbatas (`CORS_ALLOWED_ORIGINS`).
2. **Kontrol Akses Berbasis Role & Kepemilikan (RBAC + Anti-IDOR):** 4 peran (`reporter`, `officer`, `expert`, `admin`) ditegakkan melalui middleware (`active.user`, `role`) dan Laravel Policies (`UserPolicy`, `DamageReportPolicy`, `DecisionCriterionPolicy`, `CriterionWeightVersionPolicy`). Pelapor tidak dapat melihat laporan belum terverifikasi milik pelapor lain (`403`) dan tidak dapat memverifikasi laporannya sendiri (`403`). Identitas pelapor (`reporter_id`) disensor untuk pengakses non-pemilik di luar `officer`/`admin`.
3. **Service Layer, Transaksi Atomik & Proteksi Admin Terakhir:** `UserAccountService`, `ReportStatusService`, dan `AuditLogService` membungkus operasi multi-tabel di dalam `DB::transaction()`. `UserAccountService` menggunakan `lockForUpdate()` untuk mencegah penonaktifan atau penurunan role akun `admin` aktif terakhir (`409 Conflict`).
4. **Penanganan Error Konsisten & Audit Log Tersanitasi:** `app/Exceptions/Handler.php` mengembalikan amplop JSON konsisten (`401`, `403`, `404`, `409`, `422`, `429`, `500`) tanpa pernah membocorkan stack trace, SQL query, atau path file internal. `AuditLogService` secara otomatis menghapus kunci rahasia (`password`, `token`, dll.) sebelum menyimpan ke `audit_logs`.

## Hasil build dan tes terakhir

| Perintah | Hasil | Tanggal |
|---|---|---|
| `php artisan test` (pada `roadpriority_test`) | **`104 passed` (0 failed)** dalam `3.26s` (`AuthAndAccessControlTest` 16 tes, `DatabaseSchemaIntegrityTest` 8 tes, `HealthEndpointTest` 2 tes, `AhpServiceTest`, `TopsisServiceTest`, `SensitivityServiceTest`, `SnapshotServiceTest`, `ExampleTest`) | 2026-10-29 19:52 WIB |
| `php artisan route:list --path=api` | 12 rute API terdaftar (`api/health`, `api/login`, `api/logout`, `api/me`, `api/users`, `api/audit-logs`, `api/reports/{damageReport}`, `api/reports/{damageReport}/status`, `api/criteria`) | 2026-10-29 19:51 WIB |
| `npm run lint` (`frontend/`) | `Found 0 warnings and 0 errors` | 2026-10-29 19:24 WIB |
| `npm run build` (`frontend/`) | `built in 151ms` | 2026-10-29 19:03 WIB |

## Requirement dan acceptance criteria PRD 03

| Kriteria (PRD 03 §14) | Status | Bukti |
|---|---|---|
| Login, logout, dan profil pengguna bekerja | **Terpenuhi** | `AuthController` + `AuthAndAccessControlTest` (`login`, `logout`, `me`) |
| Password tersimpan sebagai hash dan tidak pernah muncul pada respons API | **Terpenuhi** | `Hash::make()`, `User::$hidden`, `UserResource`, `AuthAndAccessControlTest` |
| Role dan permission berjalan di server | **Terpenuhi** | `EnsureUserHasRole`, `EnsureActiveUser`, `UserPolicy`, `DamageReportPolicy`, `DecisionCriterionPolicy`, `CriterionWeightVersionPolicy` |
| Endpoint terproteksi menolak akses tanpa autentikasi atau tanpa hak akses | **Terpenuhi** | `AuthAndAccessControlTest::test_private_endpoints_reject_unauthenticated_requests_with_401` & `test_role_authorization_matrix_for_admin_user_and_criteria_endpoints` |
| Kepemilikan resource diperiksa untuk mencegah IDOR | **Terpenuhi** | `DamageReportPolicy::view` + `AuthAndAccessControlTest::test_idor_prevention_and_reporter_privacy_on_damage_reports` |
| Validasi request mengembalikan `422` dengan format error konsisten | **Terpenuhi** | Form Requests (`LoginRequest`, `StoreUserRequest`, `UpdateUserRequest`, `UpdateReportStatusRequest`, `StoreDecisionCriterionRequest`) + `Handler.php` |
| Error handler tidak membocorkan informasi sensitif | **Terpenuhi** | `AuthAndAccessControlTest::test_api_exception_handler_never_leaks_stack_trace_or_sql_details_on_unexpected_errors` |
| Service layer dan transaksi database digunakan untuk operasi multi-tabel | **Terpenuhi** | `UserAccountService`, `ReportStatusService`, `DecisionCriterionController::store` + uji rollback `test_multi_table_status_transition_rolls_back_completely_if_audit_step_throws` |
| Audit log mencatat aksi sensitif tanpa menyimpan rahasia | **Terpenuhi** | `AuditLogService` + `test_login_fails_with_wrong_password_and_redacts_secret_in_audit_log` |
| Pengujian autentikasi, otorisasi, dan validasi lulus pada database pengujian terpisah | **Terpenuhi** | `104 passed` pada database `roadpriority_test` |
| Dokumentasi dan `PROJECT_STATUS.md` telah diperbarui | **Terpenuhi** | [`docs/AUTHENTICATION.md`](AUTHENTICATION.md), [`docs/ACCESS_CONTROL.md`](ACCESS_CONTROL.md), [`docs/API_CONTRACT.md`](API_CONTRACT.md), [`docs/DECISIONS.md`](DECISIONS.md), dan dokumen ini |

## Perubahan backend dan endpoint terakhir (PRD 03)

- **Middleware (`backend/app/Http/Middleware/`):** `EnsureActiveUser.php` (`active.user`), `EnsureUserHasRole.php` (`role`).
- **Policies (`backend/app/Policies/`):** `UserPolicy.php`, `DamageReportPolicy.php`, `DecisionCriterionPolicy.php`, `CriterionWeightVersionPolicy.php`.
- **Form Requests (`backend/app/Http/Requests/`):** `Auth/LoginRequest.php`, `User/StoreUserRequest.php`, `User/UpdateUserRequest.php`, `Report/UpdateReportStatusRequest.php`, `Criterion/StoreDecisionCriterionRequest.php`.
- **Services (`backend/app/Services/`):** `AuditLogService.php`, `UserAccountService.php`, `ReportStatusService.php`.
- **Controllers & Resources (`backend/app/Http/`):** `Controllers/Api/AuthController.php`, `Controllers/Api/UserController.php`, `Controllers/Api/AuditLogController.php`, `Controllers/Api/ReportAccessController.php`, `Controllers/Api/DecisionCriterionController.php`, `Resources/UserResource.php`.
- **Seeder Admin Lokal (`backend/database/seeders/`):** `LocalAdminSeeder.php`.
- **Feature Tests (`backend/tests/Feature/`):** `AuthAndAccessControlTest.php` (16 skenario keamanan & fungsionalitas).

## Issue terbuka

| ID | Keparahan | Deskripsi | Langkah reproduksi / Mitigasi |
|---|---|---|---|
| ISS-01 | Sedang | PHP 8.0.30 & Laravel 9.52.22 memiliki 19 advisory keamanan pada `composer audit` (`DEC-002`) | Aman untuk pengembangan lokal; wajib upgrade PHP $\ge 8.2$ sebelum deployment publik |
| ISS-02 | Rendah | `ST_Distance_Sphere(POINT, LINESTRING)` menghasilkan `ERROR 1815` pada MariaDB 10.4.32 (`DEC-008`, `DEC-010`) | Telah dimitigasi dengan penyimpanan `geometry_geojson` + indeks centroid `DECIMAL(10,7)` dan perhitungan jarak titik-ke-garis di PHP pada PRD 04 |

## Keputusan menunggu persetujuan

- Konfirmasi nilai spesifik untuk **D-02** (daftar kecamatan/kelurahan wilayah studi BSD) dan **D-06** (pilihan perlakuan kriteria K4). Lihat [`docs/DECISIONS.md`](DECISIONS.md) §2.

## Langkah berikutnya yang disarankan

1. Setujui penutupan **PRD 03**.
2. Lanjutkan ke **PRD 04 — Pelaporan Kerusakan, Verifikasi, dan Penilaian Kondisi Lapangan** (pengiriman laporan masyarakat beserta validasi foto, pencocokan segmen jalan terdekat dalam meter, alur antrean verifikasi petugas, deteksi kandidat laporan duplikat, dan pencatatan penilaian kondisi lapangan `damage_assessments`).
