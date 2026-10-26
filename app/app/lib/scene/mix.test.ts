import { describe, expect, it } from 'vitest'
import { clamp, distanceM, pushWindow, ringAround } from './mix'

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
