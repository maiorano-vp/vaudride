import { reconnectOnDrop, type BleDevice, type BleTransport, type ConnectionState } from './transport'

/** Picks a device, connects, runs `setup` (subscriptions) and keeps the link alive. */
export async function connectDevice(
  transport: BleTransport,
  service: number,
  setup: (device: BleDevice) => Promise<void>,
  onState: (state: ConnectionState) => void,
): Promise<{ device: BleDevice; stop: () => void }> {
  const device = await transport.request(service)
  await device.connect()
  await setup(device)
  const stop = reconnectOnDrop(device, () => setup(device), onState)
  onState('connected')
  return { device, stop }
}
