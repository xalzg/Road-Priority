# Metode Pendukung Keputusan Prioritas Jalan: Hibrida AHP-TOPSIS (`DECISION_METHOD.md`)

Dokumen ini menjelaskan arsitektur konseptual, pembagian peran metode, alur reproduktibilitas snapshot, serta analisis sensitivitas pada mesin pendukung keputusan **RoadPriority** (PRD 05).

---

## 1. Rasional Kombinasi Hibrida AHP dan TOPSIS

Penentuan urutan prioritas penanganan ruas jalan merupakan masalah pengambilan keputusan multikriteria (*Multi-Criteria Decision Making* / MCDM) yang melibatkan kriteria kualitatif/ordinal (seperti tingkat kerusakan fisik dan risiko keselamatan) serta kriteria kuantitatif/kontinu (seperti kedekatan ke fasilitas publik dan frekuensi laporan terverifikasi).

Dalam **RoadPriority**, metode **AHP** (*Analytical Hierarchy Process*) dan **TOPSIS** (*Technique for Order Preference by Similarity to Ideal Solution*) dipadukan dengan pembagian peran yang tegas:

1. **AHP digunakan untuk menentukan bobot kepentingan kriteria ($w_j$) dan menguji konsistensi penilaian pakar ($CR \le 0{,}10$):**
   - Pakar membandingkan pasangan kriteria pada skala Saaty $1\text{–}9$.
   - Jika terdapat lebih dari satu pakar, penilaian digabungkan menggunakan *Aggregation of Individual Judgments* (AIJ) berbasis rata-rata geometrik (*geometric mean*).
   - Vektor bobot utama dihitung menggunakan *principal right eigenvector* (iterasi pangkat / *power iteration* hingga konvergensi $< 10^{-12}$), bukan sekadar pendekatan rata-rata baris, sehingga konsisten secara matematis dengan nilai eigen maksimum $\lambda_{\max}$.
2. **TOPSIS digunakan untuk memeringkat alternatif ruas jalan ($A_1, \dots, A_m$) berdasarkan bobot AHP:**
   - Menghindari ledakan jumlah perbandingan berpasangan yang terjadi jika AHP digunakan langsung untuk membandingkan puluhan atau ratusan ruas jalan ($\frac{m(m-1)}{2}$ pasangan per kriteria).
   - Mengevaluasi setiap ruas jalan secara simultan berdasarkan jarak Euclidean terdekat ke **Solusi Ideal Positif ($A^+$)** dan terjauh dari **Solusi Ideal Negatif ($A^-$)** dengan menghormati arah optimasi masing-masing kriteria (**benefit** vs **cost**).

---

## 2. Pemisahan Peran Rekomendasi vs Kewenangan Pemerintah

Sesuai PRD 05 §1, hasil perhitungan sistem adalah **rekomendasi urutan prioritas berbasis data dan asumsi bobot yang digunakan**, bukan keputusan otomatis yang menggantikan inspeksi teknis, ketersediaan anggaran, atau kewenangan instansi penyelenggara jalan. Setiap snapshot hasil menyertakan pernyataan batasan (*disclaimer*) eksplisit.

---

## 3. Arsitektur Komponen Mesin Keputusan

| Komponen / Kelas | Tanggung Jawab |
|---|---|
| `App\Services\DecisionEngine\AhpService` | Validasi skala Saaty $1\text{–}9$, pembentukan matriks resiprokal, agregasi rata-rata geometrik multi-pakar, perhitungan eigenvector utama ($w_j$), $\lambda_{\max}$, $CI$, dan $CR$. |
| `App\Services\DecisionEngine\TopsisService` | Validasi matriks keputusan $X$, eksklusi alternatif tidak lengkap beserta alasan (`D-09`), normalisasi vektor Euclidean ($R$), pembobotan ($V$), penentuan $A^+$ dan $A^-$, jarak $D_i^+, D_i^-$, skor preferensi $C_i$, dan pemecahan seri (*tie-breaking*) deterministik. |
| `App\Services\DecisionEngine\SensitivityService` | Analisis sensitivitas *One-at-a-Time* (OAT $\pm 10\%, \pm 20\%$ dengan penskalaan proporsional bobot lain agar $\sum w_j = 1$) dan skenario bobot sama rata (*equal weights*) beserta korelasi peringkat Spearman ($\rho$). |
| `App\Services\DecisionEngine\SnapshotService` | Pembekuan input (`criteria_snapshot`, `weight_version_snapshot`, `alternatives_snapshot`), penentuan `largest_driving_criterion`, pembuatan hash kanonik SHA-256 (`InputHasher`), dan verifikasi reproduktibilitas (`reproduce()`). |
| `App\Services\DecisionEngine\DecisionDataProvider` | Pengambilan nilai alternatif dari tabel `road_segments`, `damage_assessments` (status `final` terbaru), dan `damage_reports` (status `verified` non-duplikat), serta pembuatan laporan kesiapan data (`buildReadinessReport()`). |
| `App\Services\DecisionEngine\CriterionWeightVersionService` | Orkestrasi siklus hidup versi bobot (`draft` $\to$ `computed` $\to$ `approved`), penegakan ambang $CR \le 0{,}10$, dan *immutability* versi bobot historis. |
| `App\Services\DecisionEngine\DecisionRunService` | Eksekusi transaksi atomik `DecisionRun` dan `DecisionRunItem` (baik ruas yang diperingkat maupun yang dikeluarkan) serta pembangkitan penjelasan peringkat per ruas. |

---

## 4. Reproduktibilitas Keputusan Historis (PRD 05 §8)

1. Setiap eksekusi `POST /api/decision-runs` membekukan salinan lengkap kriteria, bobot AHP, dan nilai mentah setiap alternatif ke dalam `decision_runs.input_snapshot_reference` serta baris-baris `decision_run_items`.
2. Sistem menghitung `input_hash` SHA-256 yang tidak bergantung pada urutan pengiriman alternatif di request.
3. Saat `GET /api/decision-runs/{id}` dipanggil di masa depan—bahkan setelah ada penilaian kondisi jalan baru atau versi bobot kriteria baru—`SnapshotService::reproduce()` menghitung ulang peringkat dari snapshot beku tersebut dan memverifikasi bahwa `input_hash`, skor $C_i$, jarak $D_i^+, D_i^-$, dan urutan peringkat identik hingga toleransi $10^{-12}$ (`reproducibility_verified = true`).
