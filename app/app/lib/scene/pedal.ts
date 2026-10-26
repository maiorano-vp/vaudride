/** Pedalling geometry of the cyclist model (see pipeline/make_cyclist.py), in the model's Y (up) / Z (forward) plane. */
export const BOTTOM_BRACKET = { y: 0.3, z: 0 }
export const CRANK_LENGTH = 0.17
export const HIP = { y: 0.93, z: -0.15, x: 0.09 }
export const THIGH = 0.42
export const SHIN = 0.42
export const WHEEL_RADIUS = 0.34
export const WHEEL_FRONT = { y: 0.34, z: 0.6 }
export const WHEEL_REAR = { y: 0.34, z: -0.42 }
const ANKLE_ABOVE_PEDAL = 0.07

export type Side = 'left' | 'right'

/** Crank angle 0 = left pedal at the top, then forward over the front. The right pedal is opposite. */
export function pedalPosition(crank: number, side: Side) {
  const a = side === 'left' ? crank : crank + Math.PI
  return { y: BOTTOM_BRACKET.y + CRANK_LENGTH * Math.cos(a), z: BOTTOM_BRACKET.z + CRANK_LENGTH * Math.sin(a) }
}

/**
 * Rotations about the X axis for the thigh (at the hip) and the shin (relative to the thigh, at the knee)
 * so the ankle sits on the pedal, with the knee pointing forward. Both limbs hang down (-Y) at rotation 0.
 */
export function legPose(crank: number, side: Side) {
  const pedal = pedalPosition(crank, side)
  const ankle = { y: pedal.y + ANKLE_ABOVE_PEDAL, z: pedal.z }
  const dy = ankle.y - HIP.y
  const dz = ankle.z - HIP.z
  const reach = Math.min(Math.hypot(dy, dz), THIGH + SHIN - 1e-4)
  const uy = dy / Math.hypot(dy, dz)
  const uz = dz / Math.hypot(dy, dz)
  // two-bone solution: the knee is `along` the hip-ankle line and `bend` off it, on the side that points forward (+Z)
  const along = (THIGH ** 2 - SHIN ** 2 + reach ** 2) / (2 * reach)
  const bend = Math.sqrt(Math.max(THIGH ** 2 - along ** 2, 0))
  const [py, pz] = uy >= 0 ? [-uz, uy] : [uz, -uy]
  const knee = { y: HIP.y + uy * along + py * bend, z: HIP.z + uz * along + pz * bend }
  const thigh = Math.atan2(-(knee.z - HIP.z), -(knee.y - HIP.y))
  const shin = Math.atan2(-(ankle.z - knee.z), -(ankle.y - knee.y)) - thigh
  return { thigh, shin }
}
