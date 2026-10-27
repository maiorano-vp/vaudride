import { describe, expect, it } from 'vitest'
import type { Poi } from '~/lib/pois/nearby'
import { courseUrl, endLinks, monthRuns, monthsLabel, serviceCounts } from './realworld'

describe('endLinks', () => {
  it('asks for public transport from the finish line', () => {
    const { transit } = endLinks({ lat: 46.5, lon: 6.6 })
    expect(transit).toContain('origin=46.5,6.6')
    expect(transit).toContain('travelmode=transit')
    expect(transit).not.toContain('destination')
  })
})

describe('courseUrl', () => {
  it('joins the site and the course, whatever the trailing slash', () => {
    expect(courseUrl('https://vaudride.example', 'lavaux')).toBe('https://vaudride.example/courses/lavaux')
    expect(courseUrl('https://vaudride.example/', 'lavaux')).toBe('https://vaudride.example/courses/lavaux')
  })
})

describe('months', () => {
  it('groups consecutive months and ignores what is not a month', () => {
    expect(monthRuns([9, 5, 6, 7, 7, 11, 0, 13, 2.5])).toEqual([[5, 7], [9, 9], [11, 11]])
    expect(monthRuns([])).toEqual([])
  })

  it('writes them in the visitor’s language', () => {
    expect(monthsLabel([5, 6, 7, 8, 9], 'fr')).toBe('mai – septembre')
    expect(monthsLabel([5, 6, 7, 8, 9], 'en')).toBe('May – September')
    expect(monthsLabel([4, 5, 9], 'de')).toBe('April, Mai, September')
  })
})

describe('serviceCounts', () => {
  it('counts the water points, bike services, stations and lodging', () => {
    const poi = (category: string | null) => ({ id: category ?? 'x', category }) as unknown as Poi
    expect(serviceCounts([poi('water'), poi('water'), poi('bike'), poi('lodging'), poi('wine'), poi(null)])).toEqual({
      water: 2,
      bike: 1,
      station: 0,
      lodging: 1,
    })
  })
})
