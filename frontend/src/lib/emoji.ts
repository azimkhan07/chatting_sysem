/**
 * Emoji offered by the chat composer, grouped so the picker is scannable
 * without a search box. Kept deliberately small: these are quick-reply
 * emoji, not a full Unicode picker.
 */
export const EMOJI_GROUPS: { label: string; emoji: string[] }[] = [
  {
    label: 'Smileys',
    emoji: [
      '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂',
      '🙂', '😉', '😊', '😍', '🥰', '😘', '😗', '😋',
      '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥳', '😏',
    ],
  },
  {
    label: 'Feelings',
    emoji: [
      '😔', '😢', '😭', '😤', '😠', '😡', '🥺', '😱',
      '😳', '🥺', '😴', '🤒', '🤯', '🤠', '🥶', '🤡',
    ],
  },
  {
    label: 'Gestures',
    emoji: [
      '👍', '👎', '👏', '🙌', '🤝', '🙏', '💪', '✌️',
      '🤞', '👌', '🤌', '👋', '🫶', '👀', '🫡', '🕺',
    ],
  },
  {
    label: 'Hearts & symbols',
    emoji: [
      '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
      '💔', '💯', '✨', '🔥', '🎉', '🎊', '⭐', '💫',
    ],
  },
]

/** Appends an emoji to a caret position instead of jumping to the end. */
export function appendEmoji(value: string, emoji: string, caret: number): {
  text: string
  caret: number
} {
  const position = Math.max(0, Math.min(caret, value.length))
  const text = `${value.slice(0, position)}${emoji}${value.slice(position)}`
  return { text, caret: position + emoji.length }
}
