import { DEFAULT_PARAMS, type RiderParams } from '../physics/speed'

/** Frontal drag area (m²) for the usual riding positions. */
export const POSITIONS = { hoods: 0.36, drops: 0.32, aero: 0.28, tops: 0.4 } as const
export type Position = keyof typeof POSITIONS | 'custom'
export const POSITION_KEYS = [...Object.keys(POSITIONS), 'custom'] as Position[]

/** What the rider tells the app about themselves and their trainer. Kept on their account and in this browser. */
export interface RiderSettings {
  weightKg: number
  bikeKg: number
  position: Position
  /** Used when `position` is 'custom'; otherwise it follows the position. */
  cda: number
  ftp: number
  /** Share of the real grade the rider rides (and feels): 100 = as it is, 50 = half as steep. */
  difficultyPct: number
}

export const LIMITS = {
  weightKg: { min: 30, max: 200 },
  bikeKg: { min: 5, max: 25 },
  cda: { min: 0.15, max: 0.6 },
  ftp: { min: 50, max: 600 },
  difficultyPct: { min: 30, max: 100, step: 5 },
} as const

export const DEFAULT_SETTINGS: RiderSettings = {
  weightKg: DEFAULT_PARAMS.riderKg,
  bikeKg: DEFAULT_PARAMS.bikeKg,
  position: 'drops',
  cda: POSITIONS.drops,
  ftp: 200,
  difficultyPct: 100,
}

const clamp = (value: unknown, min: number, max: number, fallback: number): number => {
  const n = Number(value)
  return Number.isFinite(n) && value !== null && value !== '' ? Math.min(max, Math.max(min, n)) : fallback
}

/** Anything that came from storage, the server or a form → valid settings (field by field fallback to the defaults). */
export function sanitize(input: unknown): RiderSettings {
  const k = (input && typeof input === 'object' ? input : {}) as Record<string, unknown>
  const position = POSITION_KEYS.find((p) => p === k.position) ?? DEFAULT_SETTINGS.position
  const { difficultyPct: d } = LIMITS
  const difficulty = clamp(k.difficultyPct, d.min, d.max, DEFAULT_SETTINGS.difficultyPct)
  return {
    weightKg: clamp(k.weightKg, LIMITS.weightKg.min, LIMITS.weightKg.max, DEFAULT_SETTINGS.weightKg),
    bikeKg: clamp(k.bikeKg, LIMITS.bikeKg.min, LIMITS.bikeKg.max, DEFAULT_SETTINGS.bikeKg),
    position,
    cda:
      position === 'custom'
        ? clamp(k.cda, LIMITS.cda.min, LIMITS.cda.max, DEFAULT_SETTINGS.cda)
        : POSITIONS[position],
    ftp: Math.round(clamp(k.ftp, LIMITS.ftp.min, LIMITS.ftp.max, DEFAULT_SETTINGS.ftp)),
    difficultyPct: Math.round(difficulty / d.step) * d.step,
  }
}

/** The physics parameters of these settings. */
export const toParams = (s: RiderSettings): RiderParams => ({
  ...DEFAULT_PARAMS,
  riderKg: s.weightKg,
  bikeKg: s.bikeKg,
  cda: s.cda,
})

/** The grade the rider rides and the trainer applies: the real grade scaled by the difficulty. */
export const effectiveGrade = (realPct: number, difficultyPct: number): number => (realPct * difficultyPct) / 100

/**
 * Which copy of the settings wins when the browser and the account both have one: the most recently changed.
 * Times are ISO strings or undefined (never saved).
 */
export function newest(local: { at?: string } | undefined, remote: { at?: string } | undefined): 'local' | 'remote' | 'none' {
  if (!local?.at && !remote?.at) return 'none'
  if (!remote?.at) return 'local'
  if (!local?.at) return 'remote'
  return Date.parse(remote.at) >= Date.parse(local.at) ? 'remote' : 'local'
}
