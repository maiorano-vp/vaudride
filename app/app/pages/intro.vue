<script setup lang="ts">
const route = useRoute()
const { trainer, powerMeter, strap } = useDevices()

const unsupported = computed(() => [trainer, powerMeter, strap].some((d) => d.connection.value === 'unsupported'))
const hasPower = computed(() => trainer.data.value.powerW ?? powerMeter.data.value.powerW)

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
  { key: 'trainer', d: trainer, live: trainer.data.value.powerW !== undefined ? `${trainer.data.value.powerW} W · ${trainer.data.value.cadenceRpm ?? '–'} rpm · ${(trainer.data.value.speedKmh ?? 0).toFixed(1)} km/h` : '' },
  { key: 'powerMeter', d: powerMeter, live: powerMeter.data.value.powerW !== undefined ? `${powerMeter.data.value.powerW} W` : '' },
  { key: 'heartRate', d: strap, live: strap.bpm.value ? `${strap.bpm.value} bpm` : '' },
])
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('intro.title') }}</h1>
      <UserMenu />
    </header>

    <p>{{ $t('intro.lead') }}</p>
    <UAlert v-if="unsupported" color="error" variant="subtle" :description="$t('ble.unsupported')" />

    <UCard>
      <template #header>{{ $t('intro.step1') }}</template>
      <ul class="space-y-3">
        <li v-for="x in devices" :key="x.key" class="flex flex-wrap items-center gap-3">
          <UButton
            :label="$t(`ble.${x.key}`)"
            :loading="x.d.connection.value === 'connecting'"
            :color="x.d.connection.value === 'connected' ? 'success' : 'primary'"
            @click="x.d.connect()"
          />
          <span class="text-sm">
            {{ $t(`ble.state.${x.d.connection.value}`) }}<template v-if="x.d.deviceName.value"> · {{ x.d.deviceName.value }}</template>
          </span>
          <span v-if="x.live" class="ml-auto font-mono">{{ x.live }}</span>
        </li>
      </ul>
      <label v-if="sim" class="mt-4 block">
        {{ $t('ble.simPower') }} {{ simPower }} W
        <input v-model.number="simPower" type="range" min="0" max="500" step="10" class="w-full">
      </label>
    </UCard>

    <UCard>
      <template #header>{{ $t('intro.step2') }}</template>
      <p class="mb-2 text-sm">{{ $t('intro.pedal') }}</p>
      <UAlert
        :color="hasPower ? 'success' : 'neutral'"
        variant="subtle"
        :description="hasPower ? $t('intro.powerOk', { w: hasPower }) : $t('intro.powerWait')"
      />
    </UCard>

    <UCard>
      <template #header>{{ $t('intro.step3') }}</template>
      <p class="mb-2 text-sm">{{ $t('intro.resistance') }}</p>
      <label class="block">
        {{ $t('dashboard.grade') }} {{ testGrade }} %
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
    </UCard>

    <UButton
      size="xl"
      :label="$t('intro.start')"
      :to="{ path: '/ride/marchairuz-biere', query: route.query }"
    />
  </div>
</template>
