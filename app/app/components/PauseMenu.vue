<script setup lang="ts">
type View = 'menu' | 'devices' | 'kit' | 'sound' | 'account' | 'quit'

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
watch(open, (o) => o && (view.value = 'menu'))

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
