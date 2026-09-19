import type {
  ChatMessagesPage,
  ChatUnreadTotal,
  Conversation,
  ConversationMessage,
} from '@/types/chat'
import type { Comment, CommentsPage, FeedPage, Post } from '@/types/post'
import type { NotificationsPage, UnreadCountResult } from '@/types/notification'
import type { SearchSong, Song } from '@/types/song'
import type { GifResult, Story, StoryGroup, TextStyle } from '@/types/story'
import type { FollowResult, PublicUser, User, UserPage } from '@/types/user'

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
  patch: <T>(path: string, data: unknown) =>
    request<T>(path, {
      method: 'PATCH',
      body: JSON.stringify(data),
    }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

export interface LikeResult {
  liked: boolean
  likes_count: number
}

export interface HashtagSummary {
  name: string
  posts_count: number
}

export interface HashtagPageData {
  hashtag: HashtagSummary
  posts: Post[]
  next_cursor: string | null
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
  reels: (cursor?: string) =>
    api.get<FeedPage>(
      `/posts/reels?limit=10${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  explore: (cursor?: string) =>
    api.get<FeedPage>(
      `/posts/explore?limit=24${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
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

export const hashtagsApi = {
  page: (tag: string, cursor?: string) =>
    api.get<HashtagPageData>(
      `/hashtags/${encodeURIComponent(tag)}?limit=15${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  search: (query: string) =>
    api.get<{ hashtags: HashtagSummary[] }>(
      `/hashtags/search?query=${encodeURIComponent(query)}`,
    ),
}

export const songsApi = {
  list: () => api.get<{ songs: Song[] }>('/songs'),
  search: (query: string) =>
    api.get<{ songs: SearchSong[] }>(
      `/songs/search?q=${encodeURIComponent(query)}`,
    ),
  import: (song: { name: string; artist: string; url: string; genre?: string | null }) =>
    api.post<{ song: Song }>('/songs/import', song),
  gifs: (query: string) =>
    api.get<{ gifs: GifResult[] }>(`/songs/gifs?q=${encodeURIComponent(query)}`),
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
  search: (query: string) =>
    api.get<{ users: User[] }>(
      `/users/search?query=${encodeURIComponent(query)}`,
    ),
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
  create: (form: {
    media: File
    caption: string
    effects: string
    songId: number | null
    textStyle?: TextStyle
  }) => {
    const data = new FormData()
    data.append('media', form.media)
    data.append('caption', form.caption)
    data.append('effects', form.effects)
    if (form.songId !== null) data.append('song_id', String(form.songId))
    if (form.textStyle) data.append('text_style', JSON.stringify(form.textStyle))
    return api.postForm<{ story: Story }>('/stories', data)
  },
  createFromUrl: (form: {
    url: string
    caption: string
    effects: string
    songId: number | null
    textStyle?: TextStyle
  }) =>
    api.post<{ story: Story }>('/stories', {
      media_url: form.url,
      caption: form.caption,
      effects: form.effects,
      song_id: form.songId,
      text_style: form.textStyle,
    }),
  destroy: (storyId: number) => api.delete<null>(`/stories/${storyId}`),
}

export const chatApi = {
  conversations: () => api.get<{ conversations: Conversation[] }>('/chat/conversations'),
  show: (conversationId: number) =>
    api.get<{ conversation: Conversation }>(`/chat/conversations/${conversationId}`),
  startDm: (userId: number) =>
    api.post<{ conversation: Conversation }>('/chat/conversations', {
      type: 'dm',
      user_id: userId,
    }),
  createGroup: (name: string, memberIds: number[]) =>
    api.post<{ conversation: Conversation }>('/chat/conversations', {
      type: 'group',
      name,
      member_ids: memberIds,
    }),
  setMuted: (conversationId: number, muted: boolean) =>
    api.patch<{ conversation: Conversation }>(`/chat/conversations/${conversationId}`, {
      muted,
    }),
  messages: (conversationId: number, cursor?: string) =>
    api.get<ChatMessagesPage>(
      `/chat/conversations/${conversationId}/messages?limit=30${cursor ? `&cursor=${encodeURIComponent(cursor)}` : ''}`,
    ),
  send: (conversationId: number, body: string, clientId: string) =>
    api.post<{ message: ConversationMessage }>(
      `/chat/conversations/${conversationId}/messages`,
      { type: 'text', body, client_id: clientId },
    ),
  markRead: (conversationId: number, upToMessageId: number) =>
    api.post<{ read_up_to: number; unread: number }>(
      `/chat/conversations/${conversationId}/read`,
      { up_to_message_id: upToMessageId },
    ),
  typing: (conversationId: number) =>
    api.post<null>(`/chat/conversations/${conversationId}/typing`, {}),
  addMember: (conversationId: number, userId: number) =>
    api.post<{ conversation: Conversation }>(`/chat/conversations/${conversationId}/members`, {
      user_id: userId,
    }),
  removeMember: (conversationId: number, userId: number) =>
    api.delete<null>(`/chat/conversations/${conversationId}/members/${userId}`),
  unreadTotal: () => api.get<ChatUnreadTotal>('/chat/unread-total'),
}