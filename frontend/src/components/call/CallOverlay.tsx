import {
  LocalVideoTrack,
  RemoteParticipant,
  RemoteTrack,
  Room,
  RoomEvent,
  Track,
} from 'livekit-client'
import { useCallback, useEffect, useRef, useState } from 'react'

import { chatApi } from '@/lib/api'
import { useAuthStore } from '@/stores/authStore'
import { useCallStore } from '@/stores/callStore'

const LIVEKIT_URL = import.meta.env.VITE_LIVEKIT_URL ?? 'ws://127.0.0.1:7880'

interface RemoteTracks {
  audio: RemoteTrack | null
  video: RemoteTrack | null
}

type RemotePublications = Record<string, RemoteTracks>

function participantName(participant: RemoteParticipant): string {
  return participant.name || participant.identity || 'Guest'
}

function CallsIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.6 19.6 0 0 1-8.6-3.1 19.3 19.3 0 0 1-6-6A19.6 19.6 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.6 2z" />
    </svg>
  )
}

function VideoIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="m22 8-6 4 6 4V8Z" />
      <rect x="2" y="6" width="14" height="12" rx="2" />
    </svg>
  )
}

function MicOffIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <line x1="2" y1="2" x2="22" y2="22" />
      <path d="M18.5 14a8 8 0 0 0 1.5-4V7" />
      <path d="M5 7v3a7 7 0 0 0 14 0" />
      <path d="M12 19v3" />
      <path d="M8 22h8" />
    </svg>
  )
}

function MicIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <rect x="9" y="2" width="6" height="12" rx="3" />
      <path d="M5 10a7 7 0 0 0 14 0" />
      <path d="M12 19v3" />
    </svg>
  )
}

function VideoOffIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="m16 16 6 6" />
      <path d="m2 2 20 20" />
      <path d="M10 5h8a2 2 0 0 1 2 2v10a2 2 0 0 1-1 1.7" />
      <path d="M2 7v10a2 2 0 0 0 2 2h8" />
    </svg>
  )
}

function PhoneOffIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M10.7 2.7a2 2 0 0 1 2.4 1.3l1.2 3.3a2 2 0 0 1-.5 2.1l-1.3 1.3a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5l3.3 1.2a2 2 0 0 1 1.3 2.4l-.8 2.6a2 2 0 0 1-2 1.3A19.6 19.6 0 0 1 4.4 5.4a2 2 0 0 1 1.3-2z" />
      <line x1="2" y1="2" x2="22" y2="22" />
    </svg>
  )
}

