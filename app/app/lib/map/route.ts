export interface RoutePoint {
  d: number
  lat: number
  lon: number
}

/** Fewer points for the line on the map: one every `stepM` metres is plenty (the profile has one every 5 m). */
export function simplifyRoute(
  points: RoutePoint[],
  stepM = 20,
): Array<[number, number]> {
  const out: Array<[number, number]> = []
  let next = 0
  for (const p of points) {
    if (p.d >= next) {
      out.push([p.lat, p.lon])
      next = p.d + stepM
    }
  }
  const last = points[points.length - 1]
  if (
    last &&
    (out.length === 0 ||
      out[out.length - 1]![0] !== last.lat ||
      out[out.length - 1]![1] !== last.lon)
  )
    out.push([last.lat, last.lon])
  return out
}

/** The stretch of the route between two distances, for highlighting a segment. */
export function stretch(
  points: RoutePoint[],
  fromM: number,
  toM: number,
  stepM = 20,
): Array<[number, number]> {
  return simplifyRoute(
    points.filter((p) => p.d >= fromM && p.d <= toM),
    stepM,
  )
}

/** The colour of a place's marker, by category. */
export const CATEGORY_COLORS: Record<string, string> = {
  viewpoint: '#2f729f',
  culture: '#7a1f3d',
  history: '#8c5a2b',
  nature: '#2e8b57',
  water: '#1e90ff',
  wine: '#8e2c48',
  food: '#e8a317',
  lodging: '#5b4b8a',
  bike: '#555555',
  station: '#222222',
  shop: '#c76b29',
  activity: '#d1495b',
  service: '#6c757d',
  agritourism: '#7a8f2b',
  bakery: '#b8741a',
  grocery: '#3f8f6b',
  other: '#6c757d',
}

export const categoryColor = (category: string | null | undefined) =>
  CATEGORY_COLORS[category ?? 'other'] ?? CATEGORY_COLORS.other!
