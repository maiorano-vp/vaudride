<script setup lang="ts">
interface CourseSummary {
  slug: string
  name: Record<string, string>
  distance_m: number
  elevation_gain_m: number
}

const route = useRoute()
const { locale } = useI18n()
const { data } = await useFetch<{ data: CourseSummary[] }>('/api/courses')
const courses = computed(() => data.value?.data ?? [])
const km = (m: number) => (m / 1000).toFixed(1)
</script>

<template>
  <div class="mx-auto max-w-3xl p-4">
    <header class="mb-6 flex items-center justify-between">
      <h1 class="flex items-center gap-3" :aria-label="$t('app.title')">
        <img src="/vaud-logo.svg" alt="Vaud" class="h-9" >
        <!-- the logo already spells "Vaud" -->
        <span class="font-display text-3xl uppercase tracking-wide text-vaud-800">Ride</span>
      </h1>
      <UserMenu />
    </header>
    <UButton class="mb-4" :label="$t('intro.title')" :to="{ path: '/intro', query: route.query }" />
    <ul class="space-y-3">
      <li v-for="c in courses" :key="c.slug">
        <UCard>
          <NuxtLink
            class="flex items-baseline justify-between gap-4"
            :to="{ path: `/ride/${c.slug}`, query: route.query }"
          >
            <span class="font-display text-xl uppercase tracking-wide text-vaud-800">{{ c.name[locale] ?? c.name.fr }}</span>
            <span class="text-sm">{{ km(c.distance_m) }} km · {{ c.elevation_gain_m }} m D+</span>
          </NuxtLink>
          <NuxtLink class="mt-1 inline-block text-sm underline" :to="`/courses/${c.slug}`">{{ $t('course.sheet') }}</NuxtLink>
        </UCard>
      </li>
    </ul>
  </div>
</template>
