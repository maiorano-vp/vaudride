import { describe, expect, it } from 'vitest'
import { categoryColor, simplifyRoute, stretch } from './route'

const line = Array.from({ length: 101 }, (_, i) => ({ d: i * 5, lat: 46.5 + i * 0.00005, lon: 6.6 }))

describe('simplifyRoute', () => {
  it('keeps one point every 20 m and always the last one', () => {
    const simple = simplifyRoute(line)
    expect(simple).toHaveLength(26) // 0, 20, … 480 (25 points) + the end at 500 m
    expect(simple[0]).toEqual([46.5, 6.6])
    expect(simple[simple.length - 1]).toEqual([line[100]!.lat, 6.6])
  })

  it('handles an empty or one-point route', () => {
    expect(simplifyRoute([])).toEqual([])
    expect(simplifyRoute([line[0]!])).toEqual([[46.5, 6.6]])
  })
})

describe('stretch', () => {
  it('returns the part of the route between two distances', () => {
    const part = stretch(line, 100, 200)
    expect(part[0]).toEqual([line[20]!.lat, 6.6])
    expect(part[part.length - 1]).toEqual([line[40]!.lat, 6.6])
  })

  it('is empty outside the route', () => {
    expect(stretch(line, 900, 1000)).toEqual([])
  })
})

describe('categoryColor', () => {
  it('has a colour for each category, and a neutral one for unknown or missing', () => {
    expect(categoryColor('wine')).toBe('#8e2c48')
    expect(categoryColor(null)).toBe(categoryColor('other'))
    expect(categoryColor('martian')).toBe(categoryColor('other'))
  })
})
