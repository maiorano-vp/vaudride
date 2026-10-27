<script setup lang="ts">
import { endLinks, courseUrl, monthsLabel, serviceCounts } from '~/lib/course/realworld'
import { startLinks } from '~/lib/course/gpx'
import type { Poi } from '~/lib/pois/nearby'

interface Forecast {
  day: string
  temp_min: number | null
  temp_max: number | null
  precipitation_mm: number | null
  place?: string | null
}

const props = defineProps<{
  slug: string
  name: string
  profile?: { attribution: string; points: Array<{ lat: number; lon: number; ele: number }> } | null
  pois: Poi[]
  bestMonths?: number[] | null
  forecast?: Forecast[]
}>()

const { locale } = useI18n()
const origin = useRequestURL().origin
const gpx = useGpx(toRef(props, 'profile'), toRef(props, 'name'))
const { track } = useCourseAction(props.slug)

// "I rode it for real": on the rider's account, theirs alone
const { loggedIn } = useAuth()
const real = useRealRides()
const mine = computed(() => real.forCourse(props.slug))
const today = ref('') // the rider's own day, known once mounted
const day = ref('')
const saving = ref(false)
const failed = ref(false)
onMounted(() => {
  const now = new Date()
  today.value = new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10)
  day.value = today.value
  void real.load()
})
async function declare() {
  if (!day.value) return
  saving.value = true
  failed.value = false
  try {
    await real.declare(props.slug, day.value)
  } catch {
    failed.value = true
  } finally {
    saving.value = false
  }
}
const dateLabel = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(locale.value)
function download() {
  gpx.download()
  track('gpx_download')
}
async function share() {
  if (await gpx.share()) track('gpx_share')
}

const start = computed(() => (props.profile?.points[0] ? startLinks(props.profile.points[0]) : undefined))
const finish = computed(() => (props.profile?.points.length ? endLinks(props.profile.points[props.profile.points.length - 1]!) : undefined))
const services = computed(() => serviceCounts(props.pois))
const months = computed(() => (props.bestMonths?.length ? monthsLabel(props.bestMonths, locale.value) : ''))
const days = computed(() => (props.forecast ?? []).slice(0, 3))
const dayName = (iso: string) => new Intl.DateTimeFormat(locale.value, { weekday: 'long' }).format(new Date(`${iso}T12:00:00`))
</script>

