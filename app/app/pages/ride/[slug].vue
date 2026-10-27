<script setup lang="ts">
import { nearbyPoi, SHEET_ONLY_CATEGORIES, type Poi } from '~/lib/pois/nearby'
import { isFavoriteCommand } from '~/lib/pois/favorites'
import { speechText } from '~/lib/pois/speech'
import type { Segment } from '~/lib/segments/tracker'
import { availableSceneSources, resolveSceneSource, type SceneSource } from '~/lib/scene/source'

const route = useRoute()
const slug = String(route.params.slug)
const { data: course, status } = useCourseProfile(slug)

const { trainer, powerMeter, strap } = useDevices()
const { kit } = useKit()
const summaryOpen = ref(false)
const menuOpen = ref(false)

// 3D source: remembered per browser; Google is only offered when a key is configured
const { googleMapsKey, cesiumIonToken } = useRuntimeConfig().public
const keys = { google: !!googleMapsKey, ion: !!cesiumIonToken }
const { t } = useI18n()
const storedSource = useCookie<string>('scene-source', { maxAge: 60 * 60 * 24 * 365 })
const source = computed({
  get: () => resolveSceneSource(storedSource.value, keys),
  set: (v: SceneSource) => (storedSource.value = v),
})
const sourceItems = computed(() =>
  availableSceneSources(keys).map((s) => ({ value: s, label: t(`scene.source.${s}`) })),
)

const powerW = computed(() => trainer.data.value.powerW ?? powerMeter.data.value.powerW ?? 0)
const cadenceRpm = computed(() => trainer.data.value.cadenceRpm ?? powerMeter.data.value.cadenceRpm)
const heartRate = computed(() => strap.bpm.value ?? trainer.data.value.heartRate)

// ERG: during a workout the trainer holds the workout's power instead of following the grade
const workout = useWorkout()
onMounted(workout.load)
onBeforeUnmount(workout.stop)
const { state, paused, reset, seek } = useRide(
  computed(() => course.value ?? undefined),
  powerW,
  trainer.setGrade,
  workout.targetW,
  trainer.setTargetPower,
)
watch(() => state.value.elapsedS, workout.tick)
// a trainer without ERG support: the workout stops and the grade takes over again
watch(trainer.ergRefused, (refused) => refused && workout.stop())

// 3D needs WebGL; fall back to the 2D layout when unavailable or on request
const view = ref<'3d' | '2d'>('2d')
onMounted(() => {
  if (document.createElement('canvas').getContext('webgl2')) view.value = '3d'
})

// timed segments (climbs) of the course, loaded alongside; the ride works without them
const { data: courseInfo } = useAsyncData(
  `course-info-${slug}`,
  () => $fetch<{ data: { name?: Record<string, string>; segments?: Segment[]; pois?: Poi[] } }>(`/api/courses/${slug}`).catch(() => undefined),
  { server: false },
)
const { locale } = useI18n()
const courseName = computed(() => courseInfo.value?.data.name?.[locale.value] ?? courseInfo.value?.data.name?.fr ?? course.value?.name ?? '')
const segments = computed(() => courseInfo.value?.data.segments ?? [])
const pois = computed(() => courseInfo.value?.data.pois ?? [])
const poiNearby = computed(() =>
  nearbyPoi(
    state.value.distanceM,
    pois.value.filter((p) => !SHEET_ONLY_CATEGORIES.includes(p.category ?? '')),
  ),
)
const { active: activeSegment, finished: finishedSegment } = useSegments(slug, segments, state)

// each place is read aloud once, when it comes up; silence while paused or when it leaves
const speech = useSpeech()
const spoken = new Set<string>()
watch(
  () => poiNearby.value?.poi,
  (poi) => {
    if (!poi) return speech.stop()
    if (spoken.has(poi.id) || paused.value) return
    spoken.add(poi.id)
    speech.speak(speechText(poi, locale.value))
  },
)
watch(paused, (p) => p && speech.stop())
onBeforeUnmount(speech.stop)

