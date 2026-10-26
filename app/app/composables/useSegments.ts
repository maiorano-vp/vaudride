import { SegmentTracker, type Segment, type SegmentResult } from '~/lib/segments/tracker'

const RESULT_SHOWN_MS = 8000

export interface SegmentFinish extends SegmentResult {
  bestS: number
  isBest: boolean
  /** Faster than the published KOM time (indicative: it only counts once the ride is saved). */
  beatsKom: boolean
}

const storageKey = (slug: string, id: string) => `vaudride:segment-best:${slug}:${id}`

function loadBest(slug: string, id: string): number | undefined {
  try {
    const v = Number(localStorage.getItem(storageKey(slug, id)))
    return v > 0 ? v : undefined
  } catch {
    return undefined
  }
}

function saveBest(slug: string, id: string, timeS: number) {
  try {
    localStorage.setItem(storageKey(slug, id), String(timeS))
  } catch {
    // private mode or blocked storage: the best time just is not kept
  }
}

/** Times the rider on the course's segments; the best time per segment is kept in this browser. */
export function useSegments(slug: string, segments: Ref<Segment[]>, state: Ref<{ distanceM: number; elapsedS: number }>) {
  let tracker = new SegmentTracker(segments.value)
  const active = shallowRef<{ segment: Segment; startedAtS: number; bestS?: number }>()
  const finished = shallowRef<SegmentFinish>()
  let hideTimer: ReturnType<typeof setTimeout> | undefined

  watch(segments, (s) => {
    tracker = new SegmentTracker(s)
    active.value = undefined
  })

  watch(
    () => state.value.distanceM,
    (distanceM) => {
      const result = tracker.update(distanceM, state.value.elapsedS)
      const run = tracker.active
      if (run?.segment.id !== active.value?.segment.id) {
        active.value = run && { ...run, bestS: loadBest(slug, run.segment.id) }
      }
      if (result) {
        const previous = loadBest(slug, result.segment.id)
        const isBest = previous === undefined || result.timeS < previous
        if (isBest) saveBest(slug, result.segment.id, result.timeS)
        const kom = result.segment.kom_time_s
        finished.value = { ...result, bestS: isBest ? result.timeS : previous!, isBest, beatsKom: kom == null || result.timeS < kom }
        clearTimeout(hideTimer)
        hideTimer = setTimeout(() => (finished.value = undefined), RESULT_SHOWN_MS)
      }
    },
  )

  watch(
    () => state.value.distanceM < 1,
    (atStart) => {
      if (atStart) {
        tracker.reset()
        active.value = undefined
      }
    },
  )
  onBeforeUnmount(() => clearTimeout(hideTimer))

  return { active, finished }
}
