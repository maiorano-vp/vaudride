import { KIT_PRESETS } from '../kit/kit'
import type { LiveMessage, LiveTransport } from './transport'

const RIDERS = [
  { id: 'sim-lea', nickname: 'Léa', speedMs: 6.5, startM: 120, p: 210, kit: KIT_PRESETS.lavaux },
  { id: 'sim-marc', nickname: 'Marc', speedMs: 8, startM: 0, p: 260, kit: KIT_PRESETS.lac },
]

/** Two fake riders at constant speed for `?sim=1` and tests. */
export function simLiveTransport(tickMs = 1000): LiveTransport {
  return {
    available: true,
    join(_room, { onMessage }) {
      let t = 0
      const timer = setInterval(() => {
        t += tickMs / 1000
        RIDERS.forEach((r) => onMessage({ id: r.id, nickname: r.nickname, d: r.startM + r.speedMs * t, p: r.p, kit: r.kit } satisfies LiveMessage))
      }, tickMs)
      return { send() {}, close: () => clearInterval(timer) }
    },
  }
}
