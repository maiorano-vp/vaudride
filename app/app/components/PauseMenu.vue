<script setup lang="ts">
import { TEMPLATES } from '~/lib/workout/templates'
import { totalDuration, workoutName, type Workout } from '~/lib/workout/workout'

type View = 'menu' | 'devices' | 'kit' | 'workout' | 'ghost' | 'sound' | 'account' | 'quit'

const open = defineModel<boolean>('open', { required: true })
const emit = defineEmits<{ finish: [] }>()

const { trainer, powerMeter, strap } = useDevices()
const { user, logout } = useAuth()
const { t } = useI18n()
const speech = useSpeech()
const volumePct = computed({
  get: () => Math.round(speech.volume.value * 100),
  set: (v: number) => (speech.volume.value = v / 100),
})
const voice = useVoiceCommand(() => {})
const testVoice = () => speech.speak(t('sound.testPhrase'), true)

const view = ref<View>('menu')
// always reopen on the menu itself
watch(open, (o) => {
  if (!o) return
  view.value = 'menu'
  editing.value = false
})

// ERG workouts: the clock is the ride's, so the workout starts and stops with the ride
const workout = useWorkout()
const { locale } = useI18n()
const workouts = computed(() => [...TEMPLATES, ...(workout.custom.value ? [workout.custom.value] : [])])
const workoutItems = computed(() => workouts.value.map((w) => ({ value: w.id, label: workoutName(w, locale.value) })))
const selectedId = ref(TEMPLATES[0]!.id)
const selected = computed(() => workouts.value.find((w) => w.id === selectedId.value))
const ftp = computed({ get: () => workout.ftp.value, set: (v: number) => Number.isFinite(v) && workout.setFtp(v) })
const trainerReady = computed(() => trainer.connection.value === 'connected')
function startSelected() {
  if (!selected.value) return
  trainer.ergRefused.value = false
  workout.start(selected.value)
  open.value = false
}
const minutes = (w: Workout) => Math.round(totalDuration(w) / 60)

// ghosts: own best or last finished ride on this course, or the record of the next segment
const ghost = useGhost()
const fmtTime = (s: number) => `${Math.floor(s / 3600) ? `${Math.floor(s / 3600)}:` : ''}${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}:${String(Math.floor(s % 60)).padStart(2, '0')}`
const ghostItems = computed(() => {
  const mine = ghost.course.value
  const when = (g?: { duration_s: number } | null) => (g ? fmtTime(g.duration_s) : t('ghost.none'))
  return [
    { value: 'none', label: t('ghost.off') },
    { value: 'best', label: t('ghost.best'), description: when(mine?.best), disabled: !mine?.best },
    { value: 'last', label: t('ghost.last'), description: when(mine?.last), disabled: !mine?.last },
    { value: 'kom', label: t('ghost.kom'), description: t('ghost.komHelp') },
  ]
})
const ghostChoice = computed({
  get: () => ghost.choice.value,
  set: (v) => ghost.choose(v as 'none' | 'best' | 'last' | 'kom'),
})

const editing = ref(false)
const draft = ref<Array<{ min: number; pct: number }>>([])
function edit() {
  const base = workout.custom.value ?? selected.value
  draft.value = (base?.steps ?? [{ durationS: 600, ftpPct: 55 }]).map((s) => ({ min: s.durationS / 60, pct: s.ftpPct }))
  editing.value = true
}
const draftSteps = computed(() =>
  draft.value.filter((s) => s.min > 0 && s.pct > 0).map((s) => ({ durationS: Math.round(s.min * 60), ftpPct: Math.min(s.pct, 200) })),
)
function saveDraft() {
  if (!draftSteps.value.length) return
  const custom: Workout = { id: 'custom', name: { fr: t('workout.custom') }, steps: draftSteps.value }
  workout.saveCustom(custom)
  selectedId.value = 'custom'
  editing.value = false
  startSelected()
}

