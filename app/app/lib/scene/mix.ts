import { pointAt, type CourseProfile } from '../course/profile'

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

/**
 * Rectangles (counter-clockwise, in degrees) laid along the road between `fromD` and `toD`, `halfWidthM` each side,
 * one per `stepM` of road. Used to clear the trees over the road; separate rectangles overlap into a lane without the
 * self-intersections one long ribbon has in a hairpin.
 */
export function corridorQuads(
  course: CourseProfile,
  fromD: number,
  toD: number,
  halfWidthM: number,
  stepM: number,
): Array<Array<{ lat: number; lon: number }>> {
  const start = Math.max(fromD, 0)
  const end = Math.min(toD, course.distance_m)
  const overlapM = 1 // quads reach a metre into the next one: no crack between them
  const quads: Array<Array<{ lat: number; lon: number }>> = []

  for (let d = start; d < end; d += stepM) {
    const a = pointAt(course, d)
    const b = pointAt(course, Math.min(d + stepM, end))
    const cos = Math.cos((a.lat * Math.PI) / 180)
    // metres east / north of a
    const dx = ((b.lon - a.lon) * Math.PI) / 180 * cos * EARTH_RADIUS_M
    const dy = ((b.lat - a.lat) * Math.PI) / 180 * EARTH_RADIUS_M
    const length = Math.hypot(dx, dy)
    if (length < 0.5) continue
    const fx = dx / length
    const fy = dy / length
    const at = (along: number, side: number) => ({
      lat: a.lat + ((fy * along + fx * side) / EARTH_RADIUS_M) * (180 / Math.PI),
      lon: a.lon + ((fx * along - fy * side) / (EARTH_RADIUS_M * cos)) * (180 / Math.PI),
    })
    // right side forward, left side back: counter-clockwise
    quads.push([at(0, -halfWidthM), at(length + overlapM, -halfWidthM), at(length + overlapM, halfWidthM), at(0, halfWidthM)])
  }
  return quads
}

/** Keeps the last `size` values. */
export const pushWindow = (values: number[], value: number, size: number): number[] => [...values, value].slice(-size)

export const clamp = (v: number, limit: number) => Math.max(-limit, Math.min(limit, v))
