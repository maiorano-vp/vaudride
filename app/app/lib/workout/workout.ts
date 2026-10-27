/** One block of a structured workout: hold `ftpPct` % of the rider's FTP for `durationS` seconds. */
export interface WorkoutStep {
  durationS: number
  ftpPct: number
}

export interface Workout {
  id: string
  /** Display name per locale; custom workouts only have the rider's own text under `fr`. */
  name: Record<string, string>
  steps: WorkoutStep[]
}

export interface WorkoutProgress {
  index: number
  step: WorkoutStep
  /** Seconds left in the current step. */
  remainingS: number
  next?: WorkoutStep
}

export const workoutName = (w: Workout, locale: string) => w.name[locale] || w.name.fr || w.id

export const totalDuration = (w: Workout) => w.steps.reduce((sum, s) => sum + s.durationS, 0)

export const targetWatts = (step: WorkoutStep, ftp: number) => Math.round((ftp * step.ftpPct) / 100)

/** `n` times the same steps in a row, to write "5 × (4 min hard, 3 min easy)" without nesting. */
export const repeat = (n: number, ...steps: WorkoutStep[]) => Array.from({ length: n }, () => steps).flat()

/** Where the workout is `elapsedS` seconds after its start; undefined once it is over (or not started). */
export function progressAt(w: Workout, elapsedS: number): WorkoutProgress | undefined {
  if (elapsedS < 0) return undefined
  let start = 0
  for (const [index, step] of w.steps.entries()) {
    if (elapsedS < start + step.durationS) return { index, step, remainingS: start + step.durationS - elapsedS, next: w.steps[index + 1] }
    start += step.durationS
  }
  return undefined
}
