import { useNavigate } from 'react-router-dom'

import { hashtagPage, userProfile } from '@/lib/paths'

interface RichTextProps {
  text: string
  className?: string
  mentionClass?: string
  hashtagClass?: string
}

const TOKEN_SPLIT = /(@[A-Za-z0-9_.]+|#[A-Za-z0-9_]+)/g

export default function RichText({
  text,
  className = '',
  mentionClass = 'font-semibold text-sky-300 hover:underline',
  hashtagClass = 'font-semibold text-amber-300 hover:underline',
}: RichTextProps) {
  const navigate = useNavigate()
  const tokens = text.split(TOKEN_SPLIT)

  return (
    <span className={className}>
      {tokens.map((token, index) => {
        if (token.startsWith('@')) {
          return (
            <button
              key={index}
              type="button"
              onClick={() => navigate(userProfile(token.slice(1)))}
              className={mentionClass}
            >
              {token}
            </button>
          )
        }
        if (token.startsWith('#')) {
          return (
            <button
              key={index}
              type="button"
              onClick={() => navigate(hashtagPage(token.slice(1)))}
              className={hashtagClass}
            >
              {token}
            </button>
          )
        }
        return <span key={index}>{token}</span>
      })}
    </span>
  )
}