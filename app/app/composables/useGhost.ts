import { DEFAULT_KIT, type Kit } from '~/lib/kit/kit'
import { distanceAt, recordGhostDistance, speedAt, timeAtDistance, type CourseGhosts, type GhostKind, type GhostSamples } from '~/lib/ghost/ghost'
import type { Segment } from '~/lib/segments/tracker'

type Choice = 'none' | GhostKind
interface KomRecord {
  nickname: string
  time_s: number
  samples: GhostSamples
}

/** Segments are only fetched when the rider is this close to the start line (or inside the segment). */
const KOM_FETCH_AHEAD_M = 2000
const SHOWN_GAP_FROM_M = 20

/** A ghost is drawn in greys, so it cannot be mistaken for a live rider. */
const GHOST_KIT: Kit = { ...DEFAULT_KIT, jersey: '#9aa5b1', accent: '#d0d6dc', shorts: '#5b6570', helmet: '#d0d6dc', bike: '#5b6570' }

const storageKey =(slug: string) => `vaudride:ghost:${slug}`

/**
 * The ghost the rider chose to ride against: their best or last finished ride on the course, or the record of the next
 * segment. Shared between the ride page and the pause menu; the choice is kept per course in this browser.
 */
export function useGhost() {
  const choice = useState<Choice>('ghost-choice', () => 'none')
  const course = useState<CourseGhosts | undefined>('ghost-course', () => undefined)
  const records = useState<Record<string, KomRecord | null>>('ghost-records', () => ({}))
  const slug = useState('ghost-slug', () => '')

  /** Called by the ride page: restores the choice and fetches the player's rides (none when not signed in). */
  async function load(courseSlug: string) {
    slug.value = courseSlug
    records.value = {}
    course.value = undefined
    try {
      const saved = localStorage.getItem(storageKey(courseSlug))
      choice.value = saved === 'best' || saved === 'last' || saved === 'kom' ? saved : 'none'
    } catch {
      choice.value = 'none'
    }
    course.value = await $fetch<{ data: CourseGhosts }>(`/api/courses/${courseSlug}/ghosts`)
      .then((r) => r.data)
      .catch(() => undefined)
  }
  function choose(next: Choice) {
    choice.value = next
    try {
      localStorage.setItem(storageKey(slug.value), next)
    } catch {
      // private mode: the choice just does not survive the page
    }
  }
  async function loadRecord(segmentId: string) {
    if (segmentId in records.value) return
    records.value = { ...records.value, [segmentId]: null } // asked once, whatever the answer
    const record = await $fetch<{ data: KomRecord }>(`/api/segments/${segmentId}/ghost`)
      .then((r) => r.data)
      .catch(() => null)
    records.value = { ...records.value, [segmentId]: record }
  }

  return { choice, course, records, choose, load, loadRecord }
}

/** What the scene and the profile draw for a ghost: shaped like another live rider. */
export interface GhostRider {
  id: 'ghost'
  nickname: string
  powerW: number
  speedMs: number
  kit: Kit
  distanceM: number
  gapM: number
  /** Seconds the ghost is ahead of the rider at the rider's position (negative: behind); whole-course ghosts only. */
  gapS?: number
  ghost: true
}

/** The ghost as a rider on the course, from the ride's own clock. */
export function useGhostRider(
  ghost: ReturnType<typeof useGhost>,
  state: Ref<{ distanceM: number; elapsedS: number }>,
  segments: Ref<Segment[]>,
  activeRun: Ref<{ segment: Segment; startedAtS: number } | undefined>,
  label: Ref<string>,
) {
  // the record to chase: the segment the rider is on, else the next one with a KOM
  const target = computed(() =>
    segments.value
      .filter((s) => s.kom_time_s != null && s.end_m > state.value.distanceM)
      .sort((a, b) => a.start_m - b.start_m)[0],
  )
  watch(
    () => [ghost.choice.value, target.value?.id, state.value.distanceM] as const,
    ([choice, id]) => {
      const t = target.value
      if (choice === 'kom' && id && t && state.value.distanceM > t.start_m - KOM_FETCH_AHEAD_M) void ghost.loadRecord(id)
    },
  )

  return computed<GhostRider | undefined>(() => {
    const choice = ghost.choice.value
    const me = state.value // its clock stops with the pause, so the ghost stops with it
    const base = { id: 'ghost', powerW: 0, kit: GHOST_KIT, ghost: true } as const
    if (choice === 'best' || choice === 'last') {
      const samples = ghost.course.value?.[choice]?.samples
      if (!samples?.length) return undefined
      const distanceM = distanceAt(samples, me.elapsedS)
      const reached = timeAtDistance(samples, me.distanceM)
      return {
        ...base,
        nickname: label.value,
        distanceM,
        gapM: distanceM - me.distanceM,
        speedMs: speedAt(samples, me.elapsedS),
        gapS: reached !== undefined && me.distanceM > SHOWN_GAP_FROM_M ? me.elapsedS - reached : undefined,
      }
    }
    if (choice === 'kom' && target.value) {
      const record = ghost.records.value[target.value.id]
      if (!record) return undefined
      const run = activeRun.value?.segment.id === target.value.id ? me.elapsedS - activeRun.value.startedAtS : undefined
      const distanceM = recordGhostDistance(record.samples, target.value, me.distanceM, run)
      if (distanceM === undefined) return undefined
      return {
        ...base,
        nickname: `${record.nickname} (KOM)`,
        distanceM,
        gapM: distanceM - me.distanceM,
        speedMs: run === undefined ? 0 : speedAt(record.samples, run),
      }
    }
    return undefined
  })
}
