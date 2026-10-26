import { connectDevice } from '~/lib/ble/connect'
import { getTransport } from '~/lib/ble'
import { SimDevice } from '~/lib/ble/sim'
import type { BleDevice } from '~/lib/ble/transport'

export type Connection = 'idle' | 'connecting' | 'connected' | 'reconnecting' | 'unsupported' | 'error'

/** Shared connect/disconnect lifecycle for trainer, heart-rate strap and power meter. */
export function useBleDevice(service: number, setup: (device: BleDevice) => Promise<void>) {
  const connection = ref<Connection>('idle')
  const deviceName = ref<string>()
  const simDevice = shallowRef<SimDevice>()
  let stop: (() => void) | undefined

  async function connect() {
    const transport = getTransport()
    if (!transport.supported) {
      connection.value = 'unsupported'
      return
    }
    connection.value = 'connecting'
    try {
      const r = await connectDevice(transport, service, setup, (s) => {
        connection.value = s === 'stopped' ? 'idle' : s
      })
      stop = r.stop
      deviceName.value = r.device.name
      simDevice.value = r.device instanceof SimDevice ? r.device : undefined
    } catch (e) {
      // closing the browser picker is not an error
      connection.value = (e as Error).name === 'NotFoundError' ? 'idle' : 'error'
    }
  }

  function disconnect() {
    stop?.()
    stop = undefined
  }

  onBeforeUnmount(disconnect)
  return { connection, deviceName, simDevice, connect, disconnect }
}
