# Protokol Operasional Pengumpulan & Validasi Data Penelitian (`RESEARCH_DATA_COLLECTION_PROTOCOL.md`)

**Proyek:** RoadPriority — Sistem Informasi Pelaporan Jalan Rusak & SPK Prioritas Perbaikan (AHP-TOPSIS)  
**Tahap:** PRD 05.3 — Research Data Acquisition & Validation  
**Tanggal Penyusunan:** 30 Oktober 2026  

Dokumen ini merupakan prosedur operasional standar (SOP) bagi peneliti untuk mengumpulkan, memproses, memvalidasi, dan memuat data penelitian nyata ke dalam aplikasi RoadPriority tanpa mencampuradukkannya dengan data simulasi (`is_demo = true`).

---

## Prosedur A — Penetapan Batas Wilayah Studi & Kriteria Inklusi Ruas Jalan (`D-01`, `D-02`)

### A.1 Penetapan Cakupan Administratif Eksplisit
Karena kawasan "BSD City" membentang lintas batas administratif Kota Tangerang Selatan dan Kabupaten Tangerang, peneliti wajib menetapkan cakupan wilayah studi secara eksplisit bersama dosen pembimbing dari salah satu opsi berikut:
- **Opsi Wilayah A (Berbasis 1 Kecamatan Penuh — Direkomendasikan untuk kejelasan batas resmi):** Seluruh kelurahan di **Kecamatan Serpong, Kota Tangerang Selatan** (Kelurahan Rawa Buntu, Rawa Mekar Jaya, Cilenggang, Serpong, Lengkong Gudang, Lengkong Gudang Timur, Lengkong Wetan, Buaran, Ciater).
- **Opsi Wilayah B (Koridor Inti BSD Lintas Kecamatan):** Daftar kelurahan terpilih yang terdokumentasi di Kecamatan Serpong (Kota Tangsel), Kecamatan Pagedangan, dan Kecamatan Cisauk (Kab. Tangerang).

