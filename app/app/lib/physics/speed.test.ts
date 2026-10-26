import { describe, expect, it } from 'vitest'
import { solveSpeed, stepSpeed } from './speed'

const kmh = (ms: number) => ms * 3.6

describe('solveSpeed', () => {
  it('flat, 200 W is about 32–36 km/h', () => {
    const v = kmh(solveSpeed(200, 0))
    expect(v).toBeGreaterThan(30)
    expect(v).toBeLessThan(38)
  })

  it('8 % climb, 250 W is about 12–14 km/h', () => {
    const v = kmh(solveSpeed(250, 8))
    expect(v).toBeGreaterThan(11)
    expect(v).toBeLessThan(15)
  })

  it('descent at 0 W still rolls (gravity)', () => {
    expect(kmh(solveSpeed(0, -6))).toBeGreaterThan(30)
  })

  it('zero power on flat gives zero speed', () => {
    expect(solveSpeed(0, 0)).toBeCloseTo(0, 3)
  })

  it('zero power on a climb gives zero speed', () => {
    expect(solveSpeed(0, 8)).toBeCloseTo(0, 3)
  })

  it('more power is never slower', () => {
    expect(solveSpeed(300, 5)).toBeGreaterThan(solveSpeed(200, 5))
  })
})

describe('stepSpeed', () => {
  it('moves towards the target without overshooting', () => {
    const target = solveSpeed(200, 0)
    const next = stepSpeed(0, 200, 0, 0.25)
    expect(next).toBeGreaterThan(0)
    expect(next).toBeLessThan(target)
  })
})
