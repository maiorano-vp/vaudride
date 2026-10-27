/** The lead of a course page and the rest of its text, as vaud.ch sets an event page: a big sentence, then the body. */
const LEAD_MAX_CHARS = 240

/**
 * Splits a description in a lead (the first paragraph, or the first sentence of a long one) and the rest.
 * A short single paragraph is all lead; an empty text gives nothing.
 */
export function splitDescription(text: string | null | undefined): {
  lead: string
  body: string
} {
  const clean = (text ?? '').replace(/\r\n/g, '\n').trim()
  if (!clean) return { lead: '', body: '' }

  const [first = '', ...others] = clean.split(/\n\s*\n/)
  const rest = others.join('\n\n').trim()
  if (first.length <= LEAD_MAX_CHARS) return { lead: first.trim(), body: rest }

  // a long first paragraph: its first sentence is the lead, the remainder opens the body
  const end = first
    .slice(0, LEAD_MAX_CHARS)
    .search(/[.!?…](\s|$)(?!.*[.!?…](\s|$))/s)
  if (end <= 0) return { lead: '', body: clean } // no sentence end in reach: no lead rather than a cut one
  const lead = first.slice(0, end + 1).trim()
  const remainder = first.slice(end + 1).trim()
  return { lead, body: [remainder, rest].filter(Boolean).join('\n\n') }
}

/** The lowest and highest altitude of the route, in whole metres; undefined without points. */
export function elevationRange(
  points: Array<{ ele: number }>,
): { min: number; max: number } | undefined {
  if (!points.length) return undefined
  const eles = points.map((p) => p.ele)
  return {
    min: Math.round(Math.min(...eles)),
    max: Math.round(Math.max(...eles)),
  }
}

/** "44.7 km · 1 065 m D+" in the visitor's number format: the figures that stand for a course what dates do for an event. */
export function kmDplusText(
  distanceM: number,
  gainM: number,
  locale: string,
): string {
  const km = new Intl.NumberFormat(locale, {
    maximumFractionDigits: 1,
    minimumFractionDigits: 1,
  }).format(distanceM / 1000)
  const gain = new Intl.NumberFormat(locale, {
    maximumFractionDigits: 0,
  }).format(Math.round(gainM))
  return `${km} km · ${gain} m D+`
}
