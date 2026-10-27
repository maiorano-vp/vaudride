<script setup lang="ts">
import { nearbyPoi, SHEET_ONLY_CATEGORIES, type Poi } from '~/lib/pois/nearby'
import { isFavoriteCommand } from '~/lib/pois/favorites'
import { speechText } from '~/lib/pois/speech'
import type { Segment } from '~/lib/segments/tracker'
import { CHOICES } from '~/lib/scene/quality'
import { availableSceneSources, resolveSceneSource, SceneSource } from '~/lib/scene/source'

const route = useRoute()
const slug = String(route.params.slug)
const { data: course, status } = useCourseProfile(slug)

// ?demo=1: discovery mode, a fly-over at a speed chosen by hand (trade fairs, vaud.ch): no trainer, nothing recorded.
// ?speed=60 sets the starting speed in km/h.
const demo = route.query.demo === '1'
const FLY_KMH = { min: 10, max: 150, start: 40 }
// faster than this, the places go by before they could be read out: the voice is cut
const SPEECH_MAX_KMH = 60
const flyKmh = ref(Math.min(FLY_KMH.max, Math.max(FLY_KMH.min, Number(route.query.speed) || FLY_KMH.start)))
const flyMs = computed(() => (demo ? flyKmh.value / 3.6 : undefined))
const tooFastToSpeak = computed(() => demo && flyKmh.value > SPEECH_MAX_KMH)

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
const rider = useRiderSettings()
onMounted(rider.load)
const { state, paused, reset, seek } = useRide(computed(() => course.value ?? undefined), powerW, trainer.setGrade, {
  targetW: workout.targetW,
  onTarget: trainer.setTargetPower,
  flyMs,
  params: rider.params,
  difficultyPct: rider.difficultyPct,
})
// weight and drag area also reach the trainer (the wind and rolling resistance of its simulation)
watch(rider.params, trainer.setParams, { immediate: true })
watch(() => state.value.elapsedS, workout.tick)
// a trainer without ERG support: the workout stops and the grade takes over again
watch(trainer.ergRefused, (refused) => refused && workout.stop())

// How the scene is drawn follows the rider's choice and, in Auto, the machine: 3D needs WebGL2, and the lightest level
// uses the light scene (no 3D buildings or Google tiles). The 2D layout is the fallback and can be picked.
const quality = useSceneQuality()
const toast = useToast()
const view = ref<'3d' | '2d'>('2d')
const motion = useReducedMotion()
let stopListening: Array<() => void> = []
onMounted(() => {
  stopListening = [quality.detect(), motion.followSystem()]
  view.value = quality.effective.value === '2d' ? '2d' : '3d'
})
onBeforeUnmount(() => stopListening.forEach((stop) => stop()))
watch(
  () => quality.effective.value === '2d',
  (is2d) => (view.value = is2d ? '2d' : '3d'),
)
const sceneSource = computed(() => (quality.level.value === 'low' ? SceneSource.Light : source.value))
const qualityItems = computed(() => CHOICES.map((c) => ({ value: c, label: t(`quality.${c}`) })))
const qualityChoice = computed({ get: () => quality.choice.value, set: quality.choose })
function onSlow() {
  const lower = quality.stepDown()
  if (lower) toast.add({ title: t('quality.lowered', { level: t(`quality.${lower}`) }), icon: 'i-lucide-gauge', color: 'neutral' })
}

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

// the ghost (own best or last ride, or the segment record) rides next to the live riders
const ghost = useGhost()
onMounted(() => !demo && ghost.load(slug))
const ghostRider = useGhostRider(ghost, state, segments, activeSegment, computed(() => t('ghost.label')))

// each place is read aloud once, when it comes up; silence while paused or when it leaves
const speech = useSpeech()
const spoken = new Set<string>()
watch(
  () => poiNearby.value?.poi,
  (poi) => {
    if (!poi) return speech.stop()
    if (spoken.has(poi.id) || paused.value || tooFastToSpeak.value) return
    spoken.add(poi.id)
    speech.speak(speechText(poi, locale.value))
  },
)
watch(paused, (p) => p && speech.stop())
watch(tooFastToSpeak, (fast) => fast && speech.stop())
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
// the summary opens by itself at the finish line; the discovery fly-over starts again by itself instead
const LOOP_DELAY_MS = 5000
let loopTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => state.value.finished,
  (done) => {
    if (!done) return
    if (demo) loopTimer = setTimeout(restart, LOOP_DELAY_MS)
    else summaryOpen.value = true
  },
)
onBeforeUnmount(() => clearTimeout(loopTimer))
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
  computed(() => paused.value || demo), // a fly-over is not a ride: it does not show up for the live riders
)
const riders = computed(() => (demo ? [] : ghostRider.value ? [ghostRider.value, ...others.value] : others.value))
const gap = (m: number) => `${m >= 0 ? '+' : '−'}${Math.round(Math.abs(m))} m`
const ghostGapS = (o: object) => (o as { gapS?: number }).gapS
const gapSeconds = (s: number) => `${s >= 0 ? '+' : '−'}${Math.round(Math.abs(s))} s`

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
      :key="sceneSource"
      class="fill"
      :course="course"
      :distance-m="state.distanceM"
      :speed-ms="state.speedMs"
      :source="sceneSource"
      :quality="quality.profile.value"
      :reduced-motion="motion.reduced.value"
      :google-key="googleMapsKey"
      :ion-token="cesiumIonToken"
      :cadence-rpm="cadenceRpm"
      :kit="kit"
      :others="riders"
      @slow="onSlow"
    />

    <!-- discovery mode: one speed control instead of devices -->
    <label v-if="demo" class="demo-speed">
      {{ $t('demo.speed') }} {{ Math.round(flyKmh) }} km/h
      <input v-model.number="flyKmh" type="range" :min="FLY_KMH.min" :max="FLY_KMH.max" step="5" >
      <small v-if="tooFastToSpeak" role="status">{{ $t('demo.muted', { kmh: SPEECH_MAX_KMH }) }}</small>
    </label>

    <details v-else class="panel" :open="trainer.connection.value === 'idle'">
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
      <label class="mt-2 flex items-center gap-2">
        {{ $t('quality.label') }}
        <USelect v-model="qualityChoice" :items="qualityItems" size="sm" class="min-w-44" />
      </label>
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
      :demo="demo"
      :difficulty-pct="rider.difficultyPct.value"
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
      :others="riders"
      :segments="segments"
      :pois="pois"
      :seekable="simMode"
      @seek="onSeek"
    />
    <RideSummary
      v-if="!demo"
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
    <ul v-if="riders.length" class="riders" :aria-label="$t('live.riders')">
      <li v-for="o in riders.slice(0, 5)" :key="o.id">
        <span>{{ o.nickname }}</span>
        <span v-if="ghostGapS(o) !== undefined">{{ gapSeconds(ghostGapS(o)!) }}</span>
        <span v-else-if="o.id !== 'ghost'">{{ o.powerW }} W</span>
        <strong>{{ gap(o.gapM) }}</strong>
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
    <PauseMenu v-model:open="menuOpen" :demo="demo" @finish="summaryOpen = true" />
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
.demo-speed {
  display: grid;
  gap: 0.25rem;
  padding: 0.5rem 0.9rem;
  color: #fff;
  background: rgb(10 64 105 / 92%);
  border-radius: 0.5rem;
}
.immersive .demo-speed {
  position: absolute;
  left: 0.75rem;
  bottom: 2rem;
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