### A.2 Akuisisi Poligon Batas Wilayah Resmi
1. Login ke portal **Ina-Geoportal BIG** (https://tanahair.indonesia.go.id).
2. Unduh paket Peta RBI Skala 1:25.000 untuk **Kota Tangerang Selatan** (dan **Kabupaten Tangerang** jika memilih Opsi Wilayah B).
3. Buka layer `BATAS_DESAKEL_AR` atau `BATAS_KECAMATAN_AR` di **QGIS**.
4. Saring (*filter*) poligon kelurahan/kecamatan sesuai keputusan A.1, pastikan sistem koordinat adalah **WGS 84 (`EPSG:4326`)**, lalu simpan sebagai referensi pemotong (*clip layer*) wilayah studi.

### A.3 Kriteria Inklusi dan Eksklusi Ruas Jalan
- **Inklusi:** Jalan kendaraan umum di dalam poligon wilayah studi dengan kelas fungsi `arteri`, `kolektor`, `lokal`, atau `lingkungan` (pada OSM: `highway` $\in$ `{trunk, trunk_link, primary, primary_link, secondary, secondary_link, tertiary, tertiary_link, unclassified, residential, living_street, service}`).
- **Eksklusi:** Jalur khusus pejalan kaki (`footway`, `path`, `steps`, `pedestrian`), jalur sepeda terpisah (`cycleway`), jalan tol tertutup (`motorway`) yang berada di bawah kewenangan badan usaha jalan tol, jalan di dalam kavling privat tertutup yang tidak dapat diakses publik, serta ruas yang sedang dalam tahap konstruksi awal (`construction`).

---

## Prosedur B — Penyusunan, Validasi, dan Impor GeoJSON Ruas Jalan (`D-03`)

### B.1 Ekstraksi dan Kurasi Geometri di QGIS
1. Unduh ekstrak OpenStreetMap Pulau Jawa dari Geofabrik (`https://download.geofabrik.de/asia/indonesia/java-latest.osm.pbf`) dan catat tanggal snapshot unduhan.
2. Muat layer garis (`lines`) ke QGIS, lalu jalankan **Vector $\rightarrow$ Geoprocessing Tools $\rightarrow$ Clip** menggunakan poligon batas wilayah studi dari Prosedur A.2.
3. Saring hanya fitur dengan tag `highway` sesuai kriteria inklusi A.3.
4. Konversi geometri `MultiLineString` menjadi `LineString` tunggal menggunakan **Vector $\rightarrow$ Geometry Tools $\rightarrow$ Multipart to Singleparts** (karena RoadPriority secara ketat hanya menerima `LineString` tunggal).
5. Siapkan kolom atribut wajib pada tabel atribut QGIS sebelum ekspor ke GeoJSON:
   - `road_reference_code`: Kode unik dan stabil, misalnya `OSM-WAY-{osm_id}` (atau `BSD-SEG-001` s.d. `BSD-SEG-NNN` yang memetakan `osm_id` di `source_reference`).
   - `name`: Nama jalan dari tag `name` OSM. Untuk segmen yang belum memiliki nama di OSM, isi nama terstandar berdasarkan verifikasi peta/lapangan (mis. `Jl. Lingkungan Rawa Buntu Segmen 04`) — **dilarang mengarang nama jalan fiktif**.
   - `road_function`: Salah satu dari `arteri`, `kolektor`, `lokal`, `lingkungan` (atau biarkan tag `highway` OSM agar dipetakan otomatis oleh `RoadSegmentGeoJsonImporter`).
   - `road_authority`: Isi `'kota'`, `'kabupaten'`, `'provinsi'`, `'nasional'`, atau `'kawasan_pengembang'` **hanya jika telah terverifikasi**; jika belum, isi `'belum_terverifikasi'`.
   - `nearest_facility_meters`: Hasil pengukuran jarak fasilitas publik dari Prosedur D (atau biarkan `null` jika belum diukur — **jangan diisi angka tebakan**).
   - `source_name`: `"OpenStreetMap"`
   - `source_reference`: `"ODbL 1.0 - snapshot YYYY-MM-DD (osm_id={id})"`
   - `data_origin`: `"public_dataset"`
6. Ekspor layer ke format **RFC 7946 GeoJSON** dengan CRS **`EPSG:4326 - WGS 84`** dan simpan ke `backend/storage/app/study_area_roads.geojson`.

### B.2 Validasi Pra-Impor (`--dry-run`) dan Eksekusi Impor
1. Jalankan pemeriksaan `--dry-run` terlebih dahulu untuk memverifikasi seluruh feature tanpa menulis ke database:
   ```powershell
   cd backend
   C:\xampp\php\php.exe artisan roadpriority:import-segments storage/app/study_area_roads.geojson `
     --dry-run `
     --enforce-study-bbox `
     --source-name="OpenStreetMap" `
     --source-reference="ODbL 1.0" `
     --data-origin="public_dataset"
   ```
2. Pastikan tabel ringkasan menampilkan `rejected_features = 0`, `conflict_count = 0`, dan `rolled_back = TIDAK`. Jika ada feature yang ditolak, perbaiki di QGIS berdasarkan nomor `Feature #i` dan alasan yang dilaporkan CLI.
3. Setelah `dry-run` bersih, jalankan perintah yang sama **tanpa `--dry-run`** untuk menyimpan ruas resmi (`is_demo = false`) ke tabel `road_segments`.

---

## Prosedur C — Pengumpulan Asesmen Kondisi Jalan di Lapangan (`K1`, `K2`, `K3`, `K4`)

### C.1 Instrumen dan Identitas Observasi
Setiap ruas sampel yang disurvei di lapangan wajib diukur menggunakan instrumen [`docs/FIELD_PROTOCOL.md`](FIELD_PROTOCOL.md) (`protocol_version = "SDI-ADAPTED-1.0"`) dengan mencatat:
1. **Identitas Ruas:** `road_reference_code` dan `road_segment_id` yang sesuai dengan ruas di WebGIS.
2. **Waktu Observasi (`assessment_date`):** Tanggal dan jam pengamatan aktual di lapangan (UTC/WIB).
3. **Penilai (`assessed_by`):** Akun pengguna berperan `officer` atau `admin` yang melakukan pemeriksaan.
4. **Empat Parameter Fisik Dasar (`measurements` JSON):**
   - `crack_area_pct`: Estimasi persentase luas retak terhadap luas segmen amatan ($\%$).
   - `crack_width_mm`: Rata-rata lebar celah retakan ($\text{mm}$).
   - `pothole_count_per_100m`: Jumlah lubang per $100\text{ m}$ panjang segmen.
   - `rutting_depth_cm`: Rata-rata kedalaman alur roda/amblas ($\text{cm}$).
5. **Skor Kriteria Terukur:**
   - `severity_level` (`K1`, skala ordinal $1\text{–}5$, Benefit): Dikonversi dari keempat ukuran fisik SDI sesuai tabel konversi [`docs/FIELD_PROTOCOL.md` §3.1](FIELD_PROTOCOL.md#L39-L45).
   - `safety_risk_score` (`K2`, skala berjangkar $1\text{–}5$, Benefit): Dinilai berdasarkan posisi kerusakan pada lajur, geometri tikungan/turunan, penerangan, dan genangan sesuai [`docs/FIELD_PROTOCOL.md` §3.2](FIELD_PROTOCOL.md#L49-L55).
   - `road_function_score` (`K3`, skala ordinal $1\text{–}4$, Benefit): `1` (lingkungan), `2` (lokal), `3` (kolektor), `4` (arteri).
   - `impact_score` (`K4`, skala ordinal $1\text{–}5$, Benefit — jika Opsi K4-a digunakan): Berdasarkan pencacahan cepat arus kendaraan 15 menit pada jam sibuk sesuai [`docs/FIELD_PROTOCOL.md` §3.4](FIELD_PROTOCOL.md#L70-L76).
6. **Asal Data (`assessment_source`):** Setel ke `'field_survey'` (atau `'field_inspection'`) dengan `status = 'final'` dan `is_demo = false`.

### C.2 Pencatatan ke Sistem dan Verifikasi Kesiapan Data
1. Input hasil survei melalui formulir **Catat Penilaian Kondisi Lapangan** pada tab **Mesin Keputusan AHP-TOPSIS** atau melalui endpoint `POST /api/assessments`.
2. Periksa endpoint `GET /api/decision-data-readiness` (dengan `include_demo=0`) untuk memastikan ruas yang telah disurvei berpindah dari `incomplete_segments` ke `eligible_segments`.

---

## Prosedur D — Pengukuran Konsisten Jarak Fasilitas Publik (`K5` — `nearest_facility_meters`)

Untuk menghindari angka perkiraan subjektif pada kriteria `K5` (*Kedekatan fasilitas publik*, tipe `Cost` dalam satuan meter):

1. **Definisi Operasional Fasilitas Publik Target:**
   - Fasilitas Pendidikan: Sekolah Dasar (SD), SMP, SMA/SMK, Perguruan Tinggi (`amenity=school`, `amenity=university`, `amenity=college`).
   - Fasilitas Kesehatan: Rumah Sakit, Puskesmas, Klinik Utama (`amenity=hospital`, `amenity=clinic`).
   - Fasilitas Ekonomi & Pelayanan Publik: Pasar rakyat/tradisional, kantor pelayanan pemerintahan kelurahan/kecamatan (`amenity=marketplace`, `amenity=townhall`).
   - Simpul Transportasi Umum: Stasiun KRL Commuter Line (mis. Stasiun Rawa Buntu, Stasiun Serpong, Stasiun Cisauk) dan terminal/halte intermoda (`railway=station`, `public_transport=station`).
2. **Metode Pengukuran Spasial di QGIS:**
   - Muat layer `road_segments` dan layer titik/poligon fasilitas publik di wilayah studi.
   - Reproyeksikan kedua layer ke sistem koordinat terproyeksi metrik lokal: **`EPSG:32748` (WGS 84 / UTM Zone 48S)** agar jarak dihitung dalam satuan **meter** yang akurat.
   - Gunakan algoritma **Vector Analysis $\rightarrow$ Shortest line between features** atau **Join attributes by nearest (`max_distance` tidak dibatasi, `neighbors = 1`)** untuk menghitung jarak terpendek (`distance`) dari setiap geometri segmen jalan ke fasilitas publik terdekat.
   - Bulatkan hasil ke 2 angka desimal dan simpan pada atribut `nearest_facility_meters` di file GeoJSON ruas jalan, serta catat `nearest_facility_name` dan `nearest_facility_osm_id` pada dokumentasi kertas kerja peneliti.
3. **Aturan Jika Jarak Belum Diukur:**
   - **Dilarang keras** mengisi `nearest_facility_meters` dengan angka `0` (kecuali fasilitas benar-benar menempel langsung pada segmen jalan dengan jarak $0\text{ m}$ terverifikasi) atau angka tebakan.
   - Biarkan bernilai `null` pada GeoJSON; sistem akan secara transparan menandai ruas tersebut dengan `missing_or_invalid:K5` sampai pengukuran QGIS selesai dilakukan dan diperbarui dengan `--update-existing`.

---

## Prosedur E — Penyusunan Instrumen AHP Pakar & Penanganan Konsistensi ($\text{CR}$)

1. **Prasyarat Sebelum Menyusun Kuesioner AHP:**
   - Kunci terlebih dahulu daftar kriteria aktif final (`D-06`) di tabel `decision_criteria` karena jumlah pertanyaan perbandingan berpasangan adalah $\frac{n(n-1)}{2}$ pasangan untuk $n$ kriteria aktif.
2. **Format Kuesioner Pakar:**
   - Gunakan skala perbandingan berpasangan Saaty ($1, 2, 3, 4, 5, 6, 7, 8, 9$ beserta nilai kebalikannya $1/2 \dots 1/9$).
   - Catat profil setiap pakar/responden: nama/inisial (sesuai persetujuan etika/informed consent), institusi/jabatan, bidang keahlian, pengalaman kerja (tahun), dan tanggal pengisian.
3. **Input dan Perhitungan di Sistem:**
   - Buat draft versi bobot melalui `POST /api/criterion-weight-versions` dengan nama versi yang mencantumkan sumber pakar (mis. `"Bobot Pakar Skripsi v1.0 - 3 Responden AIJ"`).
   - Kirimkan matriks penilaian pakar ke `POST /api/criterion-weight-versions/{id}/calculate`.
   - Jika terdapat lebih dari 1 pakar, sistem menggabungkan penilaian menggunakan rata-rata geometrik per elemen matriks (*Aggregation of Individual Judgments* / AIJ: $a_{ij}^{(G)} = \left(\prod_{k=1}^{m} a_{ij}^{(k)}\right)^{1/m}$) sebelum menghitung vektor eigen utama dan $\text{CR}$.
4. **Aturan Wajib Jika $\text{CR} > 0{,}10$ (`AhpService::CR_THRESHOLD`):**
   - Sesuai teori Saaty dan aturan [`SnapshotService::validateWeightVersionForRun()`](../backend/app/Services/DecisionEngine/SnapshotService.php#L195-L200), matriks dengan $\text{CR} > 0{,}10$ berstatus **tidak konsisten** (`is_consistent = false`) dan **ditolak oleh sistem** untuk diaktifkan atau digunakan dalam perhitungan resmi.
   - **Prosedur perbaikan yang sah:** Peneliti **dilarang mengubah sendiri angka jawaban pakar secara sepihak** agar $\text{CR}$ turun di bawah $0{,}10$. Peneliti wajib mengidentifikasi pasangan kriteria yang paling menyumbang ketidakkonsistenan (ketidaktransitifan, misalnya $A \succ B$, $B \succ C$, tetapi $C \succ A$), lalu meminta pakar yang bersangkutan meninjau ulang (*re-elicitation*) penilaiannya secara terdokumentasi.

---

## Prosedur F — Pemisahan Pelaporan Masyarakat dari Asesmen Kondisi Jalan

Sesuai arsitektur tiga entitas pada PRD 04 dan PRD 05.3 §5.F:

1. **Laporan Kerusakan (`damage_reports`):**
   - Merupakan laporan kejadian/keluhan yang dikirim oleh pengguna terdaftar (`reporter`) beserta koordinat GPS dan foto bukti.
   - Saat dikirim, laporan berstatus `submitted` dan **belum** terikat ke segmen jalan mana pun secara final (`road_segment_id = null` atau kandidat saran).
2. **Verifikasi Laporan oleh Petugas (`PATCH /api/reports/{id}/status`):**
   - Petugas/verifikator memeriksa kebenaran foto, lokasi, potensi duplikasi (`duplicate_of_id`), dan mengikat laporan yang valid ke `road_segment_id` yang sesuai dengan mengubah status menjadi `verified`.
   - Laporan berstatus `verified` (dan bukan duplikat) hanya berkontribusi pada kriteria **frekuensi laporan (`K6`)** dan **lama belum ditangani (`K7`)**.
3. **Asesmen Kondisi Jalan (`damage_assessments`):**
   - Laporan masyarakat yang berstatus `verified` **tidak pernah** otomatis menjadi skor kerusakan teknis `K1` (`severity_level`) atau `K2` (`safety_risk_score`).
   - Untuk menghasilkan nilai `K1`, `K2`, `K3`, dan `K4`, petugas/peneliti wajib membuat rekaman `DamageAssessment` tersendiri (`POST /api/assessments`) setelah melakukan pemeriksaan teknis menggunakan rubrik `SDI-ADAPTED-1.0`.

