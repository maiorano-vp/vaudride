<script setup lang="ts">
interface Row {
  rank: number
  nickname: string
  time_s: number
  avg_power_w: number | null
  is_me: boolean
}

const props = defineProps<{ segmentId?: string; title: string }>()
const open = defineModel<boolean>('open', { required: true })

const rows = ref<Row[]>([])
const me = ref<{ rank: number; time_s: number } | null>(null)
const state = ref<'idle' | 'loading' | 'error'>('idle')

watch(
  () => [open.value, props.segmentId] as const,
  async ([isOpen, id]) => {
    if (!isOpen || !id) return
    state.value = 'loading'
    try {
      const res = await $fetch<{ data: Row[]; me: { rank: number; time_s: number } | null }>(`/api/segments/${id}/leaderboard`)
      rows.value = res.data
      me.value = res.me
      state.value = 'idle'
    } catch {
      state.value = 'error'
    }
  },
)

const fmt = (s: number) => `${Math.floor(s / 60)}:${(s % 60).toFixed(0).padStart(2, '0')}`
</script>

<template>
  <UModal v-model:open="open" :title="`${$t('segment.leaderboard')} · ${title}`">
    <template #body>
      <p v-if="state === 'loading'" role="status">{{ $t('segment.loading') }}</p>
      <p v-else-if="state === 'error'" role="alert">{{ $t('segment.leaderboardError') }}</p>
      <p v-else-if="!rows.length" class="text-slate-500">{{ $t('segment.leaderboardEmpty') }}</p>
      <ol v-else class="space-y-1">
        <li
          v-for="r in rows"
          :key="`${r.rank}-${r.nickname}`"
          class="flex items-baseline gap-3 rounded px-2 py-1"
          :class="{ 'bg-vaud-50 font-semibold': r.is_me }"
        >
          <span class="w-8 font-display text-lg text-vaud-800">{{ r.rank === 1 ? 'KOM' : r.rank }}</span>
          <span class="flex-1">{{ r.nickname }}</span>
          <span v-if="r.avg_power_w" class="text-sm text-slate-500">{{ r.avg_power_w }} W</span>
          <span class="font-mono">{{ fmt(r.time_s) }}</span>
        </li>
      </ol>
      <p v-if="me && !rows.some((r) => r.is_me)" class="mt-3 text-sm">{{ $t('segment.yourRank', { rank: me.rank, time: fmt(me.time_s) }) }}</p>
    </template>
  </UModal>
</template>
