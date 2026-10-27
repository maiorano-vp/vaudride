import { DEFAULT_PARAMS, type RiderParams } from '../physics/speed'
import type { BleCharacteristic, BleDevice } from './transport'

export const FTMS_SERVICE = 0x1826
export const INDOOR_BIKE_DATA = 0x2ad2
export const CONTROL_POINT = 0x2ad9

const OP = { requestControl: 0x00, setTargetPower: 0x05, start: 0x07, setSimulation: 0x11, response: 0x80 } as const
const RESULT_SUCCESS = 0x01
const MIN_GRADE_INTERVAL_MS = 250 // at most 4 grade updates per second
const RESPONSE_TIMEOUT_MS = 3000

export interface BikeData {
  speedKmh?: number
  cadenceRpm?: number
  powerW?: number
  heartRate?: number
  distanceM?: number
}

/** Indoor Bike Data (0x2AD2). Flag bit 0 is inverted: speed is present when it is 0. */
export function parseIndoorBikeData(v: DataView): BikeData {
  const flags = v.getUint16(0, true)
  const out: BikeData = {}
  let o = 2
  if (!(flags & 0x0001)) {
    out.speedKmh = v.getUint16(o, true) * 0.01
    o += 2
  }
  if (flags & 0x0002) o += 2 // average speed
  if (flags & 0x0004) {
    out.cadenceRpm = v.getUint16(o, true) * 0.5
    o += 2
  }
  if (flags & 0x0008) o += 2 // average cadence
  if (flags & 0x0010) {
    out.distanceM = v.getUint16(o, true) | (v.getUint8(o + 2) << 16)
    o += 3
  }
  if (flags & 0x0020) o += 2 // resistance level
  if (flags & 0x0040) {
    out.powerW = v.getInt16(o, true)
    o += 2
  }
  if (flags & 0x0080) o += 2 // average power
  if (flags & 0x0100) o += 5 // expended energy (total, per hour, per minute)
  if (flags & 0x0200) out.heartRate = v.getUint8(o)
  return out
}

/** Set Indoor Bike Simulation Parameters (0x11): wind 0.001 m/s, grade 0.01 %, Crr 0.0001, Cw 0.01 kg/m. */
export function simulationCommand(gradePct: number, p: RiderParams): Uint8Array {
  const b = new DataView(new ArrayBuffer(7))
  b.setUint8(0, OP.setSimulation)
  b.setInt16(1, 0, true)
  b.setInt16(3, Math.round(gradePct * 100), true)
  b.setUint8(5, Math.round(p.crr * 10_000))
  b.setUint8(6, Math.round(0.5 * p.rho * p.cda * 100))
  return new Uint8Array(b.buffer)
}

/** Set Target Power (0x05), ERG mode: sint16 watts. */
export function targetPowerCommand(watts: number): Uint8Array {
  const b = new DataView(new ArrayBuffer(3))
  b.setUint8(0, OP.setTargetPower)
  b.setInt16(1, Math.round(watts), true)
  return new Uint8Array(b.buffer)
}

/** The trainer answered a command with something other than "success". */
export class FtmsRefusal extends Error {}

/** What the trainer is asked to do: follow the course's grade (simulation) or hold a power (ERG). */
type Control = { kind: 'grade'; hundredths: number } | { kind: 'power'; watts: number }

interface Pending {
  opcode: number
  resolve: () => void
  reject: (e: Error) => void
}

const sameControl = (a: Control, b?: Control) =>
  !!b && a.kind === b.kind && (a.kind === 'grade' ? a.hundredths === (b as typeof a).hundredths : a.watts === (b as typeof a).watts)

export class FtmsTrainer {
  private controlPoint?: BleCharacteristic
  private unsubscribe: Array<() => void> = []
  private queue: Promise<unknown> = Promise.resolve()
  private pending?: Pending
  private sent?: Control
  private wanted?: Control
  private lastSentAt = 0
  private timer?: ReturnType<typeof setTimeout>

