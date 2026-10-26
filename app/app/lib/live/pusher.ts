import Pusher, { type PresenceChannel } from 'pusher-js'
import type { LiveTransport } from './transport'

const EVENT = 'client-pos'

/** Presence channel per room; positions travel as client events, nothing is stored server side. */
export function pusherTransport(key: string, cluster: string): LiveTransport {
  let pusher: Pusher | undefined
  const client = () =>
    (pusher ??= new Pusher(key, {
      cluster,
      channelAuthorization: {
        transport: 'ajax',
        endpoint: '/api/broadcasting/auth',
        // the Nuxt proxy adds the Sanctum token from the httpOnly cookie
        customHandler: ({ socketId, channelName }, callback) => {
          $fetch('/api/broadcasting/auth', { method: 'POST', body: { socket_id: socketId, channel_name: channelName } })
            .then((auth) => callback(null, auth as never))
            .catch((e: Error) => callback(e, null))
        },
      },
    }))

  return {
    available: true,
    join(room, { onMessage, onLeave }) {
      const name = `presence-${room}`
      const channel = client().subscribe(name) as PresenceChannel
      channel.bind(EVENT, (data: { d: number; p: number }, meta?: { user_id?: string }) => {
        const id = meta?.user_id
        if (!id || id === channel.members.myID) return
        const info = channel.members.get(id)?.info as { nickname?: string; kit?: unknown } | undefined
        onMessage({ id, nickname: info?.nickname ?? '?', d: data.d, p: data.p, kit: info?.kit })
      })
      channel.bind('pusher:member_removed', (m: { id: string }) => onLeave(m.id))
      return {
        // ignored until the subscription succeeded
        send: (position) => channel.subscribed && channel.trigger(EVENT, position),
        close: () => client().unsubscribe(name),
      }
    },
  }
}
