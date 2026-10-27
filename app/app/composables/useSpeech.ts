import { pickVoice } from '~/lib/pois/speech'

/** Reads text aloud with the browser's own voices (no data leaves the device). Volume and on/off are remembered. */
export function useSpeech() {
  const maxAge = 60 * 60 * 24 * 365
  const storedOn = useCookie<boolean>('speech-enabled', { maxAge, default: () => true })
  const storedVolume = useCookie<number>('speech-volume', { maxAge, default: () => 0.8 })
  // shared by every component using it (the ride page and the pause menu), written through to the cookies
  const enabled = useState('speech-enabled', () => storedOn.value)
  const volume = useState('speech-volume', () => storedVolume.value)
  const { locale } = useI18n()
  // known once mounted, so the server and the first client render agree
  const supported = ref(false)
  onMounted(() => (supported.value = 'speechSynthesis' in window))

  function stop() {
    if (supported.value) window.speechSynthesis.cancel()
  }

  function speak(text: string, force = false) {
    if (!supported.value || !text || (!enabled.value && !force)) return
    stop()
    const u = new SpeechSynthesisUtterance(text)
    const voice = pickVoice(window.speechSynthesis.getVoices(), locale.value)
    if (voice) u.voice = voice
    u.lang = voice?.lang ?? locale.value
    u.volume = Math.min(Math.max(volume.value, 0), 1)
    window.speechSynthesis.speak(u)
  }

  watch(enabled, (on) => {
    storedOn.value = on
    if (!on) stop()
  })
  watch(volume, (v) => {
    storedVolume.value = v
    if (v === 0) stop()
  })

  return { supported, enabled, volume, speak, stop }
}
