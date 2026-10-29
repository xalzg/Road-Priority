const BASE_URL = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')

const TOKEN_STORAGE_KEY = 'rp_auth_token'
const USER_STORAGE_KEY = 'rp_auth_user'

export function getStoredToken() {
  try {
    return sessionStorage.getItem(TOKEN_STORAGE_KEY) || ''
  } catch {
    return ''
  }
}

export function getStoredUser() {
  try {
    const raw = sessionStorage.getItem(USER_STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

export function setStoredSession(token, user) {
  try {
    if (token) {
      sessionStorage.setItem(TOKEN_STORAGE_KEY, token)
    } else {
      sessionStorage.removeItem(TOKEN_STORAGE_KEY)
    }

    if (user) {
      sessionStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))
    } else {
      sessionStorage.removeItem(USER_STORAGE_KEY)
    }
  } catch {
    // Abaikan jika sessionStorage diblokir browser
  }
}

export function clearStoredSession() {
  setStoredSession('', null)
}

/**
 * Pembungkus fetch untuk API RoadPriority dengan dukungan Bearer token Sanctum,
 * penanganan error validasi (422), dan pembatalan AbortController.
 */
export async function apiRequest(
  path,
  { method = 'GET', body = null, signal, timeoutMs = 12000, headers = {} } = {}
) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeoutMs)
  const onAbort = () => controller.abort()
  signal?.addEventListener('abort', onAbort)

  const token = getStoredToken()
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData

  const requestHeaders = {
    Accept: 'application/json',
    ...headers,
  }

  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`
  }

  if (body !== null && !isFormData && !requestHeaders['Content-Type']) {
    requestHeaders['Content-Type'] = 'application/json'
  }

  try {
    const response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers: requestHeaders,
      body: body === null ? undefined : isFormData ? body : JSON.stringify(body),
      signal: controller.signal,
    })

    let payload = null
    try {
      payload = await response.json()
    } catch {
      if (!response.ok) {
        const err = new Error(`Server menjawab dengan status HTTP ${response.status}.`)
        err.status = response.status
        throw err
      }
      throw new Error('Respons server bukan JSON yang valid.')
    }

    if (!response.ok) {
      if (response.status === 401) {
        clearStoredSession()
        if (typeof window !== 'undefined') {
          window.dispatchEvent(new CustomEvent('rp:auth_unauthorized'))
        }
      }
      const err = new Error(
        payload?.message || `Permintaan gagal dengan status HTTP ${response.status}.`
      )
      err.status = response.status
      err.errors = payload?.errors || null
      throw err
    }

    return payload
  } catch (error) {
    if (error.name === 'AbortError') {
      if (signal?.aborted) throw error
      throw new Error('Permintaan melebihi batas waktu.')
    }
    if (error instanceof TypeError) {
      throw new Error('Tidak dapat menghubungi server API. Periksa koneksi jaringan atau server backend.')
    }
    throw error
  } finally {
    clearTimeout(timer)
    signal?.removeEventListener('abort', onAbort)
  }
}

export async function apiGet(path, options = {}) {
  return apiRequest(path, { ...options, method: 'GET' })
}
