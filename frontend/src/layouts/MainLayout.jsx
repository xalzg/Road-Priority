export default function MainLayout({ children }) {
  return (
    <div className="layout">
      <header className="layout-header">
        <h1>RoadPriority</h1>
        <p>Sistem informasi pelaporan, pemetaan, dan prioritas penanganan jalan rusak</p>
      </header>
      <main className="layout-main">{children}</main>
      <footer className="layout-footer">
        Prototipe penelitian. Tahap saat ini: fondasi proyek (PRD 01).
      </footer>
    </div>
  )
}

