# RoadPriority WebGIS

Sistem informasi pelaporan, pemetaan, dan prioritas penanganan jalan rusak (WebGIS + sistem pendukung keputusan AHP-TOPSIS). Proyek skripsi Sistem Informasi.

> **Status:** tahap PRD 01 (fondasi proyek). Baru endpoint `GET /api/health` dan halaman status koneksi yang tersedia. Fitur laporan, peta, dan AHP-TOPSIS belum dibuat. Lihat [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md).

## Teknologi

| Lapisan | Teknologi (versi terpasang) |
|---|---|
| Backend | Laravel 9.52 (REST API), PHP 8.0.30 |
| Frontend | React 19 + Vite 8 |
| Database | MariaDB 10.4.32 dari XAMPP (kompatibel dengan driver `mysql` Laravel) |
| Peta | Leaflet (dipasang pada tahap WebGIS, belum ada sekarang) |

Laravel 9 dipilih karena PHP lokal 8.0.30. Lihat [docs/DECISIONS.md](docs/DECISIONS.md) (DEC-002) untuk konsekuensinya.

## Persyaratan

PHP 8.0+ dengan ekstensi umum Laravel, Composer 2, Node.js LTS, npm, Git, dan XAMPP (MySQL/MariaDB). Detail Windows: [docs/SETUP_WINDOWS.md](docs/SETUP_WINDOWS.md).

## Menjalankan

**1. Database.** Jalankan MySQL di XAMPP Control Panel, lalu buat database `roadpriority` (utf8mb4) lewat phpMyAdmin.

**2. Backend** (terminal 1):

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

**3. Frontend** (terminal 2):

```powershell
cd frontend
npm install
npm run dev
```

Buka http://localhost:5173. Cek API langsung di http://127.0.0.1:8000/api/health.

## Pengujian

```powershell
cd backend ; php artisan test
cd frontend ; npm run lint ; npm run build
```

## Dokumentasi

- [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md): status dan hasil pengujian terakhir
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): arsitektur dan alur data
- [docs/SETUP_WINDOWS.md](docs/SETUP_WINDOWS.md): setup Windows + XAMPP
- [docs/API_CONTRACT.md](docs/API_CONTRACT.md): kontrak API
- [docs/DECISIONS.md](docs/DECISIONS.md): keputusan teknis
