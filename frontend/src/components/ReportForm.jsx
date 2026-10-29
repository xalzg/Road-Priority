import { useEffect, useState } from 'react'
import {
  BSD_STUDY_AREA_BBOX,
  DAMAGE_TYPE_OPTIONS,
  createDamageReport,
  isWithinBsdReferenceBbox,
} from '../services/reportingService'
import WebGisMap from './WebGisMap'

const MAX_PHOTOS = 5
const MAX_PHOTO_BYTES = 5 * 1024 * 1024
const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp']

export default function ReportForm({
  roadSegments = [],
  onCreated,
  draft,
  onDraftChange,
}) {
  const [damageType, setDamageType] = useState(() => draft?.damageType || 'lubang')
  const [description, setDescription] = useState(() => draft?.description || '')
  const [latitude, setLatitude] = useState(
    () => draft?.latitude || String(BSD_STUDY_AREA_BBOX.centerLat)
  )
  const [longitude, setLongitude] = useState(
    () => draft?.longitude || String(BSD_STUDY_AREA_BBOX.centerLng)
  )
  const [gpsAccuracy, setGpsAccuracy] = useState(() => draft?.gpsAccuracy || '')
  const [gpsStatus, setGpsStatus] = useState({ type: '', text: '' })
  const [files, setFiles] = useState(() => draft?.files || [])
  const [fileError, setFileError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [submitStage, setSubmitStage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [createdReport, setCreatedReport] = useState(null)

  // Sinkronisasi draft ke parent component untuk mencegah data hilang saat tab berganti (PRD 06.2 §3.E)
  useEffect(() => {
    if (onDraftChange) {
      onDraftChange({
        damageType,
        description,
        latitude,
        longitude,
        gpsAccuracy,
        files,
      })
    }
  }, [damageType, description, latitude, longitude, gpsAccuracy, files, onDraftChange])

  const handleResetDraft = () => {
    if (!description.trim() && files.length === 0) return
    if (window.confirm('Hapus seluruh isi draft formulir laporan ini?')) {
      setDescription('')
      setDamageType('lubang')
      setFiles([])
      setFieldErrors({})
      setErrorMessage('')
      if (onDraftChange) {
        onDraftChange({
          damageType: 'lubang',
          description: '',
          latitude: String(BSD_STUDY_AREA_BBOX.centerLat),
          longitude: String(BSD_STUDY_AREA_BBOX.centerLng),
          gpsAccuracy: '',
          files: [],
        })
      }
    }
  }

  const numLat = Number(latitude)
  const numLng = Number(longitude)
  const coordsValid =
    latitude !== '' &&
    longitude !== '' &&
    Number.isFinite(numLat) &&
    Number.isFinite(numLng) &&
    numLat >= -90 &&
    numLat <= 90 &&
    numLng >= -180 &&
    numLng <= 180

  const insideBsdBox = coordsValid && isWithinBsdReferenceBbox(numLat, numLng)

  const handleUseDeviceGps = () => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setGpsStatus({
        type: 'error',
        text: 'Perangkat atau browser ini tidak mendukung Geolocation API. Silakan pilih titik lokasi secara manual pada peta.',
      })
      return
    }

    setGpsStatus({ type: 'loading', text: 'Mengambil koordinat GPS perangkat…' })

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = Number(pos.coords.latitude.toFixed(7))
        const lng = Number(pos.coords.longitude.toFixed(7))
        const acc = pos.coords.accuracy ? Number(pos.coords.accuracy.toFixed(1)) : ''
        setLatitude(String(lat))
        setLongitude(String(lng))
        setGpsAccuracy(String(acc))
        setGpsStatus({
          type: 'ok',
          text: `Lokasi perangkat berhasil diambil (akurasi ±${acc || '?'} m). Anda tetap dapat menggeser pin di peta.`,
        })
      },
      (err) => {
        const reason =
          err.code === 1
            ? 'Izin akses lokasi (GPS) ditolak oleh pengguna.'
            : 'Gagal memperoleh sinyal lokasi perangkat.'
        setGpsStatus({
          type: 'error',
          text: `${reason} Silakan klik pada peta atau isi koordinat secara manual.`,
        })
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    )
  }

  const handleFileChange = (evt) => {
    setFileError('')
    const selected = Array.from(evt.target.files || [])
    if (selected.length === 0) {
      setFiles([])
      return
    }

    if (selected.length > MAX_PHOTOS) {
      setFileError(`Maksimal ${MAX_PHOTOS} foto per laporan.`)
      evt.target.value = ''
      return
    }

    for (const f of selected) {
      if (!ALLOWED_MIMES.includes(f.type)) {
        setFileError(`File "${f.name}" ditolak. Hanya format JPEG, PNG, dan WebP yang diizinkan.`)
        evt.target.value = ''
        return
      }
      if (f.size > MAX_PHOTO_BYTES) {
        setFileError(`Ukuran file "${f.name}" melebihi batas 5 MB.`)
        evt.target.value = ''
        return
      }
    }

    setFiles(selected)
  }

  const validateClientSide = () => {
    const errs = {}
    if (!damageType) {
      errs.damage_type = ['Pilih jenis kerusakan jalan.']
    }
    const trimmed = description.trim()
    if (trimmed.length < 10) {
      errs.description = ['Deskripsi kerusakan minimal 10 karakter.']
    } else if (trimmed.length > 1000) {
      errs.description = ['Deskripsi kerusakan maksimal 1000 karakter.']
    }
    if (!coordsValid) {
      errs.latitude = ['Masukkan koordinat latitude (−90 s.d. 90) dan longitude (−180 s.d. 180) yang valid.']
    }
    setFieldErrors(errs)
    return Object.keys(errs).length === 0
  }

  const handleSubmit = async (evt) => {
    evt.preventDefault()
    setErrorMessage('')
    setCreatedReport(null)

    if (!validateClientSide()) {
      return
    }

    const formData = new FormData()
    formData.append('damage_type', damageType)
    formData.append('description', description.trim())
    formData.append('latitude', String(numLat))
    formData.append('longitude', String(numLng))
    if (gpsAccuracy !== '' && Number.isFinite(Number(gpsAccuracy))) {
      formData.append('gps_accuracy_m', String(Number(gpsAccuracy)))
    }
    files.forEach((f) => {
      formData.append('photos[]', f)
    })

    setSubmitting(true)
    setSubmitStage(
      files.length > 0
        ? `Mengunggah ${files.length} foto bukti dan menyimpan laporan…`
        : 'Menyimpan laporan ke server…'
    )

    try {
      const response = await createDamageReport(formData)
      setCreatedReport(response.data)
      setDescription('')
      setFiles([])
      setFieldErrors({})
      onCreated?.(response.data)
    } catch (error) {
      setErrorMessage(error.message || 'Gagal mengirim laporan kerusakan.')
      if (error.errors) {
        setFieldErrors(error.errors)
      }
    } finally {
      setSubmitting(false)
      setSubmitStage('')
    }
  }

  return (
    <section className="panel-card">
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '0.5rem',
          marginBottom: '0.5rem',
        }}
      >
        <h2 style={{ margin: 0 }}>Buat Laporan Kerusakan Jalan</h2>
        {Boolean(description.trim() || files.length > 0) && (
          <span className="pill pill-under_review draft-indicator" style={{ fontSize: '0.8rem' }}>
            ● Draft Tersimpan di Sesi
          </span>
        )}
      </div>
      <p className="text-muted">
        Laporan yang dikirim akan masuk ke status <strong>Menunggu Tinjauan (Submitted)</strong> dan
        diperiksa oleh petugas sebelum dipublikasikan ke peta umum.
      </p>

      {createdReport && (
        <div className="alert-box alert-ok" role="status">
          <strong>Laporan berhasil dikirim!</strong> Nomor Laporan Anda:{' '}
          <code>{createdReport.report_code}</code> (Status: {createdReport.status}).
          {createdReport.study_area_check?.warning && (
            <p className="small-note">{createdReport.study_area_check.warning}</p>
          )}
        </div>
      )}

      {errorMessage && (
        <div className="alert-box alert-error" role="alert">
          {errorMessage}
        </div>
      )}

      <form onSubmit={handleSubmit} className="report-form" noValidate>
        <div className="form-grid-two">
          <div className="form-group">
            <label htmlFor="report-damage-type">Jenis Kerusakan Awal *</label>
            <select
              id="report-damage-type"
              value={damageType}
              onChange={(e) => setDamageType(e.target.value)}
              disabled={submitting}
            >
              {DAMAGE_TYPE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            <small className="field-hint">
              Kategori awal pelaporan; penilaian teknis fisik (SDI) dilakukan petugas saat asesmen lapangan.
            </small>
            {fieldErrors.damage_type && (
              <p className="field-error">{fieldErrors.damage_type[0]}</p>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="report-photos">
              Foto Bukti Lapangan (JPEG/PNG/WebP, maks {MAX_PHOTOS} foto @ 5 MB)
            </label>
            <input
              id="report-photos"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              multiple
              onChange={handleFileChange}
              disabled={submitting}
            />
            {fileError && <p className="field-error">{fileError}</p>}
            {fieldErrors.photos && <p className="field-error">{fieldErrors.photos[0]}</p>}
            {files.length > 0 && (
              <ul className="file-preview-list">
                {files.map((f, idx) => (
                  <li key={`${f.name}-${idx}`}>
                    {f.name} ({(f.size / 1024).toFixed(1)} KB)
                    {fieldErrors[`photos.${idx}`] && (
                      <span className="field-error"> — {fieldErrors[`photos.${idx}`][0]}</span>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="form-group">
          <label htmlFor="report-description">
            Deskripsi Kerusakan & Patokan Lokasi * ({description.trim().length}/1000 karakter)
          </label>
          <textarea
            id="report-description"
            rows={3}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Contoh: Lubang berdiameter ±60 cm di lajur kiri dekat halte BSD Grand Boulevard..."
            disabled={submitting}
          />
          {fieldErrors.description && (
            <p className="field-error">{fieldErrors.description[0]}</p>
          )}
        </div>

        <div className="location-picker-section">
          <div className="location-picker-header">
            <div>
              <strong>Penentuan Titik Lokasi Kerusakan (WGS 84) *</strong>
              <p className="field-hint">
                Klik pada peta, geser pin 📍, atau gunakan tombol GPS perangkat di samping.
              </p>
            </div>
            <button
              type="button"
              className="btn-secondary"
              onClick={handleUseDeviceGps}
              disabled={submitting}
            >
              Gunakan Lokasi Perangkat (GPS)
            </button>
          </div>

          {gpsStatus.text && (
            <div
              className={`alert-box ${
                gpsStatus.type === 'error'
                  ? 'alert-error'
                  : gpsStatus.type === 'ok'
                  ? 'alert-ok'
                  : 'alert-wait'
              }`}
            >
              {gpsStatus.text}
            </div>
          )}

          {coordsValid && !insideBsdBox && (
            <div className="alert-box alert-wait" role="status">
              <strong>Peringatan Wilayah Studi:</strong> Titik ({numLat.toFixed(5)},{' '}
              {numLng.toFixed(5)}) berada di luar kotak referensi wilayah studi BSD/Tangerang
              (Lat {BSD_STUDY_AREA_BBOX.minLat} s.d. {BSD_STUDY_AREA_BBOX.maxLat}, Lng{' '}
              {BSD_STUDY_AREA_BBOX.minLng} s.d. {BSD_STUDY_AREA_BBOX.maxLng}). Laporan tetap dapat
              dikirim, namun akan ditandai untuk ditinjau cakupannya oleh petugas.
            </div>
          )}

          <div className="form-grid-three">
            <div className="form-group">
              <label htmlFor="report-lat">Latitude (−90 s.d. 90) *</label>
              <input
                id="report-lat"
                type="number"
                step="0.0000001"
                value={latitude}
                onChange={(e) => setLatitude(e.target.value)}
                disabled={submitting}
              />
              {fieldErrors.latitude && (
                <p className="field-error">{fieldErrors.latitude[0]}</p>
              )}
            </div>
            <div className="form-group">
              <label htmlFor="report-lng">Longitude (−180 s.d. 180) *</label>
              <input
                id="report-lng"
                type="number"
                step="0.0000001"
                value={longitude}
                onChange={(e) => setLongitude(e.target.value)}
                disabled={submitting}
              />
              {fieldErrors.longitude && (
                <p className="field-error">{fieldErrors.longitude[0]}</p>
              )}
            </div>
            <div className="form-group">
              <label htmlFor="report-acc">Akurasi GPS (meter, opsional)</label>
              <input
                id="report-acc"
                type="number"
                step="0.1"
                value={gpsAccuracy}
                onChange={(e) => setGpsAccuracy(e.target.value)}
                placeholder="Mis. 8.5"
                disabled={submitting}
              />
            </div>
          </div>

          <WebGisMap
            reports={[]}
            roadSegments={roadSegments}
            pickerMode
            pickerPosition={
              coordsValid ? { latitude: numLat, longitude: numLng } : null
            }
            onPickPosition={({ latitude: pickedLat, longitude: pickedLng }) => {
              setLatitude(String(pickedLat))
              setLongitude(String(pickedLng))
            }}
            height="340px"
          />
        </div>

        {submitting && submitStage && (
          <div className="alert-box alert-wait" aria-live="polite">
            {submitStage}
          </div>
        )}

        <div
          className="form-actions"
          style={{ display: 'flex', gap: '0.75rem', alignItems: 'center', flexWrap: 'wrap' }}
        >
          <button type="submit" disabled={submitting}>
            {submitting ? 'Mengirim Laporan…' : 'Kirim Laporan Kerusakan'}
          </button>
          {Boolean(description.trim() || files.length > 0) && (
            <button
              type="button"
              className="btn-secondary btn-sm btn-clear-draft"
              disabled={submitting}
              onClick={handleResetDraft}
            >
              Kosongkan Draft
            </button>
          )}
        </div>
      </form>
    </section>
  )
}

