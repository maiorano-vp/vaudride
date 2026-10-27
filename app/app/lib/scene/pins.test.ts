import { describe, expect, it } from 'vitest'
import { PIN_AHEAD_M, PIN_BEHIND_M, visiblePins } from './pins'

const at = (...distances: number[]) => distances.map((distance_m, i) => ({ id: `p${i}`, distance_m }))

describe('visiblePins', () => {
  it('shows a place from 800 m before it until 80 m after', () => {
    const pins = at(1000)
    expect(visiblePins(pins, 1000 - PIN_AHEAD_M - 1, 5)).toEqual([])
    expect(visiblePins(pins, 1000 - PIN_AHEAD_M, 5)).toHaveLength(1)
    expect(visiblePins(pins, 1000 + PIN_BEHIND_M, 5)).toHaveLength(1)
    expect(visiblePins(pins, 1000 + PIN_BEHIND_M + 1, 5)).toEqual([])
  })

  it('puts the nearest along the road first, behind or ahead', () => {
    const shown = visiblePins(at(1400, 1050, 940), 1000, 5)
    expect(shown.map((p) => p.distance_m)).toEqual([1050, 940, 1400]) // 50 m, 60 m behind, 400 m
  })

  it('keeps no more than the limit, dropping the farthest', () => {
    const shown = visiblePins(at(1100, 1200, 1300, 1400), 1000, 2)
    expect(shown.map((p) => p.distance_m)).toEqual([1100, 1200])
  })

  it('shows nothing for no place or a zero limit, and leaves the list as it was', () => {
    const pins = at(1100, 1050)
    expect(visiblePins([], 0, 5)).toEqual([])
    expect(visiblePins(pins, 1000, 0)).toEqual([])
    expect(pins.map((p) => p.distance_m)).toEqual([1100, 1050])
  })
})
