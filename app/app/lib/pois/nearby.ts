export interface Poi {
  id: string
  distance_m: number
  title: Record<string, string>
  description: Record<string, string> | null
  url: string | null
  category: string | null
  address?: string | null
  lat?: number
  lon?: number
  /** The cover image (scaled), and the credit to show with it. */
  image_url?: string | null
  image_credit?: string | null
  /** "osm" for places imported from OpenStreetMap, which must be credited. */
  source: string
}

/** A point is shown this far before the rider reaches it, and until they are this far past it. */
export const POI_LEAD_M = 300
export const POI_LINGER_M = 60

/** Categories that belong to the sheet (where to sleep, shops, activities), not to the road: no pop-up while riding. */
export const SHEET_ONLY_CATEGORIES = ['lodging', 'shop', 'service', 'activity']

/** Where the places of a course go on its sheet. */
export function sheetSection(poi: Poi): 'lodging' | 'services' | 'discover' {
  if (poi.category === 'lodging') return 'lodging'
  if (poi.category && ['water', 'bike', 'station', 'shop', 'service'].includes(poi.category)) return 'services'
  return 'discover'
}

/** The point of interest to show now: the next one within reach (or the one just passed), the nearest first. */
export function nearbyPoi(distanceM: number, pois: Poi[]): { poi: Poi; aheadM: number } | undefined {
  const reachable = pois
    .map((poi) => ({ poi, aheadM: poi.distance_m - distanceM }))
    .filter(({ aheadM }) => aheadM <= POI_LEAD_M && aheadM >= -POI_LINGER_M)
  return reachable.sort((a, b) => Math.abs(a.aheadM) - Math.abs(b.aheadM))[0]
}

export const poiTitle = (poi: Poi, locale: string) => poi.title[locale] || poi.title.fr || ''
export const poiDescription = (poi: Poi, locale: string) => poi.description?.[locale] || poi.description?.fr || ''

/** How many places there are per category, the biggest first: what the sheet offers as filters. */
export function categoryCounts(pois: Poi[]): Array<{ category: string; count: number }> {
  const counts = new Map<string, number>()
  for (const poi of pois) counts.set(poi.category ?? 'other', (counts.get(poi.category ?? 'other') ?? 0) + 1)
  return [...counts].map(([category, count]) => ({ category, count })).sort((a, b) => b.count - a.count || a.category.localeCompare(b.category))
}

/** The places in any of the selected categories; all of them when nothing is selected. */
export const filterPois = (pois: Poi[], selected: string[]): Poi[] =>
  selected.length ? pois.filter((poi) => selected.includes(poi.category ?? 'other')) : pois
