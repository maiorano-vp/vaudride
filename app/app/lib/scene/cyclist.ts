import type { Model, Scene } from 'cesium'
import { applyKit, visibleNodes, type Kit } from '../kit/kit'
import { BOTTOM_BRACKET, HIP, legPose, pedalPosition, THIGH, WHEEL_FRONT, WHEEL_RADIUS, WHEEL_REAR, type Side } from './pedal'

type CesiumNs = typeof import('cesium')

const MODEL_URL = '/models/cyclist.glb'
const PEDAL_X = 0.11 // pedals sit outside the crank arms

let glb: Promise<Uint8Array> | undefined
/** The base model, fetched once per page. */
const baseModel = () =>
  (glb ??= fetch(MODEL_URL).then(async (r) => {
    if (!r.ok) throw new Error(`${MODEL_URL}: ${r.status}`)
    return new Uint8Array(await r.arrayBuffer())
  }))

export interface Cyclist {
  model: Model
  /** Puts the cyclist at `position` (Earth-fixed) facing `heading` (radians clockwise from north). */
  place(position: import('cesium').Cartesian3, heading: number): void
  /** Spins the wheels with the speed and the cranks with the cadence. */
  animate(dtS: number, speedMs: number, cadenceRpm: number): void
  destroy(): void
}

/** Loads the cyclist in the kit's colours and styles and adds it to the scene. */
export async function createCyclist(Cesium: CesiumNs, scene: Scene, kit: Kit, scale: number): Promise<Cyclist> {
  const model = await Cesium.Model.fromGltfAsync({ gltf: applyKit(await baseModel(), kit), scale })
  scene.primitives.add(model)
  if (!model.ready) await new Promise<void>((resolve) => model.readyEvent.addEventListener(() => resolve()))

  const node = (name: string) => model.getNode(name)
  for (const [name, show] of Object.entries(visibleNodes(kit))) node(name).show = show

  const at = (y: number, z: number, x = 0) => new Cesium.Cartesian3(x, y, z)
  const matrix = (translation: import('cesium').Cartesian3, rotationX = 0) =>
    Cesium.Matrix4.fromRotationTranslation(Cesium.Matrix3.fromRotationX(rotationX), translation)

  const wheelFront = node('wheel_front')
  const wheelRear = node('wheel_rear')
  const crankNode = node('crank')
  const pedals = { left: node('pedal_left'), right: node('pedal_right') }
  const legs = {
    left: { thigh: node('thigh_left'), shin: node('shin_left'), x: HIP.x },
    right: { thigh: node('thigh_right'), shin: node('shin_right'), x: -HIP.x },
  }
  let wheelAngle = 0
  let crankAngle = 0
  const scratch = new Cesium.Matrix4()

  const pose = () => {
    wheelFront.matrix = matrix(at(WHEEL_FRONT.y, WHEEL_FRONT.z), wheelAngle)
    wheelRear.matrix = matrix(at(WHEEL_REAR.y, WHEEL_REAR.z), wheelAngle)
    crankNode.matrix = matrix(at(BOTTOM_BRACKET.y, BOTTOM_BRACKET.z), crankAngle)
    for (const side of ['left', 'right'] as Side[]) {
      const p = pedalPosition(crankAngle, side)
      pedals[side].matrix = matrix(at(p.y, p.z, side === 'left' ? PEDAL_X : -PEDAL_X))
      const { thigh, shin } = legPose(crankAngle, side)
      legs[side].thigh.matrix = matrix(at(HIP.y, HIP.z, legs[side].x), thigh)
      legs[side].shin.matrix = matrix(at(-THIGH, 0), shin)
    }
  }
  pose()

  return {
    model,
    place(position, heading) {
      const enu = Cesium.Transforms.eastNorthUpToFixedFrame(position)
      // the model's nose points east; a bearing θ clockwise from north is π/2 - θ counter-clockwise from east
      const facing = Cesium.Matrix4.fromRotationTranslation(Cesium.Matrix3.fromRotationZ(Math.PI / 2 - heading))
      model.modelMatrix = Cesium.Matrix4.multiply(enu, facing, scratch).clone(model.modelMatrix)
    },
    animate(dtS, speedMs, cadenceRpm) {
      wheelAngle = (wheelAngle + (speedMs * dtS) / WHEEL_RADIUS) % (2 * Math.PI)
      crankAngle = (crankAngle + ((cadenceRpm * 2 * Math.PI) / 60) * dtS) % (2 * Math.PI)
      pose()
    },
    destroy() {
      scene.primitives.remove(model)
    },
  }
}
