# PROJECT_STATUS: RoadPriority
Diperbarui: 2026-10-29 19:04 WIB oleh agen (Antigravity)

## Tahap aktif
PRD 01 (Foundation & Project Bootstrap). Status: **selesai dan disetujui pengguna** (2026-10-29 19:06). Acceptance criteria sudah diperiksa (lihat bawah). PRD 02 belum dimulai.

## Status instalasi
| Komponen | Status |
|---|---|
| Repository Git (`RoadPriority/`, monorepo) | Terinisialisasi |
| Backend Laravel (`backend/`) | Terpasang, Artisan berjalan |
| Frontend React + Vite (`frontend/`) | Terpasang, `npm install` sukses (0 vulnerability) |
| Database `roadpriority` | Dibuat (utf8mb4_unicode_ci) |
| Leaflet, tabel domain, autentikasi, data jalan | Sengaja belum dibuat (PRD 01 §11) |

## Versi teknologi aktual
PHP 8.0.30 (ZTS, `C:\xampp\php`) · Composer 2.8.12 · Node.js v24.16.0 · npm 11.13.0 · Git 2.51.1 · Laravel 9.52.22 · Sanctum 2.15.1 · React 19.3.1 · Vite 8.3.5 · MariaDB 10.4.32 (XAMPP).

## Status koneksi database
Terhubung. `php artisan migrate` berhasil menjalankan 4 migration bawaan Laravel. Tabel di `roadpriority`: `failed_jobs`, `migrations`, `password_resets`, `personal_access_tokens`, `users`. Root tanpa password pada mesin ini.

## Endpoint yang berfungsi
- `GET /api/health` → `{"success":true,"message":"RoadPriority API is running"}`
- `GET /api/user` (bawaan scaffold, `auth:sanctum`): bukan bagian kontrak, belum diuji.
- Endpoint lain di [API_CONTRACT.md](API_CONTRACT.md) berstatus *planned* (belum ada).

URL: backend http://127.0.0.1:8000 · frontend http://127.0.0.1:5173 (alamat Vite `localhost:5173`).

## Tes yang telah dijalankan (hasil aktual)
| Perintah | Hasil |
|---|---|
| `php artisan --version` | Laravel Framework 9.52.22 |
| `php artisan migrate` | 4 migration DONE |
| `php artisan migrate:status` | 4 migration berstatus Ran |
| `php artisan route:list` | `GET api/health` terdaftar (7 route total, termasuk bawaan) |
| `php artisan test` | 4 passed (2 bawaan + 2 `HealthEndpointTest`) |
| `curl http://127.0.0.1:8000/api/health` | HTTP 200, payload sesuai PRD |
| `curl http://127.0.0.1:5173/api/health` (via proxy Vite) | HTTP 200, payload sama |
| Render UI via headless Chrome, backend aktif | Menampilkan "Terhubung" + "RoadPriority API is running" |
| Render UI via headless Chrome, backend dimatikan | Menampilkan "Tidak terhubung", "HTTP 502", petunjuk, dan tombol Coba lagi |
| `npm run lint` | 0 warning, 0 error |
| `npm run build` | Sukses |
| `git check-ignore` | `backend/.env`, `node_modules`, `vendor` diabaikan Git |

**Belum diuji:** klik tombol "Coba lagi" secara interaktif (hanya dirender statis); `GET /api/user`; tampilan di browser sungguhan oleh pengguna.

## Acceptance criteria PRD 01
| Kriteria | Status |
|---|---|
| Backend terinstal dan berjalan | Terpenuhi |
| Frontend terinstal dan berjalan | Terpenuhi |
| MySQL XAMPP dapat diakses Laravel | Terpenuhi (MariaDB 10.4.32) |
| Migration awal berhasil | Terpenuhi |
| `/api/health` sesuai respons | Terpenuhi |
| Frontend menampilkan status API | Terpenuhi (headless Chrome) |
| Frontend menampilkan error saat backend mati | Terpenuhi (headless Chrome) |
| Tidak ada kredensial di Git | Terpenuhi: `.env` diabaikan, `.env.example` tanpa kredensial. Dicek lagi setelah commit. |
| Dokumentasi sesuai kondisi aktual | Terpenuhi, perlu ditinjau pengguna |
| Tidak ada fitur atau data palsu | Terpenuhi |

## Error dan risiko yang belum selesai
1. **Keamanan (DEC-002):** `composer audit` melaporkan 19 advisory pada 7 paket (laravel/framework dan paket Symfony), tingkat tertinggi *high*. Tidak diperbaiki karena Laravel 9 dan PHP 8.0 sudah tidak dipatch. Jangan deploy ke publik tanpa upgrade PHP dan Laravel. Paket `fruitcake/laravel-cors` juga ditandai *abandoned* oleh Composer.
2. `composer create-project` berakhir dengan exit code 1 padahal hasilnya valid (penyebab belum diselidiki).
3. **OneDrive (DEC-006):** `vendor/` dan `node_modules/` berada dalam folder OneDrive.
4. Konfigurasi `git user.name`/`user.email` global dipakai apa adanya (Grac / xalzstudent@gmail.com): periksa bahwa itu identitas yang Anda inginkan.

## Keputusan teknis yang telah dibuat
Lihat [DECISIONS.md](DECISIONS.md): DEC-001 sampai DEC-007, semuanya disetujui. D-01 sampai D-04 disetujui hanya pada arah umum (DEC-007); nilai konkretnya belum ditetapkan. D-05 sampai D-12 belum dibahas.

## Langkah berikutnya yang disarankan
1. ~~Setujui PRD 01, DEC-002, DEC-006~~ (selesai).
2. Audit sumber data (batas wilayah, geometri ruas, lisensi) untuk menetapkan nilai konkret D-01 sampai D-04. Belum dikerjakan.
3. Mulai jalur non-kode dari Master PRD §16: rekrut pakar AHP, rencana survei lapangan, status lisensi data geometri jalan.
4. PRD 02 (arsitektur, database, kontrak API) setelah persetujuan; keputusan D-01 sampai D-04 perlu disetujui dulu.

