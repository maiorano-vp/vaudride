interface GpxPoint {
  lat: number
  lon: number
  ele: number
}

const escapeXml = (s: string) =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/** The course as a GPX track, to load on a bike computer and ride for real. */
export function toGpx(course: { name: string; attribution: string; points: GpxPoint[] }): string {
  const points = course.points
    .map((p) => `<trkpt lat="${p.lat.toFixed(6)}" lon="${p.lon.toFixed(6)}"><ele>${p.ele.toFixed(1)}</ele></trkpt>`)
    .join('')
  return (
    '<?xml version="1.0" encoding="UTF-8"?>\n' +
    '<gpx version="1.1" creator="Vaud Ride" xmlns="http://www.topografix.com/GPX/1/1">' +
    `<metadata><name>${escapeXml(course.name)}</name><desc>${escapeXml(course.attribution)}</desc></metadata>` +
    `<trk><name>${escapeXml(course.name)}</name><trkseg>${points}</trkseg></trk></gpx>`
  )
}

/** Links to get to the start line: public-transport directions and the spot on OpenStreetMap. */
export function startLinks(start: { lat: number; lon: number }) {
  const { lat, lon } = start
  return {
    transit: `https://www.google.com/maps/dir/?api=1&destination=${lat},${lon}&travelmode=transit`,
    map: `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=15/${lat}/${lon}`,
  }
}

/** The file name for a download: letters, digits and dashes only. */
export const fileSlug = (name: string) =>
  name
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-zA-Z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase()
