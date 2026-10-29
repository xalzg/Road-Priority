import { useEffect, useState } from 'react'
import AuthorizedPhotoThumb from './AuthorizedPhotoThumb'
import {
  ALLOWED_STATUS_TRANSITIONS,
  DAMAGE_TYPE_LABELS,
  STATUS_LABELS,
  fetchReportDetail,
  updateDamageReportStatus,
} from '../services/reportingService'

export default function ReportDetailModal({
  reportIdOrCode,
  currentUser,
  roadSegments = [],
  onClose,
  onStatusUpdated,
}) {
  const [detail, setDetail] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // State form verifikasi petugas
  const [nextStatus, setNextStatus] = useState('')
  const [selectedSegmentId, setSelectedSegmentId] = useState('')
  const [duplicateOfId, setDuplicateOfId] = useState('')
  const [notes, setNotes] = useState('')
  const [updating, setUpdating] = useState(false)
  const [updateError, setUpdateError] = useState('')
  const [updateSuccess, setUpdateSuccess] = useState('')

  const isOfficerOrAdmin =
    currentUser && (currentUser.role === 'officer' || currentUser.role === 'admin')

  useEffect(() => {
    if (!reportIdOrCode) return undefined
    const controller = new AbortController()

    fetchReportDetail(reportIdOrCode, { signal: controller.signal })
      .then((res) => {
        const d = res.data
        setDetail(d)
        const allowed = ALLOWED_STATUS_TRANSITIONS[d.status] || []
        setNextStatus(allowed[0] || '')
        setSelectedSegmentId(d.road_segment_id ? String(d.road_segment_id) : '')
        setDuplicateOfId(d.duplicate_of_id ? String(d.duplicate_of_id) : '')
        setNotes('')
        setError('')
        setLoading(false)
      })
      .catch((err) => {
        if (err.name === 'AbortError') return
        setError(err.message || 'Gagal memuat detail laporan.')
        setLoading(false)
      })

    return () => controller.abort()
  }, [reportIdOrCode])

  if (!reportIdOrCode) return null

  const allowedTransitions = detail ? ALLOWED_STATUS_TRANSITIONS[detail.status] || [] : []
  const notesRequired = ['rejected', 'duplicate', 'needs_info', 'resolved'].includes(nextStatus)

  const handleStatusSubmit = async (evt) => {
    evt.preventDefault()
    if (!detail || !nextStatus) return
    setUpdateError('')
    setUpdateSuccess('')

    if (notesRequired && notes.trim().length === 0) {
      setUpdateError('Catatan verifikasi wajib diisi untuk status yang dipilih.')
      return
    }
    if (nextStatus === 'duplicate' && !duplicateOfId) {
      setUpdateError('Pilih laporan acuan (duplicate_of_id) ketika menetapkan status Duplikat.')
      return
    }

    setUpdating(true)
    try {
      const payload = {
        new_status: nextStatus,
        notes: notes.trim() || null,
        road_segment_id: selectedSegmentId ? Number(selectedSegmentId) : null,
        duplicate_of_id:
          nextStatus === 'duplicate' && duplicateOfId ? Number(duplicateOfId) : null,
      }
      await updateDamageReportStatus(detail.id, payload)
      const refreshed = await fetchReportDetail(detail.id)
      setDetail(refreshed.data)
      const nextAllowed = ALLOWED_STATUS_TRANSITIONS[refreshed.data.status] || []
      setNextStatus(nextAllowed[0] || '')
      setNotes('')
      setUpdateSuccess(`Status laporan berhasil diperbarui menjadi ${refreshed.data.status}.`)
      onStatusUpdated?.(refreshed.data)
    } catch (err) {
      setUpdateError(err.message || 'Gagal memperbarui status laporan.')
    } finally {
      setUpdating(false)
    }
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true" aria-label="Detail laporan kerusakan">
      <div className="modal-card">
        <div className="modal-header">
          <h3>
            Detail Laporan {detail?.report_code ? `— ${detail.report_code}` : ''}
          </h3>
          <button type="button" className="btn-secondary btn-sm" onClick={onClose}>
            Tutup ✕
          </button>
        </div>

        {loading && <p className="state-box">Memuat detail laporan…</p>}
        {error && <div className="alert-box alert-error">{error}</div>}

        {!loading && detail && (
          <div className="modal-body">
            <div className="detail-meta-grid">
              <div>
                <span className="meta-label">Kode Laporan</span>
                <strong>{detail.report_code}</strong>
              </div>
              <div>
                <span className="meta-label">Status Saat Ini</span>
                <span className={`pill pill-${detail.status}`}>
                  {STATUS_LABELS[detail.status] || detail.status}
                </span>
              </div>
              <div>
                <span className="meta-label">Jenis Kerusakan</span>
                <strong>{DAMAGE_TYPE_LABELS[detail.damage_type] || detail.damage_type}</strong>
              </div>
              <div>
                <span className="meta-label">Waktu Dilaporkan</span>
                <span>
                  {detail.reported_at
                    ? new Date(detail.reported_at).toLocaleString('id-ID')
                    : '-'}
                </span>
              </div>
              <div>
                <span className="meta-label">Koordinat (Lat, Lng)</span>
                <code>
                  {Number(detail.latitude).toFixed(6)}, {Number(detail.longitude).toFixed(6)}
                </code>
              </div>
              <div>
                <span className="meta-label">Segmen Jalan Terkait</span>
                <span>
                  {detail.road_segment
                    ? `${detail.road_segment.road_reference_code} — ${detail.road_segment.name}`
                    : 'Belum dihubungkan ke segmen jalan'}
                  {detail.segment_match_distance_m !== null &&
                    detail.segment_match_distance_m !== undefined &&
                    ` (${detail.segment_match_distance_m} m)`}
                </span>
              </div>
            </div>

            <div className="detail-section">
              <h4>Deskripsi Laporan</h4>
              <p className="detail-description">{detail.description}</p>
            </div>

            <div className="detail-section">
              <h4>Foto Bukti ({detail.photos?.length || 0})</h4>
              {(!detail.photos || detail.photos.length === 0) ? (
                <p className="text-muted">Tidak ada foto terlampir pada laporan ini.</p>
              ) : (
                <div className="photo-grid">
                  {detail.photos.map((p) => (
                    <AuthorizedPhotoThumb key={p.id} photo={p} />
                  ))}
                </div>
              )}
            </div>

            <div className="detail-section">
              <h4>Riwayat Status (Append-Only Audit Trail)</h4>
              {(!detail.status_histories || detail.status_histories.length === 0) ? (
                <p className="text-muted">Belum ada catatan riwayat status.</p>
              ) : (
                <ul className="history-timeline">
                  {detail.status_histories.map((h) => (
                    <li key={h.id}>
                      <strong>
                        {h.previous_status ? `${h.previous_status} → ` : ''}
                        {h.new_status}
                      </strong>{' '}
                      <small className="text-muted">
                        ({h.changed_at ? new Date(h.changed_at).toLocaleString('id-ID') : '-'})
                      </small>
                      {h.notes && <div className="history-note">Catatan: {h.notes}</div>}
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {isOfficerOrAdmin && (
              <div className="officer-verification-box">
                <h4>Panel Verifikasi Petugas</h4>

                {detail.study_area_check?.warning && (
                  <div className="alert-box alert-wait">
                    {detail.study_area_check.warning}
                  </div>
                )}

                {detail.suggested_segments?.length > 0 && (
                  <div className="helper-box">
                    <strong>Rekomendasi Segmen Jalan Terdekat (≤ 50 m):</strong>
                    <ul>
                      {detail.suggested_segments.map((s) => (
                        <li key={s.id}>
                          <code>{s.road_reference_code}</code> — {s.name} ({s.distance_meters} m){' '}
                          <button
                            type="button"
                            className="btn-secondary btn-xs"
                            onClick={() => setSelectedSegmentId(String(s.id))}
                          >
                            Pilih Segmen Ini
                          </button>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {detail.nearby_duplicate_candidates?.length > 0 && (
                  <div className="helper-box">
                    <strong>Kandidat Laporan Berdekatan (Radius 50 m & 30 Hari):</strong>
                    <p className="field-hint">
                      Periksa secara manual sebelum menetapkan sebagai duplikat.
                    </p>
                    <ul>
                      {detail.nearby_duplicate_candidates.map((c) => (
                        <li key={c.id}>
                          <code>{c.report_code}</code> ({c.status}, jarak {c.distance_meters} m){' '}
                          <button
                            type="button"
                            className="btn-secondary btn-xs"
                            onClick={() => {
                              setDuplicateOfId(String(c.id))
                              if (allowedTransitions.includes('duplicate')) {
                                setNextStatus('duplicate')
                              }
                            }}
                          >
                            Jadikan Acuan Duplikat (#{c.id})
                          </button>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {updateError && <div className="alert-box alert-error">{updateError}</div>}
                {updateSuccess && <div className="alert-box alert-ok">{updateSuccess}</div>}

                {allowedTransitions.length === 0 ? (
                  <p className="text-muted">
                    Laporan ini sudah berada pada status akhir (<code>{detail.status}</code>).
                  </p>
                ) : (
                  <form onSubmit={handleStatusSubmit} className="verification-form">
                    <div className="form-grid-two">
                      <div className="form-group">
                        <label htmlFor="verify-next-status">Transisi Status Berikutnya *</label>
                        <select
                          id="verify-next-status"
                          value={nextStatus}
                          onChange={(e) => setNextStatus(e.target.value)}
                          disabled={updating}
                        >
                          {allowedTransitions.map((st) => (
                            <option key={st} value={st}>
                              {STATUS_LABELS[st] || st}
                            </option>
                          ))}
                        </select>
                      </div>

                      <div className="form-group">
                        <label htmlFor="verify-segment">Hubungkan ke Segmen Jalan (Opsional)</label>
                        <select
                          id="verify-segment"
                          value={selectedSegmentId}
                          onChange={(e) => setSelectedSegmentId(e.target.value)}
                          disabled={updating}
                        >
                          <option value="">-- Belum dihubungkan / Tanpa segmen --</option>
                          {roadSegments.map((seg) => (
                            <option key={seg.id} value={seg.id}>
                              {seg.road_reference_code} — {seg.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    </div>

                    {nextStatus === 'duplicate' && (
                      <div className="form-group">
                        <label htmlFor="verify-dup-id">ID Laporan Acuan Duplikat *</label>
                        <input
                          id="verify-dup-id"
                          type="number"
                          value={duplicateOfId}
                          onChange={(e) => setDuplicateOfId(e.target.value)}
                          placeholder="Masukkan ID laporan utama"
                          disabled={updating}
                        />
                      </div>
                    )}

                    <div className="form-group">
                      <label htmlFor="verify-notes">
                        Catatan Verifikasi / Alasan {notesRequired ? '*' : '(Opsional)'}
                      </label>
                      <textarea
                        id="verify-notes"
                        rows={2}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Tuliskan hasil pemeriksaan foto/lokasi atau alasan penolakan/duplikat..."
                        disabled={updating}
                      />
                    </div>

                    <button type="submit" disabled={updating}>
                      {updating ? 'Menyimpan Status…' : 'Simpan Perubahan Status'}
                    </button>
                  </form>
                )}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
