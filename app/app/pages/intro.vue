<script setup lang="ts">
const route = useRoute()
const { t } = useI18n()
// the step number is shown in a disc, so the title is its text without the "1. " in front
const stepTitle = (key: string) => t(key).replace(/^\d+\.\s*/, '')
const { trainer, powerMeter, strap } = useDevices()

const unsupported = computed(() =>
  [trainer, powerMeter, strap].some(
    (d) => d.connection.value === 'unsupported',
  ),
)
const hasPower = computed(
  () => trainer.data.value.powerW ?? powerMeter.data.value.powerW,
)

// test slider: sends a grade to the trainer so the user can feel the resistance
const testGrade = ref(0)
watch(testGrade, (g) => trainer.setGrade(g))
onBeforeUnmount(() => trainer.setGrade(0))

// ?sim=1: drive the fake trainer's power
const sim = computed(() => trainer.simDevice.value)
const simPower = computed({
  get: () => sim.value?.power ?? 0,
  set: (v: number) => sim.value && (sim.value.power = v),
})

const devices = computed(() => [
  {
    key: 'trainer',
    d: trainer,
    live:
      trainer.data.value.powerW !== undefined
        ? `${trainer.data.value.powerW} W · ${trainer.data.value.cadenceRpm ?? '–'} rpm · ${(trainer.data.value.speedKmh ?? 0).toFixed(1)} km/h`
        : '',
  },
  {
    key: 'powerMeter',
    d: powerMeter,
    live:
      powerMeter.data.value.powerW !== undefined
        ? `${powerMeter.data.value.powerW} W`
        : '',
  },
  {
    key: 'heartRate',
    d: strap,
    live: strap.bpm.value ? `${strap.bpm.value} bpm` : '',
  },
])
</script>

<template>
  <div class="min-h-dvh bg-white">
    <AppHeader />

    <div class="mx-auto max-w-3xl space-y-6 px-4 pb-16">
      <h1 class="section-title">{{ $t('intro.title') }}</h1>
      <p class="max-w-prose text-lg text-vaud-950">{{ $t('intro.lead') }}</p>
      <UAlert
        v-if="unsupported"
        color="error"
        variant="subtle"
        :description="$t('ble.unsupported')"
      />

      <PageSection :title="stepTitle('intro.step1')" :step="1">
        <ul class="m-0 list-none space-y-3 p-0">
          <li
            v-for="x in devices"
            :key="x.key"
            class="flex flex-wrap items-center gap-x-4 gap-y-2 bg-white px-4 py-3"
          >
            <UButton
              :label="$t(`ble.${x.key}`)"
              :loading="x.d.connection.value === 'connecting'"
              :color="
                x.d.connection.value === 'connected' ? 'success' : 'primary'
              "
              :icon="
                x.d.connection.value === 'connected'
                  ? 'i-lucide-check'
                  : 'i-lucide-bluetooth'
              "
              @click="x.d.connect()"
            />
            <span class="text-sm text-slate-600">
              {{ $t(`ble.state.${x.d.connection.value}`)
              }}<template v-if="x.d.deviceName.value">
                · {{ x.d.deviceName.value }}</template
              >
            </span>
            <span v-if="x.live" class="ml-auto font-mono">{{ x.live }}</span>
          </li>
        </ul>
        <label v-if="sim" class="mt-4 block">
          {{ $t('ble.simPower') }} {{ simPower }} W
          <input
            v-model.number="simPower"
            type="range"
            min="0"
            max="500"
            step="10"
            class="w-full"
          >
        </label>
      </PageSection>

      <PageSection :title="stepTitle('intro.step2')" :step="2">
        <p class="mb-3 mt-0">{{ $t('intro.pedal') }}</p>
        <UAlert
          :color="hasPower ? 'success' : 'neutral'"
          variant="subtle"
          :icon="hasPower ? 'i-lucide-zap' : 'i-lucide-loader'"
          :description="
            hasPower
              ? $t('intro.powerOk', { w: hasPower })
              : $t('intro.powerWait')
          "
        />
      </PageSection>

      <PageSection :title="stepTitle('intro.step3')" :step="3">
        <p class="mb-3 mt-0">{{ $t('intro.resistance') }}</p>
        <label class="block">
          {{ $t('dashboard.grade') }} <strong>{{ testGrade }} %</strong>
          <input
            v-model.number="testGrade"
            type="range"
            min="-10"
            max="15"
            step="1"
            class="w-full"
            :disabled="trainer.connection.value !== 'connected'"
          >
        </label>
      </PageSection>

      <div class="flex flex-wrap items-center gap-4 pt-2">
        <UButton
          size="xl"
          icon="i-lucide-bike"
          :label="$t('intro.start')"
          :to="{ path: '/ride/marchairuz-biere', query: route.query }"
        />
        <UButton
          color="neutral"
          variant="outline"
          size="xl"
          icon="i-lucide-sliders-horizontal"
          :label="$t('rider.menu')"
          to="/settings"
        />
      </div>
    </div>
  </div>
</template>
