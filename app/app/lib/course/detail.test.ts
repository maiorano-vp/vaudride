import { describe, expect, it } from 'vitest'
import { elevationRange, kmDplusText, splitDescription } from './detail'

describe('splitDescription', () => {
  it('takes the first paragraph as the lead and the others as the body', () => {
    expect(
      splitDescription(
        'Une balade.\n\nLe départ est à la gare.\n\nPrévoir de l’eau.',
      ),
    ).toEqual({
      lead: 'Une balade.',
      body: 'Le départ est à la gare.\n\nPrévoir de l’eau.',
    })
  })

  it('makes a short single paragraph all lead', () => {
    expect(splitDescription('  Une balade au bord du lac.  ')).toEqual({
      lead: 'Une balade au bord du lac.',
      body: '',
    })
  })

  it('cuts a long first paragraph after its last sentence that fits, and keeps the rest', () => {
    const first =
      'Une belle boucle. ' +
      'Elle suit le lac puis grimpe dans les vignes avant de redescendre par un chemin tranquille. '
        .repeat(4)
        .trim()
    const { lead, body } = splitDescription(`${first}\n\nSuite.`)
    expect(lead.length).toBeLessThanOrEqual(240)
    expect(lead.endsWith('.')).toBe(true)
    expect(lead).toContain('Une belle boucle.')
    expect(`${lead} ${body}`).toContain('Suite.')
    expect(body.endsWith('Suite.')).toBe(true)
  })

  it('gives no lead rather than a cut one when a long paragraph has no sentence end in reach', () => {
    const text = 'mot '.repeat(120).trim()
    expect(splitDescription(text)).toEqual({ lead: '', body: text })
  })

  it('handles Windows line breaks and nothing at all', () => {
    expect(splitDescription('A.\r\n\r\nB.')).toEqual({ lead: 'A.', body: 'B.' })
    expect(splitDescription('')).toEqual({ lead: '', body: '' })
    expect(splitDescription(null)).toEqual({ lead: '', body: '' })
  })
})

describe('elevationRange', () => {
  it('gives the lowest and highest altitude in whole metres', () => {
    expect(
      elevationRange([{ ele: 372.4 }, { ele: 1122.6 }, { ele: 800 }]),
    ).toEqual({ min: 372, max: 1123 })
  })

  it('gives nothing for a route without points', () => {
    expect(elevationRange([])).toBeUndefined()
  })
})

describe('kmDplusText', () => {
  it('writes the distance with one decimal and the climb in whole metres, in the visitor’s format', () => {
    expect(kmDplusText(44_700, 1065.4, 'en')).toBe('44.7 km · 1,065 m D+')
    expect(kmDplusText(44_700, 1065.4, 'fr')).toMatch(
      /^44,7 km · 1\s065 m D\+$/u,
    )
    expect(kmDplusText(9_000, 0, 'de')).toBe('9,0 km · 0 m D+')
  })
})
