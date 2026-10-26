import { describe, expect, it } from 'vitest'
import { RideRecorder } from './recorder'

const reading = (elapsedS: number, extra = {}) => ({ elapsedS, distanceM: elapsedS * 8, speedMs: 8, powerW: 200, ...extra })

describe('RideRecorder', () => {
  it('keeps one sample per second of ride clock', () => {
    const r = new RideRecorder()
    for (let t = 0; t <= 3; t += 0.25) r.push(reading(t)) // 4 ticks per second
    expect(r.samples.map((s) => s.t)).toEqual([0, 1, 2, 3])
  })

  it('records nothing while the clock is stopped (pause)', () => {
    const r = new RideRecorder()
    r.push(reading(5))
    for (let i = 0; i < 40; i++) r.push(reading(5.2))
    expect(r.samples).toHaveLength(1)
  })

  it('shapes a sample the way the API wants it', () => {
    const r = new RideRecorder()
    r.push(reading(1.4, { distanceM: 12.34, speedMs: 8.33, powerW: 201.6, cadenceRpm: 88.4, heartRate: 141 }))
    expect(r.samples[0]).toEqual({ t: 1, d: 12.3, p: 202, c: 88, h: 141, s: 30 })
  })

  it('uses null for what no sensor reported', () => {
    const r = new RideRecorder()
    r.push(reading(0))
    expect(r.samples[0]).toMatchObject({ c: null, h: null })
  })

  it('stores when the ride started and forgets it on reset', () => {
    const r = new RideRecorder()
    const start = new Date('2026-10-26T10:00:00Z')
    r.push(reading(0), start)
    r.push(reading(1), new Date('2026-10-26T10:00:01Z'))
    expect(r.startedAt).toEqual(start)
    r.reset()
    expect(r.samples).toEqual([])
    expect(r.startedAt).toBeUndefined()
  })

  it('summarises the ride', () => {
    const r = new RideRecorder()
    ;[100, 200, 300].forEach((p, i) => r.push(reading(i, { powerW: p, heartRate: 120 + i * 10 })))
    expect(r.summary()).toEqual({ durationS: 2, distanceM: 16, avgPowerW: 200, maxPowerW: 300, avgHeartRate: 130 })
    expect(r.recordable).toBe(true)
  })

  it('needs two samples to be worth saving', () => {
    const r = new RideRecorder()
    expect(r.recordable).toBe(false)
    r.push(reading(0))
    expect(r.recordable).toBe(false)
    expect(r.summary()).toMatchObject({ durationS: 0, avgHeartRate: null })
  })
})
