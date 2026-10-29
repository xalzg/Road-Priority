const BASE_URL = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')

/**
 * Pembungkus fetch tipis untuk API RoadPriority.
 * Melempar Error dengan pesan yang dapat ditampilkan ke pengguna.
 */
export async function apiGet(path, { signal, timeoutMs = 8000 } = {}) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), timeoutMs)
  const onAbort = () => controller.abort()
  signal?.addEventListener('abort', onAbort)

  try {
    const response = await fetch(`${BASE_URL}${path}`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })

    if (!response.ok) {
      throw new Error(`Server menjawab dengan status HTTP ${response.status}.`)
    }

    try {
      return await response.json()
    } catch {
      throw new Error('Respons server bukan JSON yang valid.')
    }
  } catch (error) {
    if (error.name === 'AbortError') {
      if (signal?.aborted) throw error
      throw new Error('Permintaan melebihi batas waktu.')
    }
    if (error instanceof TypeError) {
      throw new Error('Tidak dapat menghubungi server API.')
    }
    throw error
  } finally {
    clearTimeout(timer)
    signal?.removeEventListener('abort', onAbort)
  }
}
