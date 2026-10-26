<script setup lang="ts">
import { fileSlug, startLinks, toGpx } from '~/lib/course/gpx'
import { poiDescription, poiTitle, sheetSection, type Poi } from '~/lib/pois/nearby'
import { segmentName, type Segment } from '~/lib/segments/tracker'

interface CourseInfo {
  name: Record<string, string>
  description: Record<string, string> | null
  attribution: string
  pois?: Poi[]
  segments?: Segment[]
}

const route = useRoute()
const slug = String(route.params.slug)
const { locale } = useI18n()
const { data: profile, status } = useCourseProfile(slug)
const { data: info } = await useFetch<{ data: CourseInfo }>(`/api/courses/${slug}`)

const course = computed(() => info.value?.data)
const name = computed(() => course.value?.name[locale.value] || course.value?.name.fr || '')
const description = computed(() => course.value?.description?.[locale.value] || course.value?.description?.fr || '')
const pois = computed(() => [...(course.value?.pois ?? [])].sort((a, b) => a.distance_m - b.distance_m))
const segments = computed(() => course.value?.segments ?? [])
const sections = computed(() =>
  (['discover', 'services', 'lodging'] as const)
    .map((key) => ({ key, pois: pois.value.filter((p) => sheetSection(p) === key) }))
    .filter((s) => s.pois.length),
)
const links = computed(() => (profile.value ? startLinks(profile.value.points[0]!) : undefined))
const maxGrade = computed(() => (profile.value ? Math.max(...profile.value.points.map((p) => p.grade)) : 0))
const km = (m: number) => (m / 1000).toFixed(1)

function downloadGpx() {
  if (!profile.value) return
  const blob = new Blob([toGpx({ name: name.value, attribution: profile.value.attribution, points: profile.value.points })], {
    type: 'application/gpx+xml',
  })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${fileSlug(name.value)}.gpx`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div v-if="course" class="mx-auto max-w-4xl space-y-6 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ name }}</h1>
      <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
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

    <ElevationProfile v-if="profile" :course="profile" :distance-m="0" :segments="segments" :pois="pois" />

    <div class="flex flex-wrap gap-3">
      <UButton :to="`/ride/${slug}`" :label="$t('course.ride')" />
      <UButton v-if="profile" color="neutral" variant="outline" :label="$t('course.gpx')" @click="downloadGpx" />
      <UButton v-if="links" color="neutral" variant="outline" :to="links.transit" target="_blank" rel="noopener" :label="$t('course.transit')" />
      <UButton v-if="links" color="neutral" variant="outline" :to="links.map" target="_blank" rel="noopener" :label="$t('course.start')" />
    </div>

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

    <section v-for="section in sections" :key="section.key">
      <h2 class="mb-2 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t(`course.${section.key}`) }}</h2>
      <ul class="space-y-3">
        <li v-for="p in section.pois" :key="p.id">
          <p class="m-0">
            <span class="font-semibold">{{ poiTitle(p, locale) }}</span>
            <span class="text-sm text-slate-500">
              <template v-if="section.key === 'lodging'">· {{ p.distance_m < 1500 ? $t('course.nearStart') : $t('course.nearFinish') }}</template>
              <template v-else>· km {{ km(p.distance_m) }}</template>
              <template v-if="p.category"> · {{ $t(`poi.category.${p.category}`) }}</template>
            </span>
          </p>
          <p v-if="p.address" class="m-0 text-sm text-slate-500">{{ p.address }}</p>
          <p v-if="poiDescription(p, locale)" class="m-0 text-sm">{{ poiDescription(p, locale) }}</p>
          <a v-if="p.url" :href="p.url" target="_blank" rel="noopener noreferrer" class="text-sm underline">{{ $t('poi.more') }}</a>
        </li>
      </ul>
    </section>
    <p v-if="pois.some((p) => p.source === 'osm')" class="text-xs text-slate-500">© OpenStreetMap contributors</p>

    <p class="text-xs text-slate-500">{{ course.attribution }}</p>
  </div>
  <p v-else class="p-4">{{ $t('ride.notFound') }}</p>
</template>
