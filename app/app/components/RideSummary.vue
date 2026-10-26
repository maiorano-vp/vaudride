<script setup lang="ts">
import type { RideSummary } from '~/lib/ride/recorder'
import type { SaveState, SegmentEffort } from '~/composables/useRideRecording'
import { segmentName } from '~/lib/segments/tracker'

const props = defineProps<{
  summary: RideSummary
  elevationGainM: number
  recordable: boolean
  saveState: SaveState
  finished: boolean
  efforts: SegmentEffort[]
}>()
const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ save: [uploadToStrava: boolean]; restart: [] }>()

const { locale } = useI18n()
const board = ref<{ id: string; title: string }>()
const boardOpen = ref(false)
const showBoard = (e: SegmentEffort) => {
  board.value = { id: e.segment_id, title: segmentName({ name: e.name } as never, locale.value) }
  boardOpen.value = true
}
const toStrava = ref(false)
const { data: strava } = useAsyncData('strava', () => $fetch<{ connected: boolean }>('/api/strava').catch(() => ({ connected: false })), {
  server: false,
  lazy: true,
})

const clock = (s: number) => {
  const h = Math.floor(s / 3600)
  const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0')
  const ss = String(Math.floor(s % 60)).padStart(2, '0')
  return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`
}
const rows = computed(() => [
  { key: 'time', value: clock(props.summary.durationS) },
  { key: 'distance', value: `${(props.summary.distanceM / 1000).toFixed(2)} km` },
  { key: 'climb', value: `${Math.round(props.elevationGainM)} m` },
  { key: 'avgPower', value: `${props.summary.avgPowerW} W` },
  { key: 'maxPower', value: `${props.summary.maxPowerW} W` },
  { key: 'avgHeartRate', value: props.summary.avgHeartRate ? `${props.summary.avgHeartRate} bpm` : '–' },
])
</script>

<template>
  <UModal v-model:open="open" :title="$t(finished ? 'summary.finished' : 'summary.title')">
    <template #body>
      <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
        <div v-for="r in rows" :key="r.key">
          <dt class="text-sm text-slate-500">{{ $t(`summary.${r.key}`) }}</dt>
          <dd class="font-display text-2xl tracking-wide text-vaud-800">{{ r.value }}</dd>
        </div>
      </dl>
      <section v-if="efforts.length" class="mt-5">
        <h3 class="mb-1 font-display uppercase tracking-wide">{{ $t('summary.efforts') }}</h3>
        <ul class="space-y-1">
          <li v-for="e in efforts" :key="e.segment_id" class="flex items-baseline gap-3">
            <span class="flex-1">{{ segmentName({ name: e.name } as never, locale) }}</span>
            <span class="font-mono">{{ clock(e.time_s) }}</span>
            <UBadge :color="e.is_kom ? 'warning' : 'neutral'" variant="subtle">{{ e.is_kom ? 'KOM' : `#${e.rank}` }}</UBadge>
            <UButton size="md" variant="link" color="neutral" :label="$t('segment.leaderboard')" @click="showBoard(e)" />
          </li>
        </ul>
      </section>
      <label v-if="strava?.connected && recordable && saveState !== 'saved'" class="mt-5 flex items-center gap-2">
        <input v-model="toStrava" type="checkbox" >
        {{ $t('summary.toStrava') }}
      </label>
      <p v-if="saveState === 'saved'" class="mt-5 text-vaud-green-700" role="status">
        {{ $t('summary.saved') }} <NuxtLink to="/rides" class="underline">{{ $t('summary.history') }}</NuxtLink>
      </p>
      <p v-else-if="saveState === 'error'" class="mt-5 text-red-700" role="alert">{{ $t('summary.error') }}</p>
      <p v-else-if="!recordable" class="mt-5 text-slate-500">{{ $t('summary.tooShort') }}</p>
    </template>
    <template #footer>
      <div class="flex w-full flex-wrap justify-between gap-2">
        <UButton color="neutral" variant="ghost" :label="$t(finished ? 'ride.restart' : 'summary.resume')" @click="finished ? emit('restart') : (open = false)" />
        <UButton
          v-if="saveState !== 'saved'"
          :disabled="!recordable"
          :loading="saveState === 'saving'"
          :label="$t(saveState === 'error' ? 'summary.retry' : 'summary.save')"
          @click="emit('save', toStrava)"
        />
        <UButton v-else to="/" :label="$t('summary.courses')" />
      </div>
    </template>
  </UModal>
  <SegmentLeaderboard v-model:open="boardOpen" :segment-id="board?.id" :title="board?.title ?? ''" />
</template>
