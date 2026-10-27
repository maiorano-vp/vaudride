/** What a course card needs from the course list. */
export interface CourseCardData {
  slug: string
  name: Record<string, string>
  distance_m: number
  elevation_gain_m: number
  image_url?: string | null
  image_credit?: string | null
  destination?: string | null
  kind?: string | null
}

/** The kinds of ride a card can announce, in the order the filter lists them. */
export const KINDS = ['velo', 'mtb', 'scooter'] as const
export type Kind = (typeof KINDS)[number]

const isKind = (value: unknown): value is Kind => KINDS.includes(value as Kind)

/** The translation key of the badge of a card; none for a course whose kind is unknown. */
export const kindLabelKey = (kind: string | null | undefined): string | undefined => (isKind(kind) ? `course.kind.${kind}` : undefined)

/** The courses of one kind; all of them for "all" or a kind that does not exist (a stale link). */
export const filterByKind = <T extends { kind?: string | null }>(courses: T[], kind: string): T[] =>
  isKind(kind) ? courses.filter((c) => c.kind === kind) : courses

/** How many courses there are of each kind that has some, in the filter's order. */
export const kindCounts = (courses: Array<{ kind?: string | null }>): Array<{ kind: Kind; count: number }> =>
  KINDS.map((kind) => ({ kind, count: courses.filter((c) => c.kind === kind).length })).filter((k) => k.count > 0)

/** The photo credit as it is printed: "© Name", whether the source wrote the sign or not; nothing for a blank one. */
export function creditText(credit: string | null | undefined): string | undefined {
  const text = credit?.trim().replace(/^(©|\(c\))\s*/i, '')
  return text ? `© ${text}` : undefined
}

/** "12.5" (km, one decimal). */
export const kmText = (distanceM: number) => (distanceM / 1000).toFixed(1)
