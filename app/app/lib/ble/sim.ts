import { CONTROL_POINT, FTMS_SERVICE, INDOOR_BIKE_DATA } from './ftms'
import { HEART_RATE_MEASUREMENT, HEART_RATE_SERVICE } from './heart-rate'
import type { BleCharacteristic, BleDevice, BleTransport } from './transport'

type Listener = (v: DataView) => void

/** Fake trainer / heart-rate strap for `?sim=1` and tests. Records every Control Point write in `commands`. */
export class SimDevice implements BleDevice {
  readonly commands: number[][] = []
  power = 180
  heartRate = 130
  failOpcode?: number // answer this opcode with "not supported" (0x02)
  private listeners = new Map<string, Set<Listener>>()
  private disconnectCallbacks: Array<() => void> = []
  private timers: Array<ReturnType<typeof setInterval>> = []
  connected = false

  constructor(
    readonly id: string,
    readonly name: string,
    private readonly service: number,
    private readonly tickMs = 250,
  ) {}

  async connect() {
    this.connected = true
  }

  onDisconnect(cb: () => void) {
    this.disconnectCallbacks.push(cb)
  }

  disconnect() {
    this.connected = false
    this.timers.forEach(clearInterval)
    this.timers = []
  }

  /** Simulates the device dropping the link. */
  drop() {
    this.disconnect()
    this.disconnectCallbacks.forEach((cb) => cb())
  }

  async characteristic(service: number, characteristic: number): Promise<BleCharacteristic> {
    if (service !== this.service) throw new Error('Service introuvable')
    const key = `${service}:${characteristic}`
    return {
      write: async (data) => {
        this.commands.push([...data])
        if (characteristic === CONTROL_POINT) this.emit(key, this.response(data[0]!))
      },
      subscribe: async (onValue) => {
        const set = this.listeners.get(key) ?? new Set()
        set.add(onValue)
        this.listeners.set(key, set)
        if (characteristic === INDOOR_BIKE_DATA) this.every(() => this.emit(key, this.bikeData()))
        if (characteristic === HEART_RATE_MEASUREMENT) this.every(() => this.emit(key, this.hrData()))
        return () => set.delete(onValue)
      },
    }
  }

  private every(fn: () => void) {
    this.timers.push(setInterval(fn, this.tickMs))
  }

  private emit(key: string, value: DataView) {
    queueMicrotask(() => this.listeners.get(key)?.forEach((l) => l(value)))
  }

  private response(opcode: number): DataView {
    return new DataView(Uint8Array.of(0x80, opcode, opcode === this.failOpcode ? 0x02 : 0x01).buffer)
  }

  private bikeData(): DataView {
    const b = new DataView(new ArrayBuffer(8))
    b.setUint16(0, 0x0044, true) // speed present, cadence + power present
    b.setUint16(2, Math.round(this.power * 14), true) // rough km/h × 100
    b.setUint16(4, 170, true) // 85 rpm
    b.setInt16(6, this.power, true)
    return b
  }

  private hrData(): DataView {
    return new DataView(Uint8Array.of(0x00, this.heartRate).buffer)
  }
}

export function createSimTransport(): BleTransport & { devices: SimDevice[] } {
  const devices: SimDevice[] = []
  return {
    supported: true,
    devices,
    async request(service) {
      const name = service === HEART_RATE_SERVICE ? 'Sim HR' : 'Sim Trainer'
      const device = new SimDevice(`sim-${devices.length}`, name, service === HEART_RATE_SERVICE ? HEART_RATE_SERVICE : FTMS_SERVICE)
      devices.push(device)
      return device
    },
  }
}
