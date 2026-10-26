import { describe, expect, it } from 'vitest'
import { clock, profilePath } from './card'

const points = [
  { d: 0, ele: 400 },
  { d: 500, ele: 500 },
  { d: 1000, ele: 450 },
]
const box = { x: 100, y: 50, w: 1000, h: 200 }

describe('profilePath', () => {
  it('spreads the course over the box, the low point at the bottom and the high point at the top', () => {
    expect(profilePath(points, 1000, box)).toEqual([
      [100, 250],
      [600, 50],
      [1100, 150],
    ])
  })

  it('stops at the distance reached', () => {
    expect(profilePath(points, 1000, box, 500)).toHaveLength(2)
    expect(profilePath(points, 1000, box, 0)).toHaveLength(1)
  })

  it('handles an empty or flat profile', () => {
    expect(profilePath([], 1000, box)).toEqual([])
    expect(profilePath([{ d: 0, ele: 400 }, { d: 10, ele: 400 }], 10, box)[1]).toEqual([1100, 250])
  })
})

describe('clock', () => {
  it('formats minutes and hours', () => {
    expect(clock(65)).toBe('01:05')
    expect(clock(3725)).toBe('1:02:05')
  })
})
