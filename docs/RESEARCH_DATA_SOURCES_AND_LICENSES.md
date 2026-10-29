# Sumber Data Penelitian, Keterlacakan, dan Kepatuhan Lisensi (`RESEARCH_DATA_SOURCES_AND_LICENSES.md`)

**Proyek:** RoadPriority — Sistem Informasi Pelaporan Jalan Rusak & SPK Prioritas Perbaikan (AHP-TOPSIS)  
**Tahap:** PRD 05.3 — Research Data Acquisition & Validation  
**Tanggal Verifikasi:** 30 Oktober 2026  

---

## 1. Prinsip Kebenaran Sumber & Klasifikasi Kredibilitas

Sesuai aturan **PRD 05.3 §4**:
1. **Data di internet tidak otomatis berstatus "Data Resmi Pemerintah":** Data peta kolaboratif (*crowdsourced*) seperti OpenStreetMap dibedakan secara tegas dari produk geospasial/administratif resmi pemerintah (seperti Peta RBI Badan Informasi Geospasial atau Surat Keputusan Wali Kota/Bupati tentang Status Ruas Jalan).
2. **Kejujuran Status Akuisisi File:** Tidak ada file dataset eksternal yang diklaim telah diunduh ke dalam repository ini apabila proses pengunduhan dan kurasi belum benar-benar dilakukan oleh peneliti.
3. **Pemisahan Kolom `data_origin` dan `assessment_source` di Database:**
   - `official_government`: Data yang berasal langsung dari produk hukum/instansi pemerintah resmi (BIG, Kementerian PUPR, Dinas SDABMBK Kota Tangsel, Dinas BMSDA Kab. Tangerang).
   - `public_dataset`: Dataset terbuka kolaboratif yang memiliki lisensi jelas dan dapat diaudit tetapi bukan dokumen penetapan resmi pemerintah (mis. OpenStreetMap di bawah lisensi ODbL 1.0).
   - `field_survey` / `field_inspection`: Data primer yang diamati dan diukur langsung di lapangan oleh peneliti/petugas menggunakan protokol `SDI-ADAPTED-1.0`.
   - `report_verification`: Data kondisi jalan yang berasal dari verifikasi fisik petugas atas laporan masyarakat (`damage_reports`).
   - `demo` / `demo_fixture`: Fixture simulasi semata (`is_demo = true`) yang dilarang masuk ke perhitungan keputusan resmi.

---

## 2. Katalog Sumber Data yang Diverifikasi (PRD 05.3 §4)

### 2.1 Sumber Batas Administratif & Peta Dasar Pemerintah (`DS-01`)
- **Nama Sumber:** Peta Rupa Bumi Indonesia (RBI) Skala 1:25.000 & Layer Batas Wilayah Administrasi (`BATAS_KECAMATAN_AR`, `BATAS_DESAKEL_AR`, `JALAN_LN_25K`)
- **Kategori Kredibilitas:** **Data Geospasial Resmi Pemerintah (IGD — Informasi Geospasial Dasar)**
- **Penerbit:** Badan Informasi Geospasial (BIG) Republik Indonesia
- **URL yang Dapat Diakses:** https://tanahair.indonesia.go.id (Ina-Geoportal)
- **Tanggal Verifikasi Akses URL:** 30 Oktober 2026
- **Cakupan Geografis:** Nasional (termasuk Provinsi Banten: Kota Tangerang Selatan — Kec. Serpong, Kec. Serpong Utara, Kec. Setu; serta Kabupaten Tangerang — Kec. Pagedangan, Kec. Cisauk)
- **Format Tersedia:** Shapefile (`.shp`), File Geodatabase (`.gdb`), Layanan WebGIS/WMS
- **Lisensi & Ketentuan Penggunaan:** Kebijakan Data Terbuka BIG melalui Ina-Geoportal. Wajib mencantumkan atribusi sumber: *"Sumber Peta Dasar / Batas Wilayah: Badan Informasi Geospasial (BIG)"*. Pengunduhan data vektor memerlukan akun terdaftar (gratis) milik pengguna di portal Ina-Geoportal.
- **Status Ketersediaan Lokal Saat Ini:** **Perlu diperoleh dari pihak eksternal** (belum diunduh ke repository lokal karena memerlukan login akun pribadi peneliti pada Ina-Geoportal dan penetapan cakupan kelurahan/kecamatan pada `D-02`).
- **Keterbatasan:**
  1. Kawasan kota mandiri "BSD City" merupakan kawasan pengembangan lintas batas administratif (sebagian di Kota Tangerang Selatan dan sebagian di Kabupaten Tangerang), sehingga tidak ada satu poligon administratif tunggal bernama "BSD" pada peta resmi BIG.
  2. Peneliti harus memilih daftar kecamatan/kelurahan secara eksplisit (misalnya seluruh kelurahan di Kecamatan Serpong, Kota Tangerang Selatan, atau gabungan kelurahan BSD di Kec. Serpong, Pagedangan, dan Cisauk).