const recording = useRideRecording(
  slug,
  computed(() => ({
    elapsedS: state.value.elapsedS,
    distanceM: state.value.distanceM,
    speedMs: state.value.speedMs,
    powerW: powerW.value,
    cadenceRpm: cadenceRpm.value,
    heartRate: heartRate.value,
  })),
  computed(() => state.value.elevationGainM),
)
// the summary opens by itself at the finish line
watch(() => state.value.finished, (done) => done && (summaryOpen.value = true))
function restart() {
  workout.stop()
  spoken.clear()
  reset()
  recording.reset()
  summaryOpen.value = false
  menuOpen.value = false
}

const { others } = useLiveRiders(
  String(route.params.slug),
  { distanceM: computed(() => state.value.distanceM), powerW },
  paused,
)
const gap = (m: number) => `${m >= 0 ? '+' : '−'}${Math.round(Math.abs(m))} m`

// sim mode: clicking the profile puts the rider there, and the ride stops counting as a real one
function onSeek(distanceM: number) {
  seek(distanceM)
  recording.teleported.value = true
}

// the ride stands still while the pause menu or the summary is open
watch([menuOpen, summaryOpen], ([menu, summary]) => (paused.value = menu || summary))

// a place is kept with the F key or by voice, because the rider has no hand free for the mouse
const favorites = useFavorites()
onMounted(favorites.load)
function keepPlace() {
  if (!poiNearby.value || paused.value) return
  favorites.add(poiNearby.value.poi.id)
  speech.speak(t('favorites.saved'), true)
}
const voice = useVoiceCommand((heard) => isFavoriteCommand(heard, locale.value) && keepPlace())
watch([() => voice.enabled.value, paused, voice.supported], ([on, isPaused, ok]) => (on && !isPaused && ok ? voice.start() : voice.stop()), { immediate: true })

