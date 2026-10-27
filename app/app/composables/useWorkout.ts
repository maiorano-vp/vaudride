import { progressAt, targetWatts, type Workout } from '~/lib/workout/workout'

const FTP_KEY = 'vaudride.ftp'
const CUSTOM_KEY = 'vaudride.workout'
export const DEFAULT_FTP = 200

const read = (key: string): unknown => {
  try {
    return JSON.parse(localStorage.getItem(key) ?? 'null')
  } catch {
    return null // storage blocked or corrupted: start from the defaults
  }
}
const write = (key: string, value: unknown) => {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // private mode: the setting just does not survive the page
  }
}

const isWorkout = (w: unknown): w is Workout =>
  !!w &&
  typeof w === 'object' &&
  Array.isArray((w as Workout).steps) &&
  (w as Workout).steps.every((s) => s.durationS > 0 && s.ftpPct > 0)

/**
 * The structured workout of the current ride (ERG), shared between the ride page and the pause menu.
 * The clock is the ride's own, so pausing the ride pauses the workout. FTP and the rider's own workout stay in this
 * browser only (no personal data on the server).
 */
export function useWorkout() {
  const active = useState<Workout | undefined>('workout-active', () => undefined)
  const startedAtS = useState('workout-started', () => 0)
  const elapsedS = useState('workout-elapsed', () => 0)
  const completed = useState('workout-completed', () => false)
  const ftp = useState('workout-ftp', () => DEFAULT_FTP)
  const custom = useState<Workout | undefined>('workout-custom', () => undefined)

  const progress = computed(() => (active.value ? progressAt(active.value, elapsedS.value - startedAtS.value) : undefined))
  /** The power the trainer must hold, undefined outside a workout. */
  const targetW = computed(() => (progress.value ? targetWatts(progress.value.step, ftp.value) : undefined))

  /** Called by the ride page with the ride clock. */
  function tick(rideElapsedS: number) {
    elapsedS.value = rideElapsedS
    if (active.value && !progress.value && rideElapsedS - startedAtS.value > 0) {
      active.value = undefined // over: the trainer follows the grade again
      completed.value = true
    }
  }
  function start(workout: Workout) {
    active.value = workout
    startedAtS.value = elapsedS.value
    completed.value = false
  }
  function stop() {
    active.value = undefined
    completed.value = false
  }
  function load() {
    const savedFtp = Number(read(FTP_KEY))
    if (savedFtp >= 50 && savedFtp <= 600) ftp.value = savedFtp
    const saved = read(CUSTOM_KEY)
    if (isWorkout(saved)) custom.value = saved
  }
  function setFtp(watts: number) {
    ftp.value = Math.min(600, Math.max(50, Math.round(watts)))
    write(FTP_KEY, ftp.value)
  }
  function saveCustom(workout: Workout) {
    custom.value = workout
    write(CUSTOM_KEY, workout)
  }

  return { active, progress, targetW, completed, ftp, custom, tick, start, stop, load, setFtp, saveCustom }
}
