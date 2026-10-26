type Devices = { trainer: ReturnType<typeof useTrainer>; powerMeter: ReturnType<typeof usePowerMeter>; strap: ReturnType<typeof useHeartRate> }
const byApp = new WeakMap<object, Devices>()

/** One set of BLE connections per app, so pairing on the intro page survives navigation to the ride. */
export function useDevices(): Devices {
  const app = useNuxtApp()
  let devices = byApp.get(app)
  if (!devices) {
    devices = effectScope(true).run(() => ({ trainer: useTrainer(), powerMeter: usePowerMeter(), strap: useHeartRate() }))!
    byApp.set(app, devices)
  }
  return devices
}
