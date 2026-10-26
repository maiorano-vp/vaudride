import { describe, expect, it } from 'vitest'
import { nearbyPoi, poiDescription, poiTitle, type Poi } from './nearby'

const poi = (id: string, distance_m: number): Poi => ({
  id,
  distance_m,
  title: { fr: `Lieu ${id}`, en: `Place ${id}` },
  description: { fr: 'Une description' },
  url: null,
  category: 'viewpoint',
  source: 'manual',
})

describe('nearbyPoi', () => {
  const pois = [poi('a', 1000), poi('b', 1100), poi('c', 5000)]

  it('shows nothing when no point is within reach', () => {
    expect(nearbyPoi(0, pois)).toBeUndefined()
    expect(nearbyPoi(3000, pois)).toBeUndefined()
  })

  it('announces the next point 300 m ahead', () => {
    expect(nearbyPoi(699, pois)).toBeUndefined()
    expect(nearbyPoi(700, pois)?.poi.id).toBe('a')
    expect(nearbyPoi(700, pois)?.aheadM).toBe(300)
  })

  it('keeps the point shown for 60 m after passing it', () => {
    expect(nearbyPoi(1055, [poi('a', 1000)])?.aheadM).toBe(-55)
    expect(nearbyPoi(1061, [poi('a', 1000)])).toBeUndefined()
  })

  it('picks the nearest when several are within reach', () => {
    expect(nearbyPoi(1090, pois)?.poi.id).toBe('b')
    expect(nearbyPoi(950, pois)?.poi.id).toBe('a')
  })

  it('names points in the requested language with a French fallback', () => {
    expect(poiTitle(poi('a', 0), 'en')).toBe('Place a')
    expect(poiTitle(poi('a', 0), 'de')).toBe('Lieu a')
    expect(poiDescription(poi('a', 0), 'de')).toBe('Une description')
  })
})

describe('sheetSection', () => {
  const of = (category: string | null) => ({ ...poi('x', 0), category })

  it('puts lodging, services and the rest in their own sections', async () => {
    const { sheetSection, SHEET_ONLY_CATEGORIES } = await import('./nearby')
    expect(sheetSection(of('lodging'))).toBe('lodging')
    expect(['water', 'bike', 'station', 'shop', 'service'].map((c) => sheetSection(of(c)))).toEqual(Array(5).fill('services'))
    expect([null, 'wine', 'viewpoint', 'activity'].map((c) => sheetSection(of(c)))).toEqual(Array(4).fill('discover'))
    expect(SHEET_ONLY_CATEGORIES).toEqual(expect.arrayContaining(['lodging', 'shop', 'service', 'activity']))
    expect(SHEET_ONLY_CATEGORIES).not.toContain('viewpoint')
  })
})
