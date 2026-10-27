<script setup lang="ts">
import { LIMITS, POSITION_KEYS, POSITIONS } from '~/lib/rider/settings'

const { t } = useI18n()
const { loggedIn } = useAuth()
const rider = useRiderSettings()
const { settings, sync, ftpSuggestion } = rider
onMounted(rider.load)

// the form writes through `update`, which clamps each value, so the page never holds a value the app would refuse
const field = <K extends keyof typeof settings.value>(key: K) =>
  computed({
    get: () => settings.value[key],
    set: (v) => rider.update({ [key]: v } as never),
  })
const weightKg = field('weightKg')
const bikeKg = field('bikeKg')
const ftp = field('ftp')
const cda = field('cda')
const position = field('position')
const difficultyPct = field('difficultyPct')

const positionItems = computed(() =>
  POSITION_KEYS.map((p) => ({
    value: p,
    label:
      p === 'custom'
        ? t('rider.position.custom')
        : `${t(`rider.position.${p}`)} · CdA ${POSITIONS[p].toFixed(2)}`,
  })),
)
const dateFmt = (iso: string) => new Date(iso).toLocaleDateString()

// the right to deletion: weight and the rest are removed from the account, not just reset
const erasing = ref(false)
const eraseState = ref<'done' | 'error'>()
async function erase() {
  erasing.value = true
  eraseState.value = undefined
  try {
    await rider.erase()
    eraseState.value = 'done'
  } catch {
    eraseState.value = 'error'
  } finally {
    erasing.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh bg-white">
    <AppHeader />

    <div class="mx-auto max-w-3xl space-y-6 px-4 pb-16">
      <h1 class="section-title">{{ $t('rider.title') }}</h1>
      <p class="max-w-prose text-lg text-vaud-950">{{ $t('rider.intro') }}</p>
      <UAlert color="neutral" variant="subtle" icon="i-lucide-shield-check">
        <template #description>
          {{ $t(loggedIn ? 'rider.privacyAccount' : 'rider.privacyLocal') }}
          <NuxtLink to="/privacy" class="underline">{{
            $t('privacy.link')
          }}</NuxtLink>
        </template>
      </UAlert>

      <PageSection :title="$t('rider.you')">
        <div class="space-y-5">
          <UFormField
            :label="$t('rider.weight')"
            :help="$t('rider.weightHelp')"
          >
            <UInput
              v-model.number="weightKg"
              type="number"
              :min="LIMITS.weightKg.min"
              :max="LIMITS.weightKg.max"
              step="0.5"
              class="w-32"
            />
          </UFormField>
          <UFormField :label="$t('rider.bike')" :help="$t('rider.bikeHelp')">
            <UInput
              v-model.number="bikeKg"
              type="number"
              :min="LIMITS.bikeKg.min"
              :max="LIMITS.bikeKg.max"
              step="0.5"
              class="w-32"
            />
          </UFormField>
          <UFormField
            :label="$t('rider.position.label')"
            :help="$t('rider.positionHelp')"
          >
            <USelect v-model="position" :items="positionItems" class="w-full" />
          </UFormField>
          <UFormField
            v-if="position === 'custom'"
            :label="$t('rider.cda')"
            :help="$t('rider.cdaHelp')"
          >
            <UInput
              v-model.number="cda"
              type="number"
              :min="LIMITS.cda.min"
              :max="LIMITS.cda.max"
              step="0.01"
              class="w-32"
            />
          </UFormField>
        </div>
      </PageSection>

      <PageSection :title="$t('rider.power')">
        <div class="space-y-5">
          <UFormField :label="$t('rider.ftp')" :help="$t('rider.ftpHelp')">
            <UInput
              v-model.number="ftp"
              type="number"
              :min="LIMITS.ftp.min"
              :max="LIMITS.ftp.max"
              step="5"
              class="w-32"
            />
          </UFormField>
          <UAlert
            v-if="ftpSuggestion"
            color="primary"
            variant="subtle"
            icon="i-lucide-trending-up"
            :title="$t('rider.ftpEstimate', { watts: ftpSuggestion.ftp })"
            :description="
              $t('rider.ftpEstimateFrom', {
                watts: ftpSuggestion.avg_power_w,
                date: dateFmt(ftpSuggestion.achieved_at),
              })
            "
            :actions="[
              { label: $t('rider.ftpUse'), onClick: () => rider.useEstimate() },
            ]"
          />
          <p
            v-else-if="loggedIn && rider.estimate.value === null"
            class="m-0 text-sm text-slate-600"
          >
            {{ $t('rider.ftpNone') }}
          </p>
        </div>
      </PageSection>

      <PageSection :title="$t('rider.difficulty')">
        <div class="space-y-3">
          <p class="m-0">{{ $t('rider.difficultyHelp') }}</p>
          <label class="block">
            <strong>{{
              $t('rider.difficultyNow', { pct: difficultyPct })
            }}</strong>
            <USlider
              v-model="difficultyPct"
              :min="LIMITS.difficultyPct.min"
              :max="LIMITS.difficultyPct.max"
              :step="LIMITS.difficultyPct.step"
              class="mt-4"
            />
          </label>
        </div>
      </PageSection>

      <!-- what leaves the account: apart from the settings, and in red -->
      <div
        class="flex flex-wrap items-center gap-3 border-t border-[#e6ecf0] pt-6"
      >
        <UButton
          color="neutral"
          variant="outline"
          icon="i-lucide-rotate-ccw"
          :label="$t('rider.reset')"
          @click="rider.reset()"
        />
        <UButton
          color="error"
          variant="ghost"
          icon="i-lucide-trash-2"
          :label="$t(loggedIn ? 'rider.eraseAccount' : 'rider.eraseLocal')"
          :loading="erasing"
          @click="erase"
        />
        <span v-if="loggedIn" class="text-sm text-slate-600" role="status">
          {{ $t(`rider.sync.${sync}`) }}
        </span>
      </div>
      <p
        v-if="eraseState"
        class="m-0 text-sm"
        role="status"
        :class="eraseState === 'error' ? 'text-red-700' : 'text-slate-600'"
      >
        {{ $t(`rider.erase.${eraseState}`) }}
      </p>
    </div>
  </div>
</template>
