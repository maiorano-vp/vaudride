import type { CourseProfile } from '~/lib/course/profile'
import { advance, initialRide } from '~/lib/ride'

/** Runs the physics loop (~4 Hz) and pushes the current grade to `onGrade`. */
export function useRide(course: Ref<CourseProfile | undefined>, powerW: Ref<number>, onGrade: (pct: number) => void) {
  const state = ref(initialRide())
  let timer: ReturnType<typeof setInterval> | undefined
  let last = 0

  onMounted(() => {
    last = performance.now()
    timer = setInterval(() => {
      const now = performance.now()
      const dt = Math.min((now - last) / 1000, 1)
      last = now
      if (!course.value) return
      state.value = advance(state.value, powerW.value, course.value, dt)
      onGrade(state.value.gradePct)
    }, 250)
  })
  onBeforeUnmount(() => clearInterval(timer))

  function reset() {
    state.value = initialRide(course.value)
  }
  return { state, reset }
}
