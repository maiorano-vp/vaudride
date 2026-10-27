<script setup lang="ts">
import { creditText, kindLabelKey } from '~/lib/course/card'
import {
  elevationRange,
  kmDplusText,
  splitDescription,
} from '~/lib/course/detail'
import { monthsLabel } from '~/lib/course/realworld'
import {
  categoryCounts,
  filterPois,
  poiDescription,
  poiTitle,
  sheetSection,
  type Poi,
} from '~/lib/pois/nearby'
import { segmentName, type Segment } from '~/lib/segments/tracker'
import type { InfoRow } from '~/components/CourseInfoTable.vue'

interface CourseInfo {
  name: Record<string, string>
  description: Record<string, string> | null
  attribution: string
  distance_m: number
  elevation_gain_m: number
  image_url?: string | null
  image_credit?: string | null
  images?: Array<{ url: string; credit: string | null }>
  destination?: string | null
  kind?: string | null
  best_months?: number[] | null
  forecast?: Array<{
    day: string
    temp_min: number | null
    temp_max: number | null
    precipitation_mm: number | null
    place?: string | null
  }>
  pois?: Poi[]
  segments?: Segment[]
}

const route = useRoute()
const slug = String(route.params.slug)
const { locale, t } = useI18n()
const { data: profile, status } = useCourseProfile(slug)
const favorites = useFavorites()
const { track } = useCourseAction(slug)
onMounted(favorites.load)
const onlyFavorites = ref(false)
const { data: info } = await useFetch<{ data: CourseInfo }>(
  `/api/courses/${slug}`,
)

