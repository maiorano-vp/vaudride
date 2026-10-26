/** One recorded second of a ride, in the short keys the API expects. */
export interface RideSample {
  /** Ride clock (s). */
  t: number
  /** Distance along the course (m). */
  d: number
  /** Power (W). */
  p: number
  /** Cadence (rpm), null when unknown. */
  c: number | null
  /** Heart rate (bpm), null when unknown. */
  h: number | null
  /** Speed (km/h). */
  s: number
}

export interface RideReading {
  elapsedS: number
  distanceM: number
  speedMs: number
  powerW: number
  cadenceRpm?: number
  heartRate?: number
}

export interface RideSummary {
  durationS: number
  distanceM: number
  avgPowerW: number
  maxPowerW: number
  avgHeartRate: number | null
}

/** Keeps one sample per second of ride clock, so a pause (clock stopped) records nothing. */
export class RideRecorder {
  samples: RideSample[] = []
  startedAt?: Date

  push(r: RideReading, now = new Date()) {
    const t = Math.floor(r.elapsedS)
    if (this.samples.length && t <= this.samples[this.samples.length - 1]!.t) return
    this.startedAt ??= now
    this.samples.push({
      t,
      d: Math.round(r.distanceM * 10) / 10,
      p: Math.max(0, Math.round(r.powerW)),
      c: r.cadenceRpm === undefined ? null : Math.round(r.cadenceRpm),
      h: r.heartRate ? Math.round(r.heartRate) : null,
      s: Math.round(r.speedMs * 36) / 10,
    })
  }

  reset() {
    this.samples = []
    this.startedAt = undefined
  }

  /** Enough to be worth saving: the API wants at least two samples. */
  get recordable() {
    return this.samples.length >= 2
  }

  summary(): RideSummary {
    const last = this.samples[this.samples.length - 1]
    const powers = this.samples.map((s) => s.p)
    const hearts = this.samples.map((s) => s.h).filter((h): h is number => h !== null && h > 0)
    return {
      durationS: last?.t ?? 0,
      distanceM: last?.d ?? 0,
      avgPowerW: powers.length ? Math.round(powers.reduce((a, b) => a + b, 0) / powers.length) : 0,
      maxPowerW: powers.length ? Math.max(...powers) : 0,
      avgHeartRate: hearts.length ? Math.round(hearts.reduce((a, b) => a + b, 0) / hearts.length) : null,
    }
  }
}
