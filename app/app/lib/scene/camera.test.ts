import { describe, expect, it } from 'vitest'
import { bearingRad, destination, distanceAfter, interpolateAt, lerpAngle, openness } from './camera'

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

describe('destination', () => {
  it('goes north and east by the given distance', () => {
    const north = destination(46, 6, 0, 1000)
    expect(north.lat).toBeCloseTo(46.008993, 5)
    expect(north.lon).toBeCloseTo(6, 6)
    const east = destination(46, 6, Math.PI / 2, 1000)
    expect(east.lat).toBeCloseTo(46, 6)
    expect(east.lon).toBeCloseTo(6.012946, 5)
  })
})

describe('openness', () => {
  const at = (heightM: number) => [500, 1200, 2500].map((distM) => ({ distM, heightM }))

  it('is 0 on the flat, when walled in, and with no samples', () => {
    expect(openness(1000, at(1000))).toBe(0)
    expect(openness(1000, at(1400))).toBe(0)
    expect(openness(1000, [])).toBe(0)
  })
  it('reaches 1 over a deep valley and is capped there', () => {
    expect(openness(1500, at(0))).toBe(1)
  })
  it('grows with the drop and only counts terrain below the rider', () => {
    const half = openness(1000, [{ distM: 1000, heightM: 925 }]) // 7.5 % fall = half open
    expect(half).toBeCloseTo(0.5)
    expect(openness(1000, [{ distM: 1000, heightM: 925 }, { distM: 1000, heightM: 1500 }])).toBeCloseTo(0.25)
  })
})
