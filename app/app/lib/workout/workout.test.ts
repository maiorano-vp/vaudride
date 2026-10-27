import { describe, expect, it } from 'vitest'
import { TEMPLATES } from './templates'
import { progressAt, repeat, targetWatts, totalDuration, type Workout } from './workout'

const w: Workout = {
  id: 't',
  name: { fr: 'Test' },
  steps: [
    { durationS: 60, ftpPct: 50 },
    ...repeat(2, { durationS: 120, ftpPct: 100 }, { durationS: 60, ftpPct: 40 }),
  ],
}

describe('workout', () => {
  it('repeats steps without nesting', () => {
    expect(w.steps).toHaveLength(5)
    expect(totalDuration(w)).toBe(60 + 2 * 180)
  })

  it('finds the step at a given time, with the time left and the next step', () => {
    expect(progressAt(w, 0)).toMatchObject({ index: 0, remainingS: 60, next: { ftpPct: 100 } })
    expect(progressAt(w, 59.5)).toMatchObject({ index: 0 })
    expect(progressAt(w, 60)).toMatchObject({ index: 1, remainingS: 120 })
    expect(progressAt(w, 200)).toMatchObject({ index: 2, remainingS: 40 })
    expect(progressAt(w, 419)?.next).toBeUndefined()
  })

  it('is over after its last second, and not started before the first', () => {
    expect(progressAt(w, totalDuration(w))).toBeUndefined()
    expect(progressAt(w, -1)).toBeUndefined()
  })

  it('turns a share of FTP into watts', () => {
    expect(targetWatts({ durationS: 1, ftpPct: 105 }, 250)).toBe(263)
  })

  it('ships workouts that all have a name per locale and sensible steps', () => {
    for (const t of TEMPLATES) {
      expect(Object.keys(t.name).sort()).toEqual(['de', 'en', 'fr'])
      expect(t.steps.every((s) => s.durationS > 0 && s.ftpPct > 0 && s.ftpPct <= 150)).toBe(true)
    }
    // "5 × 4 min" is five 4-minute efforts at threshold
    expect(TEMPLATES[0]!.steps.filter((s) => s.durationS === 240 && s.ftpPct === 100)).toHaveLength(5)
  })
})