const course = computed(() => info.value?.data)
const name = computed(
  () => course.value?.name[locale.value] || course.value?.name.fr || '',
)
const text = computed(() =>
  splitDescription(
    course.value?.description?.[locale.value] ||
      course.value?.description?.fr ||
      '',
  ),
)
const pois = computed(() =>
  [...(course.value?.pois ?? [])].sort((a, b) => a.distance_m - b.distance_m),
)
const segments = computed(() => course.value?.segments ?? [])
const kindKey = computed(() => kindLabelKey(course.value?.kind))
// the cover is the photo at the top: the gallery holds the other ones
const gallery = computed(() =>
  (course.value?.images ?? []).filter(
    (image) => image.url !== course.value?.image_url,
  ),
)
const selectedCategories = ref<string[]>([])
const categories = computed(() => categoryCounts(pois.value))
const shownPois = computed(() =>
  filterPois(pois.value, selectedCategories.value).filter(
    (p) => !onlyFavorites.value || favorites.isFavorite(p.id),
  ),
)
const toggleCategory = (category: string) => {
  selectedCategories.value = selectedCategories.value.includes(category)
    ? selectedCategories.value.filter((c) => c !== category)
    : [...selectedCategories.value, category]
}
const SECTION_KEYS = ['discover', 'services', 'lodging'] as const
const sections = computed(() =>
  SECTION_KEYS.map((key) => ({
    key,
    pois: shownPois.value.filter((p) => sheetSection(p) === key),
  })).filter((s) => s.pois.length),
)
const km = (m: number) => (m / 1000).toFixed(1)
// numbers in the visitor's format (a decimal comma in French and German)
const decimal = (n: number) =>
  new Intl.NumberFormat(locale.value, {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(n)

// the practical information, as the table of an event page on vaud.ch
const maxGrade = computed(() =>
  profile.value
    ? Math.max(...profile.value.points.map((p) => p.grade))
    : undefined,
)
const altitude = computed(() =>
  profile.value ? elevationRange(profile.value.points) : undefined,
)
const number = (n: number) =>
  new Intl.NumberFormat(locale.value, { maximumFractionDigits: 0 }).format(n)
const rows = computed<InfoRow[]>(() => {
  const c = course.value
  if (!c) return []
  return [
    ...(kindKey.value
      ? [
          {
            icon: 'i-lucide-bike',
            label: t('course.info.kind'),
            value: t(kindKey.value),
          },
        ]
      : []),
    ...(c.destination
      ? [
          {
            icon: 'i-lucide-map-pin',
            label: t('course.info.destination'),
            value: c.destination,
          },
        ]
      : []),
    {
      icon: 'i-lucide-route',
      label: t('course.info.distance'),
      value: `${decimal(c.distance_m / 1000)} km`,
    },
    {
      icon: 'i-lucide-trending-up',
      label: t('course.info.climb'),
      value: `${number(c.elevation_gain_m)} m`,
    },
    ...(maxGrade.value !== undefined
      ? [
          {
            icon: 'i-lucide-percent',
            label: t('course.info.maxGrade'),
            value: `${decimal(maxGrade.value)} %`,
          },
        ]
      : []),
    ...(altitude.value
      ? [
          {
            icon: 'i-lucide-mountain',
            label: t('course.info.altitude'),
            value: `${number(altitude.value.min)} – ${number(altitude.value.max)} m`,
          },
        ]
      : []),
    ...(segments.value.length
      ? [
          {
            icon: 'i-lucide-chevrons-up',
            label: t('course.info.climbs'),
            value: String(segments.value.length),
            href: '#climbs',
          },
        ]
      : []),
    ...(c.best_months?.length
      ? [
          {
            icon: 'i-lucide-sun',
            label: t('course.info.bestMonths'),
            value: monthsLabel(c.best_months, locale.value),
          },
        ]
      : []),
    ...(pois.value.length
      ? [
          {
            icon: 'i-lucide-landmark',
            label: t('course.info.places'),
            value: String(pois.value.length),
            href: '#discover',
          },
        ]
      : []),
  ]
})

// the sticky bar of pills: one per section that is there (a course without photos or climbs has no such pill)
const nav = computed(() => [
  { id: 'info', label: t('course.info.title') },
  ...(gallery.value.length
    ? [{ id: 'photos', label: t('course.photos') }]
    : []),
  { id: 'map', label: t('course.mapTitle') },
  { id: 'real', label: t('real.title') },
  ...(segments.value.length
    ? [{ id: 'climbs', label: t('course.climbs') }]
    : []),
  ...SECTION_KEYS.filter((key) =>
    pois.value.some((p) => sheetSection(p) === key),
  ).map((key) => ({ id: key, label: t(`course.${key}`) })),
])
const { active } = useScrollSpy(computed(() => nav.value.map((n) => n.id)))
</script>

<template>
  <div v-if="course" class="min-h-dvh bg-white">
    <!-- white, like vaud.ch: the tinted rows of the table show on it -->
    <div class="page-header" :class="{ 'has-image': course.image_url }">
      <AppHeader />
      <div class="mx-auto max-w-[56.5rem] px-4">
        <nav :aria-label="$t('course.breadcrumb')" class="breadcrumb">
          <ol>
            <li>
              <NuxtLink to="/" :aria-label="$t('course.home')"
                ><UIcon name="i-lucide-house" class="home"
              /></NuxtLink>
            </li>
            <li aria-hidden="true">
              <UIcon name="i-lucide-chevron-right" class="sep" />
            </li>
            <li>
              <NuxtLink to="/">{{ $t('course.itineraries') }}</NuxtLink>
            </li>
            <li aria-hidden="true">
              <UIcon name="i-lucide-chevron-right" class="sep" />
            </li>
            <li aria-current="page">{{ name }}</li>
          </ol>
        </nav>

        <figure
          v-if="course.image_url"
          class="relative m-0 aspect-[873/400] overflow-hidden bg-vaud-100"
        >
          <img :src="course.image_url" alt="" class="size-full object-cover" >
          <figcaption
            v-if="creditText(course.image_credit)"
            class="absolute right-0 bottom-0 bg-vaud-800/85 px-2 py-0.5 text-xs text-white"
          >
            {{ creditText(course.image_credit) }}
          </figcaption>
        </figure>
      </div>
    </div>

    <div class="mx-auto max-w-[56.5rem] px-4 pb-4">
      <div class="mt-8 flex flex-wrap items-center gap-3 text-vaud-800">
        <span
          v-if="kindKey"
          class="bg-vaud-green-600/80 px-1.5 py-0.5 font-display text-2xl uppercase leading-none text-white"
          >{{ $t(kindKey) }}</span
        >
        <span v-if="course.destination" class="flex items-center gap-1.5"
          ><UIcon name="i-lucide-map-pin" />{{ course.destination }}</span
        >
      </div>
      <h1 class="course-title">{{ name }}</h1>
      <p
        class="m-0 font-display text-[2.4rem] leading-tight text-vaud-green-600"
      >
        {{ kmDplusText(course.distance_m, course.elevation_gain_m, locale) }}
      </p>

      <p v-if="text.lead" class="lead">{{ text.lead }}</p>
      <div v-if="text.body" class="body-text whitespace-pre-line">
        {{ text.body }}
      </div>

      <div class="mt-8 flex flex-wrap gap-3">
        <UButton
          :to="`/ride/${slug}`"
          icon="i-lucide-bike"
          :label="$t('course.ride')"
        />
        <UButton
          :to="{ path: `/ride/${slug}`, query: { demo: '1' } }"
          color="neutral"
          variant="outline"
          icon="i-lucide-plane"
          :label="$t('course.fly')"
        />
      </div>

      <div class="pills-bar">
        <nav :aria-label="$t('course.sections')" class="pills">
          <span class="pills-label">{{ $t('course.pillsLabel') }} ›</span>
          <a
            v-for="n in nav"
            :key="n.id"
            :href="`#${n.id}`"
            class="pill"
            :aria-current="active === n.id ? 'true' : undefined"
            >{{ n.label }}</a
          >
        </nav>
      </div>

      <section id="info" class="section">
        <h2 class="section-title">{{ $t('course.info.title') }}</h2>
        <CourseInfoTable :rows="rows" />
      </section>

      <section v-if="gallery.length" id="photos" class="section">
        <h2 class="section-title">{{ $t('course.photos') }}</h2>
        <CourseGallery :images="gallery" />
      </section>

      <section id="map" class="section">
        <h2 class="section-title">{{ $t('course.mapTitle') }}</h2>
        <p v-if="!profile && status === 'pending'" role="status">
          {{ $t('ride.loading') }}
        </p>
        <div class="space-y-4">
          <ElevationProfile
            v-if="profile"
            :course="profile"
            :distance-m="0"
            :segments="segments"
            :pois="shownPois"
          />
          <CourseMap
            v-if="profile"
            :points="profile.points"
            :pois="shownPois"
            :segments="segments"
          />
        </div>
      </section>

      <RealRideBlock
        class="section"
        :slug="slug"
        :name="name"
        :profile="profile"
        :pois="pois"
        :best-months="course.best_months"
        :forecast="course.forecast"
      />

      <section v-if="segments.length" id="climbs" class="section">
        <h2 class="section-title">{{ $t('course.climbs') }}</h2>
        <ul class="m-0 list-none space-y-2 p-0 text-lg">
          <li
            v-for="s in segments"
            :key="s.id"
            class="flex flex-wrap items-baseline gap-x-3"
          >
            <span class="font-semibold">{{ segmentName(s, locale) }}</span>
            <span
              v-if="s.category"
              class="rounded bg-vaud-50 px-1.5 text-sm text-vaud-800"
              >{{ $t(`segment.category.${s.category}`) }}</span
            >
            <span class="text-sm text-slate-600">
              {{ km(s.end_m - s.start_m) }} km · km {{ km(s.start_m) }} →
              {{ km(s.end_m) }}
            </span>
          </li>
        </ul>
      </section>

      <nav
        v-if="categories.length > 1 || favorites.ids.value.length"
        :aria-label="$t('course.filter')"
        class="section flex flex-wrap items-center gap-2"
      >
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
          :variant="
            selectedCategories.includes(c.category) ? 'solid' : 'outline'
          "
          :aria-pressed="selectedCategories.includes(c.category)"
          :label="`${$t(`poi.category.${c.category}`)} (${c.count})`"
          @click="toggleCategory(c.category)"
        />
      </nav>
      <p v-if="pois.length && !sections.length" class="text-slate-600">
        {{ $t('course.noMatch') }}
      </p>

      <section
        v-for="section in sections"
        :id="section.key"
        :key="section.key"
        class="section"
      >
        <h2 class="section-title">{{ $t(`course.${section.key}`) }}</h2>
        <ul class="m-0 list-none space-y-4 p-0">
          <li v-for="p in section.pois" :key="p.id" class="flex gap-3">
            <figure v-if="p.image_url" class="m-0 flex-none">
              <img
                :src="p.image_url"
                :alt="poiTitle(p, locale)"
                loading="lazy"
                class="h-20 w-28 object-cover"
              >
              <figcaption
                v-if="p.image_credit"
                class="mt-0.5 max-w-28 text-[0.65rem] leading-tight text-slate-600"
              >
                {{ p.image_credit }}
              </figcaption>
            </figure>
            <div>
              <p class="m-0">
                <span class="font-semibold">{{ poiTitle(p, locale) }}</span>
                <UButton
                  size="xs"
                  variant="link"
                  color="neutral"
                  icon="i-lucide-star"
                  :class="{
                    'fill-amber-400 text-amber-500': favorites.isFavorite(p.id),
                  }"
                  :aria-pressed="favorites.isFavorite(p.id)"
                  :aria-label="
                    $t(
                      favorites.isFavorite(p.id)
                        ? 'favorites.remove'
                        : 'favorites.add',
                    )
                  "
                  @click="favorites.toggle(p.id)"
                />
                <span class="text-sm text-slate-600">
                  <template v-if="section.key === 'lodging'"
                    >·
                    {{
                      p.distance_m < 1500
                        ? $t('course.nearStart')
                        : $t('course.nearFinish')
                    }}</template
                  >
                  <template v-else>· km {{ km(p.distance_m) }}</template>
                  <template v-if="p.category">
                    · {{ $t(`poi.category.${p.category}`) }}</template
                  >
                </span>
              </p>
              <p v-if="p.address" class="m-0 text-sm text-slate-600">
                {{ p.address }}
              </p>
              <p v-if="poiDescription(p, locale)" class="m-0 text-sm">
                {{ poiDescription(p, locale) }}
              </p>
              <a
                v-if="p.url"
                :href="p.url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm underline"
                @click="
                  track(
                    section.key === 'lodging' ? 'lodging_link' : 'place_link',
                  )
                "
                >{{ $t('poi.more') }}</a
              >
            </div>
          </li>
        </ul>
      </section>

      <footer class="mt-16">
        <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
          <li v-if="course.destination" class="tag">
            <UIcon name="i-lucide-map-pin" class="align-text-bottom" />
            {{ course.destination }}
          </li>
          <li v-if="kindKey" class="tag">{{ $t(kindKey) }}</li>
        </ul>
        <NuxtLink
          to="/"
          class="mt-6 inline-flex items-center gap-1 font-display text-lg uppercase text-vaud-800"
        >
          <UIcon name="i-lucide-chevron-left" />{{ $t('auth.back') }}
        </NuxtLink>
        <p
          v-if="pois.some((p) => p.source === 'osm')"
          class="mt-6 mb-0 text-xs text-slate-600"
        >
          © OpenStreetMap contributors
        </p>
        <p class="mt-1 text-xs text-slate-600">{{ course.attribution }}</p>
      </footer>
    </div>
  </div>
  <p v-else class="p-4">{{ $t('ride.notFound') }}</p>
</template>

<style scoped>
/* the header of an event page on vaud.ch: a tint behind the right half of the page, from the top down to a little
   above the foot of the photo, so the photo sits half on it and half on the white */
.page-header {
  position: relative;
  isolation: isolate;
}
.page-header.has-image::after {
  position: absolute;
  inset: 0 0 60px 50%;
  z-index: -1;
  content: '';
  background: rgb(10 64 105 / 10%);
}
.breadcrumb ol {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  padding: 0;
  margin: 0 0 1.25rem;
  font-family: var(--font-display);
  font-size: 1.125rem;
  letter-spacing: 0.35px;
  text-transform: uppercase;
  list-style: none;
  color: var(--color-vaud-800);
}
.breadcrumb a {
  color: inherit;
  text-decoration: none;
}
.breadcrumb a:hover {
  text-decoration: underline;
}
.breadcrumb .home {
  vertical-align: -0.12em;
}
.breadcrumb .sep {
  font-size: 0.8em;
  color: #272e3d;
}
/* the page of an event on vaud.ch: a 873 px column, a very large title, a green line, a lead, then sections */
.course-title {
  margin: 0.5rem 0 1.25rem;
  font-family: var(--font-display);
  font-size: clamp(3rem, 9vw, 5.5rem);
  font-weight: 400;
  line-height: 1.05;
  color: var(--color-vaud-800);
}
.lead {
  margin: 2.5rem 0 1.5rem;
  font-size: clamp(1.4rem, 3.6vw, 2rem);
  line-height: 1.3;
  color: #272e3d;
}
.body-text {
  font-size: 1.125rem;
  line-height: 1.5;
  color: #272e3d;
}
.section {
  margin-top: 4.5rem;
  scroll-margin-top: 6rem; /* under the sticky bar */
}
.pills-bar {
  position: sticky;
  top: 0;
  z-index: 20;
  padding: 0.75rem 0;
  margin: 2.5rem -1rem 0;
  background: rgb(255 255 255 / 94%);
  backdrop-filter: blur(6px);
}
.pills {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding: 0 1rem;
  overflow-x: auto;
  white-space: nowrap;
}
.pills-label {
  flex: none;
  font-family: var(--font-display);
  font-size: 1.125rem;
  text-transform: uppercase;
  color: var(--color-vaud-800);
}
.pill {
  flex: none;
  padding: 9px 20px;
  font-family: var(--font-display);
  font-size: 1.125rem;
  color: var(--color-vaud-600);
  text-transform: uppercase;
  text-decoration: none;
  border: 1px solid var(--color-vaud-300);
  border-radius: 50px;
}
.pill[aria-current='true'] {
  color: #fff;
  background: var(--color-vaud-800);
  border-color: var(--color-vaud-800);
}
.tag {
  padding: 0.35rem 0.6rem;
  font-size: 0.95rem;
  color: var(--color-vaud-800);
  background: #f2f5f7;
}
</style>
