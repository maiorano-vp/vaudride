import { describe, expect, it } from 'vitest'
import { DEFAULT_PARAMS } from '../physics/speed'
import { DEFAULT_SETTINGS, effectiveGrade, newest, POSITIONS, sanitize, toParams } from './settings'

describe('sanitize', () => {
  it('gives the defaults for anything unusable', () => {
    expect(sanitize(null)).toEqual(DEFAULT_SETTINGS)
    expect(sanitize('x')).toEqual(DEFAULT_SETTINGS)
    expect(sanitize({ weightKg: 'abc', ftp: null, difficultyPct: '' })).toEqual(DEFAULT_SETTINGS)
  })

  it('keeps each value inside its limits and the difficulty on a 5 % step', () => {
    const s = sanitize({ weightKg: 5, bikeKg: 99, ftp: 5000, difficultyPct: 12 })
    expect(s).toMatchObject({ weightKg: 30, bikeKg: 25, ftp: 600, difficultyPct: 30 })
    expect(sanitize({ difficultyPct: 62 }).difficultyPct).toBe(60)
    expect(sanitize({ difficultyPct: 250 }).difficultyPct).toBe(100)
  })

  it('takes the drag area from the position, except for a custom one', () => {
    expect(sanitize({ position: 'aero', cda: 0.5 }).cda).toBe(POSITIONS.aero)
    expect(sanitize({ position: 'custom', cda: 0.41 }).cda).toBe(0.41)
    expect(sanitize({ position: 'custom', cda: 9 }).cda).toBe(0.6)
    expect(sanitize({ position: 'unknown' }).position).toBe('drops')
  })
})

describe('physics', () => {
  it('defaults match the physics defaults', () => {
    expect(toParams(DEFAULT_SETTINGS)).toEqual(DEFAULT_PARAMS)
  })

  it('carries weight and drag area into the parameters', () => {
    expect(toParams(sanitize({ weightKg: 62, bikeKg: 8, position: 'aero' }))).toMatchObject({ riderKg: 62, bikeKg: 8, cda: 0.28 })
  })

  it('scales the grade by the difficulty, descents too', () => {
    expect(effectiveGrade(8, 50)).toBe(4)
    expect(effectiveGrade(-10, 50)).toBe(-5)
    expect(effectiveGrade(8, 100)).toBe(8)
  })
})

describe('newest', () => {
  const old = { at: '2026-10-01T10:00:00Z' }
  const recent = { at: '2026-10-02T10:00:00Z' }

  it('prefers the copy changed last', () => {
    expect(newest(old, recent)).toBe('remote')
    expect(newest(recent, old)).toBe('local')
  })

  it('takes whichever exists, or nothing', () => {
    expect(newest(undefined, recent)).toBe('remote')
    expect(newest(old, {})).toBe('local')
    expect(newest(undefined, undefined)).toBe('none')
  })

  it('lets the account win a tie, so devices converge', () => {
    expect(newest(old, old)).toBe('remote')
  })
})
