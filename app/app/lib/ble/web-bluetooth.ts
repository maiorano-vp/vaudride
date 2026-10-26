/// <reference types="web-bluetooth" />
import type { BleCharacteristic, BleDevice, BleTransport } from './transport'

class WebBleDevice implements BleDevice {
  private server?: BluetoothRemoteGATTServer

  constructor(private readonly device: BluetoothDevice) {}

  get id() {
    return this.device.id
  }

  get name() {
    return this.device.name ?? 'Appareil BLE'
  }

  async connect() {
    this.server = await this.device.gatt!.connect()
  }

  async characteristic(service: number, characteristic: number): Promise<BleCharacteristic> {
    if (!this.server) throw new Error('Appareil non connecté')
    const c = await (await this.server.getPrimaryService(service)).getCharacteristic(characteristic)
    return {
      write: (data) => c.writeValueWithResponse(data as BufferSource),
      subscribe: async (onValue) => {
        const handler = () => onValue(c.value!)
        c.addEventListener('characteristicvaluechanged', handler)
        await c.startNotifications()
        return () => {
          c.removeEventListener('characteristicvaluechanged', handler)
          c.stopNotifications().catch(() => {})
        }
      },
    }
  }

  onDisconnect(callback: () => void) {
    this.device.addEventListener('gattserverdisconnected', callback)
  }

  disconnect() {
    this.device.gatt?.disconnect()
  }
}

export const webBluetooth: BleTransport = {
  get supported() {
    return typeof navigator !== 'undefined' && 'bluetooth' in navigator
  },
  async request(service) {
    const device = await navigator.bluetooth.requestDevice({ filters: [{ services: [service] }] })
    return new WebBleDevice(device)
  },
}
