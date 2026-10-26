export interface Poi {
  id: string
  distance_m: number
  title: Record<string, string>
  description: Record<string, string> | null
  url: string | null
  category: string | null
  /** "osm" for places imported from OpenStreetMap, which must be credited. */
  source: string
}

/** A point is shown this far before the rider reaches it, and until they are this far past it. */
export const POI_LEAD_M = 300
export const POI_LINGER_M = 60

/** The point of interest to show now: the next one within reach (or the one just passed), the nearest first. */
export function nearbyPoi(distanceM: number, pois: Poi[]): { poi: Poi; aheadM: number } | undefined {
  const reachable = pois
    .map((poi) => ({ poi, aheadM: poi.distance_m - distanceM }))
    .filter(({ aheadM }) => aheadM <= POI_LEAD_M && aheadM >= -POI_LINGER_M)
  return reachable.sort((a, b) => Math.abs(a.aheadM) - Math.abs(b.aheadM))[0]
}

export const poiTitle = (poi: Poi, locale: string) => poi.title[locale] || poi.title.fr || ''
export const poiDescription = (poi: Poi, locale: string) => poi.description?.[locale] || poi.description?.fr || ''
