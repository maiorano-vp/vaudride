// not in TypeScript's DOM types yet
interface Recognition {
  continuous: boolean
  lang: string
  onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null
  onend: (() => void) | null
  onerror: ((e: { error: string }) => void) | null
  start(): void
  abort(): void
}
const recognitionCtor = (): (new () => Recognition) | undefined =>
  (window as never as Record<string, new () => Recognition>).SpeechRecognition ??
  (window as never as Record<string, new () => Recognition>).webkitSpeechRecognition

/**
 * Listens for spoken commands with the browser's speech recognition (off until the player turns it on:
 * Chrome sends the audio to its own servers). `onHear` gets every phrase recognised.
 */
export function useVoiceCommand(onHear: (transcript: string) => void) {
  const maxAge = 60 * 60 * 24 * 365
  const storedOn = useCookie<boolean>('voice-command', { maxAge, default: () => false })
  const enabled = useState('voice-command', () => storedOn.value)
  const { locale, locales } = useI18n()
  // the full language tag of the locale (fr-CH, de-CH, en), which the recognition understands better than "de"
  const language = computed(() => locales.value.find((l) => typeof l !== 'string' && l.code === locale.value) as { language?: string } | undefined)
  // known once mounted, so the server and the first client render agree
  const supported = ref(false)
  let recognition: Recognition | undefined
  let wanted = false

  function stop() {
    wanted = false
    recognition?.abort()
    recognition = undefined
  }

  function start() {
    if (!supported.value || recognition) return
    const Ctor = recognitionCtor()
    if (!Ctor) return
    recognition = new Ctor()
    recognition.continuous = true
    recognition.lang = language.value?.language ?? locale.value
    recognition.onresult = (e) => {
      // the voice of the app itself must not be taken for a command
      if (window.speechSynthesis.speaking) return
      const last = e.results[e.results.length - 1]
      if (last) onHear(last[0]?.transcript ?? '')
    }
    // the browser ends the session by itself every so often
    recognition.onend = () => {
      recognition = undefined
      if (wanted) start()
    }
    recognition.onerror = (e) => {
      if (e.error === 'not-allowed' || e.error === 'service-not-allowed') enabled.value = false
    }
    wanted = true
    recognition.start()
  }

  onMounted(() => {
    supported.value = !!recognitionCtor()
  })
  onBeforeUnmount(stop)
  watch(enabled, (on) => {
    storedOn.value = on
    if (!on) stop()
  })

  return { supported, enabled, start, stop }
}
