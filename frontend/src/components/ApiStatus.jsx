import { useEffect, useState } from 'react'
import { getHealth } from '../services/healthService'

const LABELS = {
  loading: 'Memeriksa koneksiÃ¢â‚¬Â¦',
  success: 'Terhubung',
  error: 'Tidak terhubung',
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
    <section className="status-card" aria-live="polite">
      <h2>Status backend</h2>
      <p className={`status-badge status-${status}`}>
        <span className="status-icon" aria-hidden="true">
          {status === 'success' ? 'Ã¢Å“â€œ' : status === 'error' ? 'Ã¢Å“â€¢' : 'Ã¢â‚¬Â¦'}
        </span>
        {LABELS[status]}
      </p>
      {message && <p className="status-message">{message}</p>}
      {status === 'error' && (
        <p className="status-hint">
          Pastikan backend Laravel berjalan di http://127.0.0.1:8000 dan MySQL
          XAMPP aktif.
        </p>
      )}
      <button
        type="button"
        onClick={retry}
        disabled={status === 'loading'}
      >
        Coba lagi
      </button>
    </section>
  )
}
