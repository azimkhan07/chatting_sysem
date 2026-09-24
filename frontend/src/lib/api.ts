import type {
  ChatMessagesPage,
  ChatUnreadTotal,
  Conversation,
  ConversationMessage,
  MessageReactionName,
  MessageReactionResult,
} from '@/types/chat'
import type { Comment, CommentsPage, FeedPage, Post } from '@/types/post'
import type { NotificationsPage, UnreadCountResult } from '@/types/notification'
import type { SearchSong, Song } from '@/types/song'
import type { GifResult, Story, StoryGroup, TextStyle } from '@/types/story'
import type {
  Thread,
  ThreadDetail,
  ThreadEntry,
  ThreadReactionName,
  ThreadReactionResult,
} from '@/types/thread'
import type { FollowResult, PublicUser, User, UserPage } from '@/types/user'

const API_BASE = '/api/v1'
const AUTH_STORAGE_KEY = 'amtechat.auth'
const ADMIN_STORAGE_KEY = 'amtechat.admin'

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

function readFromStorage(key: string): string | null {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    const parsed = JSON.parse(raw) as { state?: { token?: string | null } }
    return parsed.state?.token ?? null
  } catch {
    return null
  }
}

const readToken = () => readFromStorage(AUTH_STORAGE_KEY)
const readAdminToken = () => readFromStorage(ADMIN_STORAGE_KEY)

type TokenReader = () => string | null

