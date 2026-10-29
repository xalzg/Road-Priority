# Laporan Kesiapan Data Mesin Keputusan (`DECISION_DATA_READINESS.md`)

Dokumen ini memenuhi ketentuan **PRD 05 §2 & §7** mengenai audit kesiapan data alternatif ruas jalan sebelum dan selama eksekusi perhitungan prioritas AHP-TOPSIS.

---

## 1. Sumber Data per Kriteria Kandidat (`K1`–`K7`)

| Kode | Nama Kriteria | Tipe | Unit / Skala | Rentang Valid | Sumber Tabel & Aturan Agregasi (`DecisionDataProvider`) | Status Ketersediaan Skema & Layanan |
|---|---|---|---|---|---|---|
| `K1` | Tingkat kerusakan | `benefit` | Ordinal 1–5 | $[1, 5]$ | `damage_assessments.severity_level` dari asesmen berstatus `final` terbaru pada ruas jalan (`latest_final_assessment`) | **Tersedia penuh** |
| `K2` | Risiko keselamatan | `benefit` | Ordinal 1–5 | $[1, 5]$ | `damage_assessments.safety_risk_score` dari asesmen berstatus `final` terbaru pada ruas jalan (`latest_final_assessment`) | **Tersedia penuh** |
| `K3` | Fungsi jalan | `benefit` | Ordinal 1–4 | $[1, 4]$ | `damage_assessments.road_function_score` atau pemetaan `road_segments.road_function` (`lingkungan=1, lokal=2, kolektor=3, arteri=4`) | **Tersedia penuh** |
| `K4` | Intensitas pengguna jalan | `benefit` | Ordinal 1–5 | $[1, 5]$ | `damage_assessments.impact_score` dari asesmen berstatus `final` terbaru (`latest_final_assessment`) | **Tersedia di skema; menunggu keputusan D-06 (Opsi K4-a vs K4-b)** |
| `K5` | Kedekatan fasilitas publik | `cost` | Meter | $[0, \infty)$ | `road_segments.nearest_facility_meters` (`minimum_distance_m`) | **Tersedia penuh** |
| `K6` | Frekuensi laporan | `benefit` | Laporan (bulat) | $[0, \infty)$ | Jumlah `damage_reports` berstatus `verified` dan `duplicate_of_id IS NULL` pada ruas jalan (`count_unique_verified_reports`) | **Tersedia penuh** |
| `K7` | Lama belum ditangani | `benefit` | Hari (bulat) | $[0, \infty)$ | Selisih hari sejak `reported_at` tertua pada laporan `verified` aktif di ruas jalan terhadap waktu evaluasi (`max_verified_report_age_days`) | **Tersedia penuh** |

---

## 2. Kebijakan Penanganan Data Hilang (`D-09`: `exclude_and_log`)

Sesuai **PRD 05 §6.1 & §7**:
1. Sistem **dilarang** mengganti nilai kosong (`null`) dengan angka `0` atau rata-rata secara diam-diam karena hal tersebut memalsukan kondisi fisik ruas jalan.
2. Untuk kriteria berbasis laporan (`K6` dan `K7`), ruas jalan yang sudah memiliki asesmen lapangan final tetapi memiliki $0$ laporan masyarakat memang memiliki nilai faktual `0` laporan / `0` hari. Namun untuk kriteria berbasis asesmen fisik (`K1`, `K2`, `K4`) atau atribut spasi (`K3`, `K5`), ketiadaan data menghasilkan `null`.
3. Setiap ruas jalan yang memiliki nilai `null` atau di luar rentang $[x_{\min}, x_{\max}]$ pada kriteria aktif:
   - Ditampilkan pada bagian `incomplete_segments` di endpoint `GET /api/decision-data-readiness`.
   - Dikeluarkan dari matriks normalisasi TOPSIS saat `POST /api/decision-runs` dijalankan, dan disimpan secara eksplisit ke tabel `decision_run_items` dengan `is_included = false` serta `exclusion_reason` berisi daftar kode alasan (`missing_or_invalid:{kode}` atau `out_of_range:{kode}`).

---

## 3. Pemisahan Data Resmi vs Data Demonstrasi/Simulasi

1. Baris pada `road_segments`, `damage_reports`, dan `damage_assessments` yang berasal dari `DemoSimulationSeeder` ditandai `is_demo = true` dan `data_origin = 'demo'`.
2. Pada eksekusi keputusan resmi (`is_simulation = false`), `DecisionDataProvider` menyaring `where('is_demo', false)` dan `SnapshotService::validateDataOriginForRun()` menolak setiap alternatif berlabel demo dengan kode error `demo_data_in_official_run` (`422`).
3. Data demo hanya dapat dievaluasi apabila pengguna secara eksplisit memilih mode simulasi (`is_simulation = true`).

