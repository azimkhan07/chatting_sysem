import { useState } from 'react'
import type { CSSProperties, ReactNode } from 'react'

interface AuthFieldProps {
  label: string
  name: string
  type?: string
  value: string
  placeholder?: string
  autoComplete?: string
  error?: string
  hint?: string
  onChange: (value: string) => void
}

export function AuthField({
  label,
  name,
  type = 'text',
  value,
  placeholder,
  autoComplete,
  error,
  hint,
  onChange,
}: AuthFieldProps) {
  const isPassword = type === 'password'
  const [revealed, setRevealed] = useState(false)

  return (
    <div className="space-y-1.5">
      <label
        htmlFor={name}
        className="block text-sm font-medium text-slate-300"
      >
        {label}
      </label>
      <div className="relative">
        <input
          id={name}
          name={name}
          type={isPassword && revealed ? 'text' : type}
          value={value}
          placeholder={placeholder}
          autoComplete={autoComplete ?? name}
          onChange={(event) => onChange(event.target.value)}
          className={[
            error ? 'input-field is-invalid' : 'input-field',
            isPassword ? 'pr-11' : '',
          ].join(' ')}
        />
        {isPassword ? (
          <button
            type="button"
            onClick={() => setRevealed((current) => !current)}
            aria-label={revealed ? 'Hide password' : 'Show password'}
            tabIndex={-1}
            className="absolute right-1 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-500 transition hover:text-slate-300"
          >
            {revealed ? <EyeOffIcon /> : <EyeIcon />}
          </button>
        ) : null}
      </div>
      {error ? (
        <p className="text-sm text-rose-400">{error}</p>
      ) : hint ? (
        <p className="text-sm text-slate-500">{hint}</p>
      ) : null}
    </div>
  )
}

export function EyeIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" className="h-4.5 w-4.5" aria-hidden="true">
      <path
        d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
      />
      <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  )
}

export function EyeOffIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" className="h-4.5 w-4.5" aria-hidden="true">
      <path
        d="M3 3l18 18"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
      />
      <path
        d="M10.6 5.1A9.6 9.6 0 0 1 12 5c6.5 0 10 7 10 7a17.4 17.4 0 0 1-2.9 3.9M6.7 6.6A17.5 17.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 4.9-1.3"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
      />
      <path
        d="M9.9 9.9a3 3 0 0 0 4.2 4.2"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
      />
    </svg>
  )
}

export function FormError({ message }: { message: string }) {
  return (
    <div
      role="alert"
      className="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-300"
    >
      {message}
    </div>
  )
}

export function Spinner({ className = 'h-5 w-5' }: { className?: string }) {
  return (
    <span
      className={`animate-spin rounded-full border-2 border-white/30 border-t-white ${className}`}
      aria-hidden="true"
    />
  )
}

export function BrandMark({ compact = false }: { compact?: boolean }) {
  return (
    <div className="flex items-center gap-2.5">
      {compact ? null : (
        <span className="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700 shadow-lg shadow-brand-600/30">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            className="h-5 w-5 text-white"
            aria-hidden="true"
          >
            <path
              d="M4 6.5h16M4 12h16M4 17.5h10"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
            />
          </svg>
        </span>
      )}
      <span className="text-lg font-bold tracking-tight text-white">
        amte<span className="text-brand-400">CHAT</span>
      </span>
    </div>
  )
}

function ChatBubble({
  style,
  initials,
  lines,
}: {
  style: CSSProperties
  initials: string
  lines: string[]
}) {
  return (
    <div
      className="bubble flex items-end gap-2.5 rounded-2xl border border-white/10 bg-slate-900/70 p-3.5 backdrop-blur"
      style={style}
    >
      <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-[11px] font-bold text-[#fff]">
        {initials}
      </span>
      <div className="space-y-1.5">
        {lines.map((line, index) => (
          <div
            key={index}
            className="h-2 rounded-full bg-slate-700/70"
            style={{ width: line }}
          />
        ))}
      </div>
    </div>
  )
}

export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="relative grid h-svh overflow-hidden lg:grid-cols-2">
      <div className="mesh-bg">
        <div className="orb -top-32 -left-32 h-96 w-96 bg-brand-600/30" />
        <div className="orb bottom-0 right-0 h-96 w-96 bg-fuchsia-600/20 [animation-delay:-6s]" />
        <div className="orb top-1/2 -left-40 h-72 w-72 bg-brand-500/20 [animation-delay:-12s]" />
      </div>

      <div className="relative z-10 hidden flex-col justify-between p-10 lg:flex xl:p-14">
        <BrandMark />

        <div className="max-w-md">
          <h1 className="text-4xl font-extrabold leading-tight tracking-tight text-white xl:text-5xl">
            Where your world
            <br />
            <span className="text-gradient">stays close.</span>
          </h1>
          <p className="mt-4 text-lg text-slate-400">
            One account, every conversation. Quick, smooth and built for the
            way you move online.
          </p>

          <div className="mt-10 hidden space-y-4 xl:block">
            <ChatBubble
              style={{ animationDelay: '0s' }}
              initials="AR"
              lines={['w-44', 'w-28']}
            />
            <ChatBubble
              style={{ animationDelay: '1.5s', marginLeft: '4rem' }}
              initials="SM"
              lines={['w-56', 'w-36']}
            />
            <ChatBubble
              style={{ animationDelay: '3s' }}
              initials="PK"
              lines={['w-32', 'w-48']}
            />
          </div>
        </div>

        <p className="text-sm text-slate-500">
          amteCHAT · a social hub in the making
        </p>
      </div>

      <main className="relative z-10 flex items-center justify-center px-4 py-6 sm:px-6">
        <div className="w-full max-w-md">
          <div className="mb-5 flex justify-center lg:hidden">
            <BrandMark compact />
          </div>
          {children}
        </div>
      </main>
    </div>
  )
}