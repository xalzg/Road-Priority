# Rencana Tinjauan Literatur Terstruktur (`LITERATURE_PLAN.md`)

**Tanggal penyusunan:** 29 Oktober 2026  
**Ruang lingkup:** Master PRD v2.0 — PRD 01 Bagian F & Lampiran D (Untuk BAB II Skripsi)

> **Aturan integritas akademik:** Dokumen ini adalah protokol pencarian dan penyaringan literatur. Jangan mengutip artikel, tahun, atau halaman ke dalam naskah skripsi sebelum berkas PDF/sumber aslinya diperiksa sendiri oleh penulis.

---

## 1. Tujuan Tinjauan Literatur

1. Memetakan penelitian terdahulu yang menggabungkan **WebGIS** dan **Sistem Pendukung Keputusan (SPK / MCDM)** untuk **prioritas penanganan/pemeliharaan jalan rusak**.
2. Memvalidasi pemilihan kriteria kandidat (**K1–K7**, keputusan **D-06**) terhadap literatur akademik dan pedoman teknis jalan di Indonesia.
3. Memverifikasi rujukan metodologis primer untuk **AHP** (Saaty), **TOPSIS** (Hwang & Yoon), **SDI Bina Marga**, **System Usability Scale (SUS)** (Brooke), dan korelasi peringkat **Spearman/Kendall**.

---

## 2. Basis Data & Kata Kunci Pencarian

### 2.1 Basis Data Akademik
- **Internasional:** Scopus / ScienceDirect, IEEE Xplore, SpringerLink, Google Scholar, Semantic Scholar.
- **Nasional (Indonesia):** Garuda (Garba Rujukan Digital Kemdikbudristek), SINTA (jurnal terakreditasi S1–S4 bidang Sistem Informasi, Teknik Sipil, dan Geomatika).

### 2.2 Kueri Pencarian Terstruktur

| Kelompok Topik | Kueri Bahasa Indonesia | Kueri Bahasa Inggris |
|---|---|---|
| SPK Prioritas Jalan (AHP-TOPSIS) | `("prioritas perbaikan jalan" OR "pemeliharaan jalan") AND ("AHP" AND "TOPSIS")` | `("road maintenance priority" OR "pavement maintenance prioritization") AND ("AHP" AND "TOPSIS")` |
| Integrasi WebGIS + Kerusakan Jalan | `("WebGIS" OR "SIG berbasis web") AND ("kerusakan jalan" OR "pelaporan jalan rusak") AND ("sistem pendukung keputusan")` | `("WebGIS" OR "spatial decision support system") AND ("road damage" OR "pavement distress") AND ("MCDM" OR "TOPSIS")` |
| Kriteria & Metode Penilaian Kondisi (SDI) | `"Surface Distress Index" AND "Bina Marga" AND "prioritas"` | `("Surface Distress Index" OR "Pavement Condition Index") AND ("AHP" OR "TOPSIS")` |

---

## 3. Kriteria Inklusi & Eksklusi Penelitian Terdahulu (BAB II)

| Kriteria | Inklusi (Diterima) | Eksklusi (Ditolak) |
|---|---|---|
| **Rentang tahun** | 5–7 tahun terakhir untuk penelitian terdahulu terapan; tanpa batas tahun untuk pustaka metode klasik (Saaty 1980, Hwang & Yoon 1981, Brooke 1996). | Artikel terapan lama yang tidak memiliki teks lengkap atau tidak jelas metodologinya. |
| **Kesesuaian domain** | Membahas penentuan prioritas pemeliharaan/penanganan jalan, infrastruktur transportasi, atau pelaporan kerusakan jalan berbasis GIS. | Hanya membahas klasifikasi citra *deep learning* tanpa komponen pemetaan atau keputusan prioritas. |
| **Transparansi metode** | Mencantumkan kriteria yang dipakai, cara pembobotan, dan metode perankingan alternatif. | Artikel yang tidak menjelaskan kriteria atau hanya menampilkan tangkapan layar tanpa evaluasi. |
| **Keterperiksaan** | Memiliki DOI, URL jurnal resmi, atau prosiding yang dapat diakses dan diperiksa. | Kutipan sekunder yang tidak dapat dilacak sumber aslinya. |

---

## 4. Matriks Ekstraksi Literatur (Templat Tabel BAB II)

Saat membaca setiap artikel yang lolos seleksi, catat dalam tabel berikut untuk menyusun *state of the art* dan merumuskan celah penelitian secara jujur:

| No | Penulis & Tahun | Judul & Jurnal/Prosiding | Unit Analisis (Ruas / Titik / Wilayah) | Metode MCDM | Kriteria yang Digunakan | Integrasi WebGIS & Alur Verifikasi Laporan | Metode Evaluasi (CR, Sensitivitas, Korelasi, SUS) | Keterbatasan / Celah terhadap RoadPriority |
|---|---|---|---|---|---|---|---|---|
| 1 | *[Diisi setelah baca PDF]* | | | | | | | |

---

## 5. Daftar Pustaka Metodologis yang Wajib Diverifikasi Fisik/PDF-nya

1. **AHP & Tabel RI:** Saaty, T. L. (1980). *The Analytic Hierarchy Process*. McGraw-Hill. *(Verifikasi tabel RI untuk $n=1\dots 10$ pada edisi yang dirujuk)*.
2. **TOPSIS:** Hwang, C. L., & Yoon, K. (1981). *Multiple Attribute Decision Making: Methods and Applications*. Springer-Verlag.
3. **SUS:** Brooke, J. (1996). *SUS: A quick and dirty usability scale*. Dalam *Usability Evaluation in Industry*.
4. **Regulasi Jalan:** UU No. 38 Tahun 2004 tentang Jalan (beserta perubahan UU No. 2 Tahun 2022) dan PP No. 34 Tahun 2006 tentang Jalan *(untuk definisi fungsi arteri, kolektor, lokal, lingkungan dan status kewenangan jalan)*.
5. **Privasi Data:** UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP).
6. **Panduan Survei Kondisi Jalan:** Manual/Panduan Survei Kondisi Jalan Direktorat Jenderal Bina Marga (metode SDI, No. SMD-03/RCS atau dokumen pedoman terbaru yang berlaku).

