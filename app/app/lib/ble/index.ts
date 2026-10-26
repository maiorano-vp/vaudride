import { createSimTransport } from './sim'
import type { BleTransport } from './transport'
import { webBluetooth } from './web-bluetooth'

let simTransport: ReturnType<typeof createSimTransport> | undefined

/** `?sim=1` selects the simulator; otherwise Web Bluetooth. One shared sim transport per page load. */
export function getTransport(): BleTransport {
  if (import.meta.client && new URLSearchParams(location.search).get('sim') === '1') {
    return (simTransport ??= createSimTransport())
  }
  return webBluetooth
}
