import ApiStatus from '../components/ApiStatus'

export default function MainLayout({ children }) {
  return (
    <div className="layout">
      <header className="layout-header">
        <div className="layout-header-inner">
          <div>
            <h1>RoadPriority WebGIS</h1>
            <p>
              Sistem informasi pelaporan, pemetaan, dan prioritas penanganan jalan rusak (Wilayah
              Studi: BSD &amp; Sekitarnya)
            </p>
          </div>
          <ApiStatus />
        </div>
      </header>
      <main className="layout-main">{children}</main>
      <footer className="layout-footer site-footer">
        Prototipe penelitian skripsi Sistem Informasi — RoadPriority: Sistem Informasi Pelaporan
        Kerusakan Jalan &amp; Penentuan Prioritas Perbaikan (AHP-TOPSIS). Basemap &copy; OpenStreetMap
        contributors (ODbL).
      </footer>
    </div>
  )
}
