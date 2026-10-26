import { RideRecorder, type RideReading } from '~/lib/ride/recorder'

export type SaveState = 'idle' | 'saving' | 'saved' | 'error'

/** How the ride did on a segment, once saved: rank among the players' best times (1 = KOM). */
export interface SegmentEffort {
  segment_id: string
  name: Record<string, string>
  time_s: number
  rank: number
  is_kom: boolean
}

/** Records the ride second by second and saves it on the player's account when they ask. */
export function useRideRecording(slug: string, reading: Ref<RideReading>, elevationGainM: Ref<number>) {
  const recorder = new RideRecorder()
  const version = ref(0) // bumped on each sample so the summary below recomputes
  const saveState = ref<SaveState>('idle')
  const efforts = ref<SegmentEffort[]>([])

  watch(
    () => reading.value.elapsedS,
    () => {
      const before = recorder.samples.length
      recorder.push(reading.value)
      if (recorder.samples.length !== before) version.value++
    },
  )

  const summary = computed(() => {
    void version.value
    return recorder.summary()
  })
  const recordable = computed(() => {
    void version.value
    return recorder.recordable
  })

  async function save(uploadToStrava: boolean) {
    if (!recorder.recordable || !recorder.startedAt) return
    saveState.value = 'saving'
    try {
      const { data } = await $fetch<{ data: { efforts?: SegmentEffort[] } }>('/api/rides', {
        method: 'POST',
        body: {
          course: slug,
          started_at: recorder.startedAt.toISOString(),
          elevation_gain_m: Math.round(elevationGainM.value),
          upload_to_strava: uploadToStrava,
          samples: recorder.samples,
        },
      })
      efforts.value = data.efforts ?? []
      saveState.value = 'saved'
    } catch {
      saveState.value = 'error'
    }
  }

  function reset() {
    recorder.reset()
    version.value++
    saveState.value = 'idle'
    efforts.value = []
  }

  return { summary, recordable, saveState, efforts, save, reset }
}
