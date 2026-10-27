/** How much the 3D scene asks of the machine, from sharpest to lightest. */
export type QualityLevel = 'high' | 'balanced' | 'low'
/** What the rider picks: let the app decide, a level, or the flat 2D view. */
export type QualityChoice = 'auto' | QualityLevel | '2d'

export const LEVELS: readonly QualityLevel[] = ['high', 'balanced', 'low']
export const CHOICES: readonly QualityChoice[] = ['auto', ...LEVELS, '2d']

export interface QualityProfile {
  /** Anti-aliasing samples: 4 = smooth edges, 1 = off. */
  msaaSamples: number
  /** Terrain detail: Cesium's screen-space error (lower = sharper, heavier). */
  globeSse: number
  /** Detail of the buildings and trees. */
  tilesetSse: number
  /** Share of the screen's pixels that are drawn. */
  resolutionScale: number
  /** Frames per second to aim for; undefined = as often as the screen refreshes. */
  targetFrameRate?: number
  /** The nearest riders that get a 3D model. */
  maxOtherModels: number
  /** The most place pins shown at once. */
  maxPins: number
  /** Whether the swisstopo trees are drawn. */
  trees: boolean
}

export const PROFILES: Record<QualityLevel, QualityProfile> = {
  high: { msaaSamples: 4, globeSse: 1.5, tilesetSse: 6, resolutionScale: 1, maxOtherModels: 8, maxPins: 5, trees: true },
  balanced: { msaaSamples: 2, globeSse: 2.5, tilesetSse: 10, resolutionScale: 0.85, maxOtherModels: 4, maxPins: 4, trees: true },
  low: { msaaSamples: 1, globeSse: 4, tilesetSse: 16, resolutionScale: 0.65, targetFrameRate: 30, maxOtherModels: 2, maxPins: 2, trees: false },
}

/** What the browser tells about the machine; every field is optional because many browsers hide them. */
export interface Device {
  webgl2: boolean
  /** Gigabytes of memory, rounded down to a power of two and capped at 8 (Chromium only). */
  deviceMemory?: number
  hardwareConcurrency?: number
  /** The browser or the network asks for less data. */
  saveData?: boolean
  battery?: { level: number; charging: boolean }
}

export const LOW_BATTERY = 0.2

/** The level that suits this device, or the 2D view when it cannot draw the scene at all. */
export function recommendQuality(d: Device): QualityLevel | '2d' {
  if (!d.webgl2) return '2d'
  if (d.battery && !d.battery.charging && d.battery.level < LOW_BATTERY) return 'low'
  if (d.saveData) return 'low'
  const memory = d.deviceMemory ?? Infinity
  const cores = d.hardwareConcurrency ?? Infinity
  if (memory <= 2 || cores <= 2) return 'low'
  if (memory <= 4 || cores <= 4) return 'balanced'
  return 'high'
}

/** What is drawn for a choice on this device; no choice makes WebGL appear where it is missing. */
export function resolveQuality(choice: QualityChoice, d: Device): QualityLevel | '2d' {
  if (!d.webgl2 || choice === '2d') return '2d'
  return choice === 'auto' ? recommendQuality(d) : choice
}

/** The level below, or undefined from the lowest. */
export const nextLower = (level: QualityLevel): QualityLevel | undefined => LEVELS[LEVELS.indexOf(level) + 1]

/** The lighter of two levels. */
export const lighter = (a: QualityLevel, b: QualityLevel): QualityLevel => (LEVELS.indexOf(a) >= LEVELS.indexOf(b) ? a : b)

export const parseChoice = (value: unknown): QualityChoice => CHOICES.find((c) => c === value) ?? 'auto'

/**
 * Notices when the scene is too slow: the frame rate under `minFps` for `strikes` windows in a row.
 * Starts counting after a warm-up (tiles are still loading), and ignores a window the page spent hidden or frozen.
 */
export class FpsWatchdog {
  private startedAt?: number
  private windowStart?: number
  private frames = 0
  private slowWindows = 0

  constructor(
    private readonly minFps = 24,
    private readonly windowMs = 5000,
    private readonly strikes = 2,
    private readonly warmupMs = 10_000,
  ) {}

  /** Call once per drawn frame, with a clock in ms; true when the scene should be made lighter. */
  frame(nowMs: number): boolean {
    this.startedAt ??= nowMs
    if (nowMs - this.startedAt < this.warmupMs) return false
    this.windowStart ??= nowMs
    this.frames++
    const elapsed = nowMs - this.windowStart
    if (elapsed < this.windowMs) return false

    const frozen = elapsed > this.windowMs * 2 // a hidden tab or a stalled page, not a slow scene
    const fps = (this.frames * 1000) / elapsed
    this.windowStart = nowMs
    this.frames = 0
    this.slowWindows = !frozen && fps < this.minFps ? this.slowWindows + 1 : 0
    if (this.slowWindows < this.strikes) return false
    this.reset()
    return true
  }

  /** Starts again with a warm-up, after the scene was changed. */
  reset() {
    this.startedAt = undefined
    this.windowStart = undefined
    this.frames = 0
    this.slowWindows = 0
  }
}
