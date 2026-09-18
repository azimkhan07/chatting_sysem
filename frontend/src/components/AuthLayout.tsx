import type { ReactNode } from 'react'

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
  return (
    <div className="space-y-1.5">
      <label
        htmlFor={name}
        className="block text-sm font-medium text-slate-300"
      >
        {label}
      </label>
      <input
        id={name}
        name={name}
        type={type}
        value={value}
        placeholder={placeholder}
        autoComplete={autoComplete ?? name}
        onChange={(event) => onChange(event.target.value)}
        className={[
          'w-full rounded-xl border bg-slate-900/60 px-4 py-3 text-slate-100',
          'placeholder:text-slate-500 outline-none transition',
          'focus:border-brand-400 focus:ring-4 focus:ring-brand-500/20',
          error ? 'border-rose-500/60' : 'border-slate-700',
        ].join(' ')}
      />
      {error ? (
        <p className="text-sm text-rose-400">{error}</p>
      ) : hint ? (
        <p className="text-sm text-slate-500">{hint}</p>
      ) : null}
    </div>
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
        <span className="grid h-10 w-10 place-items-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-600 shadow-lg shadow-brand-600/30">
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

export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="relative grid min-h-svh lg:grid-cols-2">
      <div className="pointer-events-none absolute inset-0 overflow-hidden lg:relative">
        <div className="absolute inset-0 bg-gradient-to-br from-slate-950 via-brand-950/60 to-slate-950" />
        <div className="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-brand-600/30 blur-3xl" />
        <div className="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-fuchsia-600/20 blur-3xl" />
      </div>

      <div className="relative z-10 hidden flex-col justify-between p-10 lg:flex xl:p-14">
        <BrandMark />
        <div className="max-w-md">
          <h1 className="text-4xl font-extrabold leading-tight tracking-tight text-white xl:text-5xl">
            Where your world
            <br />
            <span className="bg-gradient-to-r from-brand-300 to-fuchsia-300 bg-clip-text text-transparent">
              stays close.
            </span>
          </h1>
          <p className="mt-4 text-lg text-slate-400">
            One account, every conversation. Quick, smooth and built for the
            way you move online.
          </p>
        </div>
        <p className="text-sm text-slate-500">
          amteCHAT · a social hub in the making
        </p>
      </div>

      <main className="relative z-10 flex items-center justify-center px-4 py-10 sm:px-6">
        <div className="w-full max-w-md">
          <div className="mb-8 flex justify-center lg:hidden">
            <BrandMark />
          </div>
          {children}
        </div>
      </main>
    </div>
  )
}