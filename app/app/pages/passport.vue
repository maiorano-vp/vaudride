<script setup lang="ts">
import {
  filterByKind,
  kindCounts,
  type CourseCardData,
} from '~/lib/course/card'
import { parseShow, passportProgress, suggestStarters } from '~/lib/passport'

const route = useRoute()
const { t } = useI18n()
const { data } = await useFetch<{ data: CourseCardData[] }>('/api/courses')
const real = useRealRides()
onMounted(real.load)

const progress = computed(() =>
  passportProgress(data.value?.data ?? [], real.rides.value),
)
const percent = computed(() =>
  progress.value.total
    ? Math.round((progress.value.count / progress.value.total) * 100)
    : 0,
)

// the filters live in the address (?show=done&type=mtb), as on the home page
const show = computed(() => parseShow(route.query.show))
const type = computed(() =>
  typeof route.query.type === 'string' ? route.query.type : 'all',
)
const setQuery = (patch: Record<string, string | undefined>) =>
  navigateTo({ query: { ...route.query, ...patch } }, { replace: true })

// the ridden ones first, the latest on top, then the ones left to discover
const entries = computed(() => [
  ...[...progress.value.done]
    .sort((a, b) => b.days[0]!.localeCompare(a.days[0]!))
    .map((d) => ({ course: d.course, days: d.days })),
  ...progress.value.todo.map((course) => ({
    course,
    days: undefined as string[] | undefined,
  })),
])
const kinds = computed(() => kindCounts(entries.value.map((e) => e.course)))
const shown = computed(() =>
  filterByKind(
    entries.value.map((e) => e.course),
    type.value,
  )
    .map((course) => entries.value.find((e) => e.course.slug === course.slug)!)
    .filter((e) =>
      show.value === 'done' ? e.days : show.value === 'todo' ? !e.days : true,
    ),
)
// a bike to begin with
const starters = computed(() =>
  progress.value.count === 0
    ? suggestStarters(progress.value.todo.filter((c) => c.kind !== 'scooter'))
    : [],
)

const showFilters = computed(() => [
  {
    value: 'all',
    label: t('passport.filter.all'),
    count: entries.value.length,
  },
  {
    value: 'done',
    label: t('passport.filter.done'),
    count: progress.value.count,
  },
  {
    value: 'todo',
    label: t('passport.filter.todo'),
    count: progress.value.todo.length,
  },
])
const kindFilters = computed(() => [
  { value: 'all', label: t('home.filter.all') },
  ...kinds.value.map((k) => ({
    value: k.kind as string,
    label: t(`course.kind.${k.kind}`),
  })),
])
const activeKind = computed(() =>
  kindFilters.value.some((f) => f.value === type.value) ? type.value : 'all',
)
</script>

<template>
  <div class="min-h-dvh bg-white">
    <AppHeader />

    <div class="mx-auto max-w-6xl px-4 pb-16">
      <h1 class="section-title">{{ $t('passport.title') }}</h1>
      <p class="max-w-prose text-lg text-vaud-950">
        {{ $t('passport.intro') }}
      </p>

      <!-- the count: the number of courses ridden for real, big, and how far that goes -->
      <section
        class="counter"
        :aria-label="
          $t('passport.progress', {
            count: progress.count,
            total: progress.total,
          })
        "
      >
        <p class="m-0 font-display leading-none text-vaud-800">
          <span class="big" :class="{ none: progress.count === 0 }">{{
            progress.count
          }}</span>
          <span class="of"> / {{ progress.total }}</span>
        </p>
        <div class="grow">
          <p class="m-0 mb-2 font-display text-2xl uppercase text-vaud-800">
            {{ $t('passport.counterLabel') }}
          </p>
          <div
            class="bar"
            role="progressbar"
            :aria-valuemin="0"
            :aria-valuemax="progress.total"
            :aria-valuenow="progress.count"
            :aria-label="$t('passport.title')"
          >
            <div class="fill" :style="{ width: `${percent}%` }" />
          </div>
        </div>
      </section>

      <!-- nothing ridden yet: a nudge, and the three easiest to start with -->
      <section v-if="starters.length" class="mt-14">
        <h2 class="section-title">{{ $t('passport.start') }}</h2>
        <p class="max-w-prose text-lg text-vaud-950">
          {{ $t('passport.startHelp') }}
        </p>
        <ul
          class="m-0 grid list-none gap-x-8 p-0 sm:grid-cols-2 lg:grid-cols-3"
        >
          <li v-for="c in starters" :key="c.slug">
            <PassportCard :course="c" />
          </li>
        </ul>
      </section>

      <nav
        :aria-label="$t('passport.filter.label')"
        class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-3"
      >
        <div class="flex flex-wrap gap-2">
          <UButton
            v-for="f in showFilters"
            :key="f.value"
            size="md"
            color="neutral"
            :variant="show === f.value ? 'solid' : 'outline'"
            :aria-pressed="show === f.value"
            :label="`${f.label} (${f.count})`"
            @click="setQuery({ show: f.value === 'all' ? undefined : f.value })"
          />
        </div>
        <div v-if="kindFilters.length > 2" class="flex flex-wrap gap-2">
          <UButton
            v-for="f in kindFilters"
            :key="f.value"
            size="md"
            color="neutral"
            :variant="activeKind === f.value ? 'soft' : 'ghost'"
            :aria-pressed="activeKind === f.value"
            :label="f.label"
            @click="setQuery({ type: f.value === 'all' ? undefined : f.value })"
          />
        </div>
      </nav>

      <ul
        class="m-0 mt-8 grid list-none gap-x-8 p-0 sm:grid-cols-2 lg:grid-cols-3"
      >
        <li v-for="e in shown" :key="e.course.slug">
          <PassportCard :course="e.course" :days="e.days" />
        </li>
      </ul>
      <p v-if="!shown.length" class="mt-8 text-lg text-slate-600" role="status">
        {{ $t('passport.nothing') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.counter {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 2.5rem;
  align-items: flex-end;
  padding: 1.75rem 2rem;
  margin-top: 2rem;
  background: rgb(10 64 105 / 10%); /* the tint of the page header */
}
.big {
  font-size: clamp(4.5rem, 12vw, 7rem);
  color: var(--color-vaud-green-600);
}
.big.none {
  color: var(--color-vaud-800);
}
.of {
  font-size: clamp(2rem, 5vw, 3rem);
}
.grow {
  flex: 1;
  min-width: 14rem;
  padding-bottom: 0.5rem;
}
.bar {
  height: 1rem;
  overflow: hidden;
  background: #fff;
}
.fill {
  height: 100%;
  background: var(--color-vaud-green-600);
  transition: width 0.4s;
}
</style>
