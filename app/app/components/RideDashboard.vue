<script setup lang="ts">
const props = defineProps<{
  overlay?: boolean
  powerW?: number
  cadenceRpm?: number
  heartRate?: number
  speedMs: number
  gradePct: number
  distanceM: number
  elevationGainM: number
  elapsedS: number
}>()

const dash = '–'
const time = computed(() => {
  const s = Math.floor(props.elapsedS)
  const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0')
  const ss = String(s % 60).padStart(2, '0')
  return s >= 3600 ? `${Math.floor(s / 3600)}:${mm}:${ss}` : `${mm}:${ss}`
})
const tiles = computed(() => [
  { key: 'power', value: props.powerW?.toFixed(0) ?? dash, unit: 'W' },
  { key: 'cadence', value: props.cadenceRpm?.toFixed(0) ?? dash, unit: 'rpm' },
  { key: 'heartRate', value: props.heartRate?.toString() ?? dash, unit: 'bpm' },
  { key: 'speed', value: (props.speedMs * 3.6).toFixed(1), unit: 'km/h' },
  { key: 'grade', value: props.gradePct.toFixed(1), unit: '%' },
  { key: 'distance', value: (props.distanceM / 1000).toFixed(2), unit: 'km' },
  { key: 'climb', value: props.elevationGainM.toFixed(0), unit: 'm' },
  { key: 'time', value: time.value, unit: '' },
])
</script>

<template>
  <dl class="dashboard" :class="{ overlay }">
    <div v-for="t in tiles" :key="t.key" class="tile">
      <dt>{{ $t(`dashboard.${t.key}`) }}</dt>
      <dd>
        {{ t.value }}<small>{{ t.unit }}</small>
      </dd>
    </div>
  </dl>
</template>

<style scoped>
.dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.75rem;
  margin: 0;
}
.tile {
  background: #f3f3f3;
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
}
dt {
  font-size: 0.8rem;
  text-transform: uppercase;
  opacity: 0.6;
}
dd {
  margin: 0;
  font-size: 2rem;
  font-variant-numeric: tabular-nums;
}
small {
  font-size: 0.9rem;
  margin-left: 0.25rem;
  opacity: 0.6;
}

/* HUD over the 3D scene: glass tiles, light text, compact */
.overlay {
  grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
  gap: 0.4rem;
}
.overlay .tile {
  padding: 0.4rem 0.6rem;
  color: #fff;
  background: rgb(0 0 0 / 50%);
  backdrop-filter: blur(6px);
  text-shadow: 0 1px 2px rgb(0 0 0 / 60%);
}
.overlay dt {
  font-size: 0.7rem;
}
.overlay dd {
  font-size: 2.1rem;
}
</style>
