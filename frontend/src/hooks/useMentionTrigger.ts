import { useRef, useState } from 'react'

/**
 * The `@name` being typed at the caret, or null when there is not one.
 *
 * The composer's tag picker is driven entirely by this. A user is halfway
 * through typing `@sa` and the question the UI has to answer is not "what is
 * in the caption" but "what are they typing right now, in front of the
 * caret". Scanning the whole caption for any `@word` gets that wrong the
 * moment there are two mentions, or one earlier one they already finished.
 *
 * So the text is sliced: caret position back to the nearest whitespace (or a
 * start-of-string that begins an `@`), and that fragment is the trigger. A
 * word containing characters outside the username set also ends the match,
 * because `@name.` at the end of a sentence is prose, not a half-typed mention.
 */
const MENTION_CHARS = /[A-Za-z0-9_.]/

export interface ActiveMention {
  /** The text after the `@`, e.g. `sa`. Empty for a bare `@`. */
  term: string
  /** Where the fragment starts, so the caller can splice it back out. */
  start: number
  /** Caret position, i.e. where the fragment ends. */
  end: number
}

export function findActiveMention(text: string, caret: number): ActiveMention | null {
  const upToCaret = text.slice(0, caret)

  // Walk back over the fragment, stopping at whitespace. Bounded by the
  // username length the server accepts so a 4000-character "word" does not
  // become one long scan on every keystroke.
  let start = caret
  const floor = Math.max(0, caret - 32)

  while (start > floor && MENTION_CHARS.test(upToCaret[start - 1])) {
    start -= 1
  }

  if (start === caret) {
    // Nothing typed after the `@` yet, but there may still be a lone `@`.
    if (caret > 0 && upToCaret[caret - 1] === '@') {
      const before = caret >= 2 ? upToCaret[caret - 2] : ''
      // Only a bare `@` that starts a word, not the `me@` in an email.
      if (before === '' || /\s/.test(before)) {
        return { term: '', start: caret - 1, end: caret }
      }
    }
    return null
  }

  if (upToCaret[start - 1] !== '@') {
    return null
  }

  // `@` must begin the word too, so `a@b` is never a mention.
  const before = start >= 2 ? upToCaret[start - 2] : ''
  if (before !== '' && !/\s/.test(before)) {
    return null
  }

  return { term: upToCaret.slice(start, caret), start: start - 1, end: caret }
}

/**
 * Caret position of a textarea, falling back to the end of the text.
 *
 * `selectionStart` is the caret for a collapsed selection, which is the only
 * case that matters: a highlight is not a caret and has no meaningful
 * "half-typed mention".
 */
export function caretOf(element: HTMLTextAreaElement | null): number {
  if (!element) return 0
  return element.selectionStart ?? element.value.length
}

/**
 * Re-inserts the caret where a splice happened.
 *
 * Without this, picking a suggestion would leave the caret at the end of the
 * caption, and the next character typed would land after the mention instead
 * of continuing the sentence around it.
 */
function placeCaret(element: HTMLTextAreaElement | null, position: number) {
  if (!element) return
  element.focus()
  element.setSelectionRange(position, position)
}

/**
 * Text and caret that survive an external edit.
 *
 * A textarea's value cannot be changed from outside without the browser
 * collapsing the caret to the end on the next render, so any programmatic
 * change has to put the selection back by hand. The caret is therefore tracked
 * as state alongside the text, and re-applied on the following frame.
 */
export function useTrackedTextarea(initial = '') {
  const [text, setText] = useState(initial)
  const [caret, setCaret] = useState(0)
  const element = useRef<HTMLTextAreaElement | null>(null)

  return {
    text,
    caret,
    ref: element,
    setText,

    /**
     * Replaces the text and leaves the caret at `position`.
     *
     * The DOM selection is set on the next frame rather than immediately,
     * because React has not re-rendered the textarea yet at this point and the
     * old value is still mounted.
     */
    setTextWithCaret: (next: string, position: number) => {
      setText(next)
      setCaret(position)
      window.requestAnimationFrame(() => placeCaret(element.current, position))
    },

    onChange: (event: React.ChangeEvent<HTMLTextAreaElement>) => {
      setText(event.target.value)
      setCaret(caretOf(event.target))
    },
    onSelect: (event: React.SyntheticEvent<HTMLTextAreaElement>) => {
      setCaret(caretOf(event.currentTarget))
    },
    onKeyUp: (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
      setCaret(caretOf(event.currentTarget))
    },
    onClick: (event: React.MouseEvent<HTMLTextAreaElement>) => {
      setCaret(caretOf(event.currentTarget))
    },
  }
}
