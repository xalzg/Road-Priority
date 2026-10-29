import { Suspense, lazy, useEffect, useState } from 'react'
import ReportDetailModal from '../components/ReportDetailModal'
import ReportForm from '../components/ReportForm'
import WebGisMap from '../components/WebGisMap'

const DecisionEnginePanel = lazy(() => import('../components/DecisionEnginePanel'))
import { clearStoredSession, getStoredToken, getStoredUser } from '../services/api'
import {
  BSD_STUDY_AREA_BBOX,
  DAMAGE_TYPE_LABELS,
  DAMAGE_TYPE_OPTIONS,
  STATUS_LABELS,
  fetchCurrentUser,
  fetchReports,
  fetchRoadSegments,
  loginUser,
  logoutUser,
} from '../services/reportingService'

const VALID_TABS = ['public_map', 'create_report', 'my_reports', 'officer_queue', 'decision_engine']
const VALID_SUBTABS = ['criteria_readiness', 'ahp_weights', 'topsis_runs', 'run_verification']

function getUrlParams() {
  if (typeof window === 'undefined') return { tab: null, subtab: null, report: null }
  const p = new URLSearchParams(window.location.search)
  return {
    tab: p.get('tab'),
    subtab: p.get('subtab'),
    report: p.get('report'),
  }
}

function isTabPermitted(tab, user) {
  if (!tab || tab === 'public_map') return true
  if (tab === 'create_report' || tab === 'my_reports') return Boolean(user)
  if (tab === 'officer_queue') return user && (user.role === 'officer' || user.role === 'admin')
  if (tab === 'decision_engine') return user && ['officer', 'expert', 'admin'].includes(user.role)
  return false
}

