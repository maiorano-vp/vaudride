<script setup lang="ts">
import { categoryCounts, filterPois, poiDescription, poiTitle, sheetSection, type Poi } from '~/lib/pois/nearby'
import { segmentName, type Segment } from '~/lib/segments/tracker'

interface CourseInfo {
  name: Record<string, string>
  description: Record<string, string> | null
  attribution: string
  best_months?: number[] | null
  forecast?: Array<{ day: string; temp_min: number | null; temp_max: number | null; precipitation_mm: number | null; place?: string | null }>
  pois?: Poi[]
  segments?: Segment[]
}

const route = useRoute()
const slug = String(route.params.slug)
const { locale } = useI18n()
const { data: profile, status } = useCourseProfile(slug)
const favorites = useFavorites()
const { track } = useCourseAction(slug)
onMounted(favorites.load)
const onlyFavorites = ref(false)
const { data: info } = await useFetch<{ data: CourseInfo }>(`/api/courses/${slug}`)

const course = computed(() => info.value?.data)
const name = computed(() => course.value?.name[locale.value] || course.value?.name.fr || '')
const description = computed(() => course.value?.description?.[locale.value] || course.value?.description?.fr || '')
const pois = computed(() => [...(course.value?.pois ?? [])].sort((a, b) => a.distance_m - b.distance_m))
const segments = computed(() => course.value?.segments ?? [])
const selectedCategories = ref<string[]>([])
const categories = computed(() => categoryCounts(pois.value))
const shownPois = computed(() =>
  filterPois(pois.value, selectedCategories.value).filter((p) => !onlyFavorites.value || favorites.isFavorite(p.id)),
)
const toggleCategory = (category: string) => {
  selectedCategories.value = selectedCategories.value.includes(category)
    ? selectedCategories.value.filter((c) => c !== category)
    : [...selectedCategories.value, category]
}
const sections = computed(() =>
  (['discover', 'services', 'lodging'] as const)
    .map((key) => ({ key, pois: shownPois.value.filter((p) => sheetSection(p) === key) }))
    .filter((s) => s.pois.length),
)
const maxGrade = computed(() => (profile.value ? Math.max(...profile.value.points.map((p) => p.grade)) : 0))
const km = (m: number) => (m / 1000).toFixed(1)
</script>