const unsupported = computed(() => [trainer, powerMeter, strap].some((d) => d.connection.value === 'unsupported'))
const devices = computed(() => [
  { key: 'trainer', label: t('ble.trainer'), icon: 'i-lucide-bike', device: trainer },
  { key: 'powerMeter', label: t('ble.powerMeter'), icon: 'i-lucide-zap', device: powerMeter },
  { key: 'heartRate', label: t('ble.heartRate'), icon: 'i-lucide-heart-pulse', device: strap },
])

const items = computed(() => [
  { key: 'continue', icon: 'i-lucide-play', action: () => (open.value = false) },
  { key: 'devices', icon: 'i-lucide-bluetooth', action: () => (view.value = 'devices') },
  { key: 'kit', icon: 'i-lucide-shirt', action: () => (view.value = 'kit') },
  { key: 'workout', icon: 'i-lucide-timer', action: () => (view.value = 'workout') },
  { key: 'ghost', icon: 'i-lucide-ghost', action: () => (view.value = 'ghost') },
  ...(speech.supported.value ? [{ key: 'sound', icon: 'i-lucide-volume-2', action: () => (view.value = 'sound') }] : []),
  { key: 'account', icon: 'i-lucide-user', action: () => (view.value = 'account') },
  { key: 'quit', icon: 'i-lucide-log-out', action: () => (view.value = 'quit') },
])

