<script setup lang="ts">
import { targetWatts, type Workout, type WorkoutProgress } from '~/lib/workout/workout'

const props = defineProps<{
  workout?: Workout
  progress?: WorkoutProgress
  ftp: number
  powerW: number
  completed: boolean
  refused: boolean
}>()

const fmt = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`
const targetW = computed(() => (props.progress ? targetWatts(props.progress.step, props.ftp) : 0))
const nextW = computed(() => (props.progress?.next ? targetWatts(props.progress.next, props.ftp) : undefined))
// green within 5 % of the target, orange otherwise
const onTarget = computed(() => Math.abs(props.powerW - targetW.value) <= targetW.value * 0.05)
</script>

<template>
  <aside v-if="progress || completed || refused" class="workout" role="status">
    <template v-if="progress && workout">
      <p class="name">{{ $t('workout.step', { n: progress.index + 1, total: workout.steps.length }) }}</p>
      <p class="figures">
        <strong>{{ fmt(progress.remainingS) }}</strong>
        <span :class="onTarget ? 'ok' : 'off'">{{ $t('workout.target', { watts: targetW }) }}</span>
        <span v-if="nextW !== undefined && progress.next">
          {{ $t('workout.next', { watts: nextW, min: fmt(progress.next.durationS) }) }}
        </span>
      </p>
    </template>
    <p v-else-if="refused" class="name">{{ $t('workout.unsupported') }}</p>
    <p v-else class="name">{{ $t('workout.done') }}</p>
  </aside>
</template>

<style scoped>
.workout {
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
.ok {
  font-weight: 700;
  color: #7ee08a;
}
.off {
  font-weight: 700;
  color: #ffb84d;
}
</style>
