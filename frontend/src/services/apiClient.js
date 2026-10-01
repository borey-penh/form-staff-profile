/**
 * Fetch wrapper for the Laravel JSON API with Sanctum bearer auth.
 * Errors are normalized to { message, errors } shape.
 */
const BASE = '/api'
const TOKEN_KEY = 'portal_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}
export function setToken(token) {
  token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY)
}

async function request(method, path, { body, params, formData } = {}) {
  const url = new URL(BASE + path, window.location.origin)
  Object.entries(params ?? {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null) url.searchParams.set(k, v)
  })

  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  if (formData) {
    // Let the browser set multipart boundary
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let res
  try {
    res = await fetch(url, {
      method,
      headers,
      body: formData ?? (body !== undefined ? JSON.stringify(body) : undefined),
    })
  } catch (networkErr) {
    // fetch() only throws here when the request never got a response
    // (connection refused, server down, DNS failure…)
    const err = new Error(
      'Cannot reach the API server. Make sure Laravel is running: cd backend && php artisan serve'
    )
    err.status = 0
    err.errors = {}
    throw err
  }

  if (res.status === 401) {
    setToken(null)
    window.location.href = '/login'
    throw new Error('Session expired. Please log in again.')
  }

  const payload = await res.json().catch(() => null)

  if (!res.ok) {
    const err = new Error(payload?.message || `Request failed (${res.status})`)
    err.status = res.status
    err.errors = payload?.errors ?? {}
    throw err
  }
  return payload
}

export default {
  get: (path, opts) => request('GET', path, opts),
  post: (path, body, opts) => request('POST', path, { ...opts, body }),
  put: (path, body, opts) => request('PUT', path, { ...opts, body }),
  postForm: (path, formData) => request('POST', path, { formData }),
  del: (path) => request('DELETE', path),
}
