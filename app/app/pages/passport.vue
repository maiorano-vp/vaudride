<script setup lang="ts">
import { passportProgress } from '~/lib/passport'

interface CourseSummary {
  slug: string
  name: Record<string, string>
  distance_m: number
  elevation_gain_m: number
}

const { locale } = useI18n()
const { data } = await useFetch<{ data: CourseSummary[] }>('/api/courses')
const real = useRealRides()
onMounted(real.load)

const progress = computed(() => passportProgress(data.value?.data ?? [], real.rides.value))
const name = (c: CourseSummary) => c.name[locale.value] ?? c.name.fr
const km = (m: number) => (m / 1000).toFixed(1)
const dateLabel = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(locale.value)
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('passport.title') }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>

    <p>{{ $t('passport.intro') }}</p>
    <p class="font-display text-2xl text-vaud-800" role="status">{{ $t('passport.progress', { count: progress.count, total: progress.total }) }}</p>
    <UProgress :model-value="progress.count" :max="Math.max(progress.total, 1)" :aria-label="$t('passport.title')" />

    <section v-if="progress.done.length">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t('passport.done') }}</h2>
      <ul class="space-y-2">
        <li v-for="d in progress.done" :key="d.course.slug">
          <UCard>
            <div class="flex items-baseline justify-between gap-4">
              <NuxtLink :to="`/courses/${d.course.slug}#real`" class="font-display text-lg uppercase tracking-wide text-vaud-800 underline">
                <UIcon name="i-lucide-badge-check" class="mr-1 align-text-bottom text-vaud-green-700" />{{ name(d.course) }}
              </NuxtLink>
              <span class="text-sm text-slate-500">{{ km(d.course.distance_m) }} km · {{ d.course.elevation_gain_m }} m D+</span>
            </div>
            <p class="m-0 text-sm text-slate-600">{{ d.days.map(dateLabel).join(' · ') }}</p>
          </UCard>
        </li>
      </ul>
    </section>

    <section v-if="progress.todo.length">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t('passport.todo') }}</h2>
      <ul class="space-y-1">
        <li v-for="c in progress.todo" :key="c.slug" class="flex items-baseline justify-between gap-4">
          <NuxtLink :to="`/courses/${c.slug}#real`" class="underline">{{ name(c) }}</NuxtLink>
          <span class="text-sm text-slate-500">{{ km(c.distance_m) }} km · {{ c.elevation_gain_m }} m D+</span>
        </li>
      </ul>
    </section>
  </div>
</template>
