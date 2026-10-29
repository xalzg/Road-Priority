# Arsitektur: RoadPriority (tahap PRD 01)

## Alur

```text
Browser (React, Vite dev server :5173)
        |  fetch('/api/...')
        v
Proxy Vite  --->  Laravel API (php artisan serve :8000)
                         |  PDO (driver mysql)
                         v
                  MariaDB/MySQL XAMPP (:3306), database `roadpriority`
```

1. React memanggil path relatif `/api/...`.
2. Saat development, Vite meneruskan `/api` ke `http://127.0.0.1:8000` (`frontend/vite.config.js`), sehingga tidak perlu pengaturan CORS khusus.
3. Laravel menangani request lewat `routes/api.php`, controller, dan Eloquent.
4. Laravel membaca dan menulis data ke database `roadpriority`.

## Peran tiap alat di development

| Alat | Fungsi |
|---|---|
| XAMPP | Menyediakan MariaDB/MySQL (dan phpMyAdmin). Apache XAMPP **tidak** dipakai untuk menyajikan aplikasi. |
| Laravel Artisan | `php artisan serve` menjalankan server PHP untuk API; juga `migrate`, `route:list`, `test`. |
| Vite | Dev server frontend dengan hot reload, proxy `/api`, dan `npm run build` untuk build produksi. |

## Struktur repository

- `backend/`: Laravel (API, migration, tes)
- `frontend/`: React + Vite (`src/services` untuk pemanggilan API, `components`, `layouts`, `pages`, `styles`)
- `docs/`: dokumentasi

## Prinsip (dari Master PRD v2.0)

- Laravel menjadi sumber kebenaran aturan bisnis dan perhitungan; React tidak menghitung ulang AHP-TOPSIS.
- Modul berkomunikasi lewat kontrak API terdokumentasi ([API_CONTRACT.md](API_CONTRACT.md)).
- Belum ada tabel domain; skema database dirancang pada PRD 02.
