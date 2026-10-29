import { useEffect, useState } from 'react'
import { getHealth } from '../services/healthService'

const LABELS = {
  loading: 'Memeriksa koneksi…',
  success: 'API Terhubung',
  error: 'API Terputus',
}

export default function ApiStatus() {
  const [state, setState] = useState({ status: 'loading', message: '' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    getHealth({ signal: controller.signal })
      .then((data) => {
        if (data?.success !== true) {
          throw new Error('Respons API tidak sesuai kontrak.')
        }
        setState({ status: 'success', message: data.message })
      })
      .catch((error) => {
        if (error.name === 'AbortError') return
        setState({ status: 'error', message: error.message })
      })
    return () => controller.abort()
  }, [attempt])

  const retry = () => {
    setState({ status: 'loading', message: '' })
    setAttempt((n) => n + 1)
  }

  const { status, message } = state

  return (
    <div className="api-status-inline" aria-live="polite" title={message || LABELS[status]}>
      <span className={`status-badge status-${status}`}>
        <span className="status-icon" aria-hidden="true">
          {status === 'success' ? '✓' : status === 'error' ? '✕' : '…'}
        </span>
        {LABELS[status]}
      </span>
      {status === 'error' && (
        <button
          type="button"
          className="btn-secondary btn-sm"
          onClick={retry}
        >
          Coba lagi
        </button>
      )}
    </div>
  )
}
