<script setup lang="ts">
import type { Kit } from '~/lib/kit/kit'
import { createCyclist, type Cyclist } from '~/lib/scene/cyclist'

const props = defineProps<{ kit: Kit }>()

const container = ref<HTMLDivElement>()
const status = ref<'loading' | 'ready' | 'error'>('loading')
let destroy: (() => void) | undefined

const TURN_RAD_PER_S = 0.5
const PREVIEW_CADENCE_RPM = 70
const PREVIEW_SPEED_MS = 6
const CAMERA_RANGE_M = 3.6
const CAMERA_PITCH_RAD = -0.12

async function start() {
  ;(window as unknown as { CESIUM_BASE_URL: string }).CESIUM_BASE_URL = '/cesium/'
  const Cesium = await import('cesium')
  await import('cesium/Build/Cesium/Widgets/widgets.css')
  Cesium.Ion.defaultAccessToken = ''

  const viewer = new Cesium.Viewer(container.value!, {
    baseLayer: false,
    baseLayerPicker: false,
    geocoder: false,
    homeButton: false,
    sceneModePicker: false,
    navigationHelpButton: false,
    animation: false,
    timeline: false,
    fullscreenButton: false,
    infoBox: false,
    selectionIndicator: false,
  })
  const { scene } = viewer
  scene.globe.show = false
  scene.skyAtmosphere.show = false
  scene.skyBox.show = false
  if (scene.sun) scene.sun.show = false
  if (scene.moon) scene.moon.show = false
  scene.fog.enabled = false
  scene.backgroundColor = Cesium.Color.fromCssColorString('#dfe8ef')
  scene.highDynamicRange = false
  if (scene.msaaSupported) scene.msaaSamples = 4
  const controller = scene.screenSpaceCameraController
  controller.enableRotate = controller.enableZoom = controller.enableTilt = controller.enableTranslate = controller.enableLook = false

  // a point in the air above Vaud, only there to give the model a place on the globe
  const position = Cesium.Cartesian3.fromDegrees(6.6, 46.5, 500)
  const enu = Cesium.Transforms.eastNorthUpToFixedFrame(position)
  const col = (i: number) => Cesium.Matrix4.getColumn(enu, i, new Cesium.Cartesian4())
  const [east, north, up] = [0, 1, 2].map((i) => new Cesium.Cartesian3(col(i).x, col(i).y, col(i).z))
  // a soft key light from above and a little to the side, whatever the camera does
  const light = new Cesium.Cartesian3(
    -up!.x * 0.9 + east!.x * 0.35 - north!.x * 0.2,
    -up!.y * 0.9 + east!.y * 0.35 - north!.y * 0.2,
    -up!.z * 0.9 + east!.z * 0.35 - north!.z * 0.2,
  )
  scene.light = new Cesium.DirectionalLight({ direction: Cesium.Cartesian3.normalize(light, light), intensity: 2.5 })
  const target = Cesium.Cartesian3.fromDegrees(6.6, 46.5, 500.8)

  let cyclist: Cyclist | undefined
  let build = 0
  const show = async (kit: Kit) => {
    const mine = ++build
    const next = await createCyclist(Cesium, scene, kit, 1)
    if (mine !== build) return next.destroy() // a newer kit was asked for meanwhile
    cyclist?.destroy()
    cyclist = next
    cyclist.place(position, 0)
  }
  await show(props.kit)
  const stop = watch(() => props.kit, (kit) => void show({ ...kit }).catch(() => {}), { deep: true })

  let angle = 0.9
  let last = performance.now()
  scene.preRender.addEventListener(() => {
    const now = performance.now()
    const dt = Math.min((now - last) / 1000, 0.25)
    last = now
    angle += TURN_RAD_PER_S * dt
    cyclist?.animate(dt, PREVIEW_SPEED_MS, PREVIEW_CADENCE_RPM)
    viewer.camera.lookAt(target, new Cesium.HeadingPitchRange(angle, CAMERA_PITCH_RAD, CAMERA_RANGE_M))
  })

  return () => {
    stop()
    viewer.destroy()
  }
}

onMounted(async () => {
  try {
    destroy = await start()
    status.value = 'ready'
  } catch {
    status.value = 'error'
  }
})
onBeforeUnmount(() => destroy?.())
</script>

<template>
  <div class="preview">
    <div ref="container" class="viewer" />
    <p v-if="status !== 'ready'" class="state" :role="status === 'error' ? 'alert' : 'status'">
      {{ $t(status === 'error' ? 'scene.error' : 'scene.loading') }}
    </p>
  </div>
</template>

<style scoped>
.preview {
  position: relative;
  min-height: 22rem;
  overflow: hidden;
  background: #dfe8ef;
  border-radius: 0.5rem;
}
.viewer {
  position: absolute;
  inset: 0;
}
.state {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  margin: 0;
}
</style>
