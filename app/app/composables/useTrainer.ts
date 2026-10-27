import { FtmsTrainer, FTMS_SERVICE, type BikeData } from '~/lib/ble/ftms'
import { CYCLING_POWER_MEASUREMENT, CYCLING_POWER_SERVICE, cadenceBetween, parseCyclingPower } from '~/lib/ble/cycling-power'

/** FTMS smart trainer: live data + grade control (simulation) or target power (ERG). */
export function useTrainer() {
  const data = ref<BikeData>({})
  /** Set when the trainer refuses a target power: it cannot do ERG. */
  const ergRefused = ref(false)
  let trainer: FtmsTrainer | undefined
  const device = useBleDevice(FTMS_SERVICE, async (d) => {
    trainer ??= new FtmsTrainer(d, (v) => (data.value = { ...data.value, ...v }), undefined, () => (ergRefused.value = true))
    await trainer.setup()
  })
  return {
    ...device,
    data,
    ergRefused,
    setGrade: (pct: number) => trainer?.setGrade(pct),
    setTargetPower: (watts: number) => trainer?.setTargetPower(watts),
  }
}

/** Fallback: Bluetooth power meter only, no resistance control. */
export function usePowerMeter() {
  const data = ref<BikeData>({})
  let prevCrank: { revs: number; time: number } | undefined
  const device = useBleDevice(CYCLING_POWER_SERVICE, async (d) => {
    const c = await d.characteristic(CYCLING_POWER_SERVICE, CYCLING_POWER_MEASUREMENT)
    await c.subscribe((v) => {
      const m = parseCyclingPower(v)
      const cadenceRpm = m.crank && prevCrank ? cadenceBetween(prevCrank, m.crank) : undefined
      if (m.crank) prevCrank = m.crank
      data.value = { ...data.value, powerW: m.powerW, ...(cadenceRpm !== undefined && { cadenceRpm }) }
    })
  })
  return { ...device, data }
}