function finish() {
  open.value = false
  emit('finish')
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="view === 'menu' ? $t('ride.paused') : $t(`pause.${view}`)"
    :ui="{ overlay: 'bg-black/60 backdrop-blur-sm' }"
  >
    <template #body>
      <UButton
        v-if="view !== 'menu'"
        class="mb-4"
        color="neutral"
        variant="ghost"
        icon="i-lucide-arrow-left"
        :label="$t('auth.back')"
        @click="view = 'menu'"
      />

      <ul v-if="view === 'menu'" class="space-y-2">
        <li v-for="item in items" :key="item.key">
          <UButton
            block
            size="xl"
            :color="item.key === 'continue' ? 'primary' : 'neutral'"
            :variant="item.key === 'continue' ? 'solid' : 'outline'"
            :icon="item.icon"
            :label="$t(`pause.${item.key}`)"
            class="justify-start"
            @click="item.action()"
          />
        </li>
      </ul>

      <div v-else-if="view === 'devices'" class="space-y-3">
        <p v-if="unsupported" role="alert" class="text-red-700">{{ $t('ble.unsupported') }}</p>
        <div v-for="d in devices" :key="d.key" class="flex items-center justify-between gap-3">
          <span class="flex items-center gap-2">
            <UIcon :name="d.icon" />
            {{ d.label }}
          </span>
          <span class="flex items-center gap-3">
            <UBadge :color="d.device.connection.value === 'connected' ? 'success' : 'neutral'" variant="subtle">
              {{ $t(`ble.state.${d.device.connection.value}`) }}
            </UBadge>
            <UButton
              size="md"
              color="neutral"
              variant="outline"
              :loading="d.device.connection.value === 'connecting'"
              :disabled="d.device.connection.value === 'unsupported'"
              :label="$t('pause.connect')"
              @click="d.device.connect()"
            />
          </span>
        </div>
      </div>

      <KitEditor v-else-if="view === 'kit'" :preview="false" />

      <div v-else-if="view === 'workout'" class="space-y-4">
        <p class="text-sm text-slate-500">{{ $t('workout.intro') }}</p>
        <p v-if="!trainerReady" role="alert" class="text-sm text-red-700">{{ $t('workout.needsTrainer') }}</p>
        <label class="block">
          {{ $t('workout.ftp') }}
          <UInput v-model.number="ftp" type="number" min="50" max="600" step="5" class="mt-1 w-32" />
        </label>

        <template v-if="!editing">
          <label class="block">
            {{ $t('workout.choose') }}
            <USelect v-model="selectedId" :items="workoutItems" class="mt-1 w-full" />
          </label>
          <p v-if="selected" class="text-sm text-slate-500">{{ $t('workout.total', { min: minutes(selected) }) }}</p>
          <div class="flex flex-wrap gap-2">
            <UButton icon="i-lucide-play" :label="$t('workout.start')" :disabled="!trainerReady || !selected" @click="startSelected" />
            <UButton
              v-if="workout.active.value"
              color="neutral"
              variant="outline"
              icon="i-lucide-square"
              :label="$t('workout.stop')"
              @click="workout.stop()"
            />
            <UButton color="neutral" variant="outline" icon="i-lucide-pencil" :label="$t('workout.edit')" @click="edit" />
          </div>
        </template>

        <div v-else class="space-y-2">
          <p class="font-semibold">{{ $t('workout.steps') }}</p>
          <div v-for="(s, i) in draft" :key="i" class="flex items-end gap-2">
            <label class="text-sm">
              {{ $t('workout.minutes') }}
              <UInput v-model.number="s.min" type="number" min="0.5" step="0.5" class="mt-1 w-28" />
            </label>
            <label class="text-sm">
              {{ $t('workout.percent') }}
              <UInput v-model.number="s.pct" type="number" min="20" max="200" step="5" class="mt-1 w-28" />
            </label>
            <UButton
              color="neutral"
              variant="ghost"
              icon="i-lucide-trash-2"
              :aria-label="$t('workout.removeStep')"
              :disabled="draft.length === 1"
              @click="draft.splice(i, 1)"
            />
          </div>
          <div class="flex flex-wrap gap-2">
            <UButton
              color="neutral"
              variant="outline"
              icon="i-lucide-plus"
              :label="$t('workout.addStep')"
              @click="draft.push({ min: 5, pct: 75 })"
            />
            <UButton icon="i-lucide-play" :label="$t('workout.save')" :disabled="!trainerReady || !draftSteps.length" @click="saveDraft" />
          </div>
        </div>
      </div>

      <div v-else-if="view === 'ghost'" class="space-y-4">
        <p class="text-sm text-slate-500">{{ $t('ghost.intro') }}</p>
        <p v-if="!user" role="alert" class="text-sm text-red-700">{{ $t('ghost.needsAccount') }}</p>
        <URadioGroup v-model="ghostChoice" :items="ghostItems" />
      </div>

      <div v-else-if="view === 'sound'" class="space-y-4">
        <USwitch v-model="speech.enabled.value" :label="$t('sound.readPois')" />
        <label class="block">
          {{ $t('sound.volume') }} — {{ volumePct }} %
          <USlider v-model="volumePct" :min="0" :max="100" :step="5" class="mt-2" />
        </label>
        <UButton color="neutral" variant="outline" icon="i-lucide-play" :label="$t('sound.test')" @click="testVoice" />
        <div v-if="voice.supported.value">
          <USwitch v-model="voice.enabled.value" :label="$t('sound.voiceCommand')" />
          <p class="mt-1 text-sm text-slate-500">{{ $t('sound.voiceCommandNote') }}</p>
        </div>
      </div>

      <div v-else-if="view === 'account'" class="space-y-4">
        <p v-if="user">
          <strong>{{ user.nickname }}</strong><br >
          <span class="text-slate-500">{{ user.email }}</span>
        </p>
        <!-- new tabs: leaving this page would lose the ride in progress -->
        <div class="flex flex-wrap gap-2">
          <UButton to="/rides" target="_blank" color="neutral" variant="outline" icon="i-lucide-history" :label="$t('rides.menu')" />
          <UButton to="/account" target="_blank" color="neutral" variant="outline" icon="i-lucide-settings" :label="$t('auth.account')" />
        </div>
        <UButton color="error" variant="ghost" icon="i-lucide-log-out" :label="$t('auth.logout')" @click="logout()" />
      </div>

      <div v-else class="space-y-3">
        <p>{{ $t('pause.quitHelp') }}</p>
        <UButton block size="lg" icon="i-lucide-flag" :label="$t('pause.finishSave')" @click="finish" />
        <UButton block size="lg" color="error" variant="outline" icon="i-lucide-door-open" :label="$t('pause.quitWithout')" to="/" />
      </div>
    </template>
  </UModal>
</template>
