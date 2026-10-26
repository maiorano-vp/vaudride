<script setup lang="ts">
import type { Property } from 'cesium'
import { pointAt, type CourseProfile } from '~/lib/course/profile'
import { bearingRad, distanceAfter, interpolateAt, lerpAngle } from '~/lib/scene/camera'

const props = defineProps<{ course: CourseProfile; distanceM: number; speedMs: number }>()

const TERRAIN_URL = 'https://3d.geo.admin.ch/ch.swisstopo.terrain.3d/v1/'
const IMAGERY_URL = 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.swissimage/default/current/3857/{z}/{x}/{y}.jpeg'
const BUILDINGS_URL = 'https://3d.geo.admin.ch/ch.swisstopo.swissbuildings3d.3d/v1/tileset.json'
const VEGETATION_URL = 'https://3d.geo.admin.ch/ch.swisstopo.vegetation.3d/v1/tileset.json'
// swissBUILDINGS3D is untextured: roofs, plaster walls and windows are drawn procedurally.
// Everything is computed in the model's own frame (small numbers, float-safe): local "up" comes from the model origin.
const BUILDING_SHADER = `
float hash(vec2 p) { return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453); }

void fragmentMain(FragmentInput fsInput, inout czm_modelMaterial material) {
  vec3 p = fsInput.attributes.positionMC;
  vec3 upMC = normalize(transpose(mat3(czm_model)) * normalize(czm_model[3].xyz));
  vec3 n = normalize(transpose(mat3(czm_modelView)) * fsInput.attributes.normalEC);
  float variant = hash(floor(p.xy / 30.0) + floor(p.z / 30.0));

  if (dot(n, upMC) > 0.5) {
    // roof: terracotta or slate, with tile rows
    vec3 roof = variant > 0.45 ? vec3(0.55, 0.27, 0.20) : vec3(0.30, 0.31, 0.34);
    vec3 along = normalize(cross(upMC, vec3(0.0, 0.0, 1.0)) + vec3(0.001));
    material.diffuse = roof * (0.85 + 0.15 * step(0.5, fract(dot(p, along) / 0.45)));
  } else {
    // wall: plaster in a few tints, floors every 3 m, windows on each floor
    vec3 plaster = variant < 0.25 ? vec3(0.91, 0.87, 0.78)
                 : variant < 0.55 ? vec3(0.86, 0.84, 0.80)
                 : variant < 0.80 ? vec3(0.88, 0.78, 0.60) : vec3(0.84, 0.72, 0.68);
    vec3 tangent = normalize(cross(upMC, n));
    float h = dot(p, upMC) / 3.0;
    float u = dot(p, tangent) / 3.2;
    bool win = fract(h) > 0.32 && fract(h) < 0.70 && fract(u) > 0.30 && fract(u) < 0.62;
    material.diffuse = win ? vec3(0.17, 0.23, 0.31) : plaster;
  }
}
`
const LOOKAHEAD_M = 40 // for the travel heading
const TARGET_AHEAD_M = 12 // camera looks at a point this far in front of the rider
const CAMERA_RANGE_M = 32 // → camera ~20 m behind the rider
const CAMERA_PITCH_RAD = -0.24
const FOV_DEG = 70
const FOV_SPEED_GAIN = 1.2 // degrees of extra field of view per m/s: speed feels faster
const FOV_MAX_EXTRA_DEG = 12
const AVATAR_SCALE = 1.6
const SAMPLE_EVERY = 8 // route points between terrain height samples (~40 m)

const container = ref<HTMLDivElement>()
const status = ref<'loading' | 'ready' | 'error'>('loading')
let destroy: (() => void) | undefined
let base = { d: 0, t: performance.now() }

watch(
  () => props.distanceM,
  (d) => (base = { d, t: performance.now() }),
)

