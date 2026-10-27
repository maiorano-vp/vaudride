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
export function passportProgress<C extends PassportCourse>(courses: C[], rides: PassportRide[]) {
  const days = new Map<string, string[]>()
  for (const ride of rides) days.set(ride.course.slug, [...(days.get(ride.course.slug) ?? []), ride.ridden_on].sort().reverse())

  const done = courses.filter((c) => days.has(c.slug))
  return {
    done: done.map((course) => ({ course, days: days.get(course.slug)! })),
    todo: courses.filter((c) => !days.has(c.slug)),
    // declarations on a course that is no longer listed do not count towards the total
    count: done.length,
    total: courses.length,
  }
}
