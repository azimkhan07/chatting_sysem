import { useEffect, useRef, useState } from 'react'

import { Spinner } from '@/components/AuthLayout'
import { BrushIcon, XIcon } from '@/components/icons'

const COLORS = ['#ffffff', '#f43f5e', '#fb923c', '#facc15', '#4ade80', '#38bdf8', '#a78bfa', '#111827']

/**
 * A small touch/mouse canvas that becomes a PNG and is uploaded as a `drawing`
 * message. Exported as a blob rather than a data URI on purpose: the send
 * endpoint validates `media_url`, and a data URI would be both rejected and a
 * smuggling vector if it ever were accepted.
 */
export function DrawingCanvas({
  busy,
  onCancel,
  onSubmit,
}: {
  busy: boolean
  onCancel: () => void
  onSubmit: (blob: Blob) => void
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const drawing = useRef(false)
  const last = useRef<{ x: number; y: number } | null>(null)
  const [color, setColor] = useState(COLORS[0])
  const [size, setSize] = useState(6)
  const [dirty, setDirty] = useState(false)

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return
    const context = canvas.getContext('2d')
    if (!context) return

    // Transparent, not white: a transparent PNG composites over whatever
    // wallpaper the chat is using.
    context.clearRect(0, 0, canvas.width, canvas.height)
  }, [])

  function pointFrom(event: React.PointerEvent<HTMLCanvasElement>) {
    const canvas = canvasRef.current
    if (!canvas) return { x: 0, y: 0 }
    const rect = canvas.getBoundingClientRect()
    return {
      x: ((event.clientX - rect.left) / rect.width) * canvas.width,
      y: ((event.clientY - rect.top) / rect.height) * canvas.height,
    }
  }

  function start(event: React.PointerEvent<HTMLCanvasElement>) {
    const context = canvasRef.current?.getContext('2d')
    if (!context) return
    event.currentTarget.setPointerCapture(event.pointerId)
    drawing.current = true
    last.current = pointFrom(event)
    setDirty(true)
  }

  function move(event: React.PointerEvent<HTMLCanvasElement>) {
    if (!drawing.current) return
    const context = canvasRef.current?.getContext('2d')
    if (!context || !last.current) return

    const next = pointFrom(event)
    context.strokeStyle = color
    context.lineWidth = size
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.beginPath()
    context.moveTo(last.current.x, last.current.y)
    context.lineTo(next.x, next.y)
    context.stroke()
    last.current = next
  }

  function end() {
    drawing.current = false
    last.current = null
  }

  function clear() {
    const canvas = canvasRef.current
    const context = canvas?.getContext('2d')
    if (!canvas || !context) return
    context.clearRect(0, 0, canvas.width, canvas.height)
    setDirty(false)
  }

  function submit() {
    const canvas = canvasRef.current
    if (!canvas) return
    canvas.toBlob((blob) => {
      if (blob) onSubmit(blob)
    }, 'image/png')
  }

  return (
    <div className="absolute bottom-full left-0 z-20 mb-2 w-[min(24rem,calc(100vw-2rem))] rounded-2xl border border-white/10 bg-midnight-900 p-3 shadow-2xl">
      <div className="mb-2 flex items-center justify-between">
        <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Draw</p>
        <button
          type="button"
          onClick={onCancel}
          aria-label="Close drawing pad"
          className="rounded-lg p-1 text-slate-500 transition hover:bg-white/5 hover:text-white"
        >
          <XIcon className="h-4 w-4" />
        </button>
      </div>

      <div className="overflow-hidden rounded-xl border border-white/10 bg-[#0b1120]">
        <canvas
          ref={canvasRef}
          width={480}
          height={300}
          onPointerDown={start}
          onPointerMove={move}
          onPointerUp={end}
          onPointerLeave={end}
          onPointerCancel={end}
          className="h-40 w-full touch-none cursor-crosshair"
        />
      </div>

      <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
        {COLORS.map((option) => (
          <button
            key={option}
            type="button"
            onClick={() => setColor(option)}
            aria-label={`Colour ${option}`}
            className={[
              'h-6 w-6 rounded-full ring-offset-1 ring-offset-midnight-900 transition',
              color === option ? 'ring-2 ring-white' : 'ring-0',
            ].join(' ')}
            style={{ backgroundColor: option }}
          />
        ))}
        <div className="ml-auto flex items-center gap-1.5">
          <label className="flex items-center gap-1 text-[11px] text-slate-500">
            Size
            <input
              type="range"
              min={2}
              max={24}
              value={size}
              onChange={(event) => setSize(Number(event.target.value))}
              className="w-16"
            />
          </label>
        </div>
      </div>

      <div className="mt-3 flex items-center gap-2">
        <button
          type="button"
          onClick={clear}
          className="rounded-xl border border-white/10 px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/5"
        >
          Clear
        </button>
        <button
          type="button"
          onClick={submit}
          disabled={!dirty || busy}
          className="btn-primary ml-auto flex items-center gap-1.5 px-4 py-1.5 text-xs"
        >
          {busy ? <Spinner className="h-3.5 w-3.5" /> : <BrushIcon className="h-3.5 w-3.5" />}
          Send drawing
        </button>
      </div>
    </div>
  )
}
