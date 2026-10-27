import type { CourseProfile } from '~/lib/course/profile'
import { advance, initialRide, seekRide } from '~/lib/ride'

/**
 * Runs the physics loop (~4 Hz) and pushes the current grade to `onGrade`.
 * While a workout gives a `targetW` (ERG), the trainer gets that power instead of the grade:
 * the rider still rides the course, but the resistance is the workout's.
 */
export function useRide(
  course: Ref<CourseProfile | undefined>,
  powerW: Ref<number>,
  onGrade: (pct: number) => void,
  targetW?: Ref<number | undefined>,
  onTarget?: (watts: number) => void,
) {
  const state = ref(initialRide())
  const paused = ref(false)
  let timer: ReturnType<typeof setInterval> | undefined
  let last = 0

  onMounted(() => {
    last = performance.now()
    timer = setInterval(() => {
      const now = performance.now()
      const dt = Math.min((now - last) / 1000, 1)
      last = now
      if (!course.value || paused.value) return
      state.value = advance(state.value, powerW.value, course.value, dt)
      if (targetW?.value !== undefined && onTarget) onTarget(targetW.value)
      else onGrade(state.value.gradePct)
    }, 250)
  })
  onBeforeUnmount(() => clearInterval(timer))

  // stop the clock and release the trainer's resistance; the next tick sends the grade (or target) again
  watch(paused, (p) => p && onGrade(0))

  function reset() {
    state.value = initialRide(course.value)
    paused.value = false
  }
  /** Puts the rider at a distance along the course (sim mode). */
  function seek(distanceM: number) {
    if (!course.value) return
    state.value = seekRide(state.value, course.value, distanceM)
    if (targetW?.value === undefined) onGrade(state.value.gradePct)
  }
  return { state, paused, reset, seek }
}