- **Metode Validasi:**
  1. Periksa sistem referensi koordinat (pastikan `EPSG:4326` / WGS 84 sebelum diekspor ke GeoJSON).
  2. Cocokkan kode wilayah (`KDEPUM` / `KDCBPS` $\rightarrow$ `administrative_areas.external_code`) dengan publikasi resmi Badan Pusat Statistik:
     - BPS Kota Tangerang Selatan: https://tangselkota.bps.go.id (*Kecamatan Serpong Dalam Angka*)
     - BPS Kabupaten Tangerang: https://tangerangkab.bps.go.id (*Kecamatan Pagedangan Dalam Angka* & *Kecamatan Cisauk Dalam Angka*)

---

### 2.2 Sumber Geometri Jaringan Jalan & Titik Fasilitas Publik (`DS-02`)
- **Nama Sumber:** OpenStreetMap (OSM) Road Network (`highway=*`) & Points of Interest (`amenity=*`, `public_transport=*`) — Ekstrak Pulau Jawa
- **Kategori Kredibilitas:** **Dataset Spasial Terbuka Kolaboratif (*Crowdsourced Open Data*) — BUKAN SK Jalan Resmi Pemerintah**
- **Penerbit:** OpenStreetMap Contributors (didistribusikan secara berkala oleh Geofabrik GmbH)
- **URL yang Dapat Diakses:**
  - Portal Utama & Hak Cipta: https://www.openstreetmap.org/copyright
  - Unduhan Ekstrak Indonesia: https://download.geofabrik.de/asia/indonesia.html
  - Unduhan Ekstrak Pulau Jawa (`.osm.pbf` & `.shp.zip`): https://download.geofabrik.de/asia/indonesia/java.html (`https://download.geofabrik.de/asia/indonesia/java-latest.osm.pbf`)
- **Tanggal Verifikasi Akses URL:** 30 Oktober 2026
- **Cakupan Geografis:** Seluruh Pulau Jawa & Provinsi Banten, mencakup koridor BSD City dan sekitarnya (`longitude` $106{,}58^\circ \text{ s.d. } 106{,}75^\circ\text{ BT}$, `latitude` $-6{,}38^\circ \text{ s.d. } -6{,}22^\circ\text{ LS}$)
- **Format Tersedia:** Protocolbuffer Binary Format (`.osm.pbf`), Shapefile (`.shp.zip`), dan GeoJSON (melalui ekspor QGIS)
- **Lisensi & Kewajiban Atribusi:**
  - **Open Database License (ODbL) 1.0:** https://opendatacommons.org/licenses/odbl/1-0/
  - Wajib menampilkan atribusi: `© OpenStreetMap contributors (ODbL)` pada antarmuka peta WebGIS (sudah diterapkan pada [`WebGisMap.jsx`](../frontend/src/components/WebGisMap.jsx)) dan menyimpan `source_name = "OpenStreetMap"` serta `source_reference = "ODbL 1.0 — snapshot YYYY-MM-DD"` pada tabel `road_segments`.
- **Status Ketersediaan Lokal Saat Ini:** **Sudah diverifikasi kebijakan lisensi dan kompatibilitas importernya**; file ekstrak wilayah studi **Perlu diperoleh dari pihak eksternal** oleh peneliti melalui Geofabrik/QGIS.
- **Keterbatasan:**
  1. Atribut `highway` (`primary`, `secondary`, `tertiary`, `residential`) di OSM ditentukan oleh komunitas pemeta berdasarkan karakteristik fisik/peran jalur, bukan selalu identik dengan penetapan fungsi jalan dalam SK Wali Kota/Bupati.
  2. Satu ruas jalan bernama sama (mis. *Jl. Pahlawan Seribu* atau *Jl. Letnan Sutopo*) di OSM sering terbagi menjadi puluhan segmen `way` pendek akibat pemotongan di setiap jembatan, putaran balik (*U-turn*), atau perubahan jumlah lajur.
  3. Sebagian jalan klaster perumahan di BSD tidak memiliki atribut `name` di OSM.
