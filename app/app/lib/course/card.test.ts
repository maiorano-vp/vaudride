import { describe, expect, it } from 'vitest'
import { creditText, filterByKind, kindCounts, kindLabelKey, kmText } from './card'

const courses = [
  { slug: 'a', kind: 'velo' },
  { slug: 'b', kind: 'mtb' },
  { slug: 'c', kind: 'velo' },
  { slug: 'd', kind: null },
  { slug: 'e' },
]

describe('kindLabelKey', () => {
  it('points at the badge text of a known kind, and none for the others', () => {
    expect(kindLabelKey('mtb')).toBe('course.kind.mtb')
    expect(kindLabelKey('scooter')).toBe('course.kind.scooter')
    expect(kindLabelKey('gondola')).toBeUndefined()
    expect(kindLabelKey(null)).toBeUndefined()
    expect(kindLabelKey(undefined)).toBeUndefined()
  })
})

describe('filterByKind', () => {
  it('keeps the courses of a kind', () => {
    expect(filterByKind(courses, 'velo').map((c) => c.slug)).toEqual(['a', 'c'])
    expect(filterByKind(courses, 'mtb').map((c) => c.slug)).toEqual(['b'])
  })

  it('gives every course for "all" and for a kind that does not exist', () => {
    expect(filterByKind(courses, 'all')).toHaveLength(5)
    expect(filterByKind(courses, 'nonsense')).toHaveLength(5)
  })
})

describe('kindCounts', () => {
  it('counts the kinds that have courses, in the filter order, leaving out the empty ones', () => {
    expect(kindCounts(courses)).toEqual([
      { kind: 'velo', count: 2 },
      { kind: 'mtb', count: 1 },
    ])
    expect(kindCounts([])).toEqual([])
  })
})

describe('creditText', () => {
  it('prints a copyright line with one sign, whatever the source wrote', () => {
    expect(creditText('Raphaël Dupertuis')).toBe('© Raphaël Dupertuis')
    expect(creditText('©Simcast')).toBe('© Simcast')
    expect(creditText('© Vaud Promotion')).toBe('© Vaud Promotion')
    expect(creditText('(c) Someone')).toBe('© Someone')
  })

  it('prints nothing for a blank or missing credit', () => {
    expect(creditText('   ')).toBeUndefined()
    expect(creditText(null)).toBeUndefined()
    expect(creditText(undefined)).toBeUndefined()
  })
})

it('writes the distance in kilometres with one decimal', () => {
  expect(kmText(12_540)).toBe('12.5')
})
