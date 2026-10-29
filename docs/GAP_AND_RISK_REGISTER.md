# Register Kesenjangan Data & Risiko (`GAP_AND_RISK_REGISTER.md`)

**Tanggal pembaruan:** 29 Oktober 2026  
**Ruang lingkup:** Master PRD v2.0 §15 & PRD 01 Bagian B–C

---

## 1. Kesenjangan Data & Teknis yang Ditemukan pada Audit PRD 01

| ID Celah | Temuan Aktual | Dampak terhadap Sistem / Skripsi | Rencana Penyelesaian & Target Tahap |
|---|---|---|---|
| GAP-01 | **Kawasan BSD bukan satu wilayah administratif tunggal:** membentang di Kota Tangerang Selatan (Kec. Serpong, Kec. Serpong Utara, sebagian Kec. Setu) dan Kabupaten Tangerang (Kec. Pagedangan, Kec. Cisauk). | Validasi poligon wilayah studi (FR-REP-02) dan cakupan ruas jalan (D-02) tidak bisa sekadar merujuk "BSD" tanpa daftar kelurahan/kecamatan eksplisit. | Tetapkan poligon batas wilayah studi dari layer `BATAS_DESAKEL_AR` / `BATAS_KECAMATAN_AR` BIG (DS-01), misalnya Kecamatan Serpong (atau daftar kelurahan BSD yang disepakati bersama dosen pembimbing pada **D-02** sebelum PRD 02 dikunci). |
| GAP-02 | **Dualisme kewenangan jalan di BSD City:** sebagian ruas merupakan Jalan Kota/Kabupaten/Provinsi, sedangkan jalan boulevard dan klaster yang belum diserahkan sebagai PSU dikelola oleh pengembang (**Sinar Mas Land / Estate Management BSD**). | Jika tidak dibedakan, rekomendasi sistem berisiko disalahartikan sebagai instruksi resmi kepada satu instansi (R-07). | Tambahkan kolom `road_authority` (`nasional`, `provinsi`, `kota`, `kabupaten`, `kawasan_pengembang`, `belum_terverifikasi`) pada `road_segments` dan tampilkan disclaimer wajib pada seluruh halaman hasil (FR-UI-07). |
| GAP-03 | **Server Overpass API publik mengalami `502 Gateway Timeout` saat kueri langsung skala kawasan.** | Pengambilan geometri jalan OSM tidak boleh dilakukan secara *on-the-fly* dari backend ke Overpass API saat runtime aplikasi. | Unduh ekstrak statis OSM (Geofabrik Indonesia / BBBike / ekspor QGIS), potong sesuai poligon wilayah studi, dan impor ke tabel `road_segments` melalui seeder/command terverifikasi. |
| GAP-04 | **`ST_Distance_Sphere(POINT, LINESTRING)` gagal di MariaDB 10.4.32 (`ERROR 1815`).** | Map-matching jarak titik laporan ke garis ruas jalan (`segment_match_distance_m`, FR-REP-03) tidak dapat dihitung langsung dengan `ST_Distance_Sphere` di kueri SQL. | Gunakan `MBRIntersects` pada `SPATIAL INDEX` di SQL untuk menyaring kandidat ruas terdekat, lalu hitung jarak geodesik titik-ke-segmen garis dalam meter di service layer PHP. |
| GAP-05 | **Data volume lalu lintas resmi (LHR) untuk kriteria K4 tidak tersedia merata pada jalan lokal/kawasan.** | Jika K4 dipaksakan memakai angka LHR resmi, sebagian besar ruas lokal akan memiliki data hilang dan tereliminasi oleh aturan D-09. | Selesaikan lewat keputusan **D-06**: gunakan rubrik kelas intensitas lalu lintas hasil observasi lapangan (1–5) pada `damage_assessments`, atau keluarkan K4 dengan persetujuan pembimbing. |
| GAP-06 | **Tumpang tindih konseptual antara K1 (Tingkat kerusakan) dan K2 (Risiko keselamatan).** | Dalam AHP, kriteria harus sedapat mungkin independen agar tidak menghitung ganda faktor yang sama. | Pisahkan definisi operasional di `FIELD_PROTOCOL.md`: **K1** murni mengukur dimensi fisik kerusakan perkerasan (retak, lubang, alur), sedangkan **K2** mengukur bahaya kontekstual lokasi kerusakan (tikungan/tanjakan, kecepatan lalu lintas, drainase/genangan, visibilitas malam, potensi kecelakaan roda dua). |
| GAP-07 | **PHP 8.0.30 & Laravel 9.52.22 telah melewati masa dukungan keamanan (`composer audit`: 19 advisory).** | Aman untuk pengembangan lokal, namun berisiko tinggi bila dideploy ke server publik tanpa pembaruan. | Dicatat pada DEC-002; wajib upgrade ke PHP 8.2+ sebelum deployment publik (PRD 08). |

