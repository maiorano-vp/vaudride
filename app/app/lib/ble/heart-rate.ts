export const HEART_RATE_SERVICE = 0x180d
export const HEART_RATE_MEASUREMENT = 0x2a37

/** Heart Rate Measurement: flag bit 0 = value is uint16 instead of uint8. */
export function parseHeartRate(v: DataView): number {
  return v.getUint8(0) & 0x01 ? v.getUint16(1, true) : v.getUint8(1)
}
