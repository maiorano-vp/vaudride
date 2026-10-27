export interface PassportCourse {
  slug: string
}

export interface PassportRide {
  id: string
  /** The day, as YYYY-MM-DD. */
  ridden_on: string
  course: { slug: string }
}

/** How far the rider has got through the courses: which ones they rode for real, and the days they did. */
export function passportProgress<C extends PassportCourse>(
  courses: C[],
  rides: PassportRide[],
) {
  const days = new Map<string, string[]>()
  for (const ride of rides)
    days.set(
      ride.course.slug,
      [...(days.get(ride.course.slug) ?? []), ride.ridden_on].sort().reverse(),
    )

  const done = courses.filter((c) => days.has(c.slug))
  return {
    done: done.map((course) => ({ course, days: days.get(course.slug)! })),
    todo: courses.filter((c) => !days.has(c.slug)),
    // declarations on a course that is no longer listed do not count towards the total
    count: done.length,
    total: courses.length,
  }
}

export type PassportShow = 'all' | 'done' | 'todo'

/** What the passport shows: the courses ridden for real, those left to discover, or both. An unknown value shows both. */
export const parseShow = (value: unknown): PassportShow =>
  value === 'done' || value === 'todo' ? value : 'all'

/** The day on a stamp, "12.10.26", the same in every language. */
export function stampDate(iso: string): string {
  const [year = '', month = '', day = ''] = iso.split('-')
  return `${day}.${month}.${year.slice(2)}`
}

/** Where to begin: the shortest, then the flattest, of the courses not ridden yet. */
export function suggestStarters<
  C extends { distance_m: number; elevation_gain_m: number },
>(todo: C[], count = 3): C[] {
  return [...todo]
    .sort(
      (a, b) =>
        a.distance_m - b.distance_m || a.elevation_gain_m - b.elevation_gain_m,
    )
    .slice(0, count)
}
