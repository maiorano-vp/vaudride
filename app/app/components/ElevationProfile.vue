<script setup lang="ts">
import { pointAt, type CourseProfile } from '~/lib/course/profile'

const props = defineProps<{ course: CourseProfile; distanceM: number; others?: Array<{ id: string; distanceM: number }> }>()

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

const otherMarkers = computed(() =>
  (props.others ?? []).map((o) => {
    const d = Math.min(Math.max(o.distanceM, 0), props.course.distance_m)
    return { id: o.id, cx: geometry.value.x(d), cy: geometry.value.y(pointAt(props.course, d).ele) }
  }),
)

const marker = computed(() => {
  const here = pointAt(props.course, props.distanceM)
  return { cx: geometry.value.x(props.distanceM), cy: geometry.value.y(here.ele) }
})
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" role="img" :aria-label="$t('profile.label')" class="profile">
    <polygon :points="geometry.area" class="area" />
    <polyline :points="geometry.line" class="line" />
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
.area {
  fill: #cfe3d4;
}
.line {
  fill: none;
  stroke: #2f6b45;
  stroke-width: 2;
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
