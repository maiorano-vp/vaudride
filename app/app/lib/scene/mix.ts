/** Helpers for the mixed 3D source: swisstopo around the rider, Google photogrammetry further away. */

const EARTH_RADIUS_M = 6_371_000

/** A regular polygon (counter-clockwise) of `radiusM` around a point, in degrees. */
export function ringAround(lat: number, lon: number, radiusM: number, sides = 24): Array<{ lat: number; lon: number }> {
  const dLat = (radiusM / EARTH_RADIUS_M) * (180 / Math.PI)
  const dLon = dLat / Math.cos((lat * Math.PI) / 180)
  return Array.from({ length: sides }, (_, i) => {
    const a = (2 * Math.PI * i) / sides
    return { lat: lat + dLat * Math.sin(a), lon: lon + dLon * Math.cos(a) }
  })
}

/** Ground distance between two nearby points (m); fine for a few kilometres. */
export function distanceM(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const x = ((lon2 - lon1) * Math.PI) / 180 * Math.cos((((lat1 + lat2) / 2) * Math.PI) / 180)
  const y = ((lat2 - lat1) * Math.PI) / 180
  return Math.hypot(x, y) * EARTH_RADIUS_M
}

/** Keeps the last `size` values. */
export const pushWindow = (values: number[], value: number, size: number): number[] => [...values, value].slice(-size)

export const clamp = (v: number, limit: number) => Math.max(-limit, Math.min(limit, v))
