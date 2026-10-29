# Draf Protokol Penilaian Kondisi Jalan di Lapangan (`FIELD_PROTOCOL.md`)

**Versi protokol:** `SDI-ADAPTED-1.0` (Draf untuk persetujuan dosen pembimbing — **D-08**)  
**Tanggal penyusunan:** 29 Oktober 2026  
**Ruang lingkup:** Master PRD v2.0 — PRD 01 Bagian D & PRD 05 §5.1

> **Catatan status [PERLU VERIFIKASI & PERSETUJUAN D-08]:** Protokol ini mengadaptasi empat parameter pengamatan visual dari metode *Surface Distress Index* (SDI) Direktorat Jenderal Bina Marga yang dipetakan ke skala ordinal 1–5 untuk kriteria **K1** (Tingkat kerusakan), serta rubrik terpisah untuk **K2** (Risiko keselamatan) dan **K4** (Kepadatan pengguna jalan). Wajib disetujui dosen pembimbing sebelum survei lapangan resmi dimulai.

---

## 1. Tujuan & Pemisahan Antar-Kriteria

Agar tidak terjadi penghitungan ganda (*double counting*) antara kriteria pada metode AHP-TOPSIS:
- **K1 (Tingkat Kerusakan Fisik):** hanya menilai kondisi fisik permukaan perkerasan jalan (luas retak, lebar retak, jumlah lubang, kedalaman alur/amblas).
- **K2 (Risiko Keselamatan Kontekstual):** menilai tingkat bahaya bagi pengguna jalan akibat interaksi kerusakan dengan geometri jalan, kecepatan arus, visibilitas/penerangan, dan drainase/genangan.
- **K4 (Intensitas Pengguna Jalan — bila memakai observasi lapangan):** menilai kelas kepadatan arus kendaraan yang melintasi ruas tersebut.

---

## 2. Pemilihan & Jumlah Ruas Sampel

1. **Unit amatan:** ruas jalan (`road_segments`) di dalam poligon wilayah studi BSD dan sekitarnya (D-01, D-02). Untuk ruas yang sangat panjang ($> 500\text{ m}$), pengamatan dilakukan per segmen $100\text{ m} \text{ s.d. } 200\text{ m}$ dan diambil segmen dengan kondisi terburuk atau nilai agregat sesuai kesepakatan pembimbing.
2. **Jumlah sampel usulan:** minimal **15–30 ruas jalan** yang mencakup variasi fungsi jalan (`arteri`, `kolektor`, `lokal`, `lingkungan`) dan tingkat kerusakan agar matriks TOPSIS serta uji korelasi peringkat (Spearman/Kendall pada PRD 08) memiliki daya beda yang memadai.

---

## 3. Rubrik Pengukuran Lapangan

### 3.1 Parameter Fisik SDI Bina Marga & Pemetaan ke Skor K1 (Skala 1–5, Benefit)

Catat empat parameter fisik pada objek JSON `damage_assessments.measurements`:
1. `crack_area_pct` ($\text{SDI}_1$): estimasi persentase luas retak terhadap luas segmen amatan ($0\%$, $<10\%$, $10\text{–}30\%$, $>30\%$).
2. `crack_width_mm` ($\text{SDI}_2$): rata-rata lebar bukaan retak ($0$, $<1\text{ mm}$ halus, $1\text{–}3\text{ mm}$ sedang, $>3\text{ mm}$ lebar).
3. `pothole_count_per_100m` ($\text{SDI}_3$): jumlah lubang per $100\text{ m}$ ($0$, $<10$, $10\text{–}50$, $>50$ lubang).
4. `rutting_depth_cm` ($\text{SDI}_4$): kedalaman alur/bekas roda atau amblas ($0$, $<1\text{ cm}$, $1\text{–}3\text{ cm}$, $>3\text{ cm}$).

**Tabel Konversi ke Skor K1 (`severity_score`, Benefit — semakin tinggi semakin diprioritaskan):**

| Skor K1 | Kategori Kondisi | Acuan Nilai SDI / Indikator Visual Lapangan |
|---|---|---|
| **1** | Sangat Baik | $\text{SDI} < 25$; permukaan rata, tidak ada lubang, retak rambut sangat minim ($<5\%$). |
| **2** | Baik | $25 \le \text{SDI} < 50$; retak halus lokal ($<10\%$), belum ada lubang terbuka atau alur berarti. |
| **3** | Sedang | $50 \le \text{SDI} < 100$; retak cukup terlihat ($10\text{–}30\%$) atau terdapat $1\text{–}5$ lubang dangkal per $100\text{ m}$. |
| **4** | Rusak Ringan | $100 \le \text{SDI} \le 150$; retak buaya meluas, $6\text{–}20$ lubang per $100\text{ m}$, atau alur sedalam $1\text{–}3\text{ cm}$. |
| **5** | Rusak Berat | $\text{SDI} > 150$; perkerasan terkelupas/amblas parah, $>20$ lubang atau lubang dalam ($>5\text{ cm}$) yang membahayakan struktur jalan. |