// Space opens the pause menu, and closes it again (unless a control has the focus: Space presses it)
function onKey(e: KeyboardEvent) {
  if (e.code === 'KeyF' && !e.metaKey && !e.ctrlKey && !e.altKey) return keepPlace()
  if (e.code !== 'Space' || summaryOpen.value || state.value.finished) return
  if (e.target instanceof HTMLElement && e.target.closest('button, a, input, select, textarea, [role=combobox], [role=option]')) return
  e.preventDefault()
  menuOpen.value = !menuOpen.value
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

const unsupported = computed(() => [trainer, powerMeter, strap].some((d) => d.connection.value === 'unsupported'))
// ?sim=1 (hardware-free mode): the profile can be clicked to move the rider, without connecting anything
const simMode = computed(() => route.query.sim === '1')
// ?sim=1 shows a power slider that drives the fake trainer
const sim = computed(() => trainer.simDevice.value)
const simPower = computed({
  get: () => sim.value?.power ?? 0,
  set: (v: number) => sim.value && (sim.value.power = v),
})

// ?sim=1&d=1234: the current distance (m, every 10 m) follows in the URL, and opening it puts the rider back there
const DEBUG_STEP_M = 10
let seekedFromUrl = false
watch(
  course,
  (c) => {
    if (!c || seekedFromUrl) return
    seekedFromUrl = true
    const d = Number(route.query.d)
    if (simMode.value && route.query.d !== undefined && Number.isFinite(d) && d > 0) onSeek(d)
  },
  { immediate: true },
)
watch(
  () => Math.round(state.value.distanceM / DEBUG_STEP_M) * DEBUG_STEP_M,
  (d) => {
    if (!simMode.value || !course.value) return
    const url = new URL(window.location.href)
    url.searchParams.set('d', String(d))
    window.history.replaceState(window.history.state, '', url)
  },
)
</script>

<template>
  <div v-if="course" class="stage" :class="{ immersive: view === '3d' }">
    <Scene3D
      v-if="view === '3d'"
      :key="source"
      class="fill"
      :course="course"
      :distance-m="state.distanceM"
      :speed-ms="state.speedMs"
      :source="source"
      :google-key="googleMapsKey"
      :ion-token="cesiumIonToken"
      :cadence-rpm="cadenceRpm"
      :kit="kit"
      :others="others"
    />

    <details class="panel" :open="trainer.connection.value === 'idle'">
      <summary>{{ course.name }} · {{ $t('ble.devices') }}</summary>
      <p v-if="unsupported" role="alert">{{ $t('ble.unsupported') }}</p>
      <div class="devices">
        <UButton
          size="md"
          class="ring-1 ring-white/40"
          :loading="trainer.connection.value === 'connecting'"
          :label="`${$t('ble.trainer')} — ${$t(`ble.state.${trainer.connection.value}`)}`"
          @click="trainer.connect()"
        />
        <UButton
          size="md"
          class="ring-1 ring-white/40"
          :label="`${$t('ble.powerMeter')} — ${$t(`ble.state.${powerMeter.connection.value}`)}`"
          @click="powerMeter.connect()"
        />
        <UButton
          size="md"
          class="ring-1 ring-white/40"
          :label="`${$t('ble.heartRate')} — ${$t(`ble.state.${strap.connection.value}`)}`"
          @click="strap.connect()"
        />
        <UButton size="md" class="ring-1 ring-white/40" :label="$t('summary.end')" @click="summaryOpen = true" />
        <UButton size="md" class="ring-1 ring-white/40" :label="$t('ride.restart')" @click="restart()" />
        <UButton
          size="md"
          class="ring-1 ring-white/40"
          :label="$t(view === '3d' ? 'scene.show2d' : 'scene.show3d')"
          @click="view = view === '3d' ? '2d' : '3d'"
        />
      </div>
      <label v-if="view === '3d'" class="mt-2 flex items-center gap-2">
        {{ $t('scene.source.label') }}
        <USelect v-model="source" :items="sourceItems" size="sm" class="min-w-56" />
      </label>
      <UserMenu class="mt-2" />
      <label v-if="sim">
        {{ $t('ble.simPower') }} {{ simPower }} W
        <input v-model.number="simPower" type="range" min="0" max="500" step="10" >
      </label>
    </details>

    <RideDashboard
      class="hud-top"
      :overlay="view === '3d'"
      :power-w="powerW"
      :cadence-rpm="cadenceRpm"
      :heart-rate="heartRate"
      :speed-ms="state.speedMs"
      :grade-pct="state.gradePct"
      :distance-m="state.distanceM"
      :elevation-gain-m="state.elevationGainM"
      :elapsed-s="state.elapsedS"
    />
    <ElevationProfile
      class="hud-bottom"
      :course="course"
      :distance-m="state.distanceM"
      :others="others"
      :segments="segments"
      :pois="pois"
      :seekable="simMode"
      @seek="onSeek"
    />
    <RideSummary
      v-model:open="summaryOpen"
      :summary="recording.summary.value"
      :elevation-gain-m="state.elevationGainM"
      :recordable="recording.recordable.value"
      :teleported="recording.teleported.value"
      :save-state="recording.saveState.value"
      :efforts="recording.efforts.value"
      :course-name="courseName"
      :course-profile="course ?? undefined"
      :kept-places="pois.filter((p) => favorites.added.value.includes(p.id))"
      :finished="state.finished"
      @save="recording.save($event)"
      @restart="restart()"
    />
    <PoiCard v-if="poiNearby" class="poi-card-slot" :poi="poiNearby.poi" :ahead-m="poiNearby.aheadM" :favorite="favorites.isFavorite(poiNearby.poi.id)" />
    <SegmentHud
      class="segment-hud"
      :active="activeSegment"
      :finished="finishedSegment"
      :distance-m="state.distanceM"
      :elapsed-s="state.elapsedS"
    />
    <WorkoutHud
      class="workout-hud"
      :workout="workout.active.value"
      :progress="workout.progress.value"
      :ftp="workout.ftp.value"
      :power-w="powerW"
      :completed="workout.completed.value"
      :refused="trainer.ergRefused.value"
    />
    <ul v-if="others.length" class="riders" :aria-label="$t('live.riders')">
      <li v-for="o in others.slice(0, 5)" :key="o.id">
        <span>{{ o.nickname }}</span> <span>{{ o.powerW }} W</span> <strong>{{ gap(o.gapM) }}</strong>
      </li>
    </ul>
    <UButton
      v-if="speech.supported"
      class="mute"
      color="neutral"
      variant="solid"
      :icon="speech.enabled.value && speech.volume.value > 0 ? 'i-lucide-volume-2' : 'i-lucide-volume-x'"
      :aria-label="$t(speech.enabled.value ? 'sound.mute' : 'sound.unmute')"
      :aria-pressed="!speech.enabled.value"
      @click="speech.enabled.value = !speech.enabled.value"
    />
    <UButton
      class="pause"
      :icon="menuOpen ? 'i-lucide-play' : 'i-lucide-pause'"
      :label="$t(menuOpen ? 'ride.resume' : 'ride.pause')"
      :disabled="state.finished"
      @click="menuOpen = !menuOpen"
    />
    <PauseMenu v-model:open="menuOpen" @finish="summaryOpen = true" />
    <p v-if="state.finished" class="finished" role="status">{{ $t('ride.finished') }}</p>
    <small v-if="view === '2d'">{{ course.attribution }}</small>
  </div>
  <p v-else-if="status === 'error'">{{ $t('ride.notFound') }}</p>
  <p v-else>{{ $t('ride.loading') }}</p>
</template>

<style scoped>
.stage {
  display: grid;
  gap: 1rem;
  max-width: 60rem;
  margin: 0 auto;
  padding: 1rem;
}
.devices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

/* immersive: the 3D scene fills the screen, everything else floats over it */
.immersive {
  position: fixed;
  inset: 0;
  display: block;
  max-width: none;
  margin: 0;
  padding: 0;
  overflow: hidden;
  background: #000;
}
.fill {
  position: absolute;
  inset: 0;
}
.immersive .panel {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  z-index: 2;
  max-width: min(26rem, calc(100% - 1.5rem));
  padding: 0.5rem 0.75rem;
  color: #fff;
  background: rgb(0 0 0 / 55%);
  backdrop-filter: blur(6px);
  border-radius: 0.5rem;
}
.immersive .panel summary {
  cursor: pointer;
}
.immersive .hud-top {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 1;
  width: min(56rem, calc(100% - 1.5rem));
  pointer-events: none;
}
.immersive .hud-bottom {
  position: absolute;
  left: 50%;
  bottom: 2rem;
  z-index: 1;
  width: min(48rem, calc(100% - 1.5rem));
  transform: translateX(-50%);
  opacity: 0.9;
}
.riders {
  padding: 0.5rem 0.75rem;
  font-size: 0.9rem;
  list-style: none;
  background: rgb(255 255 255 / 85%);
  border-radius: 0.5rem;
}
.riders li {
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
}
.immersive .poi-card-slot {
  position: absolute;
  left: 0.75rem;
  bottom: 11rem;
  z-index: 2;
}
.immersive .segment-hud {
  position: absolute;
  top: 6.5rem;
  right: 0.75rem;
  z-index: 2;
  min-width: 14rem;
}
.immersive .workout-hud {
  position: absolute;
  top: 11rem;
  right: 0.75rem;
  z-index: 2;
  min-width: 14rem;
}
.immersive .riders {
  position: absolute;
  left: 0.75rem;
  bottom: 2rem;
  z-index: 2;
  min-width: 12rem;
}
.immersive .pause {
  position: absolute;
  right: 0.75rem;
  bottom: 2rem;
  z-index: 2;
}
.immersive .mute {
  position: absolute;
  right: 9.5rem;
  bottom: 2rem;
  z-index: 2;
}
.finished {
  position: absolute;
  top: 40%;
  left: 50%;
  z-index: 3;
  padding: 0.75rem 1.5rem;
  font-size: 1.5rem;
  color: #fff;
  background: rgb(0 0 0 / 65%);
  border-radius: 0.5rem;
  transform: translateX(-50%);
}
</style>