<template>
  <section id="real" class="space-y-4" :aria-labelledby="'real-title'">
    <div>
      <h2 id="real-title" class="section-title">{{ $t('real.title') }}</h2>
      <p class="max-w-prose text-slate-600">{{ $t('real.intro') }}</p>
    </div>

    <ol class="grid gap-4 sm:grid-cols-2">
      <li>
        <UCard class="h-full">
          <h3 class="mb-2 font-display uppercase tracking-wide text-vaud-800">1 · {{ $t('real.getThere') }}</h3>
          <div class="flex flex-wrap gap-2">
            <UButton v-if="start" color="neutral" variant="outline" icon="i-lucide-train-front" :to="start.transit" target="_blank" rel="noopener" :label="$t('course.transit')" @click="track('transit_start')" />
            <UButton v-if="start" color="neutral" variant="outline" icon="i-lucide-map-pin" :to="start.map" target="_blank" rel="noopener" :label="$t('course.start')" @click="track('map_start')" />
          </div>
        </UCard>
      </li>

      <li>
        <UCard class="h-full">
          <h3 class="mb-2 font-display uppercase tracking-wide text-vaud-800">2 · {{ $t('real.takeTheRoute') }}</h3>
          <div class="flex flex-wrap items-start gap-4">
            <div class="flex flex-col gap-2">
              <UButton v-if="profile" icon="i-lucide-download" :label="$t('course.gpx')" @click="download" />
              <UButton v-if="profile && gpx.canShare.value" color="neutral" variant="outline" icon="i-lucide-share-2" :label="$t('real.gpxShare')" @click="share" />
            </div>
            <figure class="m-0 hidden sm:block">
              <QrCode :text="courseUrl(origin, slug)" :label="$t('real.qrLabel')" :size="112" />
              <figcaption class="mt-1 max-w-28 text-xs text-slate-500">{{ $t('real.qrHelp') }}</figcaption>
            </figure>
          </div>
        </UCard>
      </li>

      <li>
        <UCard class="h-full">
          <h3 class="mb-2 font-display uppercase tracking-wide text-vaud-800">3 · {{ $t('real.onTheWay') }}</h3>
          <ul class="space-y-1 text-sm">
            <li v-if="services.water"><UIcon name="i-lucide-droplet" class="mr-1 align-text-bottom" />{{ $t('real.water', { n: services.water }, services.water) }}</li>
            <li v-if="services.bike"><UIcon name="i-lucide-wrench" class="mr-1 align-text-bottom" />{{ $t('real.bike', { n: services.bike }, services.bike) }}</li>
            <li v-if="services.station"><UIcon name="i-lucide-train-front" class="mr-1 align-text-bottom" />{{ $t('real.station', { n: services.station }, services.station) }}</li>
            <li v-if="months"><UIcon name="i-lucide-sun" class="mr-1 align-text-bottom" />{{ $t('real.bestMonths', { months }) }}</li>
            <li v-if="services.lodging"><UIcon name="i-lucide-bed" class="mr-1 align-text-bottom" /><a href="#lodging" class="underline">{{ $t('real.lodging', { n: services.lodging }, services.lodging) }}</a></li>
          </ul>
          <div v-if="days.length" class="mt-3">
            <p class="m-0 text-sm font-semibold">{{ $t('real.forecast') }}</p>
            <ul class="space-y-0.5 text-sm text-slate-600">
              <li v-for="d in days" :key="d.day">
                <span class="capitalize">{{ dayName(d.day) }}</span> :
                <template v-if="d.temp_min != null && d.temp_max != null">{{ Math.round(d.temp_min) }}–{{ Math.round(d.temp_max) }} °C</template>
                <template v-if="d.precipitation_mm != null">, {{ d.precipitation_mm }} mm</template>
              </li>
            </ul>
          </div>
        </UCard>
      </li>

      <li>
        <UCard class="h-full">
          <h3 class="mb-2 font-display uppercase tracking-wide text-vaud-800">4 · {{ $t('real.getBack') }}</h3>
          <UButton v-if="finish" color="neutral" variant="outline" icon="i-lucide-train-front" :to="finish.transit" target="_blank" rel="noopener" :label="$t('real.transitEnd')" @click="track('transit_end')" />
        </UCard>
      </li>
    </ol>

    <UCard>
      <h3 class="mb-2 font-display uppercase tracking-wide text-vaud-800">{{ $t('real.iDidIt') }}</h3>
      <template v-if="loggedIn">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="declare">
          <label class="text-sm">
            {{ $t('real.when') }}
            <UInput v-model="day" type="date" :max="today" class="mt-1 w-44" />
          </label>
          <UButton type="submit" icon="i-lucide-badge-check" :loading="saving" :label="$t('real.declare')" />
        </form>
        <p v-if="failed" role="alert" class="mt-2 text-sm text-red-700">{{ $t('real.declareError') }}</p>
        <ul v-if="mine.length" class="mt-3 space-y-1 text-sm">
          <li v-for="r in mine" :key="r.id" class="flex items-center gap-2">
            <UIcon name="i-lucide-badge-check" class="text-vaud-green-700" />
            {{ $t('real.rodeOn', { date: dateLabel(r.ridden_on) }) }}
            <UButton size="md" variant="link" color="neutral" :label="$t('real.remove')" @click="real.remove(r.id)" />
          </li>
        </ul>
        <NuxtLink to="/passport" class="mt-3 inline-block text-sm underline">{{ $t('passport.open') }}</NuxtLink>
      </template>
      <p v-else class="m-0 text-sm">
        {{ $t('real.signIn') }}
        <NuxtLink to="/login" class="underline">{{ $t('auth.login') }}</NuxtLink>
      </p>
    </UCard>
  </section>
</template>
