import type { Comment, CommentsPage, FeedPage, Post } from '@/types/post'
import type { NotificationsPage, UnreadCountResult } from '@/types/notification'
import type { Story, StoryGroup } from '@/types/story'
import type { FollowResult, PublicUser, UserPage } from '@/types/user'

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
  postForm: <T>(path: string, data: FormData) =>
    request<T>(path, {
      method: 'POST',
      body: data,
    }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export interface LikeResult {
  liked: boolean
  likes_count: number
}

export const postsApi = {
  list: (cursor?: string) =>
    api.get<FeedPage>(
      `/posts?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  mine: (cursor?: string) =>
    api.get<FeedPage>(
      `/posts/me?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  create: (form: { body: string; media: File[] }) => {
    const data = new FormData()
    data.append('body', form.body)
    for (const file of form.media) data.append('media[]', file)
    return api.postForm<{ post: Post }>('/posts', data)
  },
  like: (postId: number) => api.post<LikeResult>(`/posts/${postId}/like`, {}),
  unlike: (postId: number) => api.delete<LikeResult>(`/posts/${postId}/like`),
}

export const commentsApi = {
  list: (postId: number, cursor?: string) =>
    api.get<CommentsPage>(
      `/posts/${postId}/comments?limit=20${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  create: (postId: number, body: string) =>
    api.post<{ comment: Comment }>(`/posts/${postId}/comments`, { body }),
}

export const passwordApi = {
  sendLink: (email: string) =>
    api.post<{ message: string }>('/password/email', { email }),
  reset: (payload: { email: string; token: string; password: string }) =>
    api.post<{ message: string }>('/password/reset', {
      email: payload.email,
      token: payload.token,
      password: payload.password,
      password_confirmation: payload.password,
    }),
}

export const usersApi = {
  get: (identifier: string | number) =>
    api.get<PublicUser>(`/users/${encodeURIComponent(String(identifier))}`),
  postsOf: (identifier: string | number, cursor?: string) =>
    api.get<FeedPage>(
      `/users/${encodeURIComponent(String(identifier))}/posts?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  followers: (identifier: string | number, cursor?: string) =>
    api.get<UserPage>(
      `/users/${encodeURIComponent(String(identifier))}/followers?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  following: (identifier: string | number, cursor?: string) =>
    api.get<UserPage>(
      `/users/${encodeURIComponent(String(identifier))}/following?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  follow: (identifier: string | number) =>
    api.post<FollowResult>(`/users/${encodeURIComponent(String(identifier))}/follow`, {}),
  unfollow: (identifier: string | number) =>
    api.delete<FollowResult>(`/users/${encodeURIComponent(String(identifier))}/follow`),
}

export const notificationsApi = {
  list: (cursor?: string) =>
    api.get<NotificationsPage>(
      `/notifications?limit=30${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  unreadCount: () => api.get<UnreadCountResult>('/notifications/unread-count'),
  markAllRead: () => api.post<{ updated: number }>('/notifications/read', {}),
}

export const storiesApi = {
  list: () => api.get<{ stories: StoryGroup[] }>('/stories'),
  create: (form: { media: File; caption: string }) => {
    const data = new FormData()
    data.append('media', form.media)
    data.append('caption', form.caption)
    return api.postForm<{ story: Story }>('/stories', data)
  },
  destroy: (storyId: number) => api.delete<null>(`/stories/${storyId}`),
}