---

## 2. Register Risiko Proyek (Master PRD §15 + Status Mitigasi Aktual)

| ID | Risiko | Tingkat | Status Mitigasi Saat Ini |
|---|---|---|---|
| R-01 | Geometri ruas tidak tersedia atau lisensi tidak jelas | Tinggi | **Termitigasi di desain:** sumber geometri OSM (ODbL) dan batas BIG telah diidentifikasi di `DATA_SOURCE_REGISTER.md` beserta kewajiban atribusinya. |
| R-02 | Pakar sulit diperoleh atau penilaian tidak konsisten ($CR > 0{,}10$) | Tinggi | **Mesin siap:** `AhpService` menghitung $CR$ per pakar dan menolak versi bobot dengan $CR > 0{,}10$ pada run resmi (`SnapshotService`). Rekrutmen pakar harus dimulai sekarang (jalur non-kode). |
| R-03 | Tidak ada *ground truth* untuk evaluasi | Sedang | Evaluasi PRD 08 hanya mengklaim kesesuaian peringkat (Spearman $\rho$, Kendall $\tau$, top-$k$) terhadap penilaian independen, bukan akurasi absolut. |
| R-04 | Akurasi GPS rendah atau salah ruas | Sedang | Skema `damage_reports` menyimpan `gps_accuracy_m` dan `segment_match_distance_m`; penetapan ruas memerlukan konfirmasi petugas. |
| R-05 | Spam, laporan palsu, duplikat | Sedang | Dirancang pada PRD 03–04: akun terverifikasi, throttle, verifikasi petugas, dan deteksi kandidat duplikat (`duplicate_of_id`) tanpa penggabungan otomatis. |
| R-06 | Kebocoran data pribadi atau foto sensitif | Tinggi | Dirancang pada PRD 02–03: `public_id` ULID, penghapusan EXIF, `is_public` default `false`, dan pemisahan storage di luar webroot. |
| R-07 | Kewenangan jalan tidak jelas sehingga hasil dibaca sebagai keputusan resmi | Sedang | Kolom `road_authority` ditambahkan di `DATA_DICTIONARY.md`; `SnapshotService` menyertakan teks `disclaimer` pada setiap snapshot hasil. |
| R-08 | Peringkat sensitif terhadap bobot | Sedang | **Termitigasi di kode:** `SensitivityService` telah diimplementasikan dan diuji (Skenario A $\pm 10\%, \pm 20\%$ dan Skenario B bobot sama + korelasi Spearman). |
| R-09 | *Scope creep* | Sedang | Dikendalikan melalui gate antar-PRD dan pencatatan di `DECISIONS.md`. |
| R-10 | Agen AI mengarang data atau klaim tes | Tinggi | Seluruh klaim tes disertai keluaran terminal nyata; data contoh di `reference.json` diberi label ilustratif dan `SnapshotService` menolak `is_demo = true` pada run resmi. |
| R-11 | Pelanggaran ketentuan basemap | Sedang | OSM Tile Usage Policy telah diverifikasi di `DATA_SOURCE_REGISTER.md` (DS-03). |
| R-12 | Waktu terbatas untuk survei, pakar, responden | Tinggi | Draf `FIELD_PROTOCOL.md` telah disiapkan sejak PRD 01 agar kegiatan lapangan dan rekrutmen pakar dapat segera berjalan paralel. |

