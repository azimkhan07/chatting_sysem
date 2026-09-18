import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useRef, useState } from 'react'

import { SparkleIcon } from '@/components/icons'

interface Message {
  id: number
  from: 'user' | 'bot'
  text: string
}

const INITIAL_MESSAGES: Message[] = [
  {
    id: 1,
    from: 'bot',
    text: 'Hey! I am the amteCHAT assistant. Ask me anything — or type “human” to reach a real person.',
  },
]

const BOT_REPLIES: { match: RegExp; reply: string }[] = [
  {
    match: /human|agent|person|live/i,
    reply:
      'A support ticket has been filed for you and a human will jump in shortly. Thanks for reaching out!',
  },
  {
    match: /blue|tick|premium|verify/i,
    reply:
      'The blue tick is our paid status — planned at around ₹5/month, launching in an upcoming phase. You will be able to manage it from Settings.',
  },
  {
    match: /password|login|sign in/i,
    reply:
      'You can sign in with your username, email or mobile, plus your password. Forgot it? Password reset arrives in the next phase.',
  },
  {
    match: /feed|post|create/i,
    reply:
      'Posts are coming in the next phase! Right now you can set up your profile and get familiar with the app.',
  },
]

export default function SupportChat() {
  const [open, setOpen] = useState(false)
  const [input, setInput] = useState('')
  const [messageId, setMessageId] = useState(INITIAL_MESSAGES.length)
  const [messages, setMessages] = useState<Message[]>(INITIAL_MESSAGES)
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const area = scrollRef.current
    if (area) area.scrollTop = area.scrollHeight
  }, [messages, open])

  function send(text: string) {
    const trimmed = text.trim()
    if (!trimmed) return

    const id = messageId + 1
    setMessageId(id)
    setMessages((prev) => [...prev, { id, from: 'user', text: trimmed }])
    setInput('')

    const reply =
      BOT_REPLIES.find(({ match }) => match.test(trimmed))?.reply ??
      'Got it — noted! For anything specific, try “blue tick”, “login” or “feed”. A human is also one message away.'
    setMessages((prev) => [...prev, { id: id + 1, from: 'bot', text: reply }])
  }

  return (
    <>
      <AnimatePresence>
        {open ? (
          <motion.div
            initial={{ opacity: 0, y: 24, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 24, scale: 0.96 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
            className="fixed bottom-20 right-3 z-30 flex h-[26rem] w-[calc(100vw-1.5rem)] max-w-sm flex-col overflow-hidden rounded-3xl border border-white/10 bg-midnight-950/95 shadow-2xl shadow-black/50 backdrop-blur-xl md:bottom-5 md:right-5"
            role="dialog"
            aria-label="Support chat"
          >
            <header className="flex items-center justify-between bg-gradient-to-r from-brand-600 to-fuchsia-600 px-4 py-3">
              <div className="flex items-center gap-2.5">
                <span className="grid h-8 w-8 place-items-center rounded-full bg-white/15">
                  <SparkleIcon className="h-4 w-4 text-white" />
                </span>
                <div>
                  <p className="text-sm font-bold text-white">amteCHAT support</p>
                  <p className="text-[11px] text-white/70">Usually answers instantly</p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-lg p-1 text-white/80 transition hover:bg-white/10"
                aria-label="Close chat"
              >
                <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" aria-hidden="true">
                  <path
                    d="m6 6 12 12M18 6 6 18"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                  />
                </svg>
              </button>
            </header>

            <div
              ref={scrollRef}
              className="no-scrollbar flex-1 space-y-3 overflow-y-auto p-4"
            >
              {messages.map((message) => (
                <div
                  key={message.id}
                  className={message.from === 'bot' ? 'pr-8' : 'pl-8'}
                >
                  <p
                    className={[
                      'rounded-2xl px-3.5 py-2 text-sm',
                      message.from === 'bot'
                        ? 'bg-white/5 text-slate-200'
                        : 'bg-gradient-to-r from-brand-500 to-brand-700 text-white',
                    ].join(' ')}
                  >
                    {message.text}
                  </p>
                </div>
              ))}
            </div>

            <form
              className="flex items-center gap-2 border-t border-white/5 p-3"
              onSubmit={(event) => {
                event.preventDefault()
                send(input)
              }}
            >
              <input
                value={input}
                onChange={(event) => setInput(event.target.value)}
                placeholder="Type a message…"
                className="input-field"
                aria-label="Message"
              />
              <button
                type="submit"
                className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-r from-brand-500 to-brand-700 text-white transition hover:brightness-110 active:scale-95"
                aria-label="Send"
              >
                <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" aria-hidden="true">
                  <path
                    d="M5 12h14m0 0-6-6m6 6-6 6"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </button>
            </form>
          </motion.div>
        ) : null}
      </AnimatePresence>

      <motion.button
        type="button"
        onClick={() => setOpen((current) => !current)}
        whileHover={{ scale: 1.06 }}
        whileTap={{ scale: 0.94 }}
        className="fixed bottom-20 right-4 z-30 grid h-13 w-13 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-fuchsia-600 text-white shadow-xl shadow-brand-600/40 md:bottom-5 md:right-5"
        aria-label="Open support chat"
      >
        <SparkleIcon className="h-6 w-6" />
      </motion.button>
    </>
  )
}