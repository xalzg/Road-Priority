# Spesifikasi Formula & Algoritma: Mesin Keputusan AHP-TOPSIS

**Versi algoritma:** `ahp-topsis-1.0.0` (`TopsisService::ALGORITHM_VERSION`)  
**Implementasi:** `backend/app/Services/DecisionEngine/`  
**Referensi independen:** `backend/tests/Reference/ahp_topsis_reference.py`  
**Fixture uji tanpa pembulatan:** `backend/tests/Fixtures/reference.json`

Dokumen ini mendefinisikan rumus matematis, aturan presisi, tie-breaking, penanganan kasus tepi, dan analisis sensitivitas yang diimplementasikan pada mesin keputusan RoadPriority sesuai Master PRD v2.0 (PRD 05 dan Lampiran C).

---

## 1. Analytic Hierarchy Process (AHP)

Implementasi berada di `App\Services\DecisionEngine\AhpService`.

### 1.1 Matriks Perbandingan Berpasangan

Untuk $n$ kriteria ($1 \le n \le 10$), matriks perbandingan berpasangan $A = [a_{ij}]_{n \times n}$ memenuhi:

1. **Kepositifan:** $a_{ij} > 0$ untuk seluruh $i, j \in \{1, \dots, n\}$.
2. **Diagonal utama:** $a_{ii} = 1$ (toleransi validasi $10^{-9}$).
3. **Resiprokal:** $a_{ji} = 1 / a_{ij}$, atau $|a_{ij} \cdot a_{ji} - 1| \le 10^{-9}$.
4. **Skala Saaty:** setiap penilaian pakar tunggal diambil dari himpunan $\{1, 2, 3, 4, 5, 6, 7, 8, 9\}$ atau kebalikannya $\{1/2, \dots, 1/9\}$.

### 1.2 Agregasi Penilaian Kelompok (AIJ)

> **Status keputusan:** agregasi *geometric mean* (Aggregation of Individual Judgments / AIJ) berstatus **[USULAN, D-07]**.

Jika terdapat $K \ge 1$ pakar dengan matriks resiprokal $A^{(1)}, \dots, A^{(K)}$, matriks gabungan $A^{\text{group}} = [g_{ij}]$ dihitung per pasangan untuk $i < j$:

$$g_{ij} = \left( \prod_{k=1}^{K} a_{ij}^{(k)} \right)^{1/K} = \exp\left( \frac{1}{K} \sum_{k=1}^{K} \ln a_{ij}^{(k)} \right), \qquad g_{ji} = \frac{1}{g_{ij}}, \qquad g_{ii} = 1$$

Konsistensi ($CR$) setiap pakar wajib diperiksa secara individual sebelum digabungkan.

### 1.3 Vektor Bobot Prioritas (Eigenvector Utama)

Bobot prioritas $w = (w_1, \dots, w_n)^T$ adalah eigenvector utama yang dinormalisasi sehingga $\sum_{i=1}^n w_i = 1$. Dihitung dengan *power iteration*:

1. Inisialisasi $w^{(0)}_i = 1/n$.
2. Pada iterasi $t = 1, 2, \dots, T_{\max}$ ($T_{\max} = 10\,000$):
   $$y^{(t)} = A w^{(t-1)}, \qquad w^{(t)}_i = \frac{y^{(t)}_i}{\sum_{j=1}^n y^{(t)}_j}$$
3. Berhenti ketika $\max_i |w^{(t)}_i - w^{(t-1)}_i| < 10^{-12}$. Jika belum konvergen pada $T_{\max}$, lempar `DecisionEngineException` (`ahp_not_converged`).

### 1.4 Uji Konsistensi

Nilai eigen maksimum $\lambda_{\max}$, *Consistency Index* ($CI$), dan *Consistency Ratio* ($CR$):

$$\lambda_{\max} = \frac{1}{n} \sum_{i=1}^{n} \frac{(Aw)_i}{w_i}, \qquad CI = \frac{\lambda_{\max} - n}{n - 1}, \qquad CR = \begin{cases} 0, & n \le 2 \\ \dfrac{CI}{RI_n}, & n \ge 3 \end{cases}$$

Jika karena pembulatan floating-point diperoleh $-10^{-12} < CI < 0$, nilai $CI$ ditetapkan $0$.

Tabel *Random Index* ($RI_n$) yang digunakan (Master PRD §5.2; **[PERLU VERIFIKASI terhadap sumber edisi yang dikutip di skripsi]**):

| $n$ | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|---|---|---|---|---|---|---|---|---|---|---|
| $RI_n$ | 0,00 | 0,00 | 0,58 | 0,90 | 1,12 | 1,24 | 1,32 | 1,41 | 1,45 | 1,49 |

- Matriks dinyatakan **konsisten** (`is_consistent = true`) jika dan hanya jika $CR \le 0{,}10$.
- Versi bobot dengan $CR > 0{,}10$ atau berstatus selain `approved` ditolak pada decision run resmi (`SnapshotService`), dan hanya diizinkan bila `is_simulation = true`.

---

## 2. TOPSIS

Implementasi berada di `App\Services\DecisionEngine\TopsisService`.

### 2.1 Penyaringan Kelayakan Alternatif

Sebelum membentuk matriks keputusan, alternatif diurutkan secara kanonik berdasarkan `code` (menaik, perbandingan byte/ASCII) agar hasil dan hash input tidak bergantung pada urutan pengiriman data.

Setiap alternatif diperiksa terhadap seluruh kriteria:
- Nilai tidak boleh `null`, bukan angka, `NaN`, atau `INF` $\rightarrow$ alasan pengecualian `missing_or_invalid:{kode}`.
- Jika kriteria mendefinisikan `min` dan/atau `max`, nilai di luar $[min, max]$ $\rightarrow$ alasan pengecualian `out_of_range:{kode}`.

Alternatif yang memiliki minimal satu alasan pengecualian dikeluarkan dari matriks keputusan dan dicatat dalam daftar `excluded` (**[USULAN, D-09]**). Jika alternatif yang memenuhi syarat tersisa $m < 2$, run ditolak (`insufficient_alternatives`).

### 2.2 Normalisasi Vektor

Untuk matriks keputusan $X = [x_{ij}]_{m \times n}$ ($i = 1, \dots, m$ alternatif layak; $j = 1, \dots, n$ kriteria):

$$d_j = \sqrt{\sum_{i=1}^{m} x_{ij}^2}, \qquad r_{ij} = \begin{cases} 0, & d_j = 0 \\ \dfrac{x_{ij}}{d_j}, & d_j > 0 \end{cases}$$

- Jika $d_j = 0$ (seluruh nilai pada kolom $j$ bernilai $0$), $r_{ij} = 0$ dan kode kriteria dimasukkan ke `flags.zero_norm_criteria`.
- Jika $\max_i x_{ij} - \min_i x_{ij} \le 10^{-12}$, kode kriteria dimasukkan ke `flags.constant_criteria` (kriteria tidak diskriminatif).

### 2.3 Matriks Terbobot

Dengan bobot $w_j \ge 0$ dan $|\sum_{j=1}^n w_j - 1| \le 10^{-9}$:

$$v_{ij} = w_j \cdot r_{ij}$$

### 2.4 Solusi Ideal Positif ($A^+$) dan Negatif ($A^-$)

Untuk setiap kriteria $j$:

$$A^+_j = \begin{cases} \max_i v_{ij}, & \text{jika } j \text{ bertipe benefit} \\ \min_i v_{ij}, & \text{jika } j \text{ bertipe cost} \end{cases}, \qquad A^-_j = \begin{cases} \min_i v_{ij}, & \text{jika } j \text{ bertipe benefit} \\ \max_i v_{ij}, & \text{jika } j \text{ bertipe cost} \end{cases}$$

### 2.5 Jarak Euclid dan Nilai Preferensi ($C_i$)

$$D^+_i = \sqrt{\sum_{j=1}^{n} (v_{ij} - A^+_j)^2}, \qquad D^-_i = \sqrt{\sum_{j=1}^{n} (v_{ij} - A^-_j)^2}$$

$$C_i = \begin{cases} 0{,}5, & \text{jika } D^+_i + D^-_i \le 10^{-15} \text{ untuk seluruh } i \\ \dfrac{D^-_i}{D^+_i + D^-_i}, & \text{lainnya} \end{cases}$$

Ketika $D^+_i + D^-_i \le 10^{-15}$ untuk seluruh alternatif (semua alternatif bernilai identik pada seluruh kriteria ber-bobot $>0$), seluruh $C_i$ ditetapkan $0{,}5$ dan `flags.non_discriminative_run = true`.

### 2.6 Aturan Peringkat dan Tie-Breaking Deterministik

Alternatif diurutkan berdasarkan $C_i$ menurun (peringkat $1$ = prioritas tertinggi). Dua alternatif berurutan pada urutan $C_i$ dianggap **seri** apabila $|C_a - C_b| \le 10^{-9}$.

Apabila terjadi seri di antara sekelompok alternatif:
1. **Kriteria kunci (`tie_breaker_criterion`):** pilih kriteria $j^*$ dengan bobot $w_j$ terbesar (bila bobot seri dalam $10^{-12}$, pilih kode kriteria terkecil secara leksikografis). Bandingkan nilai terbobot $v_{i, j^*}$:
   - Jika $j^*$ bertipe `benefit`, nilai $v_{i, j^*}$ lebih besar mendapat peringkat lebih tinggi.
   - Jika $j^*$ bertipe `cost`, nilai $v_{i, j^*}$ lebih kecil mendapat peringkat lebih tinggi.
2. **Kode ruas (`code`):** jika $|v_{a, j^*} - v_{b, j^*}| \le 10^{-12}$, urutkan berdasarkan `code` menaik (`strcmp`).

### 2.7 Kriteria Pendorong Terbesar (`largest_driving_criterion`)

Pada `SnapshotService`, untuk setiap alternatif $i$, kriteria pendorong terbesar menuju prioritas tinggi didefinisikan sebagai kriteria yang memberikan simpangan terbobot terbesar dari solusi ideal negatif:

$$j_{\text{driver}}(i) = \arg\max_{j} |v_{ij} - A^-_j|$$

(bila seri dalam $10^{-12}$, dipilih kode kriteria terkecil).

---

## 3. Hash Input Kanonik & Reproduksibilitas (`InputHasher`, `SnapshotService`)

1. Struktur input kanonik mencakup `algorithm_version`, `criteria`, `weights`, dan `alternatives` (yang telah diurutkan berdasarkan `code`).
2. Seluruh kunci array asosiatif diurutkan dengan `ksort(..., SORT_STRING)`, dan seluruh bilangan (`int`/`float`) diformat menggunakan `sprintf('%.12F', $value)`.
3. Hash dihitung sebagai `hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))`.
4. `SnapshotService::reproduce($snapshot)` menjalankan ulang `TopsisService::calculate` pada `criteria_snapshot`, `weight_version_snapshot.weights`, dan `alternatives_snapshot`, lalu memverifikasi bahwa `input_hash`, urutan kode, peringkat, $C_i$, $D^+_i$, dan $D^-_i$ cocok dengan toleransi $10^{-12}$.

---

## 4. Analisis Sensitivitas & Korelasi Peringkat

Implementasi berada di `SensitivityService` dan `RankCorrelation`.

### 4.1 Skenario Sensitivitas

- **Skenario A (one-at-a-time):** untuk setiap kriteria $k$ dan setiap perubahan relatif $\delta \in \{-20\%, -10\%, +10\%, +20\%\}$:
  $$w'_k = w_k (1 + \delta), \qquad w'_j = w_j \cdot \frac{1 - w'_k}{1 - w_k} \quad (j \ne k)$$
  Jika $w_k \ge 1$ atau $w'_k \notin [0, 1]$, skenario tersebut dilewati dan dicatat pada `skipped`.
- **Skenario B (equal weights):** $w'_j = 1/n$ untuk seluruh $j$.

### 4.2 Korelasi Peringkat Spearman ($\rho$)

Untuk dua vektor peringkat $R^{(A)}$ dan $R^{(B)}$ pada himpunan alternatif yang sama ($m \ge 2$), `RankCorrelation::spearman` menghitung korelasi Pearson antara kedua vektor peringkat:

$$\rho = \frac{\sum_{i=1}^m (R^{(A)}_i - \bar{R}^{(A)})(R^{(B)}_i - \bar{R}^{(B)})}{\sqrt{\sum_{i=1}^m (R^{(A)}_i - \bar{R}^{(A)})^2} \sqrt{\sum_{i=1}^m (R^{(B)}_i - \bar{R}^{(B)})^2}}$$

Rumus ini identik dengan $1 - \frac{6 \sum d_i^2}{m(m^2 - 1)}$ ketika tidak ada peringkat kembar, dan otomatis menerapkan koreksi tie ketika peringkat seri diberi nilai rata-rata.

---

## 5. Verifikasi terhadap Hitungan Independen (Lampiran C)

Skrip `backend/tests/Reference/ahp_topsis_reference.py` menghitung contoh Lampiran C menggunakan metode yang berbeda dari PHP (akar polinomial karakteristik $\det(A - \lambda I) = 0$ dengan pemindaian + bisection serta eliminasi Gauss, tanpa NumPy). Selisih maksimum antara PHP (`AhpService`, `TopsisService`, `SensitivityService`) dan fixture Python (`backend/tests/Fixtures/reference.json`) adalah $< 10^{-12}$, jauh di bawah batas toleransi $10^{-6}$ pada indikator T4.

