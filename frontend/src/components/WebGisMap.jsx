import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useEffect, useRef } from 'react'
import {
  BSD_STUDY_AREA_BBOX,
  DAMAGE_TYPE_LABELS,
  STATUS_LABELS,
} from '../services/reportingService'

const STATUS_COLORS = {
  verified: '#0f6b2f',
  resolved: '#0284c7',
  under_review: '#b45309',
  submitted: '#6d28d9',
  needs_info: '#a16207',
  rejected: '#991b1b',
  duplicate: '#475569',
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/**
 * Mengelompokkan (clustering) titik laporan berdasarkan grid spasial ketika tingkat zoom < 15
 * agar tampilan peta tetap terbaca saat jumlah marker banyak (PRD 04 §7.2).
 */
function buildMapClusters(reports, zoom) {
  if (!Array.isArray(reports) || reports.length === 0) return []
  if (zoom >= 15 || reports.length <= 12) {
    return reports.map((r) => ({
      type: 'single',
      lat: Number(r.latitude),
      lng: Number(r.longitude),
      report: r,
    }))
  }

  const cellSizeDeg = zoom <= 11 ? 0.025 : zoom === 12 ? 0.012 : zoom === 13 ? 0.006 : 0.003
  const buckets = new Map()

  reports.forEach((r) => {
    const lat = Number(r.latitude)
    const lng = Number(r.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

    const key = `${Math.floor(lat / cellSizeDeg)}:${Math.floor(lng / cellSizeDeg)}`
    if (!buckets.has(key)) {
      buckets.set(key, [])
    }
    buckets.get(key).push(r)
  })

  const result = []
  buckets.forEach((group) => {
    if (group.length === 1) {
      result.push({
        type: 'single',
        lat: Number(group[0].latitude),
        lng: Number(group[0].longitude),
        report: group[0],
      })
    } else {
      const avgLat = group.reduce((acc, item) => acc + Number(item.latitude), 0) / group.length
      const avgLng = group.reduce((acc, item) => acc + Number(item.longitude), 0) / group.length
      result.push({
        type: 'cluster',
        lat: avgLat,
        lng: avgLng,
        count: group.length,
        reports: group,
      })
    }
  })

  return result
}

export default function WebGisMap({
  reports = [],
  roadSegments = [],
  selectedReportId = null,
  onSelectReport,
  onBoundsChange,
  pickerMode = false,
  pickerPosition = null,
  onPickPosition,
  showStudyBox = true,
  height = '480px',
}) {
  const containerRef = useRef(null)
  const mapRef = useRef(null)
  const markersLayerRef = useRef(null)
  const segmentsLayerRef = useRef(null)
  const pickerMarkerRef = useRef(null)
  const studyBoxLayerRef = useRef(null)
  const debounceTimerRef = useRef(null)
  const lastEmittedBboxRef = useRef('')

  const onSelectReportRef = useRef(onSelectReport)
  const onBoundsChangeRef = useRef(onBoundsChange)
  const onPickPositionRef = useRef(onPickPosition)

  useEffect(() => {
    onSelectReportRef.current = onSelectReport
  }, [onSelectReport])

  useEffect(() => {
    onBoundsChangeRef.current = onBoundsChange
  }, [onBoundsChange])

  useEffect(() => {
    onPickPositionRef.current = onPickPosition
  }, [onPickPosition])

  // Inisialisasi peta Leaflet satu kali
  useEffect(() => {
    if (!containerRef.current || mapRef.current) return undefined

    const map = L.map(containerRef.current, {
      center: [BSD_STUDY_AREA_BBOX.centerLat, BSD_STUDY_AREA_BBOX.centerLng],
      zoom: 13,
      zoomControl: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors (ODbL)',
    }).addTo(map)

    segmentsLayerRef.current = L.layerGroup().addTo(map)
    markersLayerRef.current = L.layerGroup().addTo(map)
    studyBoxLayerRef.current = L.layerGroup().addTo(map)

    const emitBounds = () => {
      if (!onBoundsChangeRef.current) return
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current)
      }
      debounceTimerRef.current = setTimeout(() => {
        const b = map.getBounds()
        const bboxStr = [
          b.getWest().toFixed(6),
          b.getSouth().toFixed(6),
          b.getEast().toFixed(6),
          b.getNorth().toFixed(6),
        ].join(',')
        if (bboxStr !== lastEmittedBboxRef.current) {
          lastEmittedBboxRef.current = bboxStr
          onBoundsChangeRef.current?.(bboxStr)
        }
      }, 350)
    }

    map.on('moveend', emitBounds)

    map.on('click', (evt) => {
      if (onPickPositionRef.current) {
        onPickPositionRef.current({
          latitude: Number(evt.latlng.lat.toFixed(7)),
          longitude: Number(evt.latlng.lng.toFixed(7)),
        })
      }
    })

    mapRef.current = map

    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current)
      }
      map.remove()
      mapRef.current = null
    }
  }, [])

  // Gambar kotak referensi wilayah studi BSD/Tangerang
  useEffect(() => {
    const layer = studyBoxLayerRef.current
    if (!layer) return
    layer.clearLayers()

    if (showStudyBox) {
      const bounds = [
        [BSD_STUDY_AREA_BBOX.minLat, BSD_STUDY_AREA_BBOX.minLng],
        [BSD_STUDY_AREA_BBOX.maxLat, BSD_STUDY_AREA_BBOX.maxLng],
      ]
      L.rectangle(bounds, {
        color: '#1d4ed8',
        weight: 1.5,
        dashArray: '6, 6',
        fillOpacity: 0.02,
        interactive: false,
      }).addTo(layer)
    }
  }, [showStudyBox])

  // Gambar geometri segmen jalan (GeoJSON memakai [longitude, latitude] -> Leaflet [latitude, longitude])
  useEffect(() => {
    const layer = segmentsLayerRef.current
    if (!layer) return
    layer.clearLayers()

    roadSegments.forEach((seg) => {
      const coords = seg?.geometry_geojson?.coordinates
      if (!Array.isArray(coords) || coords.length < 2) return

      const latLngs = coords
        .filter((pt) => Array.isArray(pt) && pt.length >= 2)
        .map((pt) => [Number(pt[1]), Number(pt[0])])

      if (latLngs.length < 2) return

      const isDemo = Boolean(seg.is_demo)
      const verifiedCount = Number(seg.verified_reports_count ?? 0)
      const topsisRank = seg.topsis_rank ?? seg.priority_rank ?? null

      let color = '#2563eb'
      let weight = 3.5

      if (topsisRank !== null && topsisRank !== undefined) {
        if (Number(topsisRank) === 1) {
          color = '#b91c1c'
          weight = 6
        } else if (Number(topsisRank) <= 3) {
          color = '#d97706'
          weight = 5
        } else {
          color = '#059669'
          weight = 4
        }
      } else {
        if (verifiedCount >= 3) {
          color = '#dc2626'
          weight = 5
        } else if (verifiedCount >= 1) {
          color = '#ea580c'
          weight = 4
        } else {
          color = '#2563eb'
          weight = 3.5
        }
      }

      const polyline = L.polyline(latLngs, {
        color: isDemo ? '#d97706' : color,
        weight,
        opacity: isDemo ? 0.75 : 0.88,
        dashArray: isDemo ? '6, 6' : undefined,
      })

      const demoBadge = isDemo
        ? `<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:0.75rem;font-weight:600;background:#fef3c7;color:#92400e;margin-top:2px;">[DATA DEMO]</span>`
        : `<span style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:0.75rem;font-weight:600;background:#dcfce7;color:#166534;margin-top:2px;">[DATA RESMI]</span>`

      const lengthText = seg.length_km ? `${Number(seg.length_km).toFixed(2)} km` : '-'
      const topsisHtml =
        topsisRank !== null && topsisRank !== undefined
          ? `<div style="margin-top:4px;padding:3px 6px;background:#f1f5f9;border-left:3px solid ${color};border-radius:3px;">
               <strong>Prioritas TOPSIS: Peringkat #${topsisRank}</strong><br/>
               <small>Koefisien Kedekatan (Ci): ${Number(seg.topsis_score ?? seg.closeness_coefficient ?? 0).toFixed(4)}</small>
             </div>`
          : ''

      const popupHtml = `
        <div class="map-popup">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
            <strong>${escapeHtml(seg.road_reference_code)}</strong>
            ${demoBadge}
          </div>
          <div style="font-weight:600;font-size:0.95rem;margin:2px 0;">${escapeHtml(seg.name)}</div>
          <small>Panjang: ${escapeHtml(lengthText)} | Fungsi: ${escapeHtml(seg.road_function || '-')} | Kewenangan: ${escapeHtml(seg.road_authority || '-')}</small><br/>
          <small>Laporan terverifikasi: <strong>${verifiedCount}</strong></small>
          ${topsisHtml}
        </div>
      `
      polyline.bindPopup(popupHtml)
      polyline.addTo(layer)
    })
  }, [roadSegments])

  // Gambar marker laporan & clustering
  useEffect(() => {
    const map = mapRef.current
    const layer = markersLayerRef.current
    if (!map || !layer) return

    const renderMarkers = () => {
      layer.clearLayers()
      const zoom = map.getZoom()
      const clusters = buildMapClusters(reports, zoom)

      clusters.forEach((item) => {
        if (item.type === 'cluster') {
          const clusterIcon = L.divIcon({
            className: 'map-cluster-icon',
            html: `<span>${item.count}</span>`,
            iconSize: [36, 36],
          })
          const marker = L.marker([item.lat, item.lng], { icon: clusterIcon })
          marker.on('click', () => {
            map.setView([item.lat, item.lng], Math.min(zoom + 2, 17))
          })
          marker.bindTooltip(`${item.count} laporan di area ini (klik untuk perbesar)`)
          marker.addTo(layer)
          return
        }

        const r = item.report
        const isSelected = selectedReportId !== null && Number(r.id) === Number(selectedReportId)
        const fillColor = STATUS_COLORS[r.status] || '#1d4ed8'

        const circle = L.circleMarker([item.lat, item.lng], {
          radius: isSelected ? 11 : 8,
          color: isSelected ? '#111827' : '#ffffff',
          weight: isSelected ? 3 : 2,
          fillColor,
          fillOpacity: 0.92,
        })

        const dateLabel = r.reported_at
          ? new Date(r.reported_at).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
            })
          : '-'

        const popupNode = document.createElement('div')
        popupNode.className = 'map-popup'
        popupNode.innerHTML = `
          <div><strong>${escapeHtml(r.report_code)}</strong></div>
          <div>Jenis: ${escapeHtml(DAMAGE_TYPE_LABELS[r.damage_type] || r.damage_type)}</div>
          <div>Status: ${escapeHtml(STATUS_LABELS[r.status] || r.status)}</div>
          <div>Tanggal: ${escapeHtml(dateLabel)}</div>
        `
        const detailBtn = document.createElement('button')
        detailBtn.type = 'button'
        detailBtn.className = 'btn-sm map-popup-btn'
        detailBtn.textContent = 'Buka Detail Laporan'
        detailBtn.addEventListener('click', () => {
          onSelectReportRef.current?.(r)
        })
        popupNode.appendChild(detailBtn)

        circle.bindPopup(popupNode)
        circle.addTo(layer)

        if (isSelected) {
          circle.openPopup()
        }
      })
    }

    renderMarkers()
    map.on('zoomend', renderMarkers)
    return () => {
      map.off('zoomend', renderMarkers)
    }
  }, [reports, selectedReportId])

  // Pusatkan peta ketika laporan dipilih dari daftar
  useEffect(() => {
    const map = mapRef.current
    if (!map || selectedReportId === null) return

    const target = reports.find((r) => Number(r.id) === Number(selectedReportId))
    if (!target) return

    const lat = Number(target.latitude)
    const lng = Number(target.longitude)
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
      map.setView([lat, lng], Math.max(map.getZoom(), 15), { animate: false })
    }
  }, [selectedReportId, reports])

  // Marker pemilihan titik lokasi laporan (pickerMode)
  useEffect(() => {
    const map = mapRef.current
    if (!map) return

    if (!pickerMode || !pickerPosition) {
      if (pickerMarkerRef.current) {
        pickerMarkerRef.current.remove()
        pickerMarkerRef.current = null
      }
      return
    }

    const lat = Number(pickerPosition.latitude)
    const lng = Number(pickerPosition.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

    if (!pickerMarkerRef.current) {
      const icon = L.divIcon({
        className: 'map-picker-pin',
        html: '<span>📍</span>',
        iconSize: [30, 30],
        iconAnchor: [15, 28],
      })
      const marker = L.marker([lat, lng], { draggable: true, icon }).addTo(map)
      marker.on('dragend', () => {
        const pos = marker.getLatLng()
        onPickPositionRef.current?.({
          latitude: Number(pos.lat.toFixed(7)),
          longitude: Number(pos.lng.toFixed(7)),
        })
      })
      pickerMarkerRef.current = marker
    } else {
      pickerMarkerRef.current.setLatLng([lat, lng])
    }
  }, [pickerMode, pickerPosition])

  return (
    <div className="webgis-map-wrapper">
      <div
        ref={containerRef}
        className="webgis-map-canvas"
        style={{ height }}
        aria-label="Peta interaktif laporan kerusakan jalan RoadPriority"
      />
      <div
        className="webgis-map-legend"
        role="region"
        aria-label="Legenda peta interaktif kerusakan dan ruas jalan"
      >
        <div className="legend-group" role="group" aria-label="Status laporan kerusakan">
          <span className="legend-title">Laporan:</span>
          <span>
            <i className="legend-dot" style={{ background: STATUS_COLORS.verified }} aria-hidden="true" />{' '}
            Terverifikasi
          </span>
          <span>
            <i className="legend-dot" style={{ background: STATUS_COLORS.resolved }} aria-hidden="true" />{' '}
            Ditangani
          </span>
          <span>
            <i className="legend-dot" style={{ background: STATUS_COLORS.under_review }} aria-hidden="true" />{' '}
            Ditinjau
          </span>
          <span>
            <i className="legend-dot" style={{ background: STATUS_COLORS.submitted }} aria-hidden="true" />{' '}
            Baru Masuk
          </span>
        </div>
        <div className="legend-group" role="group" aria-label="Kategori intensitas dan status ruas jalan">
          <span className="legend-title">Ruas Jalan:</span>
          <span>
            <i className="legend-line" style={{ background: '#dc2626' }} aria-hidden="true" /> &ge; 3 Laporan
          </span>
          <span>
            <i className="legend-line" style={{ background: '#ea580c' }} aria-hidden="true" /> 1–2 Laporan
          </span>
          <span>
            <i className="legend-line" style={{ background: '#2563eb' }} aria-hidden="true" /> 0 Laporan
          </span>
          <span>
            <i className="legend-line legend-line-demo" aria-hidden="true" /> Ruas Demo
          </span>
        </div>
      </div>
    </div>
  )
}

