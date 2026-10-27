<script setup lang="ts">
interface RideRow {
  id: string
  course?: { slug: string; name: Record<string, string> }
  started_at: string
  duration_s: number
  distance_m: number
  elevation_gain_m: number
  avg_power_w: number | null
  avg_heart_rate: number | null
}

const { locale } = useI18n()
const { data, status } = await useFetch<{ data: RideRow[] }>('/api/rides')
const rides = computed(() => data.value?.data ?? [])

const date = (iso: string) => new Date(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' })
const clock = (s: number) => {
  const h = Math.floor(s / 3600)
  const mm = String(Math.floor((s % 3600) / 60)).padStart(2, '0')
  const ss = String(Math.floor(s % 60)).padStart(2, '0')
  return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`
}
</script>

<template>
  <div class="mx-auto max-w-4xl p-4">
    <header class="mb-4 flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('rides.title') }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>
    <p v-if="status === 'error'" role="alert">{{ $t('rides.error') }}</p>
    <p v-else-if="!rides.length" class="text-slate-500">{{ $t('rides.empty') }}</p>
    <ul v-else class="space-y-3">
      <li v-for="r in rides" :key="r.id">
        <UCard>
          <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
            <span class="font-display text-xl uppercase tracking-wide text-vaud-800">
              {{ r.course?.name[locale] ?? r.course?.name.fr ?? '–' }}
            </span>
            <span class="text-sm text-slate-500">{{ date(r.started_at) }}</span>
          </div>
          <p class="mt-1 text-sm">
            {{ (r.distance_m / 1000).toFixed(1) }} km · {{ r.elevation_gain_m }} m D+ · {{ clock(r.duration_s) }}
            <template v-if="r.avg_power_w"> · {{ r.avg_power_w }} W</template>
            <template v-if="r.avg_heart_rate"> · {{ r.avg_heart_rate }} bpm</template>
          </p>
        </UCard>
      </li>
    </ul>
  </div>
</template>
