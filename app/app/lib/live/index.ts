import { simLiveTransport } from './sim'
import { unavailableTransport, type LiveTransport } from './transport'
import { pusherTransport } from './pusher'

/** `?sim=1` selects fake riders; otherwise Pusher when a key is configured. */
export function getLiveTransport(pusher: { key?: string; cluster?: string }): LiveTransport {
  if (import.meta.client && new URLSearchParams(location.search).get('sim') === '1') return simLiveTransport()
  return import.meta.client && pusher.key ? pusherTransport(pusher.key, pusher.cluster ?? 'eu') : unavailableTransport
}
