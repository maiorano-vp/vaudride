import { poiDescription, poiTitle, type Poi } from './nearby'

const MAX_CHARS = 300

/** BCP 47 tags to look for, by app locale, best first. */
const VOICE_LANGS: Record<string, string[]> = { fr: ['fr-CH', 'fr-FR', 'fr'], de: ['de-CH', 'de-DE', 'de'], en: ['en-GB', 'en-US', 'en'] }

/** What is read out for a place: "title. description", cut at the end of a sentence if it is long. */
export function speechText(poi: Poi, locale: string): string {
  const title = poiTitle(poi, locale).trim()
  const description = poiDescription(poi, locale).replace(/\s+/g, ' ').trim()
  if (!description) return title
  let text = description
  if (text.length > MAX_CHARS) {
    const cut = text.slice(0, MAX_CHARS)
    const end = Math.max(cut.lastIndexOf('. '), cut.lastIndexOf('! '), cut.lastIndexOf('? '))
    text = end > 0 ? cut.slice(0, end + 1) : `${cut.slice(0, cut.lastIndexOf(' '))}…`
  }
  return `${title}. ${text}`
}

/** The voice that best matches the locale, if the browser has one. */
export function pickVoice<V extends { lang: string }>(voices: V[], locale: string): V | undefined {
  for (const tag of VOICE_LANGS[locale] ?? [locale]) {
    const exact = voices.find((v) => v.lang.replace('_', '-').toLowerCase() === tag.toLowerCase())
    if (exact) return exact
  }
  return voices.find((v) => v.lang.toLowerCase().startsWith(locale.toLowerCase()))
}
