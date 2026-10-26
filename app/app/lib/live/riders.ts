import type { LiveMessage } from './transport'

export interface Rider {
  id: string
  name: string
  d: number
  p: number
  /** When the last position arrived (ms). */
  t: number
  speedMs: number
  kit?: unknown
}

const MAX_EXTRAPOLATE_S = 2
export const STALE_MS = 10_000

/** Adds or updates a rider; speed is derived from the last two positions so the marker can glide between messages. */
export function upsert(list: Rider[], m: LiveMessage, now: number): Rider[] {
  const prev = list.find((r) => r.id === m.id)
  const dt = prev ? (now - prev.t) / 1000 : 0
  const speedMs = prev && dt > 0 ? Math.max(0, (m.d - prev.d) / dt) : 0
  const next: Rider = { id: m.id, name: m.name, d: m.d, p: m.p, t: now, speedMs, kit: m.kit ?? prev?.kit }
  return prev ? list.map((r) => (r.id === m.id ? next : r)) : [...list, next]
}

export const remove = (list: Rider[], id: string) => list.filter((r) => r.id !== id)

export const dropStale = (list: Rider[], now: number) => list.filter((r) => now - r.t < STALE_MS)

/** Estimated distance now, extrapolated for at most two seconds. */
export const distanceAt = (r: Rider, now: number) => r.d + r.speedMs * Math.min((now - r.t) / 1000, MAX_EXTRAPOLATE_S)
