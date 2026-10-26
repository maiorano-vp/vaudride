import { HEART_RATE_MEASUREMENT, HEART_RATE_SERVICE, parseHeartRate } from '~/lib/ble/heart-rate'

export function useHeartRate() {
  const bpm = ref<number>()
  const device = useBleDevice(HEART_RATE_SERVICE, async (d) => {
    const c = await d.characteristic(HEART_RATE_SERVICE, HEART_RATE_MEASUREMENT)
    await c.subscribe((v) => (bpm.value = parseHeartRate(v)))
  })
  return { ...device, bpm }
}
