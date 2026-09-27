import type { ReactionName } from '@/lib/reactions'

export type ConversationType = 'dm' | 'group'
export type ConversationState = 'active' | 'requested'
export type MemberRole = 'owner' | 'admin' | 'member'
export type MessageKind = 'text' | 'image' | 'video' | 'gif' | 'drawing'

/** Subscription-gated chat capabilities, mirroring the backend catalogue. */
export type ChatFeatureKey =
  | 'message_requests'
  | 'chat_nickname'
  | 'chat_wallpaper'
  | 'chat_gif'
  | 'chat_drawing'
  | 'chat_pinned_messages'

export interface ChatFeature {
  key: ChatFeatureKey
  label: string
  blurb: string
  unlocked: boolean
}

/**
 * Wallpaper keys are validated server side (so the client cannot invent one),
 * but the gradient behind a key is a client concern: it is a Tailwind class
 * name, not data. Unknown keys — future gallery uploads — simply fall back to
 * the default background instead of crashing the thread.
 */
export type ChatWallpaperOption = string

export interface ChatEntitlements {
  features: ChatFeature[]
  unlocked: ChatFeatureKey[]
  wallpapers: ChatWallpaperOption[]
}

export type MessageReactionName = ReactionName

export interface ChatMember {
  user: {
    id: number
    username: string
    display_name: string
    avatar_url: string | null
    is_online: boolean
  }
  role: MemberRole
}

export interface ChatMessageSender {
  id: number
  username: string
  display_name: string
  avatar_url: string | null
  is_verified: boolean
}

export interface ReadReceipt {
  id: number
  username: string
  display_name: string
  avatar_url: string | null
}

export interface ConversationMessage {
  id: number
  conversation_id: number
  sender: ChatMessageSender | null
  type: MessageKind
  body: string | null
  media_url: string | null
  read: boolean
  read_by: ReadReceipt[]
  reactions: Record<ReactionName, number>
  my_reaction: ReactionName | null
  created_at: string
  /** Shared state, so every member sees the same pin - not per-member. */
  pinned_at: string | null
  pinned_by: number | null
  /** Server-side permission: own message, or owner/admin in a group. */
  can_pin: boolean
  /** Present on locally-created, not-yet-persisted messages. */
  client_id?: string
}

export interface PeerPresence {
  user_id: number
  is_online: boolean
  last_seen_at: string | null
}

export interface PresenceHeartbeat {
  is_online: boolean
  last_seen_at: string | null
}

export interface Conversation {
  id: number
  type: ConversationType
  /** `requested` = a pending message request, not a live chat yet. */
  state: ConversationState
  /** Only ever true for the recipient, so the sender's copy shows no buttons. */
  is_request_actionable: boolean
  display_name: string
  avatar_url: string | null
  peer_verified: boolean | null
  members_count: number
  members: ChatMember[]
  /** Online members other than the viewer. */
  online_count: number
  /** DM only: the other person's live presence. */
  peer_presence: PeerPresence | null
  last_message: ConversationMessage | null
  unread_count: number
  muted: boolean
  /** Viewer's private nickname for this chat; nobody else can see it. */
  my_nickname: string | null
  /** Viewer's private wallpaper for this chat. */
  my_wallpaper_key: string | null
  updated_at: string
}

export interface ChatMessagesPage {
  messages: ConversationMessage[]
  next_cursor: string | null
}

export interface ChatUnreadTotal {
  unread: number
}

export interface MessageReactionResult {
  message_id: number
  reaction: ReactionName | null
  reactions: Record<ReactionName, number>
}

export interface RealtimeReactionPayload {
  conversation_id: number
  message_id: number
  user_id: number
  reaction: ReactionName | null
  totals: Record<ReactionName, number>
}

export interface RealtimeDeletedPayload {
  conversation_id: number
  message_id: number
  deleted_by: number
}

export interface RealtimeMessagePayload {
  conversation_id: number
  message: ConversationMessage
}

export interface RealtimeTypingPayload {
  conversation_id: number
  user_id: number
}

export interface RealtimePresencePayload {
  conversation_id: number
  user_id: number
  is_online: boolean
  last_seen_at: string | null
}

export interface RealtimeMemberJoinedPayload {
  conversation_id: number
  user: { id: number; username: string; display_name: string }
}