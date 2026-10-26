export const CYCLING_POWER_SERVICE = 0x1818
export const CYCLING_POWER_MEASUREMENT = 0x2a63

export interface PowerMeasurement {
  powerW: number
  /** Cumulative crank revolutions and last crank event time (1/1024 s), when the sensor reports them. */
  crank?: { revs: number; time: number }
}

export function parseCyclingPower(v: DataView): PowerMeasurement {
  const flags = v.getUint16(0, true)
  const powerW = v.getInt16(2, true)
  let o = 4
  if (flags & 0x01) o += 1 // pedal power balance
  if (flags & 0x04) o += 2 // accumulated torque
  if (flags & 0x10) o += 6 // wheel revolution data
  if (flags & 0x20) return { powerW, crank: { revs: v.getUint16(o, true), time: v.getUint16(o + 2, true) } }
  return { powerW }
}

/** Cadence (rpm) between two crank readings; handles uint16 rollover. Undefined if no new event. */
export function cadenceBetween(prev: { revs: number; time: number }, next: { revs: number; time: number }) {
  const revs = (next.revs - prev.revs + 0x10000) % 0x10000
  const ticks = (next.time - prev.time + 0x10000) % 0x10000
  return ticks === 0 ? undefined : (revs * 1024 * 60) / ticks
}
