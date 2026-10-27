import { describe, expect, it } from 'vitest'
import { distanceAt, recordGhostDistance, speedAt, timeAtDistance, type GhostSamples } from './ghost'

// 10 m/s for 10 s, a stop of 5 s, then 5 m/s for 10 s
const samples: GhostSamples = [
  [0, 0],
  [10, 100],
  [15, 100],
  [25, 150],
]

describe('distanceAt', () => {
  it('interpolates between samples', () => {
    expect(distanceAt(samples, 5)).toBe(50)
    expect(distanceAt(samples, 12)).toBe(100)
    expect(distanceAt(samples, 20)).toBe(125)
  })

  it('stays at the ends before the start and after the finish', () => {
    expect(distanceAt(samples, -3)).toBe(0)
    expect(distanceAt(samples, 99)).toBe(150)
    expect(distanceAt([], 5)).toBe(0)
  })
})

describe('timeAtDistance', () => {
  it('is the inverse of distanceAt on the moving parts', () => {
    expect(timeAtDistance(samples, 50)).toBe(5)
    expect(timeAtDistance(samples, 125)).toBe(20)
  })

  it('gives the first time a stopped rider reached the point, and none past the end', () => {
    expect(timeAtDistance(samples, 100)).toBe(10)
    expect(timeAtDistance(samples, 151)).toBeUndefined()
  })
})

it('gives a speed for the animation, zero when stopped', () => {
  expect(speedAt(samples, 4)).toBe(10)
  expect(speedAt(samples, 12)).toBe(0)
  expect(speedAt(samples, 100)).toBe(0)
})

describe('recordGhostDistance', () => {
  const segment = { start_m: 1000, end_m: 2000 }
  const record: GhostSamples = [
    [0, 1000],
    [100, 2000],
  ]

  it('waits on the start line, rides on the rider’s segment clock, then waits on the finish line', () => {
    expect(recordGhostDistance(record, segment, 900, undefined)).toBe(1000)
    expect(recordGhostDistance(record, segment, 1200, 20)).toBe(1200)
    expect(recordGhostDistance(record, segment, 2010, undefined)).toBe(2000)
  })

  it('goes away once the rider is well past the finish', () => {
    expect(recordGhostDistance(record, segment, 2100, undefined)).toBeUndefined()
  })
})