export default function CallOverlay() {
  const phase = useCallStore((state) => state.phase)
  const callId = useCallStore((state) => state.callId)
  const conversationId = useCallStore((state) => state.conversationId)
  const kind = useCallStore((state) => state.kind)
  const room = useCallStore((state) => state.room)
  const serverUrl = useCallStore((state) => state.serverUrl)
  const token = useCallStore((state) => state.token)
  const caller = useCallStore((state) => state.caller)
  const busy = useCallStore((state) => state.busy)
  const setBusy = useCallStore((state) => state.setBusy)
  const activate = useCallStore((state) => state.activate)
  const end = useCallStore((state) => state.end)
  const reset = useCallStore((state) => state.reset)

  const me = useAuthStore((state) => state.user)

  const localVideoRef = useRef<HTMLVideoElement | null>(null)
  const roomRef = useRef<Room | null>(null)
  const [muted, setMuted] = useState(false)
  const [videoOff, setVideoOff] = useState(false)
  const [remotes, setRemotes] = useState<RemotePublications>({})
  const [remoteNames, setRemoteNames] = useState<Record<string, string>>({})

  const hangUp = useCallback(() => {
    const roomInstance = roomRef.current
    if (roomInstance !== null) {
      void roomInstance.disconnect()
      roomRef.current = null
    }

    const activeCallId = callId
    const activeConversationId = conversationId
    setMuted(false)
    setVideoOff(false)
    setRemotes({})
    setRemoteNames({})

    if (activeCallId !== null && activeConversationId !== null) {
      chatApi.endCall(activeCallId, activeConversationId).catch(() => {
        // Signalling is best-effort here; the room close already stops media.
      })
    }

    end()
  }, [callId, conversationId, end])

  // Keep the current remote set mirrored in a ref so the disconnect handler can
  // decide whether the last person left without re-subscribing to state.
  const remotesRef = useRef<RemotePublications>({})

  // The one true join: fire on outgoing->present token and on incoming answer.
  useEffect(() => {
    if (phase !== 'incoming' && phase !== 'outgoing') return
    if (!room || !conversationId || !token) return

    let cancelled = false
    const initiator = phase === 'outgoing'

    async function join(): Promise<void> {
      if (cancelled) return

      setBusy(true)

      const roomInstance = new Room({
        adaptiveStream: true,
        dynacast: true,
      })
      roomRef.current = roomInstance

      roomInstance
        .on(RoomEvent.TrackSubscribed, (track: RemoteTrack, _pub, participant) => {
          const identity = participant.identity
          setRemotes((current) => {
            const existing = current[identity] ?? { audio: null, video: null }
            return {
              ...current,
              [identity]: {
                audio: track.kind === 'audio' ? track : existing.audio,
                video: track.kind === 'video' ? track : existing.video,
              },
            }
          })
          setRemoteNames((current) => ({
            ...current,
            [identity]: participantName(participant),
          }))
        })
        .on(RoomEvent.TrackUnsubscribed, (track: RemoteTrack, _pub, participant) => {
          const identity = participant.identity
          setRemotes((current) => {
            const existing = current[identity]
            if (!existing) return current
            return {
              ...current,
              [identity]: {
                audio: existing.audio === track ? null : existing.audio,
                video: existing.video === track ? null : existing.video,
              },
            }
          })
          track.detach().forEach((el) => {
            const parent = el.parentElement
            if (parent !== null) parent.removeChild(el)
          })
        })
        .on(RoomEvent.ParticipantConnected, () => {
          // Caller: the callee picked up. Show the live call.
          if (initiator && !cancelled) {
            activate()
            setBusy(false)
          }
        })
        .on(RoomEvent.ParticipantDisconnected, async (participant: RemoteParticipant) => {
          const identity = participant.identity
          setRemotes((current) => {
            const next = { ...current }
            delete next[identity]
            return next
          })
          if (!initiator || Object.keys(remotesRef.current).length === 0) {
            await roomInstance.disconnect()
            roomRef.current = null
            hangUp()
          }
        })

      try {
        await roomInstance.connect(serverUrl ?? LIVEKIT_URL, joinTokenFor(token))

        await roomInstance.localParticipant.setCameraEnabled(kind === 'video')
        await roomInstance.localParticipant.setMicrophoneEnabled(true)

        const localVideo = roomInstance.localParticipant.getTrackPublication(
          Track.Source.Camera,
        )?.track

        if (localVideo instanceof LocalVideoTrack && localVideoRef.current) {
          localVideo.attach(localVideoRef.current)
        }

        if (!cancelled) {
          if (!initiator || roomInstance.remoteParticipants.size > 0) {
            activate()
            setBusy(false)
          }
        }
      } catch {
        if (!cancelled) {
          hangUp()
        }
      }
    }

    void join()

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, room, conversationId, token])

  useEffect(() => {
    remotesRef.current = remotes
  }, [remotes])
  useEffect(() => {
    const roomInstance = roomRef.current
    if (!roomInstance) return
    void roomInstance.localParticipant.setMicrophoneEnabled(!muted)
  }, [muted])

  useEffect(() => {
    const roomInstance = roomRef.current
    if (!roomInstance) return
    void roomInstance.localParticipant.setCameraEnabled(!videoOff && kind === 'video')
  }, [videoOff, kind])

  useEffect(() => {
    return () => {
      const roomInstance = roomRef.current
      if (roomInstance !== null) {
        void roomInstance.disconnect()
        roomRef.current = null
      }
    }
  }, [])

  useEffect(() => {
    if (phase === 'ended') {
      const timer = window.setTimeout(reset, 600)
      return () => window.clearTimeout(timer)
    }
  }, [phase, reset])

  if (phase === 'idle' || phase === 'ended') {
    return null
  }

  const displayName =
    caller?.display_name ?? me?.display_name ?? 'Call'
  const isIncoming = phase === 'incoming'
  const isActive = phase === 'active'
  const remoteCount = Object.keys(remotes).length
  const showLocal = kind === 'video'

  return (
    <div className="fixed inset-0 z-[90] flex flex-col bg-midnight-950/95 backdrop-blur-sm">
      <div
        className={
          isActive
            ? 'relative flex min-h-0 flex-1 flex-wrap items-center justify-center gap-3 overflow-y-auto p-4'
            : 'flex min-h-0 flex-1 flex-col items-center justify-center gap-2 px-6'
        }
      >
        {isActive && remoteCount > 0 ? (
          Object.entries(remotes).map(([identity, tracks]) => (
            <div
              key={identity}
              className={[
                'relative overflow-hidden rounded-2xl bg-black/40',
                remoteCount === 1 ? 'h-full w-full' : 'h-52 w-72',
              ].join(' ')}
            >
              {tracks.video ? (
                <video
                  ref={(el) => {
                    if (el === null) return
                    tracks.video?.attach(el)
                  }}
                  autoPlay
                  playsInline
                  className="h-full w-full object-cover"
                />
              ) : (
                <div className="grid h-full w-full place-items-center">
                  <span className="grid h-16 w-16 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-2xl font-bold text-white">
                    {(remoteNames[identity] ?? '?').charAt(0)?.toUpperCase() ?? '?'}
                  </span>
                </div>
              )}
              <span className="absolute bottom-2 left-2 rounded-lg bg-black/50 px-2 py-0.5 text-xs font-semibold text-white backdrop-blur">
                {remoteNames[identity] ?? identity}
              </span>
            </div>
          ))
        ) : (
          <div className="flex flex-col items-center gap-3 px-6">
            <span className="grid h-24 w-24 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-3xl font-bold text-white">
              {displayName.charAt(0)?.toUpperCase() ?? '?'}
            </span>
            <div className="text-center">
              <p className="text-lg font-semibold text-white">{displayName}</p>
              <p className="mt-0.5 text-sm text-slate-400">
                {isIncoming
                  ? 'Incoming call'
                  : busy
                    ? isActive
                      ? 'Connecting…'
                      : 'Ringing…'
                    : isActive
                      ? 'Connected'
                      : 'Connecting…'}
              </p>
            </div>
          </div>
        )}

        {isActive && showLocal ? (
          <div className="absolute right-3 top-3 flex h-36 w-28 overflow-hidden rounded-xl border border-white/15 bg-black shadow-lg">
            <video
              ref={localVideoRef}
              autoPlay
              playsInline
              muted
              className={[
                'h-full w-full object-cover',
                videoOff ? 'opacity-0' : 'opacity-100',
              ].join(' ')}
            />
            {videoOff ? (
              <span className="absolute inset-0 grid place-items-center bg-black/70 text-slate-300">
                <VideoOffIcon className="h-6 w-6" />
              </span>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="flex shrink-0 items-center justify-center gap-4 py-6">
        {isIncoming ? (
          <>
            <button
              type="button"
              onClick={() => {
                if (!callId || !conversationId) return
                setBusy(true)
                chatApi
                  .answerCall(callId, conversationId)
                  .then((result) => {
                    useCallStore.setState({
                      room: result.room,
                      serverUrl: result.server_url,
                      token: result.token,
                      callId: result.call.id,
                      busy: false,
                    })
                  })
                  .catch(() => {
                    setBusy(false)
                    hangUp()
                  })
              }}
              disabled={busy}
              title="Answer"
              aria-label="Answer call"
              className="grid h-16 w-16 place-items-center rounded-full bg-emerald-500 text-white transition hover:bg-emerald-400 disabled:opacity-60"
            >
              <CallsIcon className="h-7 w-7" />
            </button>
            <button
              type="button"
              onClick={() => {
                if (callId && conversationId) {
                  chatApi.rejectCall(callId, conversationId).catch(() => {})
                }
                end()
              }}
              title="Decline"
              aria-label="Decline call"
              className="grid h-16 w-16 place-items-center rounded-full bg-rose-500 text-white transition hover:bg-rose-400"
            >
              <PhoneOffIcon className="h-7 w-7" />
            </button>
          </>
        ) : (
          <>
            <button
              type="button"
              onClick={() => setMuted((value) => !value)}
              title={muted ? 'Unmute' : 'Mute'}
              aria-label={muted ? 'Unmute microphone' : 'Mute microphone'}
              className={[
                'grid h-14 w-14 place-items-center rounded-full transition',
                muted ? 'bg-white/10 text-rose-300' : 'bg-white/10 text-white hover:bg-white/20',
              ].join(' ')}
            >
              {muted ? <MicOffIcon className="h-6 w-6" /> : <MicIcon className="h-6 w-6" />}
            </button>
            {showLocal ? (
              <button
                type="button"
                onClick={() => setVideoOff((value) => !value)}
                title={videoOff ? 'Turn camera on' : 'Turn camera off'}
                aria-label={videoOff ? 'Turn camera on' : 'Turn camera off'}
                className={[
                  'grid h-14 w-14 place-items-center rounded-full transition',
                  videoOff ? 'bg-white/10 text-rose-300' : 'bg-white/10 text-white hover:bg-white/20',
                ].join(' ')}
              >
                {videoOff ? (
                  <VideoOffIcon className="h-6 w-6" />
                ) : (
                  <VideoIcon className="h-6 w-6" />
                )}
              </button>
            ) : null}
            <button
              type="button"
              onClick={hangUp}
              title="End call"
              aria-label="End call"
              className="grid h-16 w-16 place-items-center rounded-full bg-rose-500 text-white transition hover:bg-rose-400"
            >
              <PhoneOffIcon className="h-7 w-7" />
            </button>
          </>
        )}
      </div>
    </div>
  )
}

function joinTokenFor(token: string | null): string {
  return token ?? ''
}