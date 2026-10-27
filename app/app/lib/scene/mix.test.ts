import { describe, expect, it } from 'vitest'
import type { CourseProfile } from '../course/profile'
import { clamp, corridorQuads, distanceM, pushWindow, ringAround } from './mix'

describe('mixed scene helpers', () => {
  it('draws a ring of the requested radius around a point', () => {
    const ring = ringAround(46.5, 6.7, 180)
    expect(ring).toHaveLength(24)
    for (const p of ring) expect(distanceM(46.5, 6.7, p.lat, p.lon)).toBeGreaterThan(178)
    for (const p of ring) expect(distanceM(46.5, 6.7, p.lat, p.lon)).toBeLessThan(182)
  })

  it('winds counter-clockwise', () => {
    const r = ringAround(46.5, 6.7, 100, 8)
    const area = r.reduce((sum, p, i) => {
      const q = r[(i + 1) % r.length]!
      return sum + (p.lon * q.lat - q.lon * p.lat)
    }, 0)
    expect(area).toBeGreaterThan(0)
  })

  it('measures short distances', () => {
    // 0.001° of latitude is about 111 m
    expect(distanceM(46.5, 6.7, 46.501, 6.7)).toBeCloseTo(111.2, 0)
  })

  it('keeps a sliding window and clamps', () => {
    expect(pushWindow([1, 2, 3], 4, 3)).toEqual([2, 3, 4])
    expect(clamp(300, 150)).toBe(150)
    expect(clamp(-300, 150)).toBe(-150)
    expect(clamp(12, 150)).toBe(12)
  })
})

/** A straight road going east from (46.5, 6.7), a point every 10 m. */
function eastRoad(lengthM: number): CourseProfile {
  const lonPerM = 1 / (111_195 * Math.cos((46.5 * Math.PI) / 180))
  return {
    slug: 't', name: 't', version: 1, distance_m: lengthM, elevation_gain_m: 0, pois: [], attribution: '',
    points: Array.from({ length: lengthM / 10 + 1 }, (_, i) => ({ d: i * 10, lat: 46.5, lon: 6.7 + i * 10 * lonPerM, ele: 500, grade: 0 })),
  }
}

describe('corridorQuads', () => {
  it('lays one quad per step, half a width each side of the road', () => {
    const quads = corridorQuads(eastRoad(100), 0, 100, 7, 20)
    expect(quads).toHaveLength(5)
    for (const q of quads) {
      expect(q).toHaveLength(4)
      // corners are 7 m north or south of the road
      for (const p of q) expect(distanceM(46.5, p.lon, p.lat, p.lon)).toBeCloseTo(7, 0)
    }
  })

  it('winds counter-clockwise', () => {
    const [q] = corridorQuads(eastRoad(100), 0, 20, 7, 20)
    const area = q!.reduce((sum, p, i) => {
      const n = q![(i + 1) % q!.length]!
      return sum + (p.lon * n.lat - n.lon * p.lat)
    }, 0)
    expect(area).toBeGreaterThan(0)
  })

  it('stays within the course', () => {
    const course = eastRoad(100)
    const quads = corridorQuads(course, -50, 400, 7, 20)
    expect(quads).toHaveLength(5)
    expect(corridorQuads(course, 200, 300, 7, 20)).toEqual([])
  })
})