<template>
  <div v-if="course" class="mx-auto max-w-4xl space-y-6 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ name }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>

    <p v-if="description" class="max-w-prose whitespace-pre-line">{{ description }}</p>

    <dl v-if="profile" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div>
        <dt class="text-sm text-slate-500">{{ $t('dashboard.distance') }}</dt>
        <dd class="font-display text-2xl text-vaud-800">{{ km(profile.distance_m) }} km</dd>
      </div>
      <div>
        <dt class="text-sm text-slate-500">{{ $t('dashboard.climb') }}</dt>
        <dd class="font-display text-2xl text-vaud-800">{{ Math.round(profile.elevation_gain_m) }} m</dd>
      </div>
      <div>
        <dt class="text-sm text-slate-500">{{ $t('course.maxGrade') }}</dt>
        <dd class="font-display text-2xl text-vaud-800">{{ maxGrade.toFixed(1) }} %</dd>
      </div>
      <div>
        <dt class="text-sm text-slate-500">{{ $t('course.climbs') }}</dt>
        <dd class="font-display text-2xl text-vaud-800">{{ segments.length }}</dd>
      </div>
    </dl>
    <p v-else-if="status === 'pending'" role="status">{{ $t('ride.loading') }}</p>

    <ElevationProfile v-if="profile" :course="profile" :distance-m="0" :segments="segments" :pois="shownPois" />

    <CourseMap v-if="profile" :points="profile.points" :pois="shownPois" :segments="segments" />

    <div class="flex flex-wrap gap-3">
      <UButton :to="`/ride/${slug}`" :label="$t('course.ride')" />
      <UButton :to="{ path: `/ride/${slug}`, query: { demo: '1' } }" color="neutral" variant="outline" icon="i-lucide-plane" :label="$t('course.fly')" />
    </div>

    <RealRideBlock
      :slug="slug"
      :name="name"
      :profile="profile"
      :pois="pois"
      :best-months="course.best_months"
      :forecast="course.forecast"
    />

    <section v-if="segments.length">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t('course.climbs') }}</h2>
      <ul class="space-y-1">
        <li v-for="s in segments" :key="s.id" class="flex flex-wrap items-baseline gap-x-3">
          <span class="font-semibold">{{ segmentName(s, locale) }}</span>
          <span v-if="s.category" class="rounded bg-vaud-50 px-1.5 text-sm text-vaud-800">{{ $t(`segment.category.${s.category}`) }}</span>
          <span class="text-sm text-slate-500">
            {{ km(s.end_m - s.start_m) }} km · km {{ km(s.start_m) }} → {{ km(s.end_m) }}
          </span>
        </li>
      </ul>
    </section>

    <nav v-if="categories.length > 1 || favorites.ids.value.length" :aria-label="$t('course.filter')" class="flex flex-wrap items-center gap-2">
      <UButton
        size="md"
        :variant="selectedCategories.length ? 'outline' : 'solid'"
        color="neutral"
        :label="$t('course.filterAll')"
        :aria-pressed="!selectedCategories.length"
        @click="selectedCategories = []"
      />
      <UButton
        size="md"
        color="neutral"
        icon="i-lucide-star"
        :variant="onlyFavorites ? 'solid' : 'outline'"
        :aria-pressed="onlyFavorites"
        :label="$t('favorites.filter')"
        @click="onlyFavorites = !onlyFavorites"
      />
      <UButton
        v-for="c in categories"
        :key="c.category"
        size="md"
        color="neutral"
        :variant="selectedCategories.includes(c.category) ? 'solid' : 'outline'"
        :aria-pressed="selectedCategories.includes(c.category)"
        :label="`${$t(`poi.category.${c.category}`)} (${c.count})`"
        @click="toggleCategory(c.category)"
      />
    </nav>
    <p v-if="pois.length && !sections.length" class="text-slate-500">{{ $t('course.noMatch') }}</p>

    <section v-for="section in sections" :id="section.key" :key="section.key">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t(`course.${section.key}`) }}</h2>
      <ul class="space-y-3">
        <li v-for="p in section.pois" :key="p.id" class="flex gap-3">
          <figure v-if="p.image_url" class="m-0 flex-none">
            <img :src="p.image_url" :alt="poiTitle(p, locale)" loading="lazy" class="h-20 w-28 rounded object-cover" >
            <figcaption v-if="p.image_credit" class="mt-0.5 max-w-28 text-[0.65rem] leading-tight text-slate-500">{{ p.image_credit }}</figcaption>
          </figure>
          <div>
          <p class="m-0">
            <span class="font-semibold">{{ poiTitle(p, locale) }}</span>
            <UButton
              size="xs"
              variant="link"
              color="neutral"
              icon="i-lucide-star"
              :class="{ 'fill-amber-400 text-amber-500': favorites.isFavorite(p.id) }"
              :aria-pressed="favorites.isFavorite(p.id)"
              :aria-label="$t(favorites.isFavorite(p.id) ? 'favorites.remove' : 'favorites.add')"
              @click="favorites.toggle(p.id)"
            />
            <span class="text-sm text-slate-500">
              <template v-if="section.key === 'lodging'">· {{ p.distance_m < 1500 ? $t('course.nearStart') : $t('course.nearFinish') }}</template>
              <template v-else>· km {{ km(p.distance_m) }}</template>
              <template v-if="p.category"> · {{ $t(`poi.category.${p.category}`) }}</template>
            </span>
          </p>
          <p v-if="p.address" class="m-0 text-sm text-slate-500">{{ p.address }}</p>
          <p v-if="poiDescription(p, locale)" class="m-0 text-sm">{{ poiDescription(p, locale) }}</p>
          <a v-if="p.url" :href="p.url" target="_blank" rel="noopener noreferrer" class="text-sm underline" @click="track(section.key === 'lodging' ? 'lodging_link' : 'place_link')">{{ $t('poi.more') }}</a>
          </div>
        </li>
      </ul>
    </section>
    <p v-if="pois.some((p) => p.source === 'osm')" class="text-xs text-slate-500">© OpenStreetMap contributors</p>

    <p class="text-xs text-slate-500">{{ course.attribution }}</p>
  </div>
  <p v-else class="p-4">{{ $t('ride.notFound') }}</p>
</template>
