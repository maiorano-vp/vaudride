<script setup lang="ts">
import type { CourseProfile } from '~/lib/course/profile'

const route = useRoute()
// absolute URL: in dev, SSR-internal fetches cannot see public/ files
const origin = useRequestURL().origin
const { data: course } = await useFetch<CourseProfile>(`${origin}/courses/${route.params.slug}/profile.json`)

const trainer = useTrainer()
const powerMeter = usePowerMeter()
const strap = useHeartRate()

const powerW = computed(() => trainer.data.value.powerW ?? powerMeter.data.value.powerW ?? 0)
const cadenceRpm = computed(() => trainer.data.value.cadenceRpm ?? powerMeter.data.value.cadenceRpm)
const heartRate = computed(() => strap.bpm.value ?? trainer.data.value.heartRate)

const { state, reset } = useRide(computed(() => course.value ?? undefined), powerW, trainer.setGrade)

// 3D needs WebGL; fall back to the 2D layout when unavailable or on request
const view = ref<'3d' | '2d'>('2d')
onMounted(() => {
  if (document.createElement('canvas').getContext('webgl2')) view.value = '3d'
})

const unsupported = computed(() => [trainer, powerMeter, strap].some((d) => d.connection.value === 'unsupported'))
// ?sim=1 shows a power slider that drives the fake trainer
const sim = computed(() => trainer.simDevice.value)
const simPower = computed({
  get: () => sim.value?.power ?? 0,
  set: (v: number) => sim.value && (sim.value.power = v),
})
</script>

<template>
  <div v-if="course" class="stage" :class="{ immersive: view === '3d' }">
    <Scene3D v-if="view === '3d'" class="fill" :course="course" :distance-m="state.distanceM" :speed-ms="state.speedMs" />

    <details class="panel" :open="trainer.connection.value === 'idle'">
      <summary>{{ course.name }} · {{ $t('ble.devices') }}</summary>
      <p v-if="unsupported" role="alert">{{ $t('ble.unsupported') }}</p>
      <div class="devices">
        <button :disabled="trainer.connection.value === 'connecting'" @click="trainer.connect()">
          {{ $t('ble.trainer') }} — {{ $t(`ble.state.${trainer.connection.value}`) }}
        </button>
        <button @click="powerMeter.connect()">
          {{ $t('ble.powerMeter') }} — {{ $t(`ble.state.${powerMeter.connection.value}`) }}
        </button>
        <button @click="strap.connect()">{{ $t('ble.heartRate') }} — {{ $t(`ble.state.${strap.connection.value}`) }}</button>
        <button @click="reset()">{{ $t('ride.restart') }}</button>
        <button @click="view = view === '3d' ? '2d' : '3d'">{{ $t(view === '3d' ? 'scene.show2d' : 'scene.show3d') }}</button>
      </div>
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
    <ElevationProfile class="hud-bottom" :course="course" :distance-m="state.distanceM" />
    <p v-if="state.finished" class="finished" role="status">{{ $t('ride.finished') }}</p>
    <small v-if="view === '2d'">{{ course.attribution }}</small>
  </div>
  <p v-else>{{ $t('ride.notFound') }}</p>
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
