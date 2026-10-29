import {
  apiRequest,
  clearStoredSession,
  getStoredToken,
  setStoredSession,
} from './api'

export const DAMAGE_TYPE_OPTIONS = [
  { value: 'lubang', label: 'Lubang (Pothole)' },
  { value: 'retak', label: 'Retak (Crack)' },
  { value: 'permukaan_bergelombang', label: 'Permukaan Bergelombang' },
  { value: 'aspal_mengelupas', label: 'Aspal Mengelupas' },
  { value: 'kerusakan_tepi_jalan', label: 'Kerusakan Tepi Jalan' },
  { value: 'lainnya', label: 'Lainnya' },
]

export const DAMAGE_TYPE_LABELS = {
  lubang: 'Lubang (Pothole)',
  berlubang: 'Lubang (Pothole)',
  retak: 'Retak (Crack)',
  permukaan_bergelombang: 'Permukaan Bergelombang',
  aspal_mengelupas: 'Aspal Mengelupas',
  kerusakan_tepi_jalan: 'Kerusakan Tepi Jalan',
  lainnya: 'Lainnya',
}

export const STATUS_LABELS = {
  submitted: 'Menunggu Tinjauan (Submitted)',
  under_review: 'Sedang Ditinjau (Under Review)',
  needs_info: 'Perlu Informasi Tambahan',
  verified: 'Terverifikasi (Publik)',
  rejected: 'Ditolak',
  duplicate: 'Duplikat',
  resolved: 'Telah Ditangani (Resolved)',
}

export const ALLOWED_STATUS_TRANSITIONS = {
  submitted: ['under_review'],
  under_review: ['verified', 'rejected', 'duplicate', 'needs_info'],
  needs_info: ['submitted', 'under_review'],
  verified: ['resolved', 'under_review'],
  rejected: ['under_review'],
  duplicate: ['under_review'],
  resolved: [],
}

export const BSD_STUDY_AREA_BBOX = {
  minLat: -6.38,
  maxLat: -6.22,
  minLng: 106.58,
  maxLng: 106.75,
  centerLat: -6.3015,
  centerLng: 106.6525,
}

export function isWithinBsdReferenceBbox(lat, lng) {
  const numLat = Number(lat)
  const numLng = Number(lng)
  if (!Number.isFinite(numLat) || !Number.isFinite(numLng)) return false
  return (
    numLat >= BSD_STUDY_AREA_BBOX.minLat &&
    numLat <= BSD_STUDY_AREA_BBOX.maxLat &&
    numLng >= BSD_STUDY_AREA_BBOX.minLng &&
    numLng <= BSD_STUDY_AREA_BBOX.maxLng
  )
}

function buildQueryString(params = {}) {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value).trim() !== '') {
      search.set(key, String(value).trim())
    }
  })
  const str = search.toString()
  return str ? `?${str}` : ''
}

export async function loginUser({ email, password }) {
  const res = await apiRequest('/api/login', {
    method: 'POST',
    body: { email, password, device_name: 'roadpriority-webgis' },
  })
  const token = res?.data?.token || ''
  const user = res?.data?.user || null
  setStoredSession(token, user)
  return res.data
}

export async function logoutUser() {
  try {
    if (getStoredToken()) {
      await apiRequest('/api/logout', { method: 'POST' })
    }
  } finally {
    clearStoredSession()
  }
}

export async function fetchCurrentUser({ signal } = {}) {
  const res = await apiRequest('/api/me', { method: 'GET', signal })
  if (res?.data) {
    setStoredSession(getStoredToken(), res.data)
  }
  return res?.data || null
}

export async function fetchReports(params = {}, { signal } = {}) {
  const query = buildQueryString(params)
  return apiRequest(`/api/reports${query}`, { method: 'GET', signal })
}

export async function fetchReportDetail(idOrCode, { signal } = {}) {
  return apiRequest(`/api/reports/${encodeURIComponent(idOrCode)}`, {
    method: 'GET',
    signal,
  })
}

export async function createDamageReport(formData, { signal } = {}) {
  return apiRequest('/api/reports', {
    method: 'POST',
    body: formData,
    signal,
    timeoutMs: 25000,
  })
}

export async function updateDamageReportStatus(reportId, payload) {
  return apiRequest(`/api/reports/${encodeURIComponent(reportId)}/status`, {
    method: 'PATCH',
    body: payload,
  })
}

export async function fetchRoadSegments(params = {}, { signal } = {}) {
  const query = buildQueryString(params)
  return apiRequest(`/api/road-segments${query}`, { method: 'GET', signal })
}

/**
 * Mengambil blob foto laporan menggunakan header Authorization jika pengguna login,
 * agar foto privat milik pelapor atau antrean petugas dapat ditampilkan tanpa membuka URL publik bebas.
 */
export async function fetchAuthorizedPhotoObjectUrl(photoUrl, { signal } = {}) {
  const token = getStoredToken()
  const headers = {}
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }
  const response = await fetch(photoUrl, { headers, signal })
  if (!response.ok) {
    throw new Error(`Gagal memuat foto (HTTP ${response.status}).`)
  }
  const blob = await response.blob()
  return URL.createObjectURL(blob)
}
