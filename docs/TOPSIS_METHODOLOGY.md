# Metodologi Pemeringkatan TOPSIS (`TOPSIS_METHODOLOGY.md`)

Dokumen ini mendokumentasikan tahapan matematis, aturan penanganan kondisi batas, dan verifikasi numerik modul **TOPSIS** pada `App\Services\DecisionEngine\TopsisService` (PRD 05 §6 & §10).

---

## 1. Tahapan Perhitungan Matematis TOPSIS

Misalkan terdapat $m$ alternatif ruas jalan yang memenuhi syarat kelengkapan data ($m \ge 2$) dan $n$ kriteria aktif ($1 \le n \le 10$) dengan bobot ternormalisasi $w_j \ge 0$, $\sum_{j=1}^{n} w_j = 1$.

### Tahap 1 — Pembentukan Matriks Keputusan ($X$)
Matriks keputusan $X = [x_{ij}]_{m \times n}$ berisi nilai numerik aktual alternatif ke-$i$ pada kriteria ke-$j$.
- Setiap alternatif dengan nilai kosong (`null`), bukan angka (`NaN`/`INF`), atau di luar rentang valid $[x_{\min,j}, x_{\max,j}]$ **dikeluarkan sebelum normalisasi** dan dicatat ke dalam daftar `excluded` beserta kode alasannya (`missing_or_invalid:{kode}` atau `out_of_range:{kode}`).

### Tahap 2 — Matriks Keputusan Ternormalisasi ($R$)
Normalisasi vektor Euclidean dilakukan per kolom kriteria $j$:
$$\text{norm}_j = \sqrt{\sum_{i=1}^{m} x_{ij}^2}$$
$$r_{ij} = \begin{cases} \dfrac{x_{ij}}{\text{norm}_j}, & \text{norm}_j > 10^{-12} \\ 0, & \text{norm}_j \le 10^{-12} \text{ (kolom bernilai nol seluruhnya)} \end{cases}$$

### Tahap 3 — Matriks Ternormalisasi Terbobot ($V$)
Setiap elemen ternormalisasi dikalikan dengan bobot kriteria AHP $w_j$:
$$v_{ij} = w_j \cdot r_{ij}$$

### Tahap 4 — Solusi Ideal Positif ($A^+$) dan Solusi Ideal Negatif ($A^-$)
Penentuan nilai ideal bergantung secara ketat pada tipe atribut kriteria (`benefit` vs `cost`):
- Untuk kriteria **benefit** ($j \in J_{\text{benefit}}$, semakin besar semakin diprioritaskan):
  $$A_j^+ = \max_{1 \le i \le m} v_{ij}, \qquad A_j^- = \min_{1 \le i \le m} v_{ij}$$
- Untuk kriteria **cost** ($j \in J_{\text{cost}}$, semakin kecil semakin diprioritaskan):
  $$A_j^+ = \min_{1 \le i \le m} v_{ij}, \qquad A_j^- = \max_{1 \le i \le m} v_{ij}$$

### Tahap 5 — Jarak Euclidean ke Solusi Ideal ($D_i^+$ dan $D_i^-$)
$$D_i^+ = \sqrt{\sum_{j=1}^{n} \left(v_{ij} - A_j^+\right)^2}, \qquad D_i^- = \sqrt{\sum_{j=1}^{n} \left(v_{ij} - A_j^-\right)^2}$$

### Tahap 6 — Nilai Preferensi Relatif ($C_i$) dan Pemeringkatan
$$C_i = \begin{cases} \dfrac{D_i^-}{D_i^+ + D_i^-}, & D_i^+ + D_i^- > 10^{-12} \\ 0, & D_i^+ + D_i^- \le 10^{-12} \end{cases}$$
Nilai $C_i$ berada pada rentang $[0, 1]$. Alternatif diurutkan menurun berdasarkan $C_i$ (nilai $C_i$ terbesar memperoleh peringkat `#1`).

---

## 2. Aturan Pemecahan Seri (*Tie-Breaking*) dan Kondisi Batas (PRD 05 §10)

1. **Alternatif layak kurang dari 2 ($m < 2$):** Perhitungan ditolak dengan `DecisionEngineException` (`insufficient_alternatives`).
2. **Kriteria bernilai konstan pada seluruh alternatif:** Normalisasi menghasilkan $r_{ij} = \frac{1}{\sqrt{m}}$ yang sama untuk seluruh alternatif sehingga $A_j^+ = A_j^-$; kriteria tersebut otomatis tidak mendistorsi jarak $D_i^+, D_i^-$ dan ditandai pada `flags.constant_criteria`.
3. **Seluruh alternatif identik ($D_i^+ + D_i^- = 0$):** Sistem menetapkan $C_i = 0$ tanpa pembagian nol dan menandai `flags.non_discriminative = true`.
4. **Aturan *Tie-Breaking* Deterministik:** Jika dua alternatif memiliki selisih $|C_a - C_b| \le 10^{-12}$, urutan ditentukan berturut-turut oleh:
   - Performa lebih unggul pada kriteria dengan bobot terbesar ($w_j$ tertinggi: nilai mentah lebih besar untuk `benefit`, lebih kecil untuk `cost`).
   - Kode referensi ruas jalan (`code`) secara leksikografis (`strcmp`).

---

## 3. Verifikasi Numerik terhadap Tabel Lampiran C.4

Dengan bobot AHP Lampiran C ($w_{K1} = 0{,}6483, w_{K2} = 0{,}2297, w_{K5} = 0{,}1220$) dan 4 alternatif:

| Alternatif | $K_1$ (Benefit) | $K_2$ (Benefit) | $K_5$ (Cost, m) | $D_i^+$ | $D_i^-$ | $C_i$ | Peringkat |
|---|---|---|---|---|---|---|---|
| `A1` | 4 | 3 | 150 | `0.1085` | `0.1959` | `0.6436` | **2** |
| `A2` | 5 | 2 | 600 | `0.1285` | `0.2647` | `0.6732` | **1** |
| `A3` | 3 | 5 | 300 | `0.1799` | `0.1391` | `0.4360` | **3** |
| `A4` | 2 | 4 | 100 | `0.2665` | `0.1078` | `0.2880` | **4** |

Hasil di atas diuji otomatis pada `TopsisServiceTest::test_lampiran_c_matches_printed_table` dan `DecisionEngineIntegrationTest::test_full_ahp_topsis_workflow_and_lampiran_c_verification`.