- **Metode Validasi:**
  1. Potong (*clip*) layer garis jalan OSM terhadap poligon wilayah studi resmi (`DS-01`).
  2. Saring hanya kelas jalan kendaraan bermotor (`trunk`, `primary`, `secondary`, `tertiary`, `unclassified`, `residential`, `living_street`, `service` akses publik) dan keluarkan jalur pejalan kaki (`footway`, `path`, `steps`, `cycleway`, `construction`).
  3. Jalankan validasi pra-impor menggunakan `php artisan roadpriority:import-segments <path.geojson> --dry-run` untuk memeriksa kelengkapan `road_reference_code`, `name`, `road_function`, validitas `LineString` WGS 84, dan ketiadaan duplikasi.

---

### 2.3 Layanan Basemap Peta WebGIS (`DS-03`)
- **Nama Sumber:** OpenStreetMap Standard Raster Tile Server
- **Kategori Kredibilitas:** **Layanan Peta Dasar Terbuka**
- **Penerbit:** OpenStreetMap Foundation (OSMF)
- **URL yang Dapat Diakses:**
  - Endpoint Tile: `https://tile.openstreetmap.org/{z}/{x}/{y}.png`
  - Kebijakan Penggunaan: https://operations.osmfoundation.org/policies/tiles/
- **Tanggal Verifikasi Akses URL:** 30 Oktober 2026
- **Status Ketersediaan Lokal Saat Ini:** **Sudah diverifikasi** dan terintegrasi pada [`WebGisMap.jsx`](../frontend/src/components/WebGisMap.jsx).
- **Keterbatasan & Aturan Kepatuhan:** Dilarang mengunduh ubin peta secara massal (*bulk tile scraping*); hanya digunakan untuk penayangan interaktif di browser dengan atribusi terlihat.

---

### 2.4 Dokumen Status & Kewenangan Jalan Pemerintah Daerah (`DS-04`)
- **Nama Sumber:** Dokumen SK Penetapan Status & Fungsi Ruas Jalan Kota Tangerang Selatan / Kabupaten Tangerang
- **Kategori Kredibilitas:** **Data Administratif Resmi Pemerintah Daerah**
- **Penerbit:**
  - Pemerintah Kota Tangerang Selatan — Dinas Sumber Daya Air, Bina Marga, dan Bina Konstruksi (DSDABMBK): https://tangerangselatankota.go.id
  - Pemerintah Kabupaten Tangerang — Dinas Bina Marga dan Sumber Daya Air (DBMSDA): https://tangerangkab.go.id
- **Tanggal Verifikasi Akses URL:** 30 Oktober 2026
- **Status Ketersediaan Lokal Saat Ini:** **Perlu diperoleh dari pihak eksternal / Belum dapat dipastikan** ketersediaan unduhan publik langsung untuk tabel per ruas; umumnya memerlukan surat pengantar penelitian kampus atau permohonan informasi publik (PPID).
- **Keterbatasan:**
  1. Banyak ruas jalan utama dan jalan lingkungan di kawasan BSD City dibangun dan dikelola oleh pengembang swasta (**PT Bumi Serpong Damai Tbk / Sinar Mas Land — Estate Management BSD**) dan sebagian belum diserahkan sebagai Prasarana, Sarana, dan Utilitas Umum (PSU) kepada pemerintah daerah.
  2. Dokumen SK jalan daerah umumnya berbentuk daftar tabel (nama ruas, pangkal–ujung, panjang km) tanpa koordinat geometri `LineString` siap pakai.
