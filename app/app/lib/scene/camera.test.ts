import { describe, expect, it } from 'vitest'
import { bearingRad, distanceAfter, interpolateAt, lerpAngle } from './camera'

const deg = (r: number) => (r * 180) / Math.PI

describe('bearingRad', () => {
  it('north is 0, east is 90°, south is 180°', () => {
    expect(deg(bearingRad(46, 6, 46.01, 6))).toBeCloseTo(0, 1)
    expect(deg(bearingRad(46, 6, 46, 6.01))).toBeCloseTo(90, 0)
    expect(Math.abs(deg(bearingRad(46, 6, 45.99, 6)))).toBeCloseTo(180, 1)
  })
})

describe('lerpAngle', () => {
  it('takes the short way round across north', () => {
    const a = (350 * Math.PI) / 180
    const b = (10 * Math.PI) / 180
    expect(Math.cos(lerpAngle(a, b, 0.5))).toBeCloseTo(1) // 0°
  })
  it('reaches the target at t=1', () => {
    expect(lerpAngle(0.2, 1.1, 1)).toBeCloseTo(1.1)
  })
})

describe('distanceAfter', () => {
  it('advances with speed, capped by elapsed 0.5 s and course end', () => {
    expect(distanceAfter(100, 10, 0.25, 1000)).toBeCloseTo(102.5)
    expect(distanceAfter(100, 10, 5, 1000)).toBeCloseTo(105)
    expect(distanceAfter(999, 10, 0.5, 1000)).toBe(1000)
  })
})

describe('interpolateAt', () => {
  it('interpolates and clamps', () => {
    const xs = [0, 10, 20]
    const ys = [100, 110, 150]
    expect(interpolateAt(xs, ys, 5)).toBe(105)
    expect(interpolateAt(xs, ys, 15)).toBe(130)
    expect(interpolateAt(xs, ys, -3)).toBe(100)
    expect(interpolateAt(xs, ys, 99)).toBe(150)
  })
})
