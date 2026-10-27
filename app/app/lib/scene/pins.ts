/** A place to mark in the 3D scene, where it really is (not where the road passes). */
export interface ScenePin {
  id: string
  /** Where along the course it is, to know when it comes up. */
  distance_m: number
  lat: number
  lon: number
  /** Its name, already in the language of the interface. */
  title: string
  category: string | null
}

/** A pin shows this far before the rider reaches the place, and until they are this far past it. */
export const PIN_AHEAD_M = 800
export const PIN_BEHIND_M = 80
/** The name is only written over the pin from this close. */
export const PIN_LABEL_RANGE_M = 400

/** The places whose pin should be standing now, the nearest along the road first, no more than `max`. */
export function visiblePins<T extends { distance_m: number }>(pins: T[], distanceM: number, max: number): T[] {
  return pins
    .filter((p) => p.distance_m - distanceM <= PIN_AHEAD_M && p.distance_m - distanceM >= -PIN_BEHIND_M)
    .sort((a, b) => Math.abs(a.distance_m - distanceM) - Math.abs(b.distance_m - distanceM))
    .slice(0, Math.max(0, max))
}
