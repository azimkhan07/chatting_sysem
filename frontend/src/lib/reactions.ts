export const REACTIONS = ['like', 'love', 'haha', 'wow', 'sad', 'angry'] as const

export type ReactionName = (typeof REACTIONS)[number]

export const REACTION_EMOJI: Record<ReactionName, string> = {
  like: '👍',
  love: '❤️',
  haha: '😂',
  wow: '😮',
  sad: '😢',
  angry: '😡',
}

export function emptyReactions(): Record<ReactionName, number> {
  return { like: 0, love: 0, haha: 0, wow: 0, sad: 0, angry: 0 }
}