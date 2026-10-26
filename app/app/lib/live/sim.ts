import type { LiveMessage, LiveTransport } from './transport'

const RIDERS = [
  { id: 'sim-lea', name: 'Léa', speedMs: 6.5, startM: 120, p: 210 },
  { id: 'sim-marc', name: 'Marc', speedMs: 8, startM: 0, p: 260 },
]

/** Two fake riders at constant speed for `?sim=1` and tests. */
export function simLiveTransport(tickMs = 1000): LiveTransport {
  return {
    available: true,
    join(_room, { onMessage }) {
      let t = 0
      const timer = setInterval(() => {
        t += tickMs / 1000
        RIDERS.forEach((r) => onMessage({ id: r.id, name: r.name, d: r.startM + r.speedMs * t, p: r.p } satisfies LiveMessage))
      }, tickMs)
      return { send() {}, close: () => clearInterval(timer) }
    },
  }
}
