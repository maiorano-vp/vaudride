<script setup lang="ts">
const props = defineProps<{
  overlay?: boolean
  /** Discovery mode: no rider, so no power, cadence or heart rate. */
  demo?: boolean
  /** Share of the real grade the rider rides; a tile reminds them when it is below 100. */
  difficultyPct?: number
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
  ...(props.demo
    ? []
    : [
        { key: 'power', value: props.powerW?.toFixed(0) ?? dash, unit: 'W' },
        { key: 'cadence', value: props.cadenceRpm?.toFixed(0) ?? dash, unit: 'rpm' },
        { key: 'heartRate', value: props.heartRate?.toString() ?? dash, unit: 'bpm' },
      ]),
  { key: 'speed', value: (props.speedMs * 3.6).toFixed(1), unit: 'km/h' },
  { key: 'grade', value: props.gradePct.toFixed(1), unit: '%' },
  ...(!props.demo && props.difficultyPct !== undefined && props.difficultyPct < 100
    ? [{ key: 'difficulty', value: props.difficultyPct.toFixed(0), unit: '%' }]
    : []),
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
/* vaud.ch look: white cards, vaud blue, Staatliches for labels and figures */
.dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.75rem;
  margin: 0;
}
.tile {
  background: #fff;
  border-top: 3px solid var(--color-vaud-800);
  border-radius: 4px;
  padding: 0.6rem 1rem 0.75rem;
  box-shadow: 0 1px 3px rgb(39 46 61 / 12%);
}
dt {
  font-family: var(--font-display);
  font-size: 0.9rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-vaud-600);
}
dd {
  margin: 0;
  font-family: var(--font-display);
  font-size: 2.2rem;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  color: var(--color-vaud-800);
}
small {
  margin-left: 0.25rem;
  font-family: var(--font-sans);
  font-size: 0.85rem;
  color: var(--color-vaud-500);
}

/* HUD over the 3D scene: same cards, a bit denser and translucent */
.overlay {
  grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr));
  gap: 0.4rem;
}
.overlay .tile {
  padding: 0.3rem 0.6rem 0.4rem;
  background: rgb(255 255 255 / 92%);
}
.overlay dt {
  font-size: 0.8rem;
}
.overlay dd {
  font-size: 2rem;
}
</style>
