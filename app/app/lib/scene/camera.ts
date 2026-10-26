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
