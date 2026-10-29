import { apiGet } from './api'

/** GET /api/health : memeriksa ketersediaan backend. */
export function getHealth(options) {
  return apiGet('/api/health', options)
}
