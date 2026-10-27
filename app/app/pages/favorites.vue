<script setup lang="ts">
import { poiTitle, type Poi } from '~/lib/pois/nearby'

type Favorite = Poi & { course?: { slug: string; name: Record<string, string> } }

const { locale } = useI18n()
const favorites = useFavorites()
const { data, status } = await useFetch<{ data: Favorite[] }>('/api/me/favorites')
const removed = ref<string[]>([])

const byCourse = computed(() => {
  const groups = new Map<string, { slug: string; name: string; pois: Favorite[] }>()
  for (const p of data.value?.data ?? []) {
    if (!p.course || removed.value.includes(p.id)) continue
    const group = groups.get(p.course.slug) ?? { slug: p.course.slug, name: p.course.name[locale.value] ?? p.course.name.fr ?? '', pois: [] }
    group.pois.push(p)
    groups.set(p.course.slug, group)
  }
  return [...groups.values()]
})

function remove(id: string) {
  favorites.remove(id)
  removed.value.push(id)
}
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-6 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('favorites.title') }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>
    <p v-if="status === 'error'" role="alert">{{ $t('favorites.error') }}</p>
    <p v-else-if="!byCourse.length" class="text-slate-500">{{ $t('favorites.empty') }}</p>
    <section v-for="c in byCourse" :key="c.slug">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">
        <NuxtLink :to="`/courses/${c.slug}`">{{ c.name }}</NuxtLink>
      </h2>
      <ul class="space-y-2">
        <li v-for="p in c.pois" :key="p.id" class="flex items-baseline gap-3">
          <span class="flex-1">
            <span class="font-semibold">{{ poiTitle(p, locale) }}</span>
            <span class="text-sm text-slate-500">
              · km {{ (p.distance_m / 1000).toFixed(1) }}<template v-if="p.category"> · {{ $t(`poi.category.${p.category}`) }}</template>
            </span>
          </span>
          <a v-if="p.url" :href="p.url" target="_blank" rel="noopener noreferrer" class="text-sm underline">{{ $t('poi.more') }}</a>
          <UButton size="xs" variant="ghost" color="neutral" icon="i-lucide-star-off" :aria-label="$t('favorites.remove')" @click="remove(p.id)" />
        </li>
      </ul>
    </section>
  </div>
</template>
