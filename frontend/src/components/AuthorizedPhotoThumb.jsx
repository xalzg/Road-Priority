import { useEffect, useState } from 'react'
import { fetchAuthorizedPhotoObjectUrl } from '../services/reportingService'

/**
 * Komponen reusable untuk menampilkan thumbnail foto laporan terproteksi.
 * Mengunduh binary Blob dengan token Sanctum, membuat object URL sementara,
 * dan mencabut object URL saat unmount untuk mencegah kebocoran memori (PRD 06.2).
 */
export default function AuthorizedPhotoThumb({ photo, alt = 'Foto kerusakan jalan' }) {
  const [src, setSrc] = useState('')
  const [err, setErr] = useState('')

  useEffect(() => {
    let active = true
    let objectUrl = ''
    const controller = new AbortController()

    fetchAuthorizedPhotoObjectUrl(photo.url, { signal: controller.signal })
      .then((url) => {
        if (!active) {
          URL.revokeObjectURL(url)
          return
        }
        objectUrl = url
        setSrc(url)
      })
      .catch((e) => {
        if (e.name === 'AbortError' || !active) return
        setErr(e.message || 'Foto tidak dapat dimuat.')
      })

    return () => {
      active = false
      controller.abort()
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [photo.url])

  if (err) {
    return (
      <div className="photo-error-box" role="alert">
        <span>Gagal memuat foto</span>
      </div>
    )
  }

  if (!src) {
    return (
      <div className="photo-loading-box" aria-live="polite">
        <span>Memuat foto…</span>
      </div>
    )
  }

  return (
    <a href={src} target="_blank" rel="noreferrer" title="Klik untuk membuka ukuran penuh">
      <img src={src} alt={alt} className="photo-thumb-img" loading="lazy" />
    </a>
  )
}

