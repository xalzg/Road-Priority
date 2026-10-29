import { apiRequest } from './api'

export const SAATY_SCALE_OPTIONS = [
  { value: 9, label: '9 — Mutlak lebih penting (A >>> B)' },
  { value: 8, label: '8 — Antara 7 dan 9 (A >> B)' },
  { value: 7, label: '7 — Sangat lebih penting (A >> B)' },
  { value: 6, label: '6 — Antara 5 dan 7 (A > B)' },
  { value: 5, label: '5 — Lebih penting secara kuat (A > B)' },
  { value: 4, label: '4 — Antara 3 dan 5 (A > B)' },
  { value: 3, label: '3 — Sedikit lebih penting (A > B)' },
  { value: 2, label: '2 — Antara 1 dan 3 (A > B)' },
  { value: 1, label: '1 — Sama penting (A = B)' },
  { value: 1 / 2, label: '1/2 (0.5000) — Antara 1 dan 1/3 (B > A)' },
  { value: 1 / 3, label: '1/3 (0.3333) — B sedikit lebih penting dari A' },
  { value: 1 / 4, label: '1/4 (0.2500) — Antara 1/3 dan 1/5 (B > A)' },
  { value: 1 / 5, label: '1/5 (0.2000) — B lebih penting secara kuat dari A' },
  { value: 1 / 6, label: '1/6 (0.1667) — Antara 1/5 dan 1/7 (B > A)' },
  { value: 1 / 7, label: '1/7 (0.1429) — B sangat lebih penting dari A' },
  { value: 1 / 8, label: '1/8 (0.1250) — Antara 1/7 dan 1/9 (B >> A)' },
  { value: 1 / 9, label: '1/9 (0.1111) — B mutlak lebih penting dari A' },
]

export async function fetchCriteria({ signal } = {}) {
  return apiRequest('/api/criteria', { method: 'GET', signal })
}

export async function updateCriterion(id, payload) {
  return apiRequest(`/api/criteria/${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: payload,
  })
}

export async function fetchWeightVersions({ signal } = {}) {
  return apiRequest('/api/criterion-weight-versions', { method: 'GET', signal })
}

export async function fetchWeightVersionDetail(id, { signal } = {}) {
  return apiRequest(`/api/criterion-weight-versions/${encodeURIComponent(id)}`, {
    method: 'GET',
    signal,
  })
}

export async function createWeightVersion(payload) {
  return apiRequest('/api/criterion-weight-versions', {
    method: 'POST',
    body: payload,
  })
}

export async function calculateWeightVersion(id, payload) {
  return apiRequest(`/api/criterion-weight-versions/${encodeURIComponent(id)}/calculate`, {
    method: 'POST',
    body: payload,
  })
}

export async function activateWeightVersion(id) {
  return apiRequest(`/api/criterion-weight-versions/${encodeURIComponent(id)}/activate`, {
    method: 'POST',
  })
}

export async function fetchDataReadiness({ includeDemo = false, signal } = {}) {
  const qs = includeDemo ? '?include_demo=1' : ''
  return apiRequest(`/api/decision-data-readiness${qs}`, { method: 'GET', signal })
}

export async function fetchDecisionRuns({ signal } = {}) {
  return apiRequest('/api/decision-runs', { method: 'GET', signal })
}

export async function executeDecisionRun(payload) {
  return apiRequest('/api/decision-runs', {
    method: 'POST',
    body: payload,
  })
}

export async function fetchDecisionRunDetail(id, { signal } = {}) {
  return apiRequest(`/api/decision-runs/${encodeURIComponent(id)}`, {
    method: 'GET',
    signal,
  })
}

export async function recordDamageAssessment(payload) {
  return apiRequest('/api/assessments', {
    method: 'POST',
    body: payload,
  })
}
