# Dokumentasi Kontrol Akses & Otorisasi (`ACCESS_CONTROL.md`)

**Tahap:** PRD 03 — Backend API, Authentication & Access Control  
**Mekanisme:** Middleware Role (`role`, `active.user`) + Laravel Policies (`app/Policies/`)

---

## 1. Daftar Peran (Roles)

Model [`User`](../backend/app/Models/User.php) mendefinisikan 4 peran resmi:

| Kode Role | Nama Peran | Tanggung Jawab Utama |
|---|---|---|
| `reporter` | Pelapor / Masyarakat | Mengirim laporan kerusakan jalan dan memantau status laporan miliknya sendiri. |
| `officer` | Petugas / Verifikator | Meninjau, memverifikasi, meminta info tambahan, menolak, atau menyelesaikan laporan kerusakan jalan; melihat daftar kriteria dan hasil keputusan. |
| `expert` | Pakar AHP | Memberikan penilaian perbandingan berpasangan kriteria AHP (`ahp_pairwise_comparisons`) dan melihat kriteria/bobot/hasil keputusan. |
| `admin` | Administrator Sistem | Mengelola akun pengguna dan role, mengonfigurasi kriteria keputusan (`decision_criteria`), menyetujui versi bobot, mengeksekusi perhitungan resmi, dan meninjau `audit_logs`. |

> **Proteksi Mass Assignment:** Kolom `role` dan `is_active` pada tabel `users` **tidak** dimasukkan ke `$fillable` pada model `User`. Perubahan `role` dan `is_active` hanya dapat dilakukan secara eksplisit melalui [`UserAccountService`](../backend/app/Services/UserAccountService.php) oleh `admin`.

---

## 2. Lapisan Penegakan Otorisasi

1. **Middleware `auth:sanctum`:** Memastikan request membawa token Bearer Sanctum yang sah dan belum kedaluwarsa (`401 Unauthorized` jika gagal).
2. **Middleware `active.user` ([`EnsureActiveUser`](../backend/app/Http/Middleware/EnsureActiveUser.php)):** Memastikan pengguna yang terautentikasi memiliki `is_active = true`. Jika akun telah dinonaktifkan, seluruh token pengguna dicabut dan request ditolak dengan `403 Forbidden`.
3. **Middleware `role:...` ([`EnsureUserHasRole`](../backend/app/Http/Middleware/EnsureUserHasRole.php)):** Memeriksa apakah peran pengguna termasuk dalam daftar peran yang diizinkan pada grup rute (`403 Forbidden` jika tidak sesuai).
4. **Laravel Policies (`app/Policies/`):**
   - [`UserPolicy`](../backend/app/Policies/UserPolicy.php): Hanya `admin` aktif yang dapat melihat daftar pengguna, membuat pengguna baru, atau mengubah pengguna.
   - [`DamageReportPolicy`](../backend/app/Policies/DamageReportPolicy.php): Menegakkan kepemilikan resource (anti-IDOR) dan membatasi perubahan status laporan hanya untuk `officer` dan `admin`.
   - [`DecisionCriterionPolicy`](../backend/app/Policies/DecisionCriterionPolicy.php): Mengizinkan pembacaan kriteria oleh `officer`, `expert`, dan `admin`, serta membatasi pembuatan/pengubahan kriteria hanya untuk `admin`.
   - [`CriterionWeightVersionPolicy`](../backend/app/Policies/CriterionWeightVersionPolicy.php): Mengizinkan pembacaan versi bobot oleh `officer`, `expert`, dan `admin`, penilaian AHP oleh `expert` dan `admin`, serta penguncian/persetujuan versi bobot oleh `admin`.

---

## 3. Matriks Hak Akses Endpoint (PRD 03)

| Endpoint | Tamu | `reporter` | `officer` | `expert` | `admin` |
|---|---|---|---|---|---|
| `GET /api/health` | `200` | `200` | `200` | `200` | `200` |
| `POST /api/login` | `200`/`401` | `200` | `200` | `200` | `200` |
| `POST /api/logout` | `401` | `200` | `200` | `200` | `200` |
| `GET /api/me` | `401` | `200` | `200` | `200` | `200` |
| `GET /api/reports/{damageReport}` | `401` | `200` (milik sendiri / `verified`) / `403` (milik orang lain blm `verified`) | `200` (semua) | `200` (hanya `verified`, identitas pelapor disensor) / `403` | `200` (semua) |
| `PATCH /api/reports/{damageReport}/status` | `401` | `403` | `200` | `403` | `200` |
| `GET /api/criteria` | `401` | `403` | `200` | `200` | `200` |
| `POST /api/criteria` | `401` | `403` | `403` | `403` | `201` |
| `GET /api/users` | `401` | `403` | `403` | `403` | `200` |
| `POST /api/users` | `401` | `403` | `403` | `403` | `201` |
| `PATCH /api/users/{user}` | `401` | `403` | `403` | `403` | `200` (`409` jika admin aktif terakhir) |
| `GET /api/audit-logs` | `401` | `403` | `403` | `403` | `200` |

---

## 4. Aturan Kepemilikan Resource (Anti-IDOR), Privasi Pelapor, dan Proteksi Admin Terakhir

1. **Anti-IDOR pada Laporan Kerusakan (`DamageReportPolicy::view`):**
   - Pelapor A yang mencoba mengakses `GET /api/reports/{id}` milik Pelapor B yang masih berstatus `submitted`, `under_review`, `needs_info`, atau `rejected` akan ditolak dengan `403 Forbidden`.
   - Pelapor tidak pernah dapat memverifikasi laporannya sendiri (`PATCH /api/reports/{id}/status` ditolak dengan `403 Forbidden`).
2. **Privasi Identitas Pelapor (NFR-09):**
   - Pada [`ReportAccessController::show`](../backend/app/Http/Controllers/Api/ReportAccessController.php), field `reporter_id` hanya dikembalikan apabila pengakses adalah `admin`, `officer`, atau pemilik laporan itu sendiri (`$viewer->id === $damageReport->reporter_id`). Untuk pihak lain yang diizinkan melihat laporan terverifikasi (misalnya `expert`), `reporter_id` dikembalikan sebagai `null`.
3. **Pencegahan Kehilangan Seluruh Admin Aktif (`UserAccountService::updateAccount`):**
   - Sebelum menurunkan role atau menonaktifkan seorang pengguna ber-role `admin`, sistem melakukan query `lockForUpdate()` untuk menghitung jumlah `admin` aktif lainnya. Jika jumlahnya `0`, operasi dibatalkan dengan `409 Conflict` (`Cannot deactivate or demote the last active admin account.`).
4. **Pembuatan Akun Admin Awal (`LocalAdminSeeder`):**
   - Untuk pengembangan lokal, jalankan `php artisan db:seed --class=LocalAdminSeeder`. Seeder membaca `INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD` dari `.env` lokal (atau membangkitkan password acak satu-kali-tampil di terminal apabila `INITIAL_ADMIN_PASSWORD` dikosongkan, sehingga tidak ada password produksi yang disimpan di Git).

