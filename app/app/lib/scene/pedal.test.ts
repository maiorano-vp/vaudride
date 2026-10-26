import { describe, expect, it } from 'vitest'
import { HIP, legPose, pedalPosition, SHIN, THIGH } from './pedal'

/** Rebuilds the knee and ankle from the joint rotations, the way the model's node hierarchy does. */
function joints(crank: number, side: 'left' | 'right') {
  const { thigh, shin } = legPose(crank, side)
  const knee = { y: HIP.y - THIGH * Math.cos(thigh), z: HIP.z - THIGH * Math.sin(thigh) }
  const ankle = { y: knee.y - SHIN * Math.cos(thigh + shin), z: knee.z - SHIN * Math.sin(thigh + shin) }
  return { knee, ankle }
}

describe('pedalling', () => {
  it('keeps the left and right pedals opposite', () => {
    const l = pedalPosition(0.7, 'left')
    const r = pedalPosition(0.7, 'right')
    expect(l.y + r.y).toBeCloseTo(2 * 0.3, 6)
    expect(l.z + r.z).toBeCloseTo(0, 6)
  })

  it('puts the left pedal at the top at angle 0 and at the front a quarter turn later', () => {
    expect(pedalPosition(0, 'left').y).toBeGreaterThan(pedalPosition(0, 'right').y)
    expect(pedalPosition(Math.PI / 2, 'left').z).toBeGreaterThan(0.1)
  })

  it('keeps both leg segments at their length with the ankle on the pedal, all around the circle', () => {
    for (let i = 0; i < 24; i++) {
      for (const side of ['left', 'right'] as const) {
        const crank = (2 * Math.PI * i) / 24
        const { ankle } = joints(crank, side)
        const pedal = pedalPosition(crank, side)
        expect(ankle.y).toBeCloseTo(pedal.y + 0.07, 4)
        expect(ankle.z).toBeCloseTo(pedal.z, 4)
      }
    }
  })

  it('bends the knee forward of the hip-ankle line', () => {
    for (let i = 0; i < 24; i++) {
      const { knee, ankle } = joints((2 * Math.PI * i) / 24, 'left')
      // cross product of (ankle - hip) and (knee - hip): positive = knee in front (+Z side)
      const cross = (ankle.y - HIP.y) * (knee.z - HIP.z) - (ankle.z - HIP.z) * (knee.y - HIP.y)
      expect(cross).toBeLessThan(0)
    }
  })
})
