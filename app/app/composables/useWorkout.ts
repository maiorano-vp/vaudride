import { progressAt, targetWatts, type Workout } from '~/lib/workout/workout'

const CUSTOM_KEY = 'vaudride.workout'

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
 * The clock is the ride's own, so pausing the ride pauses the workout. The rider's own workout stays in this browser;
 * the FTP is one of the rider settings, which follow the account.
 */
export function useWorkout() {
  const active = useState<Workout | undefined>('workout-active', () => undefined)
  const startedAtS = useState('workout-started', () => 0)
  const elapsedS = useState('workout-elapsed', () => 0)
  const completed = useState('workout-completed', () => false)
  const rider = useRiderSettings()
  const ftp = rider.ftp
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
  /** The rider's own workout; the FTP comes with the rider settings (`useRiderSettings().load()`). */
  function load() {
    const saved = read(CUSTOM_KEY)
    if (isWorkout(saved)) custom.value = saved
  }
  const setFtp = (watts: number) => rider.update({ ftp: watts })
  function saveCustom(workout: Workout) {
    custom.value = workout
    write(CUSTOM_KEY, workout)
  }

  return { active, progress, targetW, completed, ftp, custom, tick, start, stop, load, setFtp, saveCustom }
}
