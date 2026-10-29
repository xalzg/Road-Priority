# Metodologi Analytical Hierarchy Process (`AHP_METHODOLOGY.md`)

Dokumen ini mendokumentasikan spesifikasi matematis dan aturan operasional modul **AHP** pada `App\Services\DecisionEngine\AhpService` dan `CriterionWeightVersionService` (PRD 05 §5).

---

## 1. Skala Perbandingan Berpasangan Saaty

Untuk $n$ kriteria aktif ($1 \le n \le 10$), pakar memberikan penilaian perbandingan berpasangan $a_{ij}$ untuk setiap pasangan segitiga atas ($1 \le i < j \le n$) menggunakan skala fundamental Saaty:

- Bilangan bulat: $\{1, 2, 3, 4, 5, 6, 7, 8, 9\}$
- Nilai kebalikan (resiprokal): $\left\{\frac{1}{2}, \frac{1}{3}, \frac{1}{4}, \frac{1}{5}, \frac{1}{6}, \frac{1}{7}, \frac{1}{8}, \frac{1}{9}\right\}$ (dengan toleransi *floating-point* $10^{-9}$)

Aturan struktur matriks $A = [a_{ij}]_{n \times n}$:
- Diagonal utama selalu bernilai satu: $a_{ii} = 1$ untuk seluruh $i = 1, \dots, n$.
- Segitiga bawah bersifat resiprokal terhadap segitiga atas:
  $$a_{ji} = \frac{1}{a_{ij}}$$
- Jumlah pasangan unik segitiga atas yang wajib diisi untuk $n$ kriteria adalah:
  $$\frac{n(n-1)}{2}$$

---

## 2. Agregasi Multi-Pakar (Rata-Rata Geometrik / AIJ)

Apabila penilaian diberikan oleh $K$ pakar ($K \ge 1$) dengan matriks masing-masing $A^{(1)}, \dots, A^{(K)}$, sistem menggabungkannya menggunakan *Aggregation of Individual Judgments* (AIJ) berbasis rata-rata geometrik:

$$a_{ij}^{\text{gab}} = \left(\prod_{k=1}^{K} a_{ij}^{(k)}\right)^{1/K}$$

Sifat resiprokal tetap terjaga karena $\left(\prod_{k=1}^{K} \frac{1}{a_{ij}^{(k)}}\right)^{1/K} = \frac{1}{a_{ij}^{\text{gab}}}$.

---

## 3. Perhitungan Vektor Bobot Prioritas ($w_j$) dan $\lambda_{\max}$

`AhpService::computeWeights()` menghitung vektor eigen kanan utama (*principal right eigenvector*) menggunakan iterasi pangkat (*power iteration*):

1. Inisialisasi vektor awal seragam: $w^{(0)}_i = \frac{1}{n}$.
2. Pada setiap iterasi $t = 1, 2, \dots$:
   $$y_i^{(t)} = \sum_{j=1}^{n} a_{ij} w_j^{(t-1)}, \qquad w_i^{(t)} = \frac{y_i^{(t)}}{\sum_{k=1}^{n} y_k^{(t)}}$$
3. Iterasi berhenti ketika $\max_i |w_i^{(t)} - w_i^{(t-1)}| \le 10^{-13}$ (maksimum $10.000$ iterasi).
4. Nilai eigen maksimum $\lambda_{\max}$ dihitung dari rasio Rayleigh/vektor eigen terkonvergensi:
   $$\lambda_{\max} = \sum_{i=1}^{n} \left(\sum_{j=1}^{n} a_{ij} w_j\right)$$
   karena $\sum_{i=1}^{n} w_i = 1$.

---

## 4. Uji Konsistensi ($CI$ dan $CR$)

Indeks Konsistensi (*Consistency Index* / $CI$) dan Rasio Konsistensi (*Consistency Ratio* / $CR$) dihitung dengan rumus:

$$CI = \begin{cases} 0, & n \le 2 \\ \dfrac{\lambda_{\max} - n}{n - 1}, & n \ge 3 \end{cases}$$

$$CR = \begin{cases} 0, & n \le 2 \\ \dfrac{CI}{RI_n}, & n \ge 3 \end{cases}$$

### Tabel Random Index ($RI_n$) Saaty yang Digunakan

| $n$ | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|---|---|---|---|---|---|---|---|---|---|---|
| $RI_n$ | $0{,}00$ | $0{,}00$ | $0{,}58$ | $0{,}90$ | $1{,}12$ | $1{,}24$ | $1{,}32$ | $1{,}41$ | $1{,}45$ | $1{,}49$ |

### Aturan Kelulusan Konsistensi
- Jika $CR \le 0{,}10$: matriks dinyatakan **konsisten** (`is_consistent = true`) dan dapat disetujui (`approved`) oleh Admin melalui `POST /api/criterion-weight-versions/{id}/activate`.
- Jika $CR > 0{,}10$: matriks dinyatakan **tidak konsisten** (`is_consistent = false`). Sistem menyimpan hasil perhitungan untuk ditinjau, tetapi **menolak** aktivasi versi bobot (`422 weight_version_inconsistent`) serta **menolak** penggunaannya pada perhitungan TOPSIS resmi (`422`).

---

## 5. Verifikasi Numerik terhadap Contoh Independen (Lampiran C)

Pada matriks perbandingan $3 \times 3$ untuk kriteria $K_1, K_2, K_5$:
$$A = \begin{bmatrix} 1 & 3 & 5 \\ 1/3 & 1 & 2 \\ 1/5 & 1/2 & 1 \end{bmatrix}$$

Hasil perhitungan `AhpService` (terverifikasi identik hingga $< 10^{-9}$ terhadap solver polinomial karakteristik Python di `backend/tests/Reference/ahp_topsis_reference.py`):
- $w_{K1} = 0{,}648329$, $w_{K2} = 0{,}229651$, $w_{K5} = 0{,}122020$ ($\sum w_j = 1{,}000000$)
- $\lambda_{\max} = 3{,}003695$
- $CI = 0{,}001847$
- $CR = \frac{0{,}001847}{0{,}58} = 0{,}003185 \le 0{,}10$ (**Konsisten**)

