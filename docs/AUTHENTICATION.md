# Dokumentasi Autentikasi (`AUTHENTICATION.md`)

**Tahap:** PRD 03 — Backend API, Authentication & Access Control  
**Backend:** Laravel 9.52.22 + Laravel Sanctum 2.15  
**Frontend Target:** React + Vite (`http://localhost:5173`)

---

## 1. Mekanisme Autentikasi yang Dipilih

RoadPriority menggunakan **Laravel Sanctum Personal Access Tokens** (`Bearer <token>` melalui header `Authorization`).

### Alasan Teknis Pemilihan (`DEC-011`)
1. **Kesesuaian Arsitektur:** Backend Laravel REST API (`127.0.0.1:8000`) dan Frontend React (`localhost:5173`) berjalan sebagai layanan terpisah. Penggunaan token Sanctum menghindari kompleksitas sinkronisasi cookie lintas-port/domain pada lingkungan pengujian otomatis sekaligus kompatibel penuh dengan Vite dev proxy (`/api`).
2. **Kontrol Siklus Hidup di Server:** Setiap token disimpan secara ter-hash (SHA-256) pada tabel `personal_access_tokens`, sehingga dapat dicabut seketika saat pengguna logout (`POST /api/logout`) maupun saat akun dinonaktifkan oleh Admin (`UserAccountService::updateAccount` dan middleware `EnsureActiveUser`).
3. **Masa Berlaku Terbatas:** `config/sanctum.php` mengonfigurasi `'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 480)` (bawaan **480 menit / 8 jam**), sehingga token tidak berlaku tanpa batas.

### Kebijakan Penyimpanan Token di Klien (Frontend)
- Frontend **tidak boleh** menyimpan token jangka panjang tanpa kedaluwarsa di `localStorage`.
- Pada tahap integrasi frontend (PRD 04–07), token disimpan di memori aplikasi / `sessionStorage` selama sesi tab aktif dan wajib dihapus segera ketika pengguna memanggil `POST /api/logout` atau menerima respons `401 Unauthorized` / `403 Forbidden`.

---

## 2. Alur Autentikasi API

### 2.1 Login (`POST /api/login`)
- **Middleware:** `throttle:login` (bawaan maksimal **5 percobaan per menit** per kombinasi `email|ip`, dikonfigurasi lewat `LOGIN_RATE_LIMIT_PER_MINUTE` di `.env`).
- **Validasi Input (`LoginRequest`):**
  - `email`: wajib, format email valid, maks 190 karakter.
  - `password`: wajib, string.
  - `device_name`: opsional, string maks 100 karakter (default: `'web-client'`).
- **Pemeriksaan Keamanan (`AuthController::login`):**
  1. Jika email tidak ditemukan atau `Hash::check()` gagal:
     - Mencatat `auth.login_failed` ke `audit_logs` (field `password` otomatis dihapus oleh `AuditLogService::sanitize`).
     - Mengembalikan `401 Unauthorized` (`{"success": false, "message": "Invalid credentials.", "errors": null}`).
  2. Jika `is_active === false`:
     - Mencatat `auth.login_blocked_inactive` ke `audit_logs`.
     - Mengembalikan `403 Forbidden` (`{"success": false, "message": "Account is inactive.", "errors": null}`).
  3. Jika valid dan aktif:
     - Menerbitkan token baru `$user->createToken($deviceName)`.
     - Mencatat `auth.login_succeeded` ke `audit_logs`.
     - Mengembalikan `200 OK` berisi `token`, `token_type` (`Bearer`), `expires_at` (ISO 8601), dan profil `user` (`UserResource` tanpa password/token internal).

### 2.2 Identitas Pengguna Saat Ini (`GET /api/me`)
- **Middleware:** `auth:sanctum`, `active.user`.
- **Perilaku:**
  - Mengembalikan profil pengguna yang sedang terautentikasi menggunakan `UserResource` (`id`, `name`, `email`, `role`, `is_active`, `email_verified_at`, `created_at`).
  - Jika akun dinonaktifkan setelah token diterbitkan, middleware `EnsureActiveUser` (`active.user`) mencabut seluruh token pengguna tersebut dan mengembalikan `403 Forbidden`.

### 2.3 Logout (`POST /api/logout`)
- **Middleware:** `auth:sanctum`, `active.user`.
- **Perilaku:**
  - Menghapus baris token saat ini (`currentAccessToken()->delete()`) dari tabel `personal_access_tokens`.
  - Mencatat `auth.logout` ke tabel `audit_logs`.
  - Mengembalikan `200 OK`.

---

## 3. Keamanan Kredensial & CORS

1. **Hashing Password:** Semua password di-hash menggunakan `Hash::make()` (Bcrypt bawaan Laravel) saat pembuatan/pembaruan akun di `UserAccountService` maupun `LocalAdminSeeder`.
2. **Penyembunyian Atribut Sensitif:** Model `User` mendeklarasikan `$hidden = ['password', 'remember_token']` dan seluruh respons profil melewati `UserResource`.
3. **CORS Terbatas (`config/cors.php`):** Origin yang diizinkan dibatasi secara eksplisit melalui `CORS_ALLOWED_ORIGINS` (bawaan: `http://localhost:5173,http://127.0.0.1:5173`), bukan wildcard terbuka untuk kredensial.

