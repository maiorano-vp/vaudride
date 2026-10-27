/** Compass bearing in radians from point 1 to point 2 (0 = north, clockwise). */
export function bearingRad(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const p1 = (lat1 * Math.PI) / 180
  const p2 = (lat2 * Math.PI) / 180
  const dl = ((lon2 - lon1) * Math.PI) / 180
  const y = Math.sin(dl) * Math.cos(p2)
  const x = Math.cos(p1) * Math.sin(p2) - Math.sin(p1) * Math.cos(p2) * Math.cos(dl)
  return Math.atan2(y, x)
}

/** Moves angle `a` towards `b` by fraction `t` along the shortest arc. */
export function lerpAngle(a: number, b: number, t: number): number {
  const delta = ((((b - a) % (2 * Math.PI)) + 3 * Math.PI) % (2 * Math.PI)) - Math.PI
  return a + delta * t
}

/** Dead-reckoned distance between 4 Hz physics updates so the camera glides instead of stepping. */
export function distanceAfter(baseM: number, speedMs: number, elapsedS: number, maxM: number): number {
  return Math.min(baseM + speedMs * Math.min(elapsedS, 0.5), maxM)
}

/** Linear interpolation of ys over ascending xs, clamped at both ends. */
export function interpolateAt(xs: number[], ys: number[], x: number): number {
  if (x <= xs[0]!) return ys[0]!
  const last = xs.length - 1
  if (x >= xs[last]!) return ys[last]!
  let lo = 0
  let hi = last
  while (hi - lo > 1) {
    const mid = (lo + hi) >> 1
    if (xs[mid]! <= x) lo = mid
    else hi = mid
  }
  return ys[lo]! + ((ys[hi]! - ys[lo]!) * (x - xs[lo]!)) / (xs[hi]! - xs[lo]!)
}

const EARTH_RADIUS_M = 6_371_000

/** The point `distM` away from (lat, lon) along a compass bearing (radians); fine for a few kilometres. */
export function destination(lat: number, lon: number, bearing: number, distM: number): { lat: number; lon: number } {
  const dLat = ((distM * Math.cos(bearing)) / EARTH_RADIUS_M) * (180 / Math.PI)
  const dLon = ((distM * Math.sin(bearing)) / (EARTH_RADIUS_M * Math.cos((lat * Math.PI) / 180))) * (180 / Math.PI)
  return { lat: lat + dLat, lon: lon + dLon }
}

/** Mean fall of the terrain around the rider that counts as a fully open view (rise over run). */
const OPEN_SLOPE = 0.15

/**
 * How much the landscape opens up below the rider, 0 (flat, or walled in) to 1 (valley or slope falling away).
 * `samples` are terrain heights around the rider, each at its ground distance; only terrain below the rider counts.
 */
export function openness(riderHeightM: number, samples: { distM: number; heightM: number }[]): number {
  if (samples.length === 0) return 0
  const fall = samples.reduce((sum, s) => sum + Math.max((riderHeightM - s.heightM) / s.distM, 0), 0) / samples.length
  return Math.min(fall / OPEN_SLOPE, 1)
}
