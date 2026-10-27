<script setup lang="ts">
import { filterByKind, kindCounts, type CourseCardData } from '~/lib/course/card'

const route = useRoute()
const { t } = useI18n()
const { data } = await useFetch<{ data: CourseCardData[] }>('/api/courses')
const courses = computed(() => data.value?.data ?? [])

// the filter lives in the address (?type=mtb), so a filtered list can be shared and survives a reload
const type = computed(() => (typeof route.query.type === 'string' ? route.query.type : 'all'))
const counts = computed(() => kindCounts(courses.value))
const shown = computed(() => filterByKind(courses.value, type.value))
const filters = computed(() => [
  { value: 'all', label: t('home.filter.all'), count: courses.value.length },
  ...counts.value.map((k) => ({ value: k.kind, label: t(`course.kind.${k.kind}`), count: k.count })),
])
// a link with an unknown kind shows everything, so "all" is the one pressed
const active = computed(() => (filters.value.some((f) => f.value === type.value) ? type.value : 'all'))
const choose = (value: string) => navigateTo({ query: { ...route.query, type: value === 'all' ? undefined : value } }, { replace: true })
</script>

<template>
  <div class="mx-auto max-w-6xl p-4">
    <header class="mb-6 flex items-center justify-between">
      <h1 class="flex items-center gap-3" :aria-label="$t('app.title')">
        <img src="/vaud-logo.svg" alt="Vaud" class="h-9" >
        <!-- the logo already spells "Vaud" -->
        <span class="font-display text-3xl uppercase tracking-wide text-vaud-800">Ride</span>
      </h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UserMenu />
      </div>
    </header>
    <UButton class="mb-8" :label="$t('intro.title')" :to="{ path: '/intro', query: route.query }" />

    <h2 class="mb-4 font-display text-5xl uppercase leading-none text-vaud-800">
      {{ $t('home.titleLead') }} <span class="text-vaud-green-600">{{ $t('home.titleWord') }}</span>
    </h2>
    <p class="mb-4 text-slate-600" role="status">{{ $t('home.count', { n: shown.length }, shown.length) }}</p>

    <nav v-if="filters.length > 2" :aria-label="$t('home.filter.label')" class="mb-8 flex flex-wrap gap-2">
      <UButton
        v-for="f in filters"
        :key="f.value"
        size="md"
        color="neutral"
        :variant="active === f.value ? 'solid' : 'outline'"
        :aria-pressed="active === f.value"
        :label="`${f.label} (${f.count})`"
        @click="choose(f.value)"
      />
    </nav>

    <ul class="grid gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="c in shown" :key="c.slug">
        <CourseCard :course="c" />
      </li>
    </ul>
  </div>
</template>
