<script setup lang="ts">
import { pointAt, type CourseProfile } from '~/lib/course/profile'
import type { Segment } from '~/lib/segments/tracker'

const props = defineProps<{ course: CourseProfile; distanceM: number; others?: Array<{ id: string; distanceM: number }>; segments?: Segment[]; pois?: Array<{ id: string; distance_m: number }>; seekable?: boolean }>()
const emit = defineEmits<{ seek: [distanceM: number] }>()

const W = 1000
const H = 200
const MAX_POINTS = 400

const geometry = computed(() => {
  const pts = props.course.points
  const step = Math.max(1, Math.floor(pts.length / MAX_POINTS))
  const sampled = pts.filter((_, i) => i % step === 0)
  const eles = pts.map((p) => p.ele)
  const min = Math.min(...eles)
  const range = Math.max(...eles) - min || 1
  const x = (d: number) => (d / props.course.distance_m) * W
  const y = (e: number) => H - ((e - min) / range) * (H - 10) - 5
  const line = sampled.map((p) => `${x(p.d).toFixed(1)},${y(p.ele).toFixed(1)}`).join(' ')
  return { line, area: `0,${H} ${line} ${W},${H}`, x, y }
})

const bands = computed(() =>
  (props.segments ?? []).map((s) => {
    const x0 = geometry.value.x(s.start_m)
    return { id: s.id, x: x0, width: Math.max(2, geometry.value.x(s.end_m) - x0) }
  }),
)

const poiMarkers = computed(() =>
  (props.pois ?? []).map((p) => {
    const d = Math.min(Math.max(p.distance_m, 0), props.course.distance_m)
    return { id: p.id, cx: geometry.value.x(d), cy: geometry.value.y(pointAt(props.course, d).ele) }
  }),
)

const otherMarkers = computed(() =>
  (props.others ?? []).map((o) => {
    const d = Math.min(Math.max(o.distanceM, 0), props.course.distance_m)
    return { id: o.id, cx: geometry.value.x(d), cy: geometry.value.y(pointAt(props.course, d).ele) }
  }),
)

// sim mode: click or drag to choose where to be (the viewBox scales evenly, so x maps linearly to distance)
let dragging = false
function seekAt(e: PointerEvent) {
  const rect = (e.currentTarget as SVGElement).getBoundingClientRect()
  const ratio = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1)
  emit('seek', ratio * props.course.distance_m)
}
function onDown(e: PointerEvent) {
  if (!props.seekable) return
  dragging = true
  ;(e.currentTarget as SVGElement).setPointerCapture(e.pointerId)
  seekAt(e)
}
function onMove(e: PointerEvent) {
  if (dragging) seekAt(e)
}

const marker = computed(() => {
  const here = pointAt(props.course, props.distanceM)
  return { cx: geometry.value.x(props.distanceM), cy: geometry.value.y(here.ele) }
})
</script>

<template>
  <svg
    :viewBox="`0 0 ${W} ${H}`"
    role="img"
    :aria-label="$t('profile.label')"
    class="profile"
    :class="{ seekable }"
    @pointerdown="onDown"
    @pointermove="onMove"
    @pointerup="dragging = false"
    @pointercancel="dragging = false"
  >
    <polygon :points="geometry.area" class="area" />
    <rect v-for="b in bands" :key="b.id" :x="b.x" y="0" :width="b.width" :height="H" class="segment" />
    <polyline :points="geometry.line" class="line" />
    <rect v-for="p in poiMarkers" :key="p.id" :x="p.cx - 5" :y="p.cy - 5" width="10" height="10" class="poi" :transform="`rotate(45 ${p.cx} ${p.cy})`" />
    <circle v-for="o in otherMarkers" :key="o.id" :cx="o.cx" :cy="o.cy" r="6" class="other" />
    <circle :cx="marker.cx" :cy="marker.cy" r="7" class="marker" />
  </svg>
</template>

<style scoped>
.profile {
  width: 100%;
  height: auto;
  background: rgb(255 255 255 / 75%);
  border-radius: 0.5rem;
}
.profile.seekable {
  cursor: pointer;
  touch-action: none;
}
.area {
  fill: #cfe3d4;
}
.line {
  fill: none;
  stroke: #2f6b45;
  stroke-width: 2;
}
.segment {
  fill: #e63946;
  opacity: 0.18;
}
.poi {
  fill: #e8a317;
  stroke: #fff;
  stroke-width: 1.5;
}
.other {
  fill: #0a4069;
  stroke: #fff;
  stroke-width: 2;
}
.marker {
  fill: #d33;
  stroke: #fff;
  stroke-width: 2;
}
</style>