async function start() {
  ;(window as unknown as { CESIUM_BASE_URL: string }).CESIUM_BASE_URL = '/cesium/'
  const Cesium = await import('cesium')
  await import('cesium/Build/Cesium/Widgets/widgets.css')
  Cesium.Ion.defaultAccessToken = ''

  const terrain = await Cesium.CesiumTerrainProvider.fromUrl(TERRAIN_URL)
  const imagery = new Cesium.UrlTemplateImageryProvider({
    url: IMAGERY_URL,
    tilingScheme: new Cesium.WebMercatorTilingScheme(),
    maximumLevel: 20,
    rectangle: Cesium.Rectangle.fromDegrees(5.9, 45.7, 10.6, 47.9), // SWISSIMAGE only covers Switzerland
    credit: '© swisstopo',
  })
  const viewer = new Cesium.Viewer(container.value!, {
    baseLayer: new Cesium.ImageryLayer(imagery),
    terrainProvider: terrain,
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

  const { points, distance_m: total } = props.course
  viewer.entities.add({
    polyline: {
      positions: Cesium.Cartesian3.fromDegreesArray(points.flatMap((p) => [p.lon, p.lat])),
      width: 4,
      clampToGround: true,
      material: Cesium.Color.fromCssColorString('#e63946').withAlpha(0.75),
    },
  })

  // Heights from the same terrain the viewer draws, so avatar and camera sit on the ground (not the DEM profile).
  const idx = points.map((_, i) => i).filter((i) => i % SAMPLE_EVERY === 0 || i === points.length - 1)
  const ds = idx.map((i) => points[i]!.d)
  let hs = idx.map((i) => points[i]!.ele)
  try {
    const sampled = await Cesium.sampleTerrainMostDetailed(
      terrain,
      idx.map((i) => Cesium.Cartographic.fromDegrees(points[i]!.lon, points[i]!.lat)),
    )
    hs = sampled.map((c, k) => c.height ?? hs[k]!)
  } catch {
    // keep profile altitudes as a fallback
  }

  const { scene } = viewer
  scene.globe.maximumScreenSpaceError = 1.5

  // Fixed sunny late morning (summer, Vaud) so the sky is always blue and slopes are shaded the same way for everyone
  viewer.clock.currentTime = Cesium.JulianDate.fromIso8601('2026-06-21T09:30:00Z')
  viewer.clock.shouldAnimate = false
  scene.globe.enableLighting = true
  scene.light = new Cesium.SunLight()
  scene.highDynamicRange = false
  scene.globe.showGroundAtmosphere = true
  scene.skyAtmosphere.brightnessShift = 0.1
  scene.fog.enabled = true
  scene.fog.density = 0.00018 // soft haze toward the horizon
  scene.fog.minimumBrightness = 0.12
  if (scene.msaaSupported) scene.msaaSamples = 4 // smooth road, building and avatar edges

  // Real 3D buildings and trees (swisstopo 3D Tiles); added as they load so the scene never waits on them
  const addTileset = (url: string, customShader?: InstanceType<typeof Cesium.CustomShader>) =>
    Cesium.Cesium3DTileset.fromUrl(url, { maximumScreenSpaceError: 6 })
      .then((tileset) => {
        if (customShader) tileset.customShader = customShader
        viewer.scene.primitives.add(tileset)
      })
      .catch(() => {}) // optional layers: the terrain scene still works without them
  void addTileset(BUILDINGS_URL, new Cesium.CustomShader({ fragmentShaderText: BUILDING_SHADER }))
  void addTileset(VEGETATION_URL)
  const frustum = viewer.camera.frustum as InstanceType<typeof Cesium.PerspectiveFrustum>
  let fovDeg = FOV_DEG

  // Cyclist built from primitives, posed each frame: x = forward, y = up, z = right (local frame)
  let avatar = Cesium.Cartesian3.fromDegrees(points[0]!.lon, points[0]!.lat, hs[0]!)
  let heading = bearingRad(points[0]!.lat, points[0]!.lon, points[1]!.lat, points[1]!.lon)
  let pitch = CAMERA_PITCH_RAD
  let enu = Cesium.Transforms.eastNorthUpToFixedFrame(avatar)
  let orientation = Cesium.Quaternion.IDENTITY.clone()

  const part = (f: number, u: number, r: number) => {
    const sin = Math.sin(heading)
    const cos = Math.cos(heading)
    const local = new Cesium.Cartesian3(
      (f * sin + r * cos) * AVATAR_SCALE,
      (f * cos - r * sin) * AVATAR_SCALE,
      u * AVATAR_SCALE,
    )
    return Cesium.Matrix4.multiplyByPoint(enu, local, new Cesium.Cartesian3())
  }
  const addPart = (f: number, u: number, r: number, graphics: Record<string, unknown>) =>
    viewer.entities.add({
      position: new Cesium.CallbackPositionProperty(() => part(f, u, r), false),
      orientation: new Cesium.CallbackProperty(() => orientation, false) as unknown as Property,
      ...graphics,
    })
  const s = AVATAR_SCALE
  const black = Cesium.Color.fromCssColorString('#222')
  const jersey = Cesium.Color.fromCssColorString('#0a7d3b')
  const wheel = { cylinder: { length: 0.05 * s, topRadius: 0.34 * s, bottomRadius: 0.34 * s, material: black } }
  addPart(-0.55, 0.34, 0, wheel)
  addPart(0.55, 0.34, 0, wheel)
  addPart(0, 0.58, 0, { box: { dimensions: new Cesium.Cartesian3(1.0 * s, 0.05 * s, 0.05 * s), material: black } })
  addPart(-0.05, 1.05, 0, { ellipsoid: { radii: new Cesium.Cartesian3(0.32 * s, 0.24 * s, 0.18 * s), material: jersey } })
  addPart(0.28, 1.34, 0, { ellipsoid: { radii: new Cesium.Cartesian3(0.11 * s, 0.11 * s, 0.1 * s), material: Cesium.Color.WHITE } })
  addPart(0.1, 0.8, 0.1, { ellipsoid: { radii: new Cesium.Cartesian3(0.07 * s, 0.3 * s, 0.07 * s), material: black } })
  addPart(0.1, 0.8, -0.1, { ellipsoid: { radii: new Cesium.Cartesian3(0.07 * s, 0.3 * s, 0.07 * s), material: black } })

  viewer.scene.preRender.addEventListener(() => {
    const d = distanceAfter(base.d, props.speedMs, (performance.now() - base.t) / 1000, total)
    const here = pointAt(props.course, d)
    const ahead = pointAt(props.course, Math.min(d + LOOKAHEAD_M, total))
    const behind = pointAt(props.course, Math.max(d - LOOKAHEAD_M, 0))
    const target = ahead.d - here.d > 5
      ? bearingRad(here.lat, here.lon, ahead.lat, ahead.lon)
      : bearingRad(behind.lat, behind.lon, here.lat, here.lon)
    heading = lerpAngle(heading, target, 0.05)

    avatar = Cesium.Cartesian3.fromDegrees(here.lon, here.lat, interpolateAt(ds, hs, d))
    enu = Cesium.Transforms.eastNorthUpToFixedFrame(avatar)
    // local axes in the ENU frame: x forward, y up, z right
    const fwd = new Cesium.Cartesian3(Math.sin(heading), Math.cos(heading), 0)
    const up = new Cesium.Cartesian3(0, 0, 1)
    const right = new Cesium.Cartesian3(Math.cos(heading), -Math.sin(heading), 0)
    const local = Cesium.Matrix3.fromArray([fwd.x, fwd.y, fwd.z, up.x, up.y, up.z, right.x, right.y, right.z])
    const world = Cesium.Matrix3.multiply(Cesium.Matrix4.getMatrix3(enu, new Cesium.Matrix3()), local, new Cesium.Matrix3())
    orientation = Cesium.Quaternion.fromRotationMatrix(world, orientation)

    // steeper road → look a little further down so the camera keeps clear of the slope behind
    pitch += (CAMERA_PITCH_RAD - 0.6 * Math.atan(here.grade / 100) - pitch) * 0.05
    const lookAt = Cesium.Cartesian3.fromDegrees(
      pointAt(props.course, Math.min(d + TARGET_AHEAD_M, total)).lon,
      pointAt(props.course, Math.min(d + TARGET_AHEAD_M, total)).lat,
      interpolateAt(ds, hs, Math.min(d + TARGET_AHEAD_M, total)) + 1.5,
    )
    fovDeg += (FOV_DEG + Math.min(props.speedMs * FOV_SPEED_GAIN, FOV_MAX_EXTRA_DEG) - fovDeg) * 0.05
    frustum.fov = Cesium.Math.toRadians(fovDeg)
    viewer.camera.lookAt(lookAt, new Cesium.HeadingPitchRange(heading, pitch, CAMERA_RANGE_M))
  })

  return () => viewer.destroy()
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
  <div class="scene">
    <div ref="container" class="viewer" />
    <p v-if="status !== 'ready'" class="state" :role="status === 'error' ? 'alert' : 'status'">
      {{ $t(status === 'error' ? 'scene.error' : 'scene.loading') }}
    </p>
    <span class="attribution">© swisstopo</span>
  </div>
</template>

<style scoped>
.scene {
  position: relative;
  height: 100%;
  overflow: hidden;
  background: #dfe8ef;
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
.attribution {
  position: absolute;
  left: 0.5rem;
  bottom: 0.5rem;
  padding: 0.1rem 0.4rem;
  font-size: 0.75rem;
  color: #fff;
  background: rgb(0 0 0 / 55%);
  border-radius: 0.25rem;
  pointer-events: none;
}
</style>
