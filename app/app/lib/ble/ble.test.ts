import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { cadenceBetween, parseCyclingPower } from './cycling-power'
import { FtmsTrainer, FTMS_SERVICE, parseIndoorBikeData, simulationCommand, targetPowerCommand, type BikeData } from './ftms'
import { parseHeartRate } from './heart-rate'
import { createSimTransport, type SimDevice } from './sim'
import { reconnectOnDrop } from './transport'
import { DEFAULT_PARAMS } from '../physics/speed'

const view = (...bytes: number[]) => new DataView(Uint8Array.from(bytes).buffer)

describe('parsers', () => {
  it('heart rate uint8 and uint16', () => {
    expect(parseHeartRate(view(0x00, 142))).toBe(142)
    expect(parseHeartRate(view(0x01, 0x2c, 0x01))).toBe(300)
  })

  it('cycling power with crank data', () => {
    // flags 0x0020 (crank), power 215 W, crank revs 10, time 2048
    const p = parseCyclingPower(view(0x20, 0x00, 215, 0x00, 10, 0x00, 0x00, 0x08))
    expect(p.powerW).toBe(215)
    expect(p.crank).toEqual({ revs: 10, time: 2048 })
  })

  it('cadence between crank events, with rollover', () => {
    expect(cadenceBetween({ revs: 0, time: 0 }, { revs: 1, time: 1024 })).toBeCloseTo(60)
    expect(cadenceBetween({ revs: 65535, time: 65000 }, { revs: 1, time: 488 })).toBeCloseTo(120)
    expect(cadenceBetween({ revs: 5, time: 100 }, { revs: 5, time: 100 })).toBeUndefined()
  })

  it('indoor bike data: speed, cadence, power', () => {
    // flags 0x0044; speed 3250 (32.5 km/h); cadence 170 (85 rpm); power 220
    const d = parseIndoorBikeData(view(0x44, 0x00, 0xb2, 0x0c, 170, 0, 220, 0))
    expect(d).toEqual({ speedKmh: 32.5, cadenceRpm: 85, powerW: 220 })
  })

  it('target power command is a sint16 in watts', () => {
    expect([...targetPowerCommand(250)]).toEqual([0x05, 250, 0])
    expect([...targetPowerCommand(300)]).toEqual([0x05, 0x2c, 0x01])
  })

  it('simulation command encodes grade 0.01 %, Crr and Cw', () => {
    const c = simulationCommand(-7.5, DEFAULT_PARAMS)
    const v = new DataView(c.buffer)
    expect(c[0]).toBe(0x11)
    expect(v.getInt16(3, true)).toBe(-750)
    expect(c[5]).toBe(40) // 0.004
    expect(c[6]).toBe(19) // 0.192 kg/m
  })
})

describe('FtmsTrainer on the simulator', () => {
  let device: SimDevice
  let trainer: FtmsTrainer
  let received: BikeData[]

  beforeEach(async () => {
    vi.useFakeTimers()
    received = []
    device = (await createSimTransport().request(FTMS_SERVICE)) as SimDevice
    await device.connect()
    trainer = new FtmsTrainer(device, (d) => received.push(d))
  })

  afterEach(() => {
    trainer.stop()
    device.disconnect()
    vi.useRealTimers()
  })

  it('requests control then starts, in order', async () => {
    await trainer.setup()
    expect(device.commands).toEqual([[0x00], [0x07]])
  })

  it('streams bike data', async () => {
    await trainer.setup()
    await vi.advanceTimersByTimeAsync(300)
    expect(received.at(-1)?.powerW).toBe(180)
  })

  it('sends grade only when it changes and at most every 250 ms', async () => {
    await trainer.setup()
    device.commands.length = 0
    trainer.setGrade(5)
    await vi.advanceTimersByTimeAsync(10)
    trainer.setGrade(5) // unchanged
    trainer.setGrade(6)
    trainer.setGrade(7) // coalesced with the previous one
    await vi.advanceTimersByTimeAsync(1000)
    const grades = device.commands.filter((c) => c[0] === 0x11).map((c) => new DataView(Uint8Array.from(c).buffer).getInt16(3, true))
    expect(grades).toEqual([500, 700])
  })

  it('retries a refused grade on the next call', async () => {
    await trainer.setup()
    device.failOpcode = 0x11
    trainer.setGrade(4)
    await vi.advanceTimersByTimeAsync(500)
    device.failOpcode = undefined
    trainer.setGrade(4)
    await vi.advanceTimersByTimeAsync(500)
    expect(device.commands.filter((c) => c[0] === 0x11)).toHaveLength(2)
  })

  it('ERG: sends the target power, only when it changes', async () => {
    await trainer.setup()
    device.commands.length = 0
    trainer.setTargetPower(250)
    await vi.advanceTimersByTimeAsync(300)
    trainer.setTargetPower(250) // unchanged
    await vi.advanceTimersByTimeAsync(300)
    trainer.setTargetPower(180)
    await vi.advanceTimersByTimeAsync(300)
    expect(device.commands).toEqual([
      [0x05, 250, 0],
      [0x05, 180, 0],
    ])
    expect(device.power).toBe(180) // the simulated trainer holds it
  })

  it('goes back to the grade after ERG, even if the grade is the one last sent', async () => {
    await trainer.setup()
    trainer.setGrade(4)
    await vi.advanceTimersByTimeAsync(300)
    trainer.setTargetPower(200)
    await vi.advanceTimersByTimeAsync(300)
    device.commands.length = 0
    trainer.setGrade(4)
    await vi.advanceTimersByTimeAsync(300)
    expect(device.commands.map((c) => c[0])).toEqual([0x11])
  })

  it('sends the simulation again, with the new weight and drag area, when the rider settings change', async () => {
    await trainer.setup()
    trainer.setGrade(4)
    await vi.advanceTimersByTimeAsync(300)
    device.commands.length = 0
    trainer.setParams({ ...DEFAULT_PARAMS, cda: 0.4 })
    await vi.advanceTimersByTimeAsync(300)
    expect(device.commands).toHaveLength(1)
    expect(device.commands[0]![0]).toBe(0x11)
    expect(device.commands[0]![6]).toBe(Math.round(0.5 * DEFAULT_PARAMS.rho * 0.4 * 100)) // Cw follows CdA
    expect(new DataView(Uint8Array.from(device.commands[0]!).buffer).getInt16(3, true)).toBe(400) // same grade
  })

  it('tells the caller when the trainer refuses ERG', async () => {
    const refused = vi.fn()
    const erg = new FtmsTrainer(device, () => {}, DEFAULT_PARAMS, refused)
    await erg.setup()
    device.failOpcode = 0x05
    erg.setTargetPower(200)
    await vi.advanceTimersByTimeAsync(300)
    expect(refused).toHaveBeenCalledTimes(1)
    erg.stop()
  })
})

describe('reconnectOnDrop', () => {
  it('reconnects and runs setup again after a drop', async () => {
    vi.useFakeTimers()
    const device = (await createSimTransport().request(FTMS_SERVICE)) as SimDevice
    await device.connect()
    const setup = vi.fn(async () => {})
    const states: string[] = []
    const stop = reconnectOnDrop(device, setup, (s) => states.push(s))
    device.drop()
    await vi.advanceTimersByTimeAsync(10)
    expect(setup).toHaveBeenCalledTimes(1)
    expect(device.connected).toBe(true)
    expect(states).toEqual(['reconnecting', 'connected'])
    stop()
    expect(states.at(-1)).toBe('stopped')
    vi.useRealTimers()
  })
})
