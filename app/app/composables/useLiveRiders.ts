import { getLiveTransport } from '~/lib/live'
import { distanceAt, dropStale, remove, upsert, type Rider } from '~/lib/live/riders'
import type { LiveRoom } from '~/lib/live/transport'

const SEND_MS = 1000
const TICK_MS = 250

/** Shares our position on the course's presence channel and exposes the other riders, gliding between messages. */
export function useLiveRiders(slug: string, me: { distanceM: Ref<number>; powerW: Ref<number> }, paused: Ref<boolean>) {
  const { pusherKey, pusherCluster } = useRuntimeConfig().public
  const riders = shallowRef<Rider[]>([])
  const now = ref(Date.now())
  const available = ref(false)
  let room: LiveRoom | undefined
  let timers: Array<ReturnType<typeof setInterval>> = []

  onMounted(() => {
    const transport = getLiveTransport({ key: pusherKey, cluster: pusherCluster })
    available.value = transport.available
    if (!transport.available) return
    room = transport.join(`course.${slug}`, {
      onMessage: (m) => (riders.value = upsert(riders.value, m, Date.now())),
      onLeave: (id) => (riders.value = remove(riders.value, id)),
    })
    timers = [
      setInterval(() => {
        now.value = Date.now()
        riders.value = dropStale(riders.value, now.value)
      }, TICK_MS),
      setInterval(() => !paused.value && room?.send({ d: Math.round(me.distanceM.value), p: me.powerW.value }), SEND_MS),
    ]
  })
  onBeforeUnmount(() => {
    timers.forEach(clearInterval)
    room?.close()
  })

  /** Other riders with their estimated distance and gap to us (+ = ahead), furthest first. */
  const others = computed(() =>
    riders.value
      .map((r) => ({ id: r.id, name: r.name, powerW: r.p, distanceM: distanceAt(r, now.value), gapM: distanceAt(r, now.value) - me.distanceM.value }))
      .sort((a, b) => b.distanceM - a.distanceM),
  )
  return { others, available }
}
