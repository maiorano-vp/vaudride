import { describe, expect, it } from 'vitest'
import { pointAt, type CourseProfile } from './course/profile'
import { advance, fly, initialRide, seekRide } from './ride'
import { connectDevice } from './ble/connect'
import { FTMS_SERVICE } from './ble/ftms'
import { createSimTransport } from './ble/sim'

// 1 km straight climb at 8 %, points every 100 m
const course: CourseProfile = {
  slug: 't',
  name: 'T',
  version: 1,
  distance_m: 1000,
  elevation_gain_m: 80,
  points: Array.from({ length: 11 }, (_, i) => ({ d: i * 100, lat: 46, lon: 6, ele: 500 + i * 8, grade: 8 })),
  pois: [],
  attribution: '© swisstopo',
}

describe('pointAt', () => {
  it('interpolates and clamps', () => {
    expect(pointAt(course, 150).ele).toBeCloseTo(512)
    expect(pointAt(course, -5).d).toBe(0)
    expect(pointAt(course, 5000).d).toBe(1000)
  })
})

describe('advance', () => {
  it('climbs, accumulates elevation and finishes at the end', () => {
    let s = initialRide(course)
    for (let i = 0; i < 20_000 && !s.finished; i++) s = advance(s, 250, course, 0.25)
    expect(s.finished).toBe(true)
    expect(s.distanceM).toBe(1000)
    expect(s.elevationGainM).toBeCloseTo(80, 0)
    // 250 W at 8 % ≈ 12–14 km/h → ~4.5–5.5 min
    expect(s.elapsedS).toBeGreaterThan(240)
    expect(s.elapsedS).toBeLessThan(360)
  })

  it('does not move without power on a climb', () => {
    const s = advance(initialRide(course), 0, course, 0.25)
    expect(s.distanceM).toBeLessThan(0.1)
  })
})

describe('fly', () => {
  it('covers the course at the chosen speed whatever the climb, and finishes', () => {
    let s = initialRide(course)
    for (let i = 0; i < 1000 && !s.finished; i++) s = fly(s, 10, course, 0.25)
    expect(s.finished).toBe(true)
    expect(s.distanceM).toBe(1000)
    expect(s.elapsedS).toBeCloseTo(100, 0) // 1 km at 10 m/s
    expect(s.elevationGainM).toBeCloseTo(80, 0)
  })

  it('does not move once finished', () => {
    const done = { ...initialRide(course), distanceM: 1000, finished: true }
    expect(fly(done, 10, course, 0.25)).toBe(done)
  })
})

describe('connectDevice', () => {
  it('connects through the simulator and runs setup', async () => {
    const states: string[] = []
    let setups = 0
    const { device, stop } = await connectDevice(createSimTransport(), FTMS_SERVICE, async () => void setups++, (s) => states.push(s))
    expect(device.name).toBe('Sim Trainer')
    expect(setups).toBe(1)
    expect(states).toEqual(['connected'])
    stop()
  })
})

describe('seekRide', () => {
  it('moves the rider, keeps the clock and takes the grade of the new point', () => {
    const start = { ...initialRide(course), speedMs: 6, elapsedS: 40, elevationGainM: 12 }
    const there = seekRide(start, { ...course, points: course.points.map((p) => ({ ...p, grade: p.d >= 500 ? 3 : 8 })) }, 600)
    expect(there).toMatchObject({ distanceM: 600, gradePct: 3, speedMs: 6, elapsedS: 40, elevationGainM: 12, finished: false })
  })

  it('clamps to the course and clears a finish', () => {
    const done = { ...initialRide(course), distanceM: 1000, finished: true }
    expect(seekRide(done, course, -50).distanceM).toBe(0)
    expect(seekRide(done, course, 50).finished).toBe(false)
    expect(seekRide(done, course, 5000).distanceM).toBe(1000)
  })
})
