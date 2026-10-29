import { useEffect, useState } from 'react'
import ReportDetailModal from '../components/ReportDetailModal'
import ReportForm from '../components/ReportForm'
import WebGisMap from '../components/WebGisMap'
import { clearStoredSession, getStoredToken, getStoredUser } from '../services/api'
import {
  DAMAGE_TYPE_LABELS,
  DAMAGE_TYPE_OPTIONS,
  STATUS_LABELS,
  fetchCurrentUser,
  fetchReports,
  fetchRoadSegments,
  loginUser,
  logoutUser,
} from '../services/reportingService'

export default function HomePage() {
  const [currentUser, setCurrentUser] = useState(() => getStoredUser())
  const [activeTab, setActiveTab] = useState('public_map')

  // State form login
  const [showLoginPanel, setShowLoginPanel] = useState(false)
  const [loginEmail, setLoginEmail] = useState('')
  const [loginPassword, setLoginPassword] = useState('')
  const [loginLoading, setLoginLoading] = useState(false)
  const [loginError, setLoginError] = useState('')

  // State filter & pagination laporan
  const [statusFilter, setStatusFilter] = useState('')
  const [damageTypeFilter, setDamageTypeFilter] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [searchCode, setSearchCode] = useState('')
  const [useMapBbox, setUseMapBbox] = useState(false)
  const [mapBbox, setMapBbox] = useState('')
  const [showSegmentsOnMap, setShowSegmentsOnMap] = useState(true)
  const [page, setPage] = useState(1)
  const [perPage] = useState(12)
  const [refreshTick, setRefreshTick] = useState(0)

  // State data laporan & segmen
  const [reports, setReports] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, per_page: 12, total: 0, last_page: 1 })
  const [loadedQueryKey, setLoadedQueryKey] = useState('')
  const [reportsError, setReportsError] = useState('')
  const [roadSegments, setRoadSegments] = useState([])

  // State pilihan laporan (sinkronisasi daftar <-> peta <-> modal detail)
  const [selectedReportId, setSelectedReportId] = useState(null)
  const [detailModalTarget, setDetailModalTarget] = useState('')

  const isOfficerOrAdmin =
    currentUser && (currentUser.role === 'officer' || currentUser.role === 'admin')

  const currentQueryKey = [
    activeTab,
    statusFilter,
    damageTypeFilter,
    dateFrom,
    dateTo,
    searchCode.trim(),
    useMapBbox && activeTab === 'public_map' ? mapBbox : '',
    page,
    perPage,
    refreshTick,
    currentUser?.id || 'guest',
  ].join('|')

  const loadingReports = activeTab !== 'create_report' && loadedQueryKey !== currentQueryKey

  // Sinkronisasi sesi saat aplikasi dibuka
  useEffect(() => {
    const token = getStoredToken()
    if (!token) return undefined
    const controller = new AbortController()
    fetchCurrentUser({ signal: controller.signal })
      .then((user) => {
        if (user) setCurrentUser(user)
      })
      .catch(() => {
        clearStoredSession()
        setCurrentUser(null)
      })
    return () => controller.abort()
  }, [])

  // Muat daftar segmen jalan untuk overlay peta & pilihan verifikasi
  useEffect(() => {
    const controller = new AbortController()
    fetchRoadSegments({ per_page: 100 }, { signal: controller.signal })
      .then((res) => {
        setRoadSegments(res?.data?.items || [])
      })
      .catch(() => {
        // Jika segmen belum tersedia, biarkan kosong tanpa mengunci halaman
      })
    return () => controller.abort()
  }, [currentUser])

  // Muat daftar laporan sesuai tab aktif, filter, bbox, dan halaman
  useEffect(() => {
    if (activeTab === 'create_report') return undefined

    const controller = new AbortController()

    const params = {
      page,
      per_page: perPage,
    }

    if (activeTab === 'my_reports') {
      params.mine = 1
      if (statusFilter) params.status = statusFilter
    } else if (activeTab === 'officer_queue') {
      params.status = statusFilter || 'submitted'
    } else if (statusFilter) {
      params.status = statusFilter
    }

    if (damageTypeFilter) params.damage_type = damageTypeFilter
    if (dateFrom) params.date_from = dateFrom
    if (dateTo) params.date_to = dateTo
    if (searchCode.trim()) params.q = searchCode.trim()
    if (useMapBbox && mapBbox && activeTab === 'public_map') {
      params.bbox = mapBbox
    }

    fetchReports(params, { signal: controller.signal })
      .then((res) => {
        const items = res?.data?.items || []
        const pageMeta = res?.meta || res?.data?.meta || {
          current_page: 1,
          per_page: perPage,
          total: items.length,
          last_page: 1,
        }
        setReports(items)
        setMeta(pageMeta)
        setReportsError('')
        setLoadedQueryKey(currentQueryKey)
      })
      .catch((err) => {
        if (err.name === 'AbortError') return
        setReportsError(err.message || 'Gagal memuat daftar laporan.')
        setLoadedQueryKey(currentQueryKey)
      })

    return () => controller.abort()
  }, [
    activeTab,
    statusFilter,
    damageTypeFilter,
    dateFrom,
    dateTo,
    searchCode,
    useMapBbox,
    mapBbox,
    page,
    perPage,
    refreshTick,
    currentUser,
    currentQueryKey,
  ])

  const switchTab = (tab) => {
    setActiveTab(tab)
    setPage(1)
    if (tab === 'officer_queue') {
      setStatusFilter('submitted')
    } else {
      setStatusFilter('')
    }
  }

  const handleLogin = async (evt) => {
    evt.preventDefault()
    setLoginError('')
    setLoginLoading(true)
    try {
      const data = await loginUser({ email: loginEmail, password: loginPassword })
      setCurrentUser(data.user)
      setLoginPassword('')
      setShowLoginPanel(false)
      setRefreshTick((n) => n + 1)
    } catch (err) {
      setLoginError(err.message || 'Login gagal. Periksa email dan password.')
    } finally {
      setLoginLoading(false)
    }
  }

  const handleLogout = async () => {
    await logoutUser()
    setCurrentUser(null)
    if (activeTab === 'create_report' || activeTab === 'my_reports' || activeTab === 'officer_queue') {
      setActiveTab('public_map')
    }
    setRefreshTick((n) => n + 1)
  }

  const handleResetFilters = () => {
    setStatusFilter(activeTab === 'officer_queue' ? 'submitted' : '')
    setDamageTypeFilter('')
    setDateFrom('')
    setDateTo('')
    setSearchCode('')
    setUseMapBbox(false)
    setPage(1)
  }

  return (
    <div className="workspace-container">
      {/* Bar Navigasi & Identitas Pengguna */}
      <nav className="workspace-nav" aria-label="Navigasi modul pelaporan dan WebGIS">
        <div className="nav-tabs">
          <button
            type="button"
            className={`tab-btn ${activeTab === 'public_map' ? 'active' : ''}`}
            onClick={() => switchTab('public_map')}
          >
            Peta &amp; Laporan Publik
          </button>

          {currentUser && (
            <>
              <button
                type="button"
                className={`tab-btn ${activeTab === 'create_report' ? 'active' : ''}`}
                onClick={() => switchTab('create_report')}
              >
                + Buat Laporan
              </button>
              <button
                type="button"
                className={`tab-btn ${activeTab === 'my_reports' ? 'active' : ''}`}
                onClick={() => switchTab('my_reports')}
              >
                Laporan Saya
              </button>
            </>
          )}

          {isOfficerOrAdmin && (
            <button
              type="button"
              className={`tab-btn ${activeTab === 'officer_queue' ? 'active' : ''}`}
              onClick={() => switchTab('officer_queue')}
            >
              Antrean Verifikasi Petugas
            </button>
          )}
        </div>

        <div className="auth-toolbar">
          {currentUser ? (
            <div className="user-chip">
              <span>
                <strong>{currentUser.name}</strong> ({currentUser.role})
              </span>
              <button type="button" className="btn-secondary btn-sm" onClick={handleLogout}>
                Keluar
              </button>
            </div>
          ) : (
            <button
              type="button"
              className="btn-secondary btn-sm"
              onClick={() => setShowLoginPanel((v) => !v)}
            >
              {showLoginPanel ? 'Tutup Login' : 'Masuk (Pelapor / Petugas)'}
            </button>
          )}
        </div>
      </nav>

      {/* Panel Login Ringkas */}
      {showLoginPanel && !currentUser && (
        <section className="panel-card login-panel" aria-label="Form masuk pengguna">
          <h3>Masuk ke RoadPriority</h3>
          <p className="text-muted">
            Masuk sebagai Pelapor untuk mengirim laporan baru dan memantau status laporan Anda, atau
            sebagai Petugas/Admin untuk memverifikasi laporan.
          </p>
          {loginError && <div className="alert-box alert-error">{loginError}</div>}
          <form onSubmit={handleLogin} className="login-form-inline">
            <div className="form-group">
              <label htmlFor="login-email">Email</label>
              <input
                id="login-email"
                type="email"
                required
                value={loginEmail}
                onChange={(e) => setLoginEmail(e.target.value)}
                placeholder="email@roadpriority.local"
                disabled={loginLoading}
              />
            </div>
            <div className="form-group">
              <label htmlFor="login-password">Password</label>
              <input
                id="login-password"
                type="password"
                required
                value={loginPassword}
                onChange={(e) => setLoginPassword(e.target.value)}
                placeholder="••••••••"
                disabled={loginLoading}
              />
            </div>
            <button type="submit" disabled={loginLoading}>
              {loginLoading ? 'Memproses…' : 'Masuk'}
            </button>
          </form>
        </section>
      )}

      {/* Tab Buat Laporan Baru */}
      {activeTab === 'create_report' && currentUser && (
        <ReportForm
          roadSegments={roadSegments}
          onCreated={(created) => {
            setRefreshTick((n) => n + 1)
            if (created?.report_code) {
              setDetailModalTarget(created.report_code)
            }
          }}
        />
      )}

      {/* Tab Peta Publik / Laporan Saya / Antrean Petugas */}
      {activeTab !== 'create_report' && (
        <>
          {/* Bar Filter */}
          <section className="panel-card filter-bar" aria-label="Filter laporan kerusakan">
            <div className="filter-grid">
              <div className="form-group">
                <label htmlFor="filter-status">Status Laporan</label>
                <select
                  id="filter-status"
                  value={statusFilter}
                  onChange={(e) => {
                    setStatusFilter(e.target.value)
                    setPage(1)
                  }}
                >
                  <option value="">
                    {activeTab === 'public_map' && !isOfficerOrAdmin
                      ? 'Semua Terpublikasi (Verified & Resolved)'
                      : 'Semua Status'}
                  </option>
                  {(activeTab === 'public_map' && !isOfficerOrAdmin
                    ? ['verified', 'resolved']
                    : Object.keys(STATUS_LABELS)
                  ).map((st) => (
                    <option key={st} value={st}>
                      {STATUS_LABELS[st]}
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="filter-damage-type">Jenis Kerusakan</label>
                <select
                  id="filter-damage-type"
                  value={damageTypeFilter}
                  onChange={(e) => {
                    setDamageTypeFilter(e.target.value)
                    setPage(1)
                  }}
                >
                  <option value="">Semua Jenis</option>
                  {DAMAGE_TYPE_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label htmlFor="filter-date-from">Dari Tanggal</label>
                <input
                  id="filter-date-from"
                  type="date"
                  value={dateFrom}
                  onChange={(e) => {
                    setDateFrom(e.target.value)
                    setPage(1)
                  }}
                />
              </div>

              <div className="form-group">
                <label htmlFor="filter-date-to">Sampai Tanggal</label>
                <input
                  id="filter-date-to"
                  type="date"
                  value={dateTo}
                  onChange={(e) => {
                    setDateTo(e.target.value)
                    setPage(1)
                  }}
                />
              </div>

              <div className="form-group">
                <label htmlFor="filter-code">Cari Kode Laporan</label>
                <input
                  id="filter-code"
                  type="search"
                  value={searchCode}
                  onChange={(e) => {
                    setSearchCode(e.target.value)
                    setPage(1)
                  }}
                  placeholder="Mis. RPT-2026..."
                />
              </div>
            </div>

            <div className="filter-toggles">
              {activeTab === 'public_map' && (
                <>
                  <label className="checkbox-label">
                    <input
                      type="checkbox"
                      checked={useMapBbox}
                      onChange={(e) => {
                        setUseMapBbox(e.target.checked)
                        setPage(1)
                      }}
                    />
                    Saring otomatis sesuai batas area peta yang tampak (Bounding Box)
                  </label>
                  <label className="checkbox-label">
                    <input
                      type="checkbox"
                      checked={showSegmentsOnMap}
                      onChange={(e) => setShowSegmentsOnMap(e.target.checked)}
                    />
                    Tampilkan geometri segmen jalan ({roadSegments.length} ruas aktif)
                  </label>
                </>
              )}

              <button type="button" className="btn-secondary btn-sm" onClick={handleResetFilters}>
                Reset Filter
              </button>
            </div>
          </section>

          {/* Layout Peta + Daftar Sinkron */}
          <div className="map-and-list-grid">
            <section className="panel-card map-column">
              <div className="section-title-row">
                <h2>Peta Interaktif WebGIS</h2>
                {loadingReports && (
                  <span className="loading-pill" aria-live="polite">
                    Memuat data peta…
                  </span>
                )}
              </div>
              <WebGisMap
                reports={reports}
                roadSegments={showSegmentsOnMap ? roadSegments : []}
                selectedReportId={selectedReportId}
                onSelectReport={(r) => {
                  setSelectedReportId(r.id)
                  setDetailModalTarget(r.report_code || String(r.id))
                }}
                onBoundsChange={(bboxStr) => {
                  setMapBbox(bboxStr)
                }}
                height="480px"
              />
            </section>

            <section className="panel-card list-column" aria-live="polite">
              <div className="section-title-row">
                <h2>
                  {activeTab === 'my_reports'
                    ? 'Daftar Laporan Saya'
                    : activeTab === 'officer_queue'
                    ? 'Antrean Verifikasi Petugas'
                    : 'Daftar Laporan'}
                </h2>
                <span className="text-muted">Total: {meta.total} laporan</span>
              </div>

              {reportsError && (
                <div className="alert-box alert-error">
                  <p>{reportsError}</p>
                  <button
                    type="button"
                    className="btn-secondary btn-sm"
                    onClick={() => setRefreshTick((n) => n + 1)}
                  >
                    Coba Muat Ulang
                  </button>
                </div>
              )}

              {!reportsError && loadingReports && (
                <div className="state-box">Memuat daftar laporan…</div>
              )}

              {!reportsError && !loadingReports && reports.length === 0 && (
                <div className="state-box">
                  Tidak ada laporan yang cocok dengan filter saat ini.
                </div>
              )}

              {!reportsError && reports.length > 0 && (
                <ul className="report-card-list">
                  {reports.map((item) => {
                    const isSelected = Number(selectedReportId) === Number(item.id)
                    return (
                      <li
                        key={item.id}
                        className={`report-list-item ${isSelected ? 'selected' : ''}`}
                      >
                        <div className="report-item-top">
                          <code>{item.report_code}</code>
                          <span className={`pill pill-${item.status}`}>
                            {STATUS_LABELS[item.status] || item.status}
                          </span>
                        </div>
                        <div className="report-item-type">
                          <strong>
                            {DAMAGE_TYPE_LABELS[item.damage_type] || item.damage_type}
                          </strong>
                          {item.road_segment && (
                            <span className="segment-tag">
                              Ruas: {item.road_segment.road_reference_code}
                            </span>
                          )}
                        </div>
                        <p className="report-item-desc">{item.description}</p>
                        <div className="report-item-footer">
                          <small className="text-muted">
                            {item.reported_at
                              ? new Date(item.reported_at).toLocaleDateString('id-ID')
                              : '-'}{' '}
                            · Foto: {item.photos_count ?? 0}
                          </small>
                          <div className="report-item-actions">
                            <button
                              type="button"
                              className="btn-secondary btn-xs"
                              onClick={() => setSelectedReportId(item.id)}
                            >
                              Sorot di Peta
                            </button>
                            <button
                              type="button"
                              className="btn-xs"
                              onClick={() => {
                                setSelectedReportId(item.id)
                                setDetailModalTarget(item.report_code || String(item.id))
                              }}
                            >
                              {isOfficerOrAdmin ? 'Detail / Verifikasi' : 'Detail'}
                            </button>
                          </div>
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}

              {/* Kontrol Pagination */}
              {meta.last_page > 1 && (
                <div className="pagination-bar">
                  <button
                    type="button"
                    className="btn-secondary btn-sm"
                    disabled={page <= 1 || loadingReports}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    ← Sebelumnya
                  </button>
                  <span>
                    Halaman <strong>{meta.current_page}</strong> dari{' '}
                    <strong>{meta.last_page}</strong>
                  </span>
                  <button
                    type="button"
                    className="btn-secondary btn-sm"
                    disabled={page >= meta.last_page || loadingReports}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Berikutnya →
                  </button>
                </div>
              )}
            </section>
          </div>
        </>
      )}

      {/* Modal Detail & Verifikasi Laporan */}
      {detailModalTarget && (
        <ReportDetailModal
          key={detailModalTarget}
          reportIdOrCode={detailModalTarget}
          currentUser={currentUser}
          roadSegments={roadSegments}
          onClose={() => setDetailModalTarget('')}
          onStatusUpdated={() => setRefreshTick((n) => n + 1)}
        />
      )}
    </div>
  )
}
