import { describe, expect, it } from 'vitest'
import type { Poi } from './nearby'
import { pickVoice, speechText } from './speech'

const poi = (description: string | null): Poi => ({
  id: 'p',
  distance_m: 0,
  title: { fr: 'Le Pont', de: 'Die Brücke' },
  description: description === null ? null : { fr: description },
  url: null,
  category: 'history',
  source: 'manual',
})

describe('speechText', () => {
  it('reads title then description', () => {
    expect(speechText(poi('Un beau pont.'), 'fr')).toBe('Le Pont. Un beau pont.')
  })
  it('reads only the title without a description', () => {
    expect(speechText(poi(null), 'de')).toBe('Die Brücke')
  })
  it('cuts a long description at the end of a sentence', () => {
    const text = speechText(poi(`${'Phrase courte. '.repeat(40)}`), 'fr')
    expect(text.length).toBeLessThanOrEqual(320)
    expect(text.endsWith('.')).toBe(true)
  })
})

describe('pickVoice', () => {
  const voices = [{ lang: 'en_US' }, { lang: 'fr-FR' }, { lang: 'fr-CH' }, { lang: 'de-DE' }]
  it('prefers the Swiss variant', () => expect(pickVoice(voices, 'fr')).toEqual({ lang: 'fr-CH' }))
  it('falls back to another region', () => expect(pickVoice(voices, 'de')).toEqual({ lang: 'de-DE' }))
  it('handles underscores', () => expect(pickVoice(voices, 'en')).toEqual({ lang: 'en_US' }))
  it('returns nothing when no voice fits', () => expect(pickVoice([{ lang: 'it-IT' }], 'fr')).toBeUndefined())
})