  constructor(
    private readonly device: BleDevice,
    private readonly onData: (data: BikeData) => void,
    private readonly params: RiderParams = DEFAULT_PARAMS,
    /** Called when the trainer refuses a target power: it does not support ERG. */
    private readonly onErgRefused: () => void = () => {},
  ) {}

  /** Subscribes and takes control. Called on first connect and again after every reconnect. */
  async setup() {
    this.unsubscribe.forEach((u) => u())
    this.unsubscribe = []
    this.sent = undefined
    const data = await this.device.characteristic(FTMS_SERVICE, INDOOR_BIKE_DATA)
    this.unsubscribe.push(await data.subscribe((v) => this.onData(parseIndoorBikeData(v))))
    this.controlPoint = await this.device.characteristic(FTMS_SERVICE, CONTROL_POINT)
    this.unsubscribe.push(await this.controlPoint.subscribe((v) => this.onResponse(v)))
    await this.command(Uint8Array.of(OP.requestControl))
    await this.command(Uint8Array.of(OP.start))
  }

  /** Grade in percent. Sent at most 4×/s and only when it changed (0.01 % resolution). */
  setGrade(gradePct: number) {
    this.want({ kind: 'grade', hundredths: Math.round(Math.max(-20, Math.min(20, gradePct)) * 100) })
  }

  /** ERG: the trainer holds this power whatever the rider's cadence or the grade. Same throttling as the grade. */
  setTargetPower(watts: number) {
    this.want({ kind: 'power', watts: Math.round(Math.max(0, Math.min(2000, watts))) })
  }

  private want(control: Control) {
    this.wanted = control
    if (this.timer) return
    const wait = Math.max(0, this.lastSentAt + MIN_GRADE_INTERVAL_MS - Date.now())
    this.timer = setTimeout(() => {
      this.timer = undefined
      void this.flush()
    }, wait)
  }

  stop() {
    clearTimeout(this.timer)
    this.timer = undefined
    this.unsubscribe.forEach((u) => u())
    this.unsubscribe = []
  }

  private async flush() {
    const control = this.wanted
    if (!control || sameControl(control, this.sent)) return
    this.lastSentAt = Date.now()
    try {
      await this.command(
        control.kind === 'grade' ? simulationCommand(control.hundredths / 100, this.params) : targetPowerCommand(control.watts),
      )
      this.sent = control
    } catch (e) {
      // not acknowledged: leave `sent` unchanged so the next call retries
      if (control.kind === 'power' && e instanceof FtmsRefusal) this.onErgRefused()
    }
  }

  /** One command at a time; resolves when the trainer answers 0x80 with success. */
  private command(bytes: Uint8Array): Promise<void> {
    const run = () =>
      new Promise<void>((resolve, reject) => {
        const timeout = setTimeout(() => {
          this.pending = undefined
          reject(new Error(`FTMS: pas de réponse à 0x${bytes[0]!.toString(16)}`))
        }, RESPONSE_TIMEOUT_MS)
        this.pending = {
          opcode: bytes[0]!,
          resolve: () => (clearTimeout(timeout), resolve()),
          reject: (e) => (clearTimeout(timeout), reject(e)),
        }
        this.controlPoint!.write(bytes).catch((e: Error) => {
          this.pending = undefined
          clearTimeout(timeout)
          reject(e)
        })
      })
    const result = this.queue.then(run, run)
    this.queue = result.catch(() => {})
    return result
  }

  private onResponse(v: DataView) {
    if (v.getUint8(0) !== OP.response || v.getUint8(1) !== this.pending?.opcode) return
    const { resolve, reject, opcode } = this.pending
    this.pending = undefined
    if (v.getUint8(2) === RESULT_SUCCESS) resolve()
    else reject(new FtmsRefusal(`FTMS: commande 0x${opcode.toString(16)} refusée (code ${v.getUint8(2)})`))
  }
}