export default function HomePage() {
  const [currentUser, setCurrentUser] = useState(() => getStoredUser())

  // Inisialisasi state dari URL query string (PRD 06.2 §3.C)
  const initialParams = getUrlParams()
  const initialTab =
    initialParams.tab &&
    VALID_TABS.includes(initialParams.tab) &&
    isTabPermitted(initialParams.tab, getStoredUser())
      ? initialParams.tab
      : 'public_map'
  const initialSubTab =
    initialParams.subtab && VALID_SUBTABS.includes(initialParams.subtab)
      ? initialParams.subtab
      : 'criteria_readiness'

  const [activeTab, setActiveTab] = useState(initialTab)
  const [decisionSubTab, setDecisionSubTab] = useState(initialSubTab)

  // State draft form pelaporan di memori sesi (PRD 06.2 §3.E - mencegah draft hilang saat berpindah tab)
  const [reportDraft, setReportDraft] = useState({
    damageType: 'lubang',
    description: '',
    latitude: String(BSD_STUDY_AREA_BBOX.centerLat),
    longitude: String(BSD_STUDY_AREA_BBOX.centerLng),
    gpsAccuracy: '',
    files: [],
  })

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
  const [debouncedSearchCode, setDebouncedSearchCode] = useState('')
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
  const [detailModalTarget, setDetailModalTarget] = useState(() => initialParams.report || '')

  const isOfficerOrAdmin =
    currentUser && (currentUser.role === 'officer' || currentUser.role === 'admin')
  const canAccessDecisionEngine =
    currentUser && ['officer', 'expert', 'admin'].includes(currentUser.role)
  const hasReportDraft = Boolean(reportDraft.description?.trim() || reportDraft.files?.length > 0)

  // Sinkronisasi navigasi ke URL query string & History API (PRD 06.2 §3.C)
  const syncUrl = (tab, subTab, reportCode, replace = false) => {
    if (typeof window === 'undefined') return
    const params = new URLSearchParams(window.location.search)

    if (tab && tab !== 'public_map') {
      params.set('tab', tab)
    } else {
      params.delete('tab')
    }

    if (tab === 'decision_engine' && subTab && subTab !== 'criteria_readiness') {
      params.set('subtab', subTab)
    } else {
      params.delete('subtab')
    }

    if (reportCode) {
      params.set('report', reportCode)
    } else {
      params.delete('report')
    }

    const query = params.toString()
    const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname
    const currentSearch = window.location.search

    if (currentSearch !== (query ? `?${query}` : '')) {
      if (replace) {
        window.history.replaceState({ tab, subTab, report: reportCode }, '', newUrl)
      } else {
        window.history.pushState({ tab, subTab, report: reportCode }, '', newUrl)
      }
    }
  }

  // Listener History API (Back / Forward)
  useEffect(() => {
    const handlePopState = () => {
      const { tab: urlTab, subtab: urlSubTab, report: urlReport } = getUrlParams()
      const resolvedTab =
        urlTab && VALID_TABS.includes(urlTab) && isTabPermitted(urlTab, currentUser)
          ? urlTab
          : 'public_map'
      const resolvedSubTab =
        urlSubTab && VALID_SUBTABS.includes(urlSubTab) ? urlSubTab : 'criteria_readiness'

      setActiveTab(resolvedTab)
      setDecisionSubTab(resolvedSubTab)
      setDetailModalTarget(urlReport || '')
      if (!urlReport) setSelectedReportId(null)
    }

    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [currentUser])

  // Peringatan sebelum menutup browser/tab jika ada draft yang belum disimpan (PRD 06.2 §3.E)
  useEffect(() => {
    const handleBeforeUnload = (e) => {
      if (hasReportDraft) {
        e.preventDefault()
        e.returnValue = ''
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [hasReportDraft])

  // Tangani otomatis event unauthorized (401) dari backend jika sesi berakhir atau database sempat terputus
  useEffect(() => {
    const handleUnauthorized = () => {
      setCurrentUser(null)
      setShowLoginPanel(true)
      setLoginError('Sesi login Anda telah berakhir atau koneksi database sempat terputus. Silakan masuk kembali.')
    }
    window.addEventListener('rp:auth_unauthorized', handleUnauthorized)
    return () => window.removeEventListener('rp:auth_unauthorized', handleUnauthorized)
  }, [])

  // Debounce input pencarian kode laporan (~300ms) untuk mencegah request berlebihan (PRD 06.3 §3)
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchCode(searchCode.trim())
    }, 300)
    return () => clearTimeout(timer)
  }, [searchCode])

  const currentQueryKey = [
    activeTab,
    statusFilter,
    damageTypeFilter,
    dateFrom,
    dateTo,
    debouncedSearchCode,
    useMapBbox && activeTab === 'public_map' ? mapBbox : '',
    page,
    perPage,
    refreshTick,
    currentUser?.id || 'guest',
  ].join('|')

  const loadingReports =
    activeTab !== 'create_report' &&
    activeTab !== 'decision_engine' &&
    loadedQueryKey !== currentQueryKey

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
        setActiveTab((prev) => {
          if (!isTabPermitted(prev, null)) {
            syncUrl('public_map', 'criteria_readiness', null, true)
            return 'public_map'
          }
          return prev
        })
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
    if (activeTab === 'create_report' || activeTab === 'decision_engine') return undefined

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
    if (debouncedSearchCode) params.q = debouncedSearchCode
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
    debouncedSearchCode,
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
    syncUrl(tab, decisionSubTab, detailModalTarget)
  }

  const handleDecisionSubTabChange = (newSubTab) => {
    setDecisionSubTab(newSubTab)
    syncUrl(activeTab, newSubTab, detailModalTarget)
  }

  const openReportDetail = (reportCodeOrId, reportId = null) => {
    if (reportId !== null) {
      setSelectedReportId(reportId)
    }
    setDetailModalTarget(reportCodeOrId)
    syncUrl(activeTab, decisionSubTab, reportCodeOrId)
  }

  const closeReportDetail = () => {
    setDetailModalTarget('')
    syncUrl(activeTab, decisionSubTab, null)
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
    setReportDraft({
      damageType: 'lubang',
      description: '',
      latitude: String(BSD_STUDY_AREA_BBOX.centerLat),
      longitude: String(BSD_STUDY_AREA_BBOX.centerLng),
      gpsAccuracy: '',
      files: [],
    })
    setActiveTab('public_map')
    syncUrl('public_map', 'criteria_readiness', null, true)
    setRefreshTick((n) => n + 1)
  }

  const handleResetFilters = () => {
    setStatusFilter(activeTab === 'officer_queue' ? 'submitted' : '')
    setDamageTypeFilter('')
    setDateFrom('')
    setDateTo('')
    setSearchCode('')
    setDebouncedSearchCode('')
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
                {hasReportDraft && <span className="draft-dot" title="Terdapat draf laporan tersimpan" />}
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

          {canAccessDecisionEngine && (
            <button
              type="button"
              className={`tab-btn ${activeTab === 'decision_engine' ? 'active' : ''}`}
              onClick={() => switchTab('decision_engine')}
            >
              Prioritas AHP-TOPSIS
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
          draft={reportDraft}
          onDraftChange={setReportDraft}
          onCreated={(created) => {
            setReportDraft({
              damageType: 'lubang',
              description: '',
              latitude: String(BSD_STUDY_AREA_BBOX.centerLat),
              longitude: String(BSD_STUDY_AREA_BBOX.centerLng),
              gpsAccuracy: '',
              files: [],
            })
            setRefreshTick((n) => n + 1)
            if (created?.report_code) {
              openReportDetail(created.report_code, created.id)
            }
          }}
        />
      )}

      {/* Tab Mesin Pendukung Keputusan AHP-TOPSIS (PRD 05 & PRD 06.3 Code-Splitting) */}
      {activeTab === 'decision_engine' && canAccessDecisionEngine && (
        <Suspense
          fallback={
            <div className="panel-card state-box" aria-live="polite">
              Memuat modul Mesin Pendukung Keputusan (AHP-TOPSIS)…
            </div>
          }
        >
          <DecisionEnginePanel
            currentUser={currentUser}
            roadSegments={roadSegments}
            subTab={decisionSubTab}
            onSubTabChange={handleDecisionSubTabChange}
          />
        </Suspense>
      )}

      {/* Tab Peta Publik / Laporan Saya / Antrean Petugas */}
      {activeTab !== 'create_report' && activeTab !== 'decision_engine' && (
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
                <label htmlFor="filter-code">
                  Cari Kode Laporan
                  {searchCode.trim() !== debouncedSearchCode && (
                    <span className="search-debouncing-indicator" aria-live="polite">
                      {' '}(mengetik…)
                    </span>
                  )}
                </label>
                <input
                  id="filter-code"
                  type="search"
                  value={searchCode}
                  onChange={(e) => {
                    setSearchCode(e.target.value)
                    setPage(1)
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault()
                      setDebouncedSearchCode(searchCode.trim())
                    }
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
                  openReportDetail(r.report_code || String(r.id), r.id)
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
                                openReportDetail(item.report_code || String(item.id), item.id)
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
          onClose={closeReportDetail}
          onStatusUpdated={() => setRefreshTick((n) => n + 1)}
        />
      )}
    </div>
  )
}
