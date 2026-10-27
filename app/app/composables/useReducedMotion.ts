/**
 * Whether the 3D camera should keep still: no widening of the field of view with speed, no rising over open landscape.
 * Follows the system's "reduce motion" setting unless the rider chose for themselves (kept in a cookie).
 * Shared between the ride page and the pause menu.
 */
export function useReducedMotion() {
  const cookie = useCookie<string | undefined>('reduce-motion', { maxAge: 60 * 60 * 24 * 365 })
  const override = useState<boolean | undefined>('reduce-motion', () => (cookie.value === '1' ? true : cookie.value === '0' ? false : undefined))
  const system = useState('reduce-motion-system', () => false)

  const reduced = computed(() => override.value ?? system.value)

  /** Reads the system setting and follows it. Returns what stops the listening. */
  function followSystem(): () => void {
    const query = matchMedia('(prefers-reduced-motion: reduce)')
    const read = () => (system.value = query.matches)
    read()
    query.addEventListener('change', read)
    return () => query.removeEventListener('change', read)
  }

  function set(value: boolean) {
    override.value = value
    cookie.value = value ? '1' : '0'
  }

  return { reduced, system, set, followSystem }
}
