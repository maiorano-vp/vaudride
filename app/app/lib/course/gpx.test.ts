import { describe, expect, it } from 'vitest'
import { fileSlug, startLinks, toGpx } from './gpx'

describe('toGpx', () => {
  const gpx = toGpx({
    name: 'Corniche <de> "Lavaux" & co',
    attribution: '© swisstopo',
    points: [
      { lat: 46.502988, lon: 6.685405, ele: 378.5 },
      { lat: 46.502944, lon: 6.685396, ele: 378.3 },
    ],
  })

  it('writes a track point per profile point, with elevation', () => {
    expect(gpx).toContain('<trkpt lat="46.502988" lon="6.685405"><ele>378.5</ele></trkpt>')
    expect(gpx.match(/<trkpt /g)).toHaveLength(2)
  })

  it('escapes the course name and keeps the attribution', () => {
    expect(gpx).toContain('<name>Corniche &lt;de&gt; &quot;Lavaux&quot; &amp; co</name>')
    expect(gpx).toContain('<desc>© swisstopo</desc>')
  })

  it('has one root and no raw markup characters in its text', () => {
    expect(gpx.match(/<gpx /g)).toHaveLength(1)
    expect(gpx.endsWith('</gpx>')).toBe(true)
    expect(gpx).not.toMatch(/&(?!amp;|lt;|gt;|quot;)/)
  })
})

describe('start links', () => {
  it('points transit directions and the map at the start', () => {
    const l = startLinks({ lat: 46.503, lon: 6.685 })
    expect(l.transit).toContain('destination=46.503,6.685&travelmode=transit')
    expect(l.map).toContain('mlat=46.503&mlon=6.685')
  })
})

describe('fileSlug', () => {
  it('makes a safe file name', () => {
    expect(fileSlug('Col du Marchairuz depuis Bière')).toBe('col-du-marchairuz-depuis-biere')
    expect(fileSlug('  ../Étape #1!  ')).toBe('etape-1')
  })
})