function makeApi(readTokenFn: TokenReader) {
  async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const token = readTokenFn()
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

  return {
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
}

export const api = makeApi(readToken)
export const adminApi = makeApi(readAdminToken)

export interface LikeResult {
  liked: boolean
  likes_count: number
}

export interface ShareResult {
  shared: boolean
  shares_count: number
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
  trending: () => api.get<FeedPage>('/posts/trending?limit=30'),
  create: (form: { body: string; media: File[] }) => {
    const data = new FormData()
    data.append('body', form.body)
    for (const file of form.media) data.append('media[]', file)
    return api.postForm<{ post: Post }>('/posts', data)
  },
  like: (postId: number) => api.post<LikeResult>(`/posts/${postId}/like`, {}),
  unlike: (postId: number) => api.delete<LikeResult>(`/posts/${postId}/like`),
  share: (postId: number) => api.post<ShareResult>(`/posts/${postId}/share`, {}),
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

export const profileApi = {
  update: (data: { display_name?: string; bio?: string | null }) =>
    api.patch<{ user: User }>('/me', data),
  uploadAvatar: (file: File) => {
    const data = new FormData()
    data.append('image', file)
    return api.postForm<{ user: User }>('/me/avatar', data)
  },
  uploadCover: (file: File) => {
    const data = new FormData()
    data.append('image', file)
    return api.postForm<{ user: User }>('/me/cover', data)
  },
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

export interface SubscriptionTier {
  key: string
  name: string
  price_paisa: number
  price_month: string
  perks: string[]
}

export interface SubscriptionData {
  id: number
  user_id: number
  plan: string
  plan_name: string
  amount_paisa: number
  status: string
  status_code: string
  auto_renew: boolean
  is_verified: boolean
  expires_at: string | null
  switch_from: {
    subscription_id: number
    plan: string
    plan_name: string
  } | null
}

export const subscriptionsApi = {
  tiers: () => api.get<{ tiers: SubscriptionTier[] }>('/subscriptions/tiers'),
  verify: (plan: string) =>
    api.post<{ subscription: SubscriptionData }>('/subscriptions/verify', { plan }),
  checkout: (plan: string) =>
    api.post<{
      subscription: SubscriptionData
      gateway: string
      client_token: string
      amount_paisa: number
    }>('/subscriptions/checkout', { plan }),
  active: () => api.get<{ subscription: SubscriptionData | null }>('/subscriptions/active'),
  switch: (plan: string) =>
    api.post<{ subscription: SubscriptionData }>('/subscriptions/switch', { plan }),
  pay: (subscriptionId: number, gateway: string, token: string) =>
    api.post<{ subscription: SubscriptionData }>(
      `/subscriptions/${subscriptionId}/pay`,
      { gateway, token },
    ),
  show: (subscriptionId: number) =>
    api.get<{ subscription: SubscriptionData }>(`/subscriptions/${subscriptionId}`),
  cancel: (subscriptionId: number) =>
    api.delete<{ subscription: SubscriptionData }>(`/subscriptions/${subscriptionId}`),
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
  react: (conversationId: number, messageId: number, reaction: MessageReactionName) =>
    api.post<MessageReactionResult>(
      `/chat/conversations/${conversationId}/messages/${messageId}/reactions`,
      { reaction },
    ),
  unreact: (conversationId: number, messageId: number) =>
    api.delete<MessageReactionResult>(
      `/chat/conversations/${conversationId}/messages/${messageId}/reactions`,
    ),
  deleteMessage: (conversationId: number, messageId: number) =>
    api.delete<null>(`/chat/conversations/${conversationId}/messages/${messageId}`),
  unreadTotal: () => api.get<ChatUnreadTotal>('/chat/unread-total'),
}

export const threadsApi = {
  show: (conversationId: number) =>
    api.get<ThreadDetail>(`/chat/groups/${conversationId}/thread`),
  start: (conversationId: number) =>
    api.post<{ thread: Thread }>(`/chat/groups/${conversationId}/thread`, {}),
  addEntry: (conversationId: number, form: { body: string; media: File | null }) => {
    const data = new FormData()
    if (form.body.trim()) data.append('body', form.body)
    if (form.media) data.append('media', form.media)
    return api.postForm<{ thread: Thread; entry: ThreadEntry }>(
      `/chat/groups/${conversationId}/thread/entries`,
      data,
    )
  },
  toggleReaction: (conversationId: number, entryId: number, reaction: ThreadReactionName) =>
    api.post<ThreadReactionResult>(
      `/chat/groups/${conversationId}/thread/entries/${entryId}/reactions`,
      { reaction },
    ),
}

export interface GroupInviteInfo {
  conversation_id: number
  code: string
  expires_at: string | null
  created_by: { id: number; display_name: string; username: string }
}

export const invitesApi = {
  current: (conversationId: number) =>
    api.get<{ invite: GroupInviteInfo | null }>(`/chat/groups/${conversationId}/invite`),
  create: (conversationId: number) =>
    api.post<{ invite: GroupInviteInfo }>(`/chat/groups/${conversationId}/invite`, {}),
  revoke: (conversationId: number) =>
    api.delete<null>(`/chat/groups/${conversationId}/invite`),
  join: (code: string) =>
    api.post<{ conversation: Conversation }>(
      `/chat/invites/${encodeURIComponent(code)}/join`,
      {},
    ),
}

export interface SubscriptionReviewUser {
  id: number
  username: string
  display_name: string
  bio: string | null
  avatar_url: string | null
  is_verified: boolean
  created_at: string | null
}

export interface SubscriptionReview {
  id: number
  status: string
  status_code: string
  plan: string
  plan_name: string
  amount_paisa: number
  price_month: string
  auto_renew: boolean
  paid: boolean
  is_verified: boolean
  verified_at: string | null
  created_at: string | null
  switch_from: {
    subscription_id: number
    plan: string
    plan_name: string
  } | null
  user: SubscriptionReviewUser | null
}

export interface SubscriptionReviewsPage {
  subscriptions: SubscriptionReview[]
  meta: { total: number; page: number; per_page: number }
}

export interface AdminStats {
  counts: Record<string, number>
  revenue_paisa: number
}

export const subscriptionsAdminApi = {
  list: (status?: string, page = 1) => {
    const query = new URLSearchParams({ page: String(page), limit: '20' })
    if (status) query.set('status', status)
    return adminApi.get<SubscriptionReviewsPage>(`/admin/subscriptions?${query}`)
  },
  stats: () => adminApi.get<{ stats: AdminStats }>('/admin/subscriptions/stats'),
  approve: (id: number) =>
    adminApi.post<{ subscription: SubscriptionReview }>(
      `/admin/subscriptions/${id}/approve`,
      {},
    ),
  reject: (id: number) =>
    adminApi.post<{ subscription: SubscriptionReview }>(
      `/admin/subscriptions/${id}/reject`,
      {},
    ),
}