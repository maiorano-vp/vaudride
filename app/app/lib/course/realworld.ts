import type { Poi } from '~/lib/pois/nearby'

/** Where to leave the course by public transport: directions from the finish line (the start has `startLinks`). */
export function endLinks(end: { lat: number; lon: number }) {
  return { transit: `https://www.google.com/maps/dir/?api=1&origin=${end.lat},${end.lon}&travelmode=transit` }
}

/** The address of a course sheet on this site, for a QR code or a link to share. */
export const courseUrl = (origin: string, slug: string) => `${origin.replace(/\/+$/, '')}/courses/${slug}`

/** Months (1–12) as runs of consecutive ones: [5, 6, 7, 9] → [[5, 7], [9, 9]]. */
export function monthRuns(months: number[]): Array<[number, number]> {
  const sorted = [...new Set(months.filter((m) => Number.isInteger(m) && m >= 1 && m <= 12))].sort((a, b) => a - b)
  const runs: Array<[number, number]> = []
  for (const m of sorted) {
    const last = runs[runs.length - 1]
    if (last && m === last[1] + 1) last[1] = m
    else runs.push([m, m])
  }
  return runs
}

/** "mai – septembre, novembre": the months in the visitor's language. */
export function monthsLabel(months: number[], locale: string): string {
  const name = (m: number) => new Intl.DateTimeFormat(locale, { month: 'long' }).format(new Date(2026, m - 1, 1))
  return monthRuns(months)
    .map(([from, to]) => (from === to ? name(from) : to === from + 1 ? `${name(from)}, ${name(to)}` : `${name(from)} – ${name(to)}`))
    .join(', ')
}

/** What the rider can count on along the way. */
export function serviceCounts(pois: Poi[]) {
  const count = (category: string) => pois.filter((p) => p.category === category).length
  return { water: count('water'), bike: count('bike'), station: count('station'), lodging: count('lodging') }
}
