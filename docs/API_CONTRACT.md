# API Contract: RoadPriority

Base path: `/api`. Format: JSON. Kontrak ini akan diperluas pada PRD 02 dan diubah hanya lewat catatan di `DECISIONS.md`.

| Endpoint | Tujuan | Status |
|---|---|---|
| `GET /api/health` | Memeriksa ketersediaan backend | **implemented** |
| `GET /api/road-segments` | Daftar ruas jalan | planned |
| `GET /api/reports` | Daftar laporan | planned |
| `POST /api/reports` | Membuat laporan | planned |
| `GET /api/criteria` | Membaca kriteria keputusan | planned |
| `POST /api/decision-runs` | Menjalankan perhitungan prioritas | planned |

Endpoint berstatus *planned* belum ada di backend dan belum mengembalikan data apa pun.

> Catatan: route bawaan scaffold `GET /api/user` (`auth:sanctum`) masih ada dan bukan bagian dari kontrak ini. Autentikasi dirancang pada PRD berikutnya.

## GET /api/health

Akses publik. Tidak memuat konfigurasi, versi, kredensial, atau data pribadi.

Respons `200 OK`:

```json
{
  "success": true,
  "message": "RoadPriority API is running"
}
```

Implementasi: `backend/app/Http/Controllers/Api/HealthController.php`. Tes: `backend/tests/Feature/HealthEndpointTest.php`.
