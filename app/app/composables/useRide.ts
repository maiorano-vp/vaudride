import type { CourseProfile } from '~/lib/course/profile'
import type { RiderParams } from '~/lib/physics/speed'
import { advance, fly, initialRide, seekRide } from '~/lib/ride'

export interface RideOptions {
  /** While a workout gives a target power (ERG), the trainer gets that power instead of the grade. */
  targetW?: Ref<number | undefined>
  onTarget?: (watts: number) => void
  /** Discovery mode: the speed is chosen by hand, with no power and no trainer. */
  flyMs?: Ref<number | undefined>
  /** The rider's weight and drag area; the defaults when absent. */
  params?: Ref<RiderParams>
  /** Share of the real grade the rider rides and the trainer applies (50 = half as steep). */
  difficultyPct?: Ref<number>
}

/**
 * Runs the physics loop (~4 Hz) and pushes the grade the trainer must apply to `onGrade`: the course's grade scaled
 * by the difficulty, which also drives the speed so that what the rider feels and how fast they go agree.
 */
export function useRide(
  course: Ref<CourseProfile | undefined>,
  powerW: Ref<number>,
  onGrade: (pct: number) => void,
  options: RideOptions = {},
) {
  const { targetW, onTarget, flyMs, params, difficultyPct } = options
  const state = ref(initialRide())
  const paused = ref(false)
  let timer: ReturnType<typeof setInterval> | undefined
  let last = 0
  const scale = () => (difficultyPct?.value ?? 100) / 100

  onMounted(() => {
    last = performance.now()
    timer = setInterval(() => {
      const now = performance.now()
      const dt = Math.min((now - last) / 1000, 1)
      last = now
      if (!course.value || paused.value) return
      if (flyMs?.value !== undefined) {
        state.value = fly(state.value, flyMs.value, course.value, dt)
        return
      }
      state.value = advance(state.value, powerW.value, course.value, dt, params?.value, scale())
      if (targetW?.value !== undefined && onTarget) onTarget(targetW.value)
      else onGrade(state.value.gradePct * scale())
    }, 250)
  })
  onBeforeUnmount(() => clearInterval(timer))

  // stop the clock and release the trainer's resistance; the next tick sends the grade (or target) again
  watch(paused, (p) => p && !flyMs?.value && onGrade(0))

  function reset() {
    state.value = initialRide(course.value)
    paused.value = false
  }
  /** Puts the rider at a distance along the course (sim mode). */
  function seek(distanceM: number) {
    if (!course.value) return
    state.value = seekRide(state.value, course.value, distanceM)
    if (targetW?.value === undefined) onGrade(state.value.gradePct * scale())
  }
  return { state, paused, reset, seek }
}
