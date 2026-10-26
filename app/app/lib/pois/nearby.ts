export interface Poi {
  id: string
  distance_m: number
  title: Record<string, string>
  description: Record<string, string> | null
  url: string | null
  category: string | null
  address?: string | null
  /** "osm" for places imported from OpenStreetMap, which must be credited. */
  source: string
}

/** A point is shown this far before the rider reaches it, and until they are this far past it. */
export const POI_LEAD_M = 300
export const POI_LINGER_M = 60

/** Categories that belong to the sheet (where to sleep, what is nearby), not to the road: no pop-up while riding. */
export const SHEET_ONLY_CATEGORIES = ['lodging']

/** Where the places of a course go on its sheet. */
export function sheetSection(poi: Poi): 'lodging' | 'services' | 'discover' {
  if (poi.category === 'lodging') return 'lodging'
  if (poi.category && ['water', 'bike', 'station'].includes(poi.category)) return 'services'
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
