# Setup Windows + XAMPP

Seluruh nilai versi di bawah adalah hasil pemeriksaan nyata pada mesin pengembangan (29 Oktober 2026).

## 1. Memeriksa alat

```powershell
php -v
composer --version
node -v
npm -v
git --version
```

Versi yang ditemukan: PHP 8.0.30 (`C:\xampp\php\php.exe`), Composer 2.8.12, Node.js v24.16.0, npm 11.13.0, Git 2.51.1.

Ekstensi PHP yang diperlukan Laravel (semuanya terpasang): bcmath, ctype, curl, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip. Periksa dengan `php -m`.

> PHP 8.0 membatasi proyek pada Laravel 9 (lihat DEC-002 di [DECISIONS.md](DECISIONS.md)).

## 2. Menjalankan MySQL (XAMPP)

1. Buka XAMPP Control Panel, klik **Start** pada **MySQL**. Apache tidak wajib; hanya diperlukan bila ingin memakai phpMyAdmin di `http://localhost/phpmyadmin`.
2. Server database XAMPP ini adalah MariaDB 10.4.32. Cek lewat `C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT VERSION();"`.

## 3. Membuat database

Lewat phpMyAdmin: **New** → nama `roadpriority` → collation `utf8mb4_unicode_ci`. Atau lewat terminal:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS roadpriority CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 4. Konfigurasi `.env`

```powershell
cd backend
copy .env.example .env
php artisan key:generate
```

Nilai penting di `backend/.env` (`.env` tidak boleh masuk Git):

```dotenv
APP_NAME=RoadPriority
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=roadpriority
DB_USERNAME=root
DB_PASSWORD=
```

Pada mesin ini root MariaDB tidak memakai password. Bila milik Anda memakai password, isi `DB_PASSWORD` di `.env` saja.

```powershell
php artisan config:clear
php artisan migrate
```

## 5. Menjalankan dua terminal

**Terminal 1, backend:**

```powershell
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2, frontend:**

```powershell
cd frontend
npm install     # sekali saja
npm run dev
```

Buka http://localhost:5173. Cek API: http://127.0.0.1:8000/api/health.

## 6. Pemeriksaan

```powershell
cd backend ; php artisan route:list ; php artisan migrate:status ; php artisan test
cd frontend ; npm run lint ; npm run build
```

## 7. Masalah yang ditemukan

| Masalah | Penyebab | Solusi |
|---|---|---|
| Laravel versi terbaru mensyaratkan PHP lebih baru dari 8.0 (hasil pengecekan prasyarat; perintah tanpa batas versi **tidak dijalankan**) | PHP XAMPP 8.0.30 | Proyek dibuat dengan `composer create-project "laravel/laravel:^9.0" backend` (berhasil), atau upgrade XAMPP (DEC-002) |
| Halaman frontend menampilkan "Tidak terhubung" dengan HTTP 502 | Backend tidak berjalan (Vite proxy tidak menemukan port 8000) | Jalankan `php artisan serve` lalu klik **Coba lagi** |
| `composer create-project` berakhir dengan exit code 1 meski Artisan, `.env`, dan `vendor` terbentuk | Penyebab pasti belum diselidiki. Instalasi diverifikasi dengan `php artisan --version` (9.52.22) dan tes yang lulus. | Verifikasi hasilnya, jangan hanya melihat exit code |
| `composer audit` melaporkan 19 advisory pada 7 paket | Laravel 9 dan PHP 8.0 sudah tidak menerima patch | Lihat DEC-002; jangan deploy ke publik |
| Folder `vendor/` dan `node_modules/` berada di dalam OneDrive | Proyek di `Documents\ML` | Lihat DEC-006 |
