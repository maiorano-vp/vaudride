import { pointAt, type CourseProfile } from './course/profile'
import { DEFAULT_PARAMS, stepSpeed, type RiderParams } from './physics/speed'

export interface RideState {
  distanceM: number
  speedMs: number
  elevationGainM: number
  elapsedS: number
  gradePct: number
  finished: boolean
}

export const initialRide = (course?: CourseProfile): RideState => ({
  distanceM: 0,
  speedMs: 0,
  elevationGainM: 0,
  elapsedS: 0,
  gradePct: course?.points[0]?.grade ?? 0,
  finished: false,
})

/** Puts the rider at `distanceM` (clamped to the course): the clock, speed and climb carry on. Sim mode only. */
export function seekRide(s: RideState, course: CourseProfile, distanceM: number): RideState {
  const d = Math.min(Math.max(distanceM, 0), course.distance_m)
  return { ...s, distanceM: d, gradePct: pointAt(course, d).grade, finished: false }
}

/** Discovery mode: one step at a speed chosen by hand, with no rider and no physics. */
export function fly(s: RideState, speedMs: number, course: CourseProfile, dtS: number): RideState {
  if (s.finished) return s
  const here = pointAt(course, s.distanceM)
  const distanceM = Math.min(s.distanceM + speedMs * dtS, course.distance_m)
  const next = pointAt(course, distanceM)
  return {
    distanceM,
    speedMs,
    elevationGainM: s.elevationGainM + Math.max(0, next.ele - here.ele),
    elapsedS: s.elapsedS + dtS,
    gradePct: next.grade,
    finished: distanceM >= course.distance_m,
  }
}

/** One physics step: power + current grade → new speed, distance, climb. */
export function advance(
  s: RideState,
  powerW: number,
  course: CourseProfile,
  dtS: number,
  params: RiderParams = DEFAULT_PARAMS,
): RideState {
  if (s.finished) return s
  const here = pointAt(course, s.distanceM)
  const speedMs = stepSpeed(s.speedMs, powerW, here.grade, dtS, params)
  const distanceM = Math.min(s.distanceM + speedMs * dtS, course.distance_m)
  const next = pointAt(course, distanceM)
  return {
    distanceM,
    speedMs,
    elevationGainM: s.elevationGainM + Math.max(0, next.ele - here.ele),
    elapsedS: s.elapsedS + dtS,
    gradePct: next.grade,
    finished: distanceM >= course.distance_m,
  }
}
