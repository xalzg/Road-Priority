import { useEffect, useState } from 'react'
import {
  SAATY_SCALE_OPTIONS,
  activateWeightVersion,
  calculateWeightVersion,
  createWeightVersion,
  executeDecisionRun,
  fetchCriteria,
  fetchDataReadiness,
  fetchDecisionRunDetail,
  fetchDecisionRuns,
  fetchWeightVersions,
  recordDamageAssessment,
  updateCriterion,
} from '../services/decisionService'

export default function DecisionEnginePanel({
  currentUser,
  roadSegments = [],
  subTab: propSubTab,
  onSubTabChange,
}) {
  const [internalSubTab, setInternalSubTab] = useState('criteria_readiness')
  const subTab = propSubTab || internalSubTab
  const setSubTab = onSubTabChange || setInternalSubTab
  const [includeDemo, setIncludeDemo] = useState(false)
  const [refreshCounter, setRefreshCounter] = useState(0)
  const [confirmActivateVersion, setConfirmActivateVersion] = useState(null)

  // Data utama dari backend
  const [criteria, setCriteria] = useState([])
  const [readiness, setReadiness] = useState(null)
  const [weightVersions, setWeightVersions] = useState([])
  const [decisionRuns, setDecisionRuns] = useState([])
  const [selectedRunDetail, setSelectedRunDetail] = useState(null)
  const [loadedKey, setLoadedKey] = useState('')
  const [loadError, setLoadError] = useState('')
  const [actionBanner, setActionBanner] = useState(null)

  // State form asesmen lapangan cepat (untuk petugas/admin melengkapi kesiapan data)
  const [assessSegmentId, setAssessSegmentId] = useState('')
  const [assessSeverity, setAssessSeverity] = useState('4')
  const [assessSafety, setAssessSafety] = useState('3')
  const [assessFunctionScore, setAssessFunctionScore] = useState('3')
  const [assessImpactScore, setAssessImpactScore] = useState('3')
  const [assessSource, setAssessSource] = useState('field_survey')
  const [assessProtocol] = useState('SDI-ADAPTED-1.0')
  const [assessSaving, setAssessSaving] = useState(false)

  // State form pembuatan & perhitungan AHP
  const [newVersionName, setNewVersionName] = useState('')
  const [selectedVersionId, setSelectedVersionId] = useState('')
  const [pairwiseValues, setPairwiseValues] = useState({})
  const [ahpBusy, setAhpBusy] = useState(false)

  // State form eksekusi TOPSIS
  const [runName, setRunName] = useState('')
  const [runWeightVersionId, setRunWeightVersionId] = useState('')
  const [runIsSimulation, setRunIsSimulation] = useState(false)
  const [runBusy, setRunBusy] = useState(false)

  const isAdmin = currentUser?.role === 'admin'
  const isExpertOrAdmin = currentUser?.role === 'expert' || isAdmin
  const isOfficerOrAdmin = currentUser?.role === 'officer' || isAdmin

  const queryKey = `${includeDemo ? 'demo' : 'official'}|${refreshCounter}`
  const isLoading = loadedKey !== queryKey

  useEffect(() => {
    const controller = new AbortController()

    Promise.all([
      fetchCriteria({ signal: controller.signal }),
      fetchDataReadiness({ includeDemo, signal: controller.signal }),
      fetchWeightVersions({ signal: controller.signal }),
      fetchDecisionRuns({ signal: controller.signal }),
    ])
      .then(([critRes, readyRes, verRes, runsRes]) => {
        const critList = critRes?.data || []
        const verList = verRes?.data || []
        const runList = runsRes?.data || []

        setCriteria(critList)
        setReadiness(readyRes?.data || null)
        setWeightVersions(verList)
        setDecisionRuns(runList)
        setLoadError('')
        setLoadedKey(queryKey)

        if (verList.length > 0) {
          setSelectedVersionId((prev) => prev || String(verList[0].id))
          const approved = verList.find((v) => v.status === 'approved')
          setRunWeightVersionId((prev) => prev || String((approved || verList[0]).id))
        }
      })
      .catch((err) => {
        if (err.name === 'AbortError') return
        setLoadError(err.message || 'Gagal memuat data mesin keputusan AHP-TOPSIS.')
        setLoadedKey(queryKey)
      })

    return () => controller.abort()
  }, [includeDemo, queryKey])

  const activeCriteria = criteria.filter((c) => c.is_active)

  const readinessSegments = readiness
    ? [...(readiness.eligible_segments || []), ...(readiness.incomplete_segments || [])].map(
        (seg) => ({
          id: seg.segment_id,
          road_reference_code: seg.code,
          name: seg.name,
          is_demo: Boolean(seg.is_demo),
        })
      )
    : []

  const assessmentSegmentOptions =
    readinessSegments.length > 0
      ? readinessSegments
      : roadSegments.filter((s) => includeDemo || !s.is_demo)

  // Pasangan segitiga atas (i < j) dari kriteria aktif
  const upperPairs = []
  for (let i = 0; i < activeCriteria.length; i += 1) {
    for (let j = i + 1; j < activeCriteria.length; j += 1) {
      upperPairs.push([activeCriteria[i], activeCriteria[j]])
    }
  }

  const getPairValue = (idA, idB) => {
    const key = `${idA}_${idB}`
    return pairwiseValues[key] !== undefined ? Number(pairwiseValues[key]) : 1
  }

  const handleToggleCriterionActive = async (criterion) => {
    setActionBanner(null)
    try {
      await updateCriterion(criterion.id, { is_active: !criterion.is_active })
      setActionBanner({
        type: 'success',
        text: `Status aktif kriteria ${criterion.code} berhasil diperbarui.`,
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal memperbarui kriteria.',
      })
    }
  }

  const handleRecordAssessment = async (evt) => {
    evt.preventDefault()
    if (!assessSegmentId) return
    setAssessSaving(true)
    setActionBanner(null)
    try {
      const targetSegment = assessmentSegmentOptions.find(
        (s) => String(s.id) === String(assessSegmentId)
      )
      const isDemoTarget = Boolean(targetSegment?.is_demo)

      await recordDamageAssessment({
        road_segment_id: Number(assessSegmentId),
        severity_level: Number(assessSeverity),
        safety_risk_score: Number(assessSafety),
        road_function_score: Number(assessFunctionScore),
        impact_score: Number(assessImpactScore),
        status: 'final',
        assessment_source: isDemoTarget ? 'demo_fixture' : assessSource,
        protocol_version: assessProtocol,
        is_demo: isDemoTarget,
      })
      setActionBanner({
        type: 'success',
        text: 'Penilaian kondisi lapangan (DamageAssessment) berhasil disimpan.',
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal menyimpan penilaian lapangan.',
      })
    } finally {
      setAssessSaving(false)
    }
  }

  const handleCreateDraftVersion = async (evt) => {
    evt.preventDefault()
    if (!newVersionName.trim()) return
    setAhpBusy(true)
    setActionBanner(null)
    try {
      const res = await createWeightVersion({
        version_name: newVersionName.trim(),
        aggregation_method: 'geometric_mean_aij',
      })
      const created = res?.data
      setNewVersionName('')
      if (created?.id) {
        setWeightVersions((prev) => [created, ...prev.filter((v) => v.id !== created.id)])
        setSelectedVersionId(String(created.id))
      }
      setActionBanner({
        type: 'success',
        text: `Draft versi bobot "${created?.version_name}" berhasil dibuat. Silakan isi perbandingan berpasangan AHP di bawah.`,
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal membuat draft versi bobot.',
      })
    } finally {
      setAhpBusy(false)
    }
  }

  const handleCalculateAhp = async (evt) => {
    evt.preventDefault()
    if (!selectedVersionId) return
    setAhpBusy(true)
    setActionBanner(null)
    try {
      const comparisons = upperPairs.map(([critA, critB]) => ({
        criterion_a_id: critA.id,
        criterion_b_id: critB.id,
        comparison_value: getPairValue(critA.id, critB.id),
      }))
      const res = await calculateWeightVersion(selectedVersionId, {
        criterion_ids: activeCriteria.map((c) => c.id),
        comparisons,
      })
      setActionBanner({
        type: res?.data?.is_consistent ? 'success' : 'warning',
        text: res?.message || 'Perhitungan bobot AHP selesai.',
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal menghitung bobot AHP.',
      })
    } finally {
      setAhpBusy(false)
    }
  }

  const executeActivateVersion = async (versionId) => {
    setAhpBusy(true)
    setActionBanner(null)
    try {
      const res = await activateWeightVersion(versionId)
      setRunWeightVersionId(String(versionId))
      setActionBanner({
        type: 'success',
        text: res?.message || 'Versi bobot berhasil disetujui dan diaktifkan.',
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal mengaktifkan versi bobot.',
      })
    } finally {
      setAhpBusy(false)
    }
  }

  const handleExecuteTopsis = async (evt) => {
    evt.preventDefault()
    if (!runName.trim() || !runWeightVersionId) return
    setRunBusy(true)
    setActionBanner(null)
    try {
      const res = await executeDecisionRun({
        name: runName.trim(),
        criterion_weight_version_id: Number(runWeightVersionId),
        is_simulation: runIsSimulation,
        parameters: { include_demo: runIsSimulation && includeDemo },
      })
      setRunName('')
      setSelectedRunDetail(res?.data || null)
      setSubTab('run_verification')
      setActionBanner({
        type: 'success',
        text: `Perhitungan prioritas TOPSIS (${res?.data?.run_code}) berhasil dieksekusi dan disimpan sebagai snapshot terverifikasi.`,
      })
      setRefreshCounter((n) => n + 1)
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal menjalankan perhitungan TOPSIS.',
      })
    } finally {
      setRunBusy(false)
    }
  }

  const handleOpenRunDetail = async (runId) => {
    setRunBusy(true)
    setActionBanner(null)
    try {
      const res = await fetchDecisionRunDetail(runId)
      setSelectedRunDetail(res?.data || null)
      setSubTab('run_verification')
    } catch (err) {
      setActionBanner({
        type: 'error',
        text: err.message || 'Gagal memuat rincian decision run.',
      })
    } finally {
      setRunBusy(false)
    }
  }

  const selectedVersion =
    weightVersions.find((v) => String(v.id) === String(selectedVersionId)) || null

  return (
    <section className="panel-card decision-engine-panel" aria-label="Mesin Pendukung Keputusan Prioritas Jalan AHP-TOPSIS">
      <div className="section-title-row">
        <div>
          <h2>Mesin Pendukung Keputusan Prioritas Jalan (AHP-TOPSIS)</h2>
          <p className="text-muted">
            Rekomendasi urutan prioritas penanganan ruas jalan berbasis kriteria terukur, konsistensi
            perbandingan berpasangan AHP (CR &le; 0,10), dan kedekatan relatif TOPSIS terhadap solusi ideal.
          </p>
        </div>
        <div className="filter-toggles">
          <label className="checkbox-label">
            <input
              type="checkbox"
              checked={includeDemo}
              onChange={(e) => setIncludeDemo(e.target.checked)}
            />
            Sertakan data berlabel <code>[DATA DEMO]</code> (khusus mode simulasi)
          </label>
          <button
            type="button"
            className="btn-secondary btn-sm"
            onClick={() => setRefreshCounter((n) => n + 1)}
          >
            Muat Ulang Data
          </button>
        </div>
      </div>

      {/* Sub-navigasi modul AHP-TOPSIS */}
      <div className="nav-tabs decision-subtabs" style={{ marginTop: '0.75rem', marginBottom: '1rem' }}>
        <button
          type="button"
          className={`tab-btn ${subTab === 'criteria_readiness' ? 'active' : ''}`}
          onClick={() => setSubTab('criteria_readiness')}
        >
          1. Kriteria &amp; Kesiapan Data
        </button>
        <button
          type="button"
          className={`tab-btn ${subTab === 'ahp_weights' ? 'active' : ''}`}
          onClick={() => setSubTab('ahp_weights')}
        >
          2. Perbandingan AHP &amp; Versi Bobot
        </button>
        <button
          type="button"
          className={`tab-btn ${subTab === 'topsis_runs' ? 'active' : ''}`}
          onClick={() => setSubTab('topsis_runs')}
        >
          3. Eksekusi &amp; Riwayat TOPSIS
        </button>
        <button
          type="button"
          className={`tab-btn ${subTab === 'run_verification' ? 'active' : ''}`}
          onClick={() => setSubTab('run_verification')}
        >
          4. Rincian Perhitungan &amp; Penjelasan Peringkat
        </button>
      </div>

      {isLoading && <div className="state-box">Memuat konfigurasi dan data AHP-TOPSIS…</div>}
      {loadError && <div className="alert-box alert-error">{loadError}</div>}
      {actionBanner && (
        <div
          className={`alert-box ${
            actionBanner.type === 'error'
              ? 'alert-error'
              : actionBanner.type === 'warning'
              ? 'alert-warning'
              : 'alert-success'
          }`}
          style={{ marginBottom: '1rem' }}
        >
          {actionBanner.text}
        </div>
      )}

      {/* SUB-TAB 1: DAFTAR KRITERIA & LAPORAN KESIAPAN DATA */}
      {subTab === 'criteria_readiness' && (
        <div>
          <h3>1.1 Daftar Kriteria Keputusan &amp; Atribut Benefit/Cost</h3>
          <p className="text-muted">
            Kriteria yang telah digunakan dalam versi bobot atau perhitungan historis bersifat{' '}
            <strong>immutable</strong> pada atribut semantik (kode, tipe benefit/cost, skala) agar riwayat
            keputusan tetap dapat direproduksi.
          </p>
          <div className="table-scroll-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Nama Kriteria</th>
                  <th>Tipe Atribut</th>
                  <th>Skala / Satuan</th>
                  <th>Rentang Valid</th>
                  <th>Aturan Agregasi</th>
                  <th>Status</th>
                  {isAdmin && <th>Aksi Admin</th>}
                </tr>
              </thead>
              <tbody>
                {criteria.map((c) => (
                  <tr key={c.id}>
                    <td>
                      <code>{c.code}</code>
                    </td>
                    <td>
                      <strong>{c.name}</strong>
                      <div className="text-muted" style={{ fontSize: '0.82rem' }}>
                        {c.description}
                      </div>
                    </td>
                    <td>
                      <span
                        className={`pill ${
                          c.criterion_type === 'benefit' ? 'pill-verified' : 'pill-under_review'
                        }`}
                      >
                        {c.criterion_type === 'benefit'
                          ? 'BENEFIT (Makin besar makin prioritas)'
                          : 'COST (Makin kecil makin prioritas)'}
                      </span>
                    </td>
                    <td>{c.unit || c.data_type}</td>
                    <td>
                      {c.minimum_value ?? '-'} s/d {c.maximum_value ?? '∞'}
                    </td>
                    <td>
                      <code>{c.aggregation_rule || '-'}</code>
                    </td>
                    <td>{c.is_active ? 'Aktif' : 'Nonaktif'}</td>
                    {isAdmin && (
                      <td>
                        <button
                          type="button"
                          className="btn-secondary btn-xs"
                          onClick={() => handleToggleCriterionActive(c)}
                        >
                          {c.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <h3>1.2 Laporan Kesiapan Data Alternatif Ruas Jalan (PRD 05 §2 &amp; §7)</h3>
          {readiness && (
            <>
              <div className="filter-grid" style={{ marginBottom: '1rem' }}>
                <div className="state-box">
                  <strong>Kriteria Aktif:</strong> {readiness.summary?.active_criteria_count}
                </div>
                <div className="state-box">
                  <strong>Total Segmen Aktif:</strong> {readiness.summary?.total_active_segments}
                </div>
                <div className="state-box">
                  <strong>Segmen Layak TOPSIS:</strong> {readiness.summary?.eligible_segments_count}
                </div>
                <div className="state-box">
                  <strong>Segmen Data Belum Lengkap:</strong>{' '}
                  {readiness.summary?.incomplete_segments_count}
                </div>
              </div>
              <p className="text-muted">
                <strong>Kebijakan Data Hilang ({readiness.missing_data_policy?.code}):</strong>{' '}
                {readiness.missing_data_policy?.description}
              </p>

              <div className="table-scroll-container">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Kode Ruas</th>
                      <th>Nama Ruas Jalan</th>
                      <th>Asal Data &amp; Sumber</th>
                      <th>Tanggal &amp; Protokol Asesmen</th>
                      {activeCriteria.map((c) => (
                        <th key={c.code}>
                          {c.code} ({c.criterion_type === 'benefit' ? '+' : '-'})
                        </th>
                      ))}
                      <th>Kelayakan TOPSIS</th>
                    </tr>
                  </thead>
                  <tbody>
                    {[
                      ...(readiness.eligible_segments || []),
                      ...(readiness.incomplete_segments || []),
                    ].map((seg) => (
                      <tr key={seg.segment_id}>
                        <td>
                          <code>{seg.code}</code>
                          {seg.is_demo && <span className="segment-tag">[DEMO]</span>}
                        </td>
                        <td>{seg.name}</td>
                        <td>
                          <div>
                            <code>{seg.data_origin || '-'}</code>
                          </div>
                          {seg.source_name && (
                            <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                              {seg.source_name}
                            </div>
                          )}
                        </td>
                        <td>
                          {seg.assessment_date ? (
                            <div>
                              <div>{new Date(seg.assessment_date).toLocaleDateString('id-ID')}</div>
                              <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                                <code>{seg.assessment_source || '-'}</code> |{' '}
                                <code>{seg.assessment_protocol || '-'}</code>
                              </div>
                            </div>
                          ) : (
                            'Belum ada asesmen'
                          )}
                        </td>
                        {activeCriteria.map((c) => {
                          const v = seg.values?.[c.code]
                          return (
                            <td key={c.code}>
                              {v !== null && v !== undefined ? (
                                <strong>{Number(v).toFixed(2)}</strong>
                              ) : (
                                <span style={{ color: '#b91c1c' }}>Kosong</span>
                              )}
                            </td>
                          )
                        })}
                        <td>
                          {seg.is_eligible ? (
                            <span className="pill pill-verified">Layak</span>
                          ) : (
                            <span style={{ color: '#b91c1c', fontSize: '0.85rem' }}>
                              Dikeluarkan:{' '}
                              {(seg.missing_or_invalid || []).map((m) => m.reason).join(', ')}
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}

          {isOfficerOrAdmin && (
            <div className="state-box" style={{ textAlign: 'left' }}>
              <h4>Catat Penilaian Kondisi Jalan Lapangan (DamageAssessment)</h4>
              <p className="text-muted">
                Digunakan oleh petugas/admin untuk mencatat skor pemeriksaan fisik ruas jalan (K1, K2, K3,
                K4) agar segmen memenuhi syarat kesiapan data TOPSIS.
              </p>
              <form onSubmit={handleRecordAssessment} className="filter-grid">
                <div className="form-group">
                  <label htmlFor="assess-segment">Pilih Segmen Jalan</label>
                  <select
                    id="assess-segment"
                    required
                    value={assessSegmentId}
                    onChange={(e) => setAssessSegmentId(e.target.value)}
                  >
                    <option value="">-- Pilih Ruas Jalan --</option>
                    {assessmentSegmentOptions.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.is_demo ? '[DATA DEMO] ' : ''}
                        {s.road_reference_code} — {s.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label htmlFor="assess-k1">Tingkat Kerusakan K1 (1–5)</label>
                  <input
                    id="assess-k1"
                    type="number"
                    min="1"
                    max="5"
                    step="1"
                    value={assessSeverity}
                    onChange={(e) => setAssessSeverity(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="assess-k2">Risiko Keselamatan K2 (1–5)</label>
                  <input
                    id="assess-k2"
                    type="number"
                    min="1"
                    max="5"
                    step="1"
                    value={assessSafety}
                    onChange={(e) => setAssessSafety(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="assess-k3">Skor Fungsi Jalan K3 (1–4)</label>
                  <input
                    id="assess-k3"
                    type="number"
                    min="1"
                    max="4"
                    step="1"
                    value={assessFunctionScore}
                    onChange={(e) => setAssessFunctionScore(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="assess-k4">Intensitas Pengguna K4 (1–5)</label>
                  <input
                    id="assess-k4"
                    type="number"
                    min="1"
                    max="5"
                    step="1"
                    value={assessImpactScore}
                    onChange={(e) => setAssessImpactScore(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="assess-source">Sumber Asesmen (Protokol {assessProtocol})</label>
                  <select
                    id="assess-source"
                    value={assessSource}
                    onChange={(e) => setAssessSource(e.target.value)}
                  >
                    <option value="field_survey">field_survey (Survei Lapangan Terstruktur)</option>
                    <option value="field_inspection">field_inspection (Inspeksi Teknis Petugas)</option>
                    <option value="report_verification">report_verification (Verifikasi Laporan Warga)</option>
                    <option value="official_secondary">official_secondary (Dokumen Teknis Sekunder)</option>
                  </select>
                </div>
                <div className="form-group" style={{ alignSelf: 'end' }}>
                  <button type="submit" disabled={assessSaving || !assessSegmentId}>
                    {assessSaving ? 'Menyimpan…' : 'Simpan Asesmen Final'}
                  </button>
                </div>
              </form>
            </div>
          )}
        </div>
      )}

      {/* SUB-TAB 2: PERBANDINGAN BERPASANGAN AHP & VERSI BOBOT */}
      {subTab === 'ahp_weights' && (
        <div>
          <h3>2.1 Daftar Versi Bobot Kriteria (AHP)</h3>
          {isExpertOrAdmin && (
            <form
              onSubmit={handleCreateDraftVersion}
              className="login-form-inline"
              style={{ marginBottom: '1rem' }}
            >
              <div className="form-group" style={{ flex: 1 }}>
                <label htmlFor="new-version-name">Nama Versi Bobot Baru</label>
                <input
                  id="new-version-name"
                  type="text"
                  required
                  value={newVersionName}
                  onChange={(e) => setNewVersionName(e.target.value)}
                  placeholder="Mis. Bobot Pakar Dinas PUPR Tahap I"
                  disabled={ahpBusy}
                />
              </div>
              <button type="submit" disabled={ahpBusy || !newVersionName.trim()}>
                + Buat Draft Versi Bobot
              </button>
            </form>
          )}

          <div className="table-scroll-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nama Versi</th>
                  <th>Status</th>
                  <th>Jumlah Kriteria</th>
                  <th>&lambda; max</th>
                  <th>CI</th>
                  <th>CR (Ambang &le; 0,10)</th>
                  <th>Konsistensi</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {weightVersions.map((ver) => (
                  <tr
                    key={ver.id}
                    style={{
                      backgroundColor:
                        String(ver.id) === String(selectedVersionId) ? '#eff6ff' : 'transparent',
                    }}
                  >
                    <td>#{ver.id}</td>
                    <td>
                      <strong>{ver.version_name}</strong>
                    </td>
                    <td>
                      <span
                        className={`pill ${
                          ver.status === 'approved'
                            ? 'pill-verified'
                            : ver.status === 'computed'
                            ? 'pill-under_review'
                            : 'pill-submitted'
                        }`}
                      >
                        {ver.status.toUpperCase()}
                      </span>
                    </td>
                    <td>{ver.criteria_count}</td>
                    <td>{ver.lambda_max !== null ? Number(ver.lambda_max).toFixed(4) : '-'}</td>
                    <td>
                      {ver.consistency_index !== null ? Number(ver.consistency_index).toFixed(4) : '-'}
                    </td>
                    <td>
                      {ver.consistency_ratio !== null ? (
                        <strong>{Number(ver.consistency_ratio).toFixed(4)}</strong>
                      ) : (
                        '-'
                      )}
                    </td>
                    <td>
                      {ver.status === 'draft' ? (
                        'Belum dihitung'
                      ) : ver.is_consistent ? (
                        <span className="pill pill-verified">KONSISTEN (CR &le; 0,10)</span>
                      ) : (
                        <span className="pill pill-rejected">TIDAK KONSISTEN (CR &gt; 0,10)</span>
                      )}
                    </td>
                    <td>
                      <div style={{ display: 'flex', gap: '0.4rem', flexWrap: 'wrap' }}>
                        <button
                          type="button"
                          className="btn-secondary btn-xs"
                          onClick={() => setSelectedVersionId(String(ver.id))}
                        >
                          Pilih / Lihat Bobot
                        </button>
                        {isAdmin && ver.status === 'computed' && ver.is_consistent && (
                          <button
                            type="button"
                            className="btn-xs"
                            disabled={ahpBusy}
                            onClick={() => setConfirmActivateVersion(ver)}
                          >
                            Setujui &amp; Aktifkan
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {selectedVersion && (
            <div className="state-box" style={{ textAlign: 'left', marginBottom: '1.5rem' }}>
              <h4>
                Rincian Bobot Kriteria pada Versi #{selectedVersion.id}: {selectedVersion.version_name}
              </h4>
              {selectedVersion.status !== 'draft' && !selectedVersion.is_consistent && (
                <div className="alert-box alert-error" style={{ marginBottom: '1rem' }}>
                  <strong>Peringatan Inkonsistensi AHP:</strong> Consistency Ratio (CR ={' '}
                  {Number(selectedVersion.consistency_ratio).toFixed(4)}) melebihi batas maksimum{' '}
                  <strong>0,10</strong>. Versi bobot ini tidak dapat disetujui untuk perhitungan resmi
                  sampai matriks perbandingan berpasangan diperbaiki.
                </div>
              )}

              {selectedVersion.weight_items?.length > 0 ? (
                <div className="table-scroll-container">
                  <table className="data-table">
                  <thead>
                    <tr>
                      <th>Kode</th>
                      <th>Nama Kriteria</th>
                      <th>Tipe</th>
                      <th>Bobot Ternormalisasi (w_j)</th>
                      <th>Persentase</th>
                    </tr>
                  </thead>
                  <tbody>
                    {selectedVersion.weight_items.map((wi) => (
                      <tr key={wi.id}>
                        <td>
                          <code>{wi.criterion?.code}</code>
                        </td>
                        <td>{wi.criterion?.name}</td>
                        <td>{wi.criterion?.criterion_type}</td>
                        <td>
                          <strong>{Number(wi.weight_value).toFixed(6)}</strong>
                        </td>
                        <td>{(Number(wi.weight_value) * 100).toFixed(2)}%</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
                <p className="text-muted">
                  Versi ini masih berstatus draft dan belum memiliki hasil perhitungan bobot.
                </p>
              )}
            </div>
          )}

          {isExpertOrAdmin && selectedVersion && selectedVersion.status !== 'approved' && (
            <div className="state-box" style={{ textAlign: 'left' }}>
              <h4>
                2.2 Form Perbandingan Berpasangan AHP (Skala Saaty 1–9) untuk Versi #
                {selectedVersion.id}
              </h4>
              <p className="text-muted">
                Bandingkan tingkat kepentingan relatif pasangan kriteria aktif ({activeCriteria.length}{' '}
                kriteria &rarr; {upperPairs.length} pasangan segitiga atas). Nilai resiprokal segitiga
                bawah (<code>a_ji = 1 / a_ij</code>) dan diagonal utama (<code>a_ii = 1</code>) diisi
                otomatis oleh server.
              </p>
              <form onSubmit={handleCalculateAhp}>
                <div className="table-scroll-container">
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>Kriteria A (Baris i)</th>
                        <th>Skala Perbandingan Saaty (A terhadap B)</th>
                        <th>Kriteria B (Kolom j)</th>
                        <th>Resiprokal Otomatis (B terhadap A)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {upperPairs.map(([critA, critB]) => {
                        const val = getPairValue(critA.id, critB.id)
                        return (
                          <tr key={`${critA.id}_${critB.id}`}>
                            <td>
                              <strong>
                                {critA.code} — {critA.name}
                              </strong>
                            </td>
                            <td>
                              <select
                                value={val}
                                onChange={(e) =>
                                  setPairwiseValues((prev) => ({
                                    ...prev,
                                    [`${critA.id}_${critB.id}`]: Number(e.target.value),
                                  }))
                                }
                              >
                                {SAATY_SCALE_OPTIONS.map((opt) => (
                                  <option key={opt.label} value={opt.value}>
                                    {opt.label}
                                  </option>
                                ))}
                              </select>
                            </td>
                            <td>
                              <strong>
                                {critB.code} — {critB.name}
                              </strong>
                            </td>
                            <td>
                              <code>{(1 / val).toFixed(4)}</code>
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
                <button type="submit" disabled={ahpBusy || upperPairs.length === 0}>
                  {ahpBusy ? 'Menghitung Eigenvector & CR…' : 'Hitung Bobot AHP & Uji Konsistensi (CR)'}
                </button>
              </form>
            </div>
          )}
        </div>
      )}

      {/* SUB-TAB 3: EKSEKUSI & RIWAYAT PERHITUNGAN TOPSIS */}
      {subTab === 'topsis_runs' && (
        <div>
          <h3>3.1 Form Eksekusi Perhitungan Prioritas TOPSIS</h3>
          <form onSubmit={handleExecuteTopsis} className="filter-grid" style={{ marginBottom: '1.5rem' }}>
            <div className="form-group">
              <label htmlFor="run-name">Nama Eksekusi / Skenario Keputusan</label>
              <input
                id="run-name"
                type="text"
                required
                value={runName}
                onChange={(e) => setRunName(e.target.value)}
                placeholder="Mis. Prioritas Penanganan Jalan Semester I"
                disabled={runBusy}
              />
            </div>
            <div className="form-group">
              <label htmlFor="run-weight-version">Versi Bobot Kriteria AHP</label>
              <select
                id="run-weight-version"
                required
                value={runWeightVersionId}
                onChange={(e) => setRunWeightVersionId(e.target.value)}
                disabled={runBusy}
              >
                <option value="">-- Pilih Versi Bobot --</option>
                {weightVersions.map((v) => (
                  <option key={v.id} value={v.id}>
                    #{v.id} {v.version_name} [{v.status.toUpperCase()} | CR:{' '}
                    {v.consistency_ratio !== null ? Number(v.consistency_ratio).toFixed(4) : '-'}]
                  </option>
                ))}
              </select>
            </div>
            <div className="form-group" style={{ alignSelf: 'end' }}>
              <label className="checkbox-label">
                <input
                  type="checkbox"
                  checked={runIsSimulation}
                  onChange={(e) => setRunIsSimulation(e.target.checked)}
                />
                Jalankan sebagai Mode Simulasi (mengizinkan versi bobot belum approved / data demo)
              </label>
            </div>
            <div className="form-group" style={{ alignSelf: 'end' }}>
              <button type="submit" disabled={runBusy || !runName.trim() || !runWeightVersionId}>
                {runBusy ? 'Mengeksekusi TOPSIS…' : 'Jalankan Perhitungan TOPSIS'}
              </button>
            </div>
          </form>

          <h3>3.2 Riwayat Eksekusi Keputusan (Decision Runs)</h3>
          <div className="table-scroll-container">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Kode Run</th>
                  <th>Nama Perhitungan</th>
                  <th>Mode</th>
                  <th>Versi Bobot</th>
                  <th>Ruas Diperingkat</th>
                  <th>Ruas Dikeluarkan</th>
                  <th>Waktu Eksekusi</th>
                  <th>Hash Input (SHA-256)</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {decisionRuns.map((run) => (
                  <tr key={run.id}>
                    <td>
                      <code>{run.run_code}</code>
                    </td>
                    <td>
                      <strong>{run.name}</strong>
                    </td>
                    <td>
                      <span
                        className={`pill ${
                          run.is_simulation ? 'pill-under_review' : 'pill-verified'
                        }`}
                      >
                        {run.is_simulation ? 'SIMULASI' : 'RESMI'}
                      </span>
                    </td>
                    <td>{run.weight_version?.version_name || `#${run.criterion_weight_version_id}`}</td>
                    <td>{run.ranked_count ?? '-'} ruas</td>
                    <td>{run.excluded_count ?? '-'} ruas</td>
                    <td>
                      {run.executed_at ? new Date(run.executed_at).toLocaleString('id-ID') : '-'}
                    </td>
                    <td>
                      <code title={run.input_hash}>
                        {run.input_hash ? `${run.input_hash.slice(0, 12)}…` : '-'}
                      </code>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="btn-xs"
                        disabled={runBusy}
                        onClick={() => handleOpenRunDetail(run.id)}
                      >
                        Buka Rincian &amp; Verifikasi
                      </button>
                    </td>
                  </tr>
                ))}
                {decisionRuns.length === 0 && (
                  <tr>
                    <td colSpan={9} style={{ textAlign: 'center' }}>
                      Belum ada riwayat perhitungan TOPSIS.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* SUB-TAB 4: RINCIAN PERHITUNGAN TOPSIS & PENJELASAN PERINGKAT */}
      {subTab === 'run_verification' && (
        <div>
          {!selectedRunDetail ? (
            <div className="state-box">
              Pilih salah satu riwayat perhitungan pada tab{' '}
              <strong>3. Eksekusi &amp; Riwayat TOPSIS</strong> untuk melihat rincian matriks{' '}
              <code>X, R, V</code>, solusi ideal{' '}
              <code>
                A<sup>+</sup>, A<sup>-</sup>
              </code>
              , jarak{' '}
              <code>
                D<sub>i</sub>
                <sup>+</sup>, D<sub>i</sub>
                <sup>-</sup>
              </code>
              , dan koefisien preferensi{' '}
              <code>
                C<sub>i</sub>
              </code>
              .
            </div>
          ) : (
            <div>
              <div className="alert-box" style={{ marginBottom: '1rem', background: '#f8fafc' }}>
                <strong>Pernyataan Batasan Rekomendasi (PRD 05 §1):</strong>{' '}
                {selectedRunDetail.disclaimer}
              </div>

              <div className="filter-grid" style={{ marginBottom: '1rem' }}>
                <div className="state-box">
                  <strong>Kode Run:</strong> <code>{selectedRunDetail.run_code}</code> (
                  {selectedRunDetail.is_simulation ? 'SIMULASI' : 'RESMI'})
                </div>
                <div className="state-box">
                  <strong>Versi Algoritma:</strong> <code>{selectedRunDetail.algorithm_version}</code>
                </div>
                <div className="state-box">
                  <strong>Verifikasi Reproduktibilitas Snapshot:</strong>{' '}
                  {selectedRunDetail.reproducibility_verified ? (
                    <span className="pill pill-verified">TERVERIFIKASI IDENTIK</span>
                  ) : (
                    <span className="pill pill-rejected">GAGAL</span>
                  )}
                </div>
                <div className="state-box">
                  <strong>Korelasi Spearman vs Bobot Sama Rata:</strong>{' '}
                  {selectedRunDetail.sensitivity_summary?.equal_weights_spearman !== null &&
                  selectedRunDetail.sensitivity_summary?.equal_weights_spearman !== undefined
                    ? Number(selectedRunDetail.sensitivity_summary.equal_weights_spearman).toFixed(4)
                    : '-'}
                </div>
              </div>

              <h3>
                4.1 Tabel Peringkat Prioritas Penanganan Jalan (D<sub>i</sub>
                <sup>+</sup>, D<sub>i</sub>
                <sup>-</sup>, C<sub>i</sub>)
              </h3>
              <div className="table-scroll-container">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Peringkat</th>
                      <th>Kode Ruas</th>
                      <th>Nama Ruas Jalan</th>
                      <th>
                        Jarak Ideal Positif (D<sub>i</sub>
                        <sup>+</sup>)
                      </th>
                      <th>
                        Jarak Ideal Negatif (D<sub>i</sub>
                        <sup>-</sup>)
                      </th>
                      <th>
                        Skor Preferensi (C<sub>i</sub>)
                      </th>
                      <th>Kriteria Pendorong Utama</th>
                      <th>Penjelasan Peringkat &amp; Sumber Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(selectedRunDetail.ranked_items || []).map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>#{item.rank_position}</strong>
                        </td>
                        <td>
                          <code>{item.road_reference_code}</code>
                        </td>
                        <td>{item.road_segment_name}</td>
                        <td>{Number(item.d_plus).toFixed(6)}</td>
                        <td>{Number(item.d_minus).toFixed(6)}</td>
                        <td>
                          <strong>{Number(item.preference_score).toFixed(6)}</strong>
                        </td>
                        <td>
                          <code>{item.largest_driving_criterion}</code>
                        </td>
                        <td style={{ fontSize: '0.85rem' }}>
                          <div>{item.explanation}</div>
                          <div className="text-muted">
                            Asal data: <code>{item.data_origin}</code> | Laporan terverifikasi:{' '}
                            {(item.source_report_ids || []).length}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {selectedRunDetail.excluded_items?.length > 0 && (
                <>
                  <h3>4.2 Ruas Jalan yang Dikeluarkan dari Perhitungan (Beserta Alasan)</h3>
                  <div style={{ overflowX: 'auto', marginBottom: '1.5rem' }}>
                    <table className="data-table">
                      <thead>
                        <tr>
                          <th>Kode Ruas</th>
                          <th>Nama Ruas Jalan</th>
                          <th>Alasan Eksklusi (D-09)</th>
                        </tr>
                      </thead>
                      <tbody>
                        {selectedRunDetail.excluded_items.map((ex) => (
                          <tr key={ex.id}>
                            <td>
                              <code>{ex.road_reference_code}</code>
                            </td>
                            <td>{ex.road_segment_name}</td>
                            <td style={{ color: '#b91c1c' }}>
                              <code>{ex.exclusion_reason}</code>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </>
              )}

              <h3>
                4.3 Rincian Tahapan Matriks TOPSIS (X &rarr; R &rarr; V) &amp; Solusi Ideal (A
                <sup>+</sup>, A<sup>-</sup>)
              </h3>
              <div className="table-scroll-container">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Kode Kriteria</th>
                      <th>Tipe</th>
                      <th>
                        Bobot AHP (w<sub>j</sub>)
                      </th>
                      <th>
                        Solusi Ideal Positif (A<sub>j</sub>
                        <sup>+</sup>)
                      </th>
                      <th>
                        Solusi Ideal Negatif (A<sub>j</sub>
                        <sup>-</sup>)
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {(selectedRunDetail.criteria_snapshot || []).map((c) => {
                      const code = c.code
                      const w = selectedRunDetail.weight_version?.weights?.[code]
                      const ap = selectedRunDetail.ideal_solutions?.positive?.[code]
                      const am = selectedRunDetail.ideal_solutions?.negative?.[code]
                      return (
                        <tr key={code}>
                          <td>
                            <code>{code}</code>
                          </td>
                          <td>{c.type}</td>
                          <td>{w !== undefined ? Number(w).toFixed(6) : '-'}</td>
                          <td>{ap !== undefined ? Number(ap).toFixed(6) : '-'}</td>
                          <td>{am !== undefined ? Number(am).toFixed(6) : '-'}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              <div className="table-scroll-container">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>Ruas</th>
                      {(selectedRunDetail.criteria_snapshot || []).map((c) => (
                        <th key={c.code}>
                          {c.code}: Mentah (x<sub>ij</sub>) | Normal (r<sub>ij</sub>) | Terbobot (v
                          <sub>ij</sub>)
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {(selectedRunDetail.ranked_items || []).map((item) => (
                      <tr key={item.id}>
                        <td>
                          <strong>{item.road_reference_code}</strong>
                        </td>
                        {(selectedRunDetail.criteria_snapshot || []).map((c) => {
                          const code = c.code
                          const raw = item.input_values?.[code]
                          const norm = item.normalized_values?.[code]
                          const weighted = item.weighted_values?.[code]
                          return (
                            <td key={code} style={{ fontFamily: 'monospace', fontSize: '0.84rem' }}>
                              x={raw !== undefined ? Number(raw).toFixed(2) : '-'} | r=
                              {norm !== undefined ? Number(norm).toFixed(4) : '-'} | v=
                              {weighted !== undefined ? Number(weighted).toFixed(4) : '-'}
                            </td>
                          )
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}
      {/* Modal Konfirmasi Aktivasi Versi Bobot AHP (PRD 06.2 §3.F) */}
      {confirmActivateVersion && (
        <div
          className="modal-backdrop"
          role="dialog"
          aria-modal="true"
          aria-labelledby="confirm-activate-title"
        >
          <div className="modal-card confirm-modal-box" style={{ maxWidth: '540px' }}>
            <div className="modal-header">
              <h3 id="confirm-activate-title">Konfirmasi Persetujuan &amp; Aktivasi Bobot AHP</h3>
              <button
                type="button"
                className="btn-secondary btn-sm"
                disabled={ahpBusy}
                onClick={() => setConfirmActivateVersion(null)}
              >
                ✕
              </button>
            </div>
            <div style={{ padding: '0.4rem 0' }}>
              <p>
                Anda akan menyetujui dan mengaktifkan{' '}
                <strong>
                  Versi #{confirmActivateVersion.id}: {confirmActivateVersion.version_name}
                </strong>{' '}
                secara permanen.
              </p>
              <div className="helper-box">
                <ul>
                  <li>
                    Status versi bobot akan diubah menjadi <strong>APPROVED</strong>.
                  </li>
                  <li>
                    Versi ini akan menjadi acuan utama pada perhitungan prioritas penanganan jalan
                    (TOPSIS) resmi.
                  </li>
                  <li>
                    Rasio Konsistensi: <strong>CR = {Number(confirmActivateVersion.consistency_ratio).toFixed(4)}</strong> (&le; 0,10 / Konsisten).
                  </li>
                  <li>
                    Tindakan ini dicatat dalam Audit Log sistem beserta identitas Anda ({currentUser?.name || 'Administrator'}).
                  </li>
                </ul>
              </div>
            </div>
            <div
              style={{
                display: 'flex',
                justifyContent: 'flex-end',
                gap: '0.75rem',
                marginTop: '1rem',
                paddingTop: '0.75rem',
                borderTop: '1px solid var(--color-border)',
              }}
            >
              <button
                type="button"
                className="btn-secondary"
                disabled={ahpBusy}
                onClick={() => setConfirmActivateVersion(null)}
              >
                Batal
              </button>
              <button
                type="button"
                className="btn-primary"
                disabled={ahpBusy}
                onClick={async () => {
                  const targetId = confirmActivateVersion.id
                  setConfirmActivateVersion(null)
                  await executeActivateVersion(targetId)
                }}
              >
                {ahpBusy ? 'Memproses Aktivasi…' : 'Ya, Setujui & Aktifkan Bobot'}
              </button>
            </div>
          </div>
        </div>
      )}
    </section>
  )
}

