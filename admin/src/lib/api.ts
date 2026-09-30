export interface AuthUser {
  id: number
  username: string
  display_name: string | null
  email?: string | null
  name?: string | null
}

export interface AuthPayload {
  access_token: string
  token_type: string
  expires_in: number
  user: AuthUser
}

interface ApiEnvelope<T> {
  data: T | null
  meta: { request_id: string }
  errors: { code: string; message: string; field?: string }[]
}

export class ApiError extends Error {
  code: string
  field?: string

  constructor(code: string, message: string, field?: string) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.field = field
  }
}

const API_BASE = '/api/v1'

function readAdminToken(): string | null {
  return localStorage.getItem('amtechat.admin')
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = readAdminToken()
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('X-Request-Id', crypto.randomUUID())
  if (init.body && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (token) headers.set('Authorization', `Bearer ${token}`)

  const res = await fetch(`${API_BASE}${path}`, { ...init, headers })

  let body: ApiEnvelope<T> | null = null
  try {
    body = (await res.json()) as ApiEnvelope<T>
  } catch {
    /* non-JSON response */
  }

  if (!res.ok) {
    const err = body?.errors?.[0]
    throw new ApiError(
      err?.code ?? 'REQUEST_FAILED',
      err?.message ?? `Request failed (${res.status})`,
      err?.field,
    )
  }

  if (body?.data === undefined) {
    throw new ApiError('INVALID_RESPONSE', 'Malformed API payload')
  }

  return body.data as T
}

export const adminApi = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, data?: unknown) =>
    request<T>(path, {
      method: 'POST',
      body: data === undefined ? undefined : JSON.stringify(data),
    }),
  patch: <T>(path: string, data: unknown) =>
    request<T>(path, { method: 'PATCH', body: JSON.stringify(data) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export const authApi = {
  login: (identifier: string, password: string) =>
    adminApi.post<AuthPayload>('/admin/auth/login', { identifier, password }),
  logout: () => adminApi.post<{ logged_out: boolean }>('/admin/auth/logout'),
}