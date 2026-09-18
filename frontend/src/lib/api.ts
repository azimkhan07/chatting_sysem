const API_BASE = '/api/v1'
const AUTH_STORAGE_KEY = 'amtechat.auth'

interface ApiEnvelope<T> {
  data: T | null
  meta: { request_id: string }
  errors: { code: string; message: string; field?: string }[]
}

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly field?: string

  constructor(status: number, code: string, message: string, field?: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.field = field
  }
}

function readToken(): string | null {
  try {
    const raw = localStorage.getItem(AUTH_STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { state?: { token?: string | null } }
    return parsed.state?.token ?? null
  } catch {
    return null
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = readToken()
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('X-Request-Id', crypto.randomUUID())
  if (init.body && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const response = await fetch(`${API_BASE}${path}`, { ...init, headers })
  const body = (await response.json().catch(() => null)) as ApiEnvelope<T> | null

  if (!response.ok) {
    const error = body?.errors?.[0]
    throw new ApiError(
      response.status,
      error?.code ?? 'UNEXPECTED_ERROR',
      error?.message ?? 'Something went wrong. Please try again.',
      error?.field,
    )
  }

  if (body === null) {
    throw new ApiError(
      response.status,
      'INVALID_RESPONSE',
      'The server returned an invalid response.',
    )
  }

  return body.data as T
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, data: unknown) =>
    request<T>(path, {
      method: 'POST',
      body: JSON.stringify(data),
    }),
}