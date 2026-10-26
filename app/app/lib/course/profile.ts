export interface CoursePoint {
  d: number
  lat: number
  lon: number
  ele: number
  grade: number
}

export interface CoursePoi {
  d: number
  id: string
  title: { fr: string; de: string; en: string }
}

export interface CourseProfile {
  slug: string
  name: string
  version: number
  distance_m: number
  elevation_gain_m: number
  points: CoursePoint[]
  pois: CoursePoi[]
  attribution: string
}

/** Point at distance `d` (m), linearly interpolated; clamped to the course ends. Points are sorted by `d`. */
export function pointAt(course: CourseProfile, d: number): CoursePoint {
  const pts = course.points
  const first = pts[0]!
  const last = pts[pts.length - 1]!
  if (d <= first.d) return first
  if (d >= last.d) return last
  let lo = 0
  let hi = pts.length - 1
  while (hi - lo > 1) {
    const mid = (lo + hi) >> 1
    if (pts[mid]!.d <= d) lo = mid
    else hi = mid
  }
  const a = pts[lo]!
  const b = pts[hi]!
  const t = (d - a.d) / (b.d - a.d)
  const mix = (x: number, y: number) => x + (y - x) * t
  return { d, lat: mix(a.lat, b.lat), lon: mix(a.lon, b.lon), ele: mix(a.ele, b.ele), grade: mix(a.grade, b.grade) }
}
