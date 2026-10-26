<script setup lang="ts">
import { segmentName } from '~/lib/segments/tracker'
import type { SegmentFinish } from '~/composables/useSegments'
import type { Segment } from '~/lib/segments/tracker'

const props = defineProps<{
  active?: { segment: Segment; startedAtS: number; bestS?: number }
  finished?: SegmentFinish
  distanceM: number
  elapsedS: number
}>()

const { locale } = useI18n()
const fmt = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`
const remainingM = computed(() => (props.active ? Math.max(0, props.active.segment.end_m - props.distanceM) : 0))
const runS = computed(() => (props.active ? props.elapsedS - props.active.startedAtS : 0))
</script>

<template>
  <aside v-if="active || finished" class="segment" role="status">
    <template v-if="active">
      <p class="name">
        <span v-if="active.segment.category" class="cat">{{ $t(`segment.category.${active.segment.category}`) }}</span>
        {{ segmentName(active.segment, locale) }}
      </p>
      <p class="figures">
        <strong>{{ fmt(runS) }}</strong>
        <span>{{ $t('segment.remaining', { km: (remainingM / 1000).toFixed(2) }) }}</span>
        <span v-if="active.bestS">{{ $t('segment.best', { time: fmt(active.bestS) }) }}</span>
      </p>
    </template>
    <template v-else-if="finished">
      <p class="name">{{ segmentName(finished.segment, locale) }}</p>
      <p class="figures">
        <strong>{{ fmt(finished.timeS) }}</strong>
        <span v-if="finished.isBest" class="pr">{{ $t('segment.newBest') }}</span>
        <span v-else>{{ $t('segment.best', { time: fmt(finished.bestS) }) }}</span>
      </p>
    </template>
  </aside>
</template>

<style scoped>
.segment {
  padding: 0.5rem 0.9rem;
  color: #fff;
  background: rgb(10 64 105 / 85%);
  border-radius: 0.5rem;
}
.name {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cat {
  padding: 0.05rem 0.4rem;
  margin-right: 0.4rem;
  font-size: 0.8rem;
  color: #0a4069;
  background: #fff;
  border-radius: 0.2rem;
}
.figures {
  display: flex;
  gap: 0.9rem;
  align-items: baseline;
  margin: 0;
  font-size: 0.9rem;
}
.figures strong {
  font-size: 1.5rem;
  font-variant-numeric: tabular-nums;
}
.pr {
  font-weight: 700;
  color: #ffd34d;
}
</style>