- **Metode Validasi & Aturan Pencatatan Jujur di Sistem:**
  - Apabila suatu ruas jalan terbukti tercantum dalam SK Jalan Kota/Kabupaten/Provinsi/Nasional, setel `road_authority` ke `'kota'`, `'kabupaten'`, `'provinsi'`, atau `'nasional'`.
  - Apabila ruas jalan berada di dalam kawasan pengembangan BSD dan dikelola oleh pengembang, setel `road_authority` ke `'kawasan_pengembang'`.
  - Apabila status kewenangan hukum suatu ruas belum dapat dibuktikan dengan dokumen resmi, **wajib** disetel ke `'belum_terverifikasi'` (default sistem pada [`RoadSegmentGeoJsonImporter.php`](../backend/app/Services/RoadSegmentGeoJsonImporter.php#L539)).

---

### 2.5 Standar Teknis Survei Kondisi Jalan Lapangan (`DS-05`)
- **Nama Sumber:** Pedoman Survei Kondisi Jalan Metode *Surface Distress Index* (SDI) & Tata Cara Pemeliharaan Jalan
- **Kategori Kredibilitas:** **Standar Teknis Resmi Pemerintah & Data Primer Survei Peneliti**
- **Penerbit Acuan:** Kementerian Pekerjaan Umum dan Perumahan Rakyat (PUPR) — Direktorat Jenderal Bina Marga
- **URL Referensi Resmi:**
  - Portal Ditjen Bina Marga: https://binamarga.pu.go.id
  - Basis Data Peraturan BPK RI (Permen PU No. 13/PRT/M/2011 tentang Tata Cara Pemeliharaan dan Penilikan Jalan): https://peraturan.bpk.go.id
  - Manual Survei Kondisi Jalan untuk Pemeliharaan (No. 001-01/M/BM/2011 — Metode SDI)
- **Tanggal Verifikasi Akses URL:** 30 Oktober 2026
- **Status Ketersediaan Lokal Saat Ini:** Instrumen operasional telah disusun di [`docs/FIELD_PROTOCOL.md`](FIELD_PROTOCOL.md) (`protocol_version = "SDI-ADAPTED-1.0"`); pelaksanaan pengumpulan nilai lapangan berstatus **Perlu dikumpulkan melalui survei**.
- **Metode Validasi:**
  1. Setiap asesmen wajib mencatat 4 ukuran fisik dasar pada kolom JSON `damage_assessments.measurements` (`crack_area_pct`, `crack_width_mm`, `pothole_count_per_100m`, `rutting_depth_cm`) beserta foto bukti lapangan.
  2. Sistem memvalidasi rentang skor `severity_level` ($1\text{–}5$), `safety_risk_score` ($1\text{–}5$), `road_function_score` ($1\text{–}4$), dan `impact_score` ($1\text{–}5$) pada [`DamageAssessmentController::store`](../backend/app/Http/Controllers/Api/DamageAssessmentController.php#L58-L72) serta menolak pencampuran data survei resmi dengan segmen demo.

---

### 2.6 Kuesioner Perbandingan Berpasangan AHP Pakar (`DS-07`)
- **Nama Sumber:** Data Primer Penilaian Pakar (*Expert Judgment*) Menggunakan Skala Perbandingan Berpasangan Thomas L. Saaty ($1\text{–}9$)
- **Kategori Kredibilitas:** **Data Primer Penelitian (Wawancara / Kuesioner Terstruktur Pakar)**
- **Responden yang Dibutuhkan:** Minimal 2–3 pakar/praktisi di bidang Teknik Sipil Jalan Raya, Perencanaan Wilayah & Kota, atau petugas teknis Dinas Bina Marga/PUPR (atau minimal 1 pakar dengan pencatatan keterbatasan penelitian sesuai `D-07`).
- **Status Ketersediaan Lokal Saat Ini:** Mesin perhitungan vektor eigen utama, uji konsistensi ($\text{CR} \le 0{,}10$), dan agregasi *Geometric Mean* (*Aggregation of Individual Judgments* / AIJ) **Sudah diverifikasi** pada [`AhpService.php`](../backend/app/Services/DecisionEngine/AhpService.php); data jawaban pakar berstatus **Perlu dikumpulkan melalui survei**.
- **Metode Validasi:**
  1. Identitas/kode pakar (`expert_id`), tanggal pengisian, dan nilai setiap pasangan $(i, j)$ disimpan ke tabel `ahp_pairwise_comparisons`.
  2. Sistem menghitung $\lambda_{\max}$, $\text{CI} = \frac{\lambda_{\max} - n}{n - 1}$, dan $\text{CR} = \frac{\text{CI}}{\text{RI}_n}$.
  3. Apabila $\text{CR} > 0{,}10$, sistem menandai `is_consistent = false` dan **memblokir** aktivasi versi bobot tersebut untuk perhitungan resmi (`422` pada `CriterionWeightVersionService::activate()`).

