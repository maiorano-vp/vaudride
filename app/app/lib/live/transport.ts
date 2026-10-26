/** Everything live-tracking the app needs, so Pusher can be swapped (or faked with `?sim=1`) without touching the UI. */
export interface LiveMessage {
  id: string
  name: string
  /** Distance along the course (m). */
  d: number
  /** Power (W). */
  p: number
  /** The rider's kit as the server shares it; unvalidated, use `sanitizeKit`. */
  kit?: unknown
}

export interface LiveRoom {
  send(position: { d: number; p: number }): void
  close(): void
}

export interface LiveTransport {
  readonly available: boolean
  join(
    room: string,
    handlers: { onMessage: (m: LiveMessage) => void; onLeave: (id: string) => void },
  ): LiveRoom
}

export const unavailableTransport: LiveTransport = {
  available: false,
  join: () => ({ send() {}, close() {} }),
}