### 3.2 Rubrik Skor K2 — Risiko Keselamatan (`safety_risk_score`, Skala 1–5, Benefit)

| Skor K2 | Tingkat Risiko | Deskripsi Berjangkar (*Anchored Scale*) |
|---|---|---|
| **1** | Sangat Rendah | Kerusakan berada di tepi jalan lurus berkecepatan rendah, terlihat jelas siang/malam, tidak mengganggu lajur kendaraan. |
| **2** | Rendah | Kerusakan di lajur jalan lurus dengan visibilitas baik; pengendara dapat melintas pelan tanpa manuver mendadak. |
| **3** | Sedang | Kerusakan memaksa kendaraan melambat atau sedikit menghindar di dalam lajur sendiri; penerangan cukup. |
| **4** | Tinggi | Kerusakan berada di lajur roda utama sehingga pengendara (khususnya roda dua) harus berpindah lajur/zig-zag, atau tertutup genangan air saat hujan. |
| **5** | Sangat Tinggi | Kerusakan berada di tikungan, turunan, persimpangan padat, atau area minim lampu jalan, berpotensi langsung menyebabkan jatuh/tabrakan. |

### 3.3 Rubrik Skor K3 — Fungsi Jalan (`road_function`, Skala 1–4, Benefit)

| Skor K3 | Klasifikasi Fungsi (`road_segments.road_function`) | Padanan Umum Tag OSM (`highway`) |
|---|---|---|
| **4** | Jalan Arteri (`arteri`) | `trunk`, `primary` |
| **3** | Jalan Kolektor (`kolektor`) | `secondary` |
| **2** | Jalan Lokal (`lokal`) | `tertiary`, `unclassified` |
| **1** | Jalan Lingkungan (`lingkungan`) | `residential`, `living_street` |

### 3.4 Rubrik Proksi K4 — Intensitas Pengguna Jalan (`traffic_intensity_score`, Skala 1–5, Benefit)

Digunakan apabila data LHR resmi tidak tersedia untuk seluruh ruas sampel (Opsi K4-a pada `DATA_SOURCE_REGISTER.md`):

| Skor K4 | Kelas Kepadatan | Indikator Pengamatan (Hitung Cepat 15 Menit pada Jam Sibuk) |
|---|---|---|
| **1** | Sangat Sepi | Jalan buntu/klaster dalam; $< 20$ kendaraan per 15 menit. |
| **2** | Sepi | Jalan lingkungan/penghubung lokal; $20\text{–}60$ kendaraan per 15 menit. |
| **3** | Sedang | Jalan lokal ramai / penghubung antarkawasan; $61\text{–}150$ kendaraan per 15 menit. |
| **4** | Padat | Jalan kolektor / akses fasilitas utama; $151\text{–}300$ kendaraan per 15 menit. |
| **5** | Sangat Padat | Koridor utama / boulevard / arteri; $> 300$ kendaraan per 15 menit. |

---

## 4. Perlengkapan, Prosedur Keselamatan, dan Etika Lapangan

1. **Perlengkapan:** ponsel dengan GPS aktif dan kamera, meteran/penggaris untuk mengukur lebar retak & kedalaman lubang dari tepi jalan, rompi reflektif, dan formulir survei cetak/digital.
2. **Keselamatan kerja (wajib):**
   - Pengamatan dilakukan dari **bahu jalan atau trotoar**; dilarang berdiri di tengah lajur kendaraan aktif untuk mengukur lubang.
   - Survei dilakukan pada siang hari dengan cuaca cerah dan minimal berdua.
3. **Privasi & etika dokumentasi foto:**
   - Hindari memotret wajah pejalan kaki atau pelat nomor kendaraan dari jarak dekat.
   - Sistem akan menghapus metadata EXIF pada versi publik dan menyediakan fitur sembunyikan foto (`is_public = false` secara bawaan).
4. **Perizinan:** untuk jalan di dalam klaster tertutup yang dikelola estate management, lakukan pengamatan hanya pada ruas terbuka untuk umum atau minta izin pengelola setempat terlebih dahulu.
