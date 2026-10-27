import {
  lighter,
  nextLower,
  parseChoice,
  PROFILES,
  resolveQuality,
  type Device,
  type QualityChoice,
  type QualityLevel,
  type QualityProfile,
} from '~/lib/scene/quality'

type BatteryManager = EventTarget & { level: number; charging: boolean }

/** What this browser says about the machine. Called once on the client; the server assumes a capable one. */
function detectDevice(): Device {
  const nav = navigator as Navigator & { deviceMemory?: number; connection?: { saveData?: boolean } }
  return {
    webgl2: !!document.createElement('canvas').getContext('webgl2'),
    deviceMemory: nav.deviceMemory,
    hardwareConcurrency: nav.hardwareConcurrency,
    saveData: nav.connection?.saveData,
  }
}

/**
 * How the 3D scene is drawn: the rider's choice (Auto, a level, or the flat 2D view), kept in a cookie. In Auto the
 * level follows the machine, the battery and the frame rate: if the scene is too slow, it steps down a level.
 * Shared between the ride page, the 3D scene and the pause menu.
 */
export function useSceneQuality() {
  const cookie = useCookie<string>('scene-quality', { maxAge: 60 * 60 * 24 * 365 })
  const choice = useState<QualityChoice>('scene-quality-choice', () => parseChoice(cookie.value))
  const device = useState<Device>('scene-quality-device', () => ({ webgl2: true }))
  /** The lightest level the frame rate has pushed Auto down to; forgotten when the rider picks something. */
  const cap = useState<QualityLevel | undefined>('scene-quality-cap', () => undefined)

  const effective = computed<QualityLevel | '2d'>(() => {
    const picked = resolveQuality(choice.value, device.value)
    return picked !== '2d' && choice.value === 'auto' && cap.value ? lighter(picked, cap.value) : picked
  })
  const level = computed(() => (effective.value === '2d' ? undefined : effective.value))
  const profile = computed<QualityProfile | undefined>(() => (level.value ? PROFILES[level.value] : undefined))

  function choose(next: QualityChoice) {
    choice.value = next
    cap.value = undefined
    cookie.value = next
  }

  /** The scene is too slow: one level lighter, in Auto only. Returns the new level, or undefined if nothing changed. */
  function stepDown(): QualityLevel | undefined {
    if (choice.value !== 'auto' || !level.value) return undefined
    const lower = nextLower(level.value)
    if (lower) cap.value = lower
    return lower
  }

  /** Reads the device and follows the battery. Returns what stops the listening. */
  function detect(): () => void {
    device.value = detectDevice()
    let battery: BatteryManager | undefined
    const read = () => battery && (device.value = { ...device.value, battery: { level: battery.level, charging: battery.charging } })
    ;(navigator as Navigator & { getBattery?: () => Promise<BatteryManager> })
      .getBattery?.()
      .then((b) => {
        battery = b
        read()
        b.addEventListener('levelchange', read)
        b.addEventListener('chargingchange', read)
      })
      .catch(() => {}) // no battery information: the rest decides
    return () => {
      battery?.removeEventListener('levelchange', read)
      battery?.removeEventListener('chargingchange', read)
    }
  }

  return { choice, device, effective, level, profile, choose, stepDown, detect }
}
