/** The picture a rider shares after a ride: course, figures and the profile with the part they rode. */
export const CARD_WIDTH = 1200
export const CARD_HEIGHT = 630

export interface CardData {
  courseName: string
  distanceM: number
  durationS: number
  elevationGainM: number
  avgPowerW: number
  /** The course profile, to draw; `ridden` is the distance reached (m). */
  profile: Array<{ d: number; ele: number }>
  courseDistanceM: number
  ridden: number
  labels: { distance: string; time: string; climb: string; power: string }
}

interface Box {
  x: number
  y: number
  w: number
  h: number
}

/** Profile points scaled into `box` (y grows downwards), up to `upTo` metres. */
export function profilePath(points: Array<{ d: number; ele: number }>, totalM: number, box: Box, upTo = totalM): Array<[number, number]> {
  if (!points.length || totalM <= 0) return []
  const eles = points.map((p) => p.ele)
  const min = Math.min(...eles)
  const range = Math.max(...eles) - min || 1
  return points
    .filter((p) => p.d <= upTo)
    .map((p) => [box.x + (p.d / totalM) * box.w, box.y + box.h - ((p.ele - min) / range) * box.h] as [number, number])
}

export const clock = (s: number) => {
  const h = Math.floor(s / 3600)
  const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0')
  const ss = String(Math.floor(s % 60)).padStart(2, '0')
  return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`
}

const loadImage = (src: string) =>
  new Promise<HTMLImageElement>((resolve, reject) => {
    const img = new Image()
    img.onload = () => resolve(img)
    img.onerror = () => reject(new Error(`Cannot load ${src}`))
    img.src = src
  })

/** Keeps a title on one line by shrinking the font until it fits. */
function fitFont(ctx: CanvasRenderingContext2D, text: string, family: string, maxPx: number, widthPx: number) {
  let size = maxPx
  do {
    ctx.font = `${size}px ${family}`
    size -= 2
  } while (ctx.measureText(text).width > widthPx && size > 24)
}

export async function renderCard(data: CardData): Promise<Blob> {
  const display = '"Staatliches", sans-serif'
  const body = '"Maitree", serif'
  // the web fonts must be ready before drawing text with them
  await Promise.all([document.fonts.load(`64px ${display}`), document.fonts.load(`24px ${body}`)]).catch(() => {})
  const [hero, logo] = await Promise.all([loadImage('/auth-hero.jpg').catch(() => undefined), loadImage('/vaud-logo.svg').catch(() => undefined)])

  const canvas = document.createElement('canvas')
  canvas.width = CARD_WIDTH
  canvas.height = CARD_HEIGHT
  const ctx = canvas.getContext('2d')!

  // background: the photo, covered and darkened
  ctx.fillStyle = '#0a334d'
  ctx.fillRect(0, 0, CARD_WIDTH, CARD_HEIGHT)
  if (hero) {
    const scale = Math.max(CARD_WIDTH / hero.width, CARD_HEIGHT / hero.height)
    ctx.drawImage(hero, (CARD_WIDTH - hero.width * scale) / 2, (CARD_HEIGHT - hero.height * scale) * 0.65, hero.width * scale, hero.height * scale)
  }
  const shade = ctx.createLinearGradient(0, 0, 0, CARD_HEIGHT)
  shade.addColorStop(0, 'rgba(10, 64, 105, 0.6)')
  shade.addColorStop(1, 'rgba(10, 51, 77, 0.95)')
  ctx.fillStyle = shade
  ctx.fillRect(0, 0, CARD_WIDTH, CARD_HEIGHT)

  // brand badge
  ctx.fillStyle = '#fff'
  ctx.beginPath()
  ctx.roundRect(48, 40, 250, 70, 8)
  ctx.fill()
  if (logo) ctx.drawImage(logo, 64, 56, 150, (150 / logo.width) * logo.height || 38)
  ctx.fillStyle = '#0a4069'
  ctx.font = `34px ${display}`
  ctx.textBaseline = 'middle'
  ctx.fillText('RIDE', 228, 76)

  // course name
  ctx.fillStyle = '#fff'
  ctx.textBaseline = 'alphabetic'
  fitFont(ctx, data.courseName.toUpperCase(), display, 76, CARD_WIDTH - 120)
  ctx.fillText(data.courseName.toUpperCase(), 60, 200)

  // figures
  const figures = [
    { label: data.labels.distance, value: `${(data.distanceM / 1000).toFixed(1)} km` },
    { label: data.labels.time, value: clock(data.durationS) },
    { label: data.labels.climb, value: `${Math.round(data.elevationGainM)} m` },
    { label: data.labels.power, value: data.avgPowerW ? `${data.avgPowerW} W` : '–' },
  ]
  figures.forEach((f, i) => {
    const x = 60 + i * 280
    ctx.fillStyle = 'rgba(255, 255, 255, 0.75)'
    ctx.font = `24px ${body}`
    ctx.fillText(f.label, x, 292)
    ctx.fillStyle = '#fff'
    ctx.font = `64px ${display}`
    ctx.fillText(f.value, x, 360)
  })

  // profile, with the ridden part highlighted
  const box = { x: 60, y: 410, w: CARD_WIDTH - 120, h: 150 }
  const area = (path: Array<[number, number]>) => {
    if (path.length < 2) return
    ctx.beginPath()
    ctx.moveTo(path[0]![0], box.y + box.h)
    path.forEach(([x, y]) => ctx.lineTo(x, y))
    ctx.lineTo(path[path.length - 1]![0], box.y + box.h)
    ctx.closePath()
    ctx.fill()
  }
  ctx.fillStyle = 'rgba(255, 255, 255, 0.22)'
  area(profilePath(data.profile, data.courseDistanceM, box))
  ctx.fillStyle = '#ffd34d'
  area(profilePath(data.profile, data.courseDistanceM, box, data.ridden))

  // footer
  ctx.fillStyle = 'rgba(255, 255, 255, 0.8)'
  ctx.font = `22px ${body}`
  ctx.textAlign = 'right'
  ctx.fillText('vaud.ch · © swisstopo', CARD_WIDTH - 60, CARD_HEIGHT - 28)
  ctx.textAlign = 'left'

  return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('No image'))), 'image/png'))
}

/** Shares the picture with the system share sheet when it takes files, and downloads it otherwise. */
export async function shareCard(blob: Blob, title: string, fileName: string) {
  const file = new File([blob], fileName, { type: 'image/png' })
  if (navigator.canShare?.({ files: [file] })) {
    try {
      await navigator.share({ files: [file], title })
      return
    } catch (e) {
      if ((e as Error).name === 'AbortError') return // the rider closed the share sheet
    }
  }
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fileName
  a.click()
  URL.revokeObjectURL(url)
}
