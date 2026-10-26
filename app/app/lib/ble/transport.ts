/** Everything BLE the app needs, so Web Bluetooth can be swapped for a native plugin (Capacitor) in V2. */
export interface BleCharacteristic {
  write(data: Uint8Array): Promise<void>
  /** Starts notifications/indications; returns an unsubscribe function. */
  subscribe(onValue: (value: DataView) => void): Promise<() => void>
}

export interface BleDevice {
  readonly id: string
  readonly name: string
  connect(): Promise<void>
  characteristic(service: number, characteristic: number): Promise<BleCharacteristic>
  onDisconnect(callback: () => void): void
  disconnect(): void
}

export interface BleTransport {
  readonly supported: boolean
  /** Opens the browser device picker for a device exposing `service`. */
  request(service: number): Promise<BleDevice>
}

export type ConnectionState = 'connected' | 'reconnecting' | 'stopped'

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

/**
 * Reconnects with exponential backoff (1 s … 10 s) whenever the device drops.
 * `setup` re-subscribes to characteristics; the caller does the first connect itself to surface errors.
 * Returns a stop function that also disconnects.
 */
export function reconnectOnDrop(
  device: BleDevice,
  setup: () => Promise<void>,
  onState: (state: ConnectionState) => void = () => {},
): () => void {
  let stopped = false
  let running = false

  async function reconnect() {
    if (running) return
    running = true
    onState('reconnecting')
    for (let attempt = 0; !stopped; attempt++) {
      try {
        await device.connect()
        await setup()
        onState('connected')
        break
      } catch {
        await sleep(Math.min(1000 * 2 ** attempt, 10_000))
      }
    }
    running = false
  }

  device.onDisconnect(() => {
    if (!stopped) void reconnect()
  })

  return () => {
    stopped = true
    device.disconnect()
    onState('stopped')
  }
}
