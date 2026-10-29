# Dokumentasi WebGIS Interaktif (`WEBGIS.md`)

**Tahap:** PRD 04 — Road Damage Reporting & WebGIS  
**Pustaka Peta:** Leaflet (`^1.9.4`) + OpenStreetMap Tile Layer  
**Komponen Utama:** [`frontend/src/components/WebGisMap.jsx`](../frontend/src/components/WebGisMap.jsx), [`frontend/src/pages/HomePage.jsx`](../frontend/src/pages/HomePage.jsx)

---

## 1. Sumber Peta Dasar (Basemap) & Atribusi

- **Tile Server:** OpenStreetMap Standard Tile Layer (`https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`).
- **Atribusi Wajib:** `© OpenStreetMap contributors (ODbL)` ditampilkan secara permanen pada kontrol atribusi peta dan footer aplikasi.
- **Sistem Referensi Koordinat (CRS):** WGS 84 (`EPSG:4326`).

---

## 2. Format Koordinat: Titik Laporan vs Geometri Segmen Jalan (`PRD 04 §8`)

| Objek | Representasi | Urutan Koordinat di Database & API | Urutan Koordinat di Leaflet |
|---|---|---|---|
| **Titik Laporan (`damage_reports`)** | Titik tunggal (`POINT`) | Field numerik terpisah `latitude` ($[-90, 90]$) dan `longitude` ($[-180, 180]$) | `[latitude, longitude]` pada `L.circleMarker([lat, lng])` |
| **Geometri Segmen Jalan (`road_segments`)** | Garis (`LineString` GeoJSON) | `geometry_geojson.coordinates`: **`[longitude, latitude]`** sesuai standar RFC 7946 GeoJSON (`DEC-008`, `DEC-010`) | Dikonversi di `WebGisMap.jsx` menjadi `[coord[1], coord[0]]` (`[latitude, longitude]`) untuk `L.polyline()` |

### Perhitungan Jarak Spasial (`GeoGeometryService.php`)
- Sesuai temuan audit `DEC-008` (di mana `ST_Distance_Sphere(POINT, LINESTRING)` pada MariaDB 10.4.32 memicu `ERROR 1815`), penyaringan kandidat dilakukan terlebih dahulu menggunakan *bounding box* derajat pada kolom terindeks (`latitude`, `longitude`, `centroid_latitude`, `centroid_longitude`), kemudian jarak titik-ke-garis (`distanceToLineStringMeters`) dan jarak antar-titik (`distanceMeters`) dihitung secara presisi dalam satuan **meter** di PHP.

---

## 3. Fitur Interaktif Peta & Performa (`PRD 04 §7.1 & §7.2`)

1. **Sinkronisasi Peta dan Daftar Laporan:**
   - Memilih item pada daftar laporan (`"Sorot di Peta"`) akan memusatkan peta ke koordinat laporan tersebut dan membuka popup marker-nya.
   - Mengklik marker di peta menampilkan popup ringkas (`report_code`, `damage_type`, `status`, tanggal laporan) dan tombol `"Buka Detail Laporan"` yang membuka modal detail tanpa membocorkan identitas pribadi pelapor.
2. **Filter Server-Side & Bounding Box (`bbox`):**
   - Mendukung filter `status`, `damage_type`, `date_from`, `date_to`, pencarian `q` (`report_code`), serta toggle *"Saring otomatis sesuai batas area peta yang tampak (Bounding Box)"*.
   - Pergerakan peta (`moveend`) di-*debounce* selama **350 ms** sebelum memperbarui parameter `bbox=minLng,minLat,maxLng,maxLat`, dan setiap request lama dibatalkan melalui `AbortController` agar respons lama tidak pernah menimpa hasil filter terbaru.
3. **Pagination & Pembatasan Hasil:**
   - Seluruh daftar laporan dan segmen jalan menggunakan pagination server-side (`per_page` dibatasi maksimal `100` di server melalui `ListDamageReportsRequest` dan `ListRoadSegmentsRequest`).
4. **Marker Clustering:**
   - Ketika tingkat zoom peta $< 15$ dan terdapat $> 12$ marker laporan yang berdekatan dalam sel grid spasial yang sama, `WebGisMap.jsx` mengelompokkan titik-titik tersebut menjadi satu *cluster marker* bernomor jumlah laporan. Mengklik *cluster marker* akan memperbesar (*zoom in*) peta ke area tersebut.

---

## 4. Batas Geografis Wilayah Studi (`PRD 04 §7.3`)

- **Cakupan Wilayah Studi:** Kawasan BSD dan sekitarnya (Tangerang Raya).
- **Mekanisme Pemeriksaan Batas Wilayah (`GeoGeometryService::evaluateStudyAreaLocation`):**
  1. Jika tabel `administrative_areas` telah memiliki rekaman dengan `is_study_area = true` dan geometri poligon terverifikasi (`boundary_geojson` bertipe `Polygon`/`MultiPolygon`), sistem menjalankan algoritma *ray-casting point-in-polygon* terhadap poligon resmi tersebut (`boundary_source = verified_polygon:{nama}`).
  2. Selama poligon batas kecamatan/kelurahan resmi dari BIG/pemerintah daerah belum dimuat ke database produksi (`GAP-02` / `D-02`), sistem menggunakan kotak pembatas referensi BSD/Tangerang (`min_lat: -6.38`, `max_lat: -6.22`, `min_lng: 106.58`, `max_lng: 106.75`) yang ditampilkan sebagai garis putus-putus biru di peta dan memberikan peringatan eksplisit (`study_area_check.warning`) apabila titik laporan berada di luar kotak referensi tersebut, tanpa mengklaim kotak referensi sebagai batas administratif resmi.

