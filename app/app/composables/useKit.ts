import { sanitizeKit, type Kit } from '~/lib/kit/kit'
import type { AuthUser } from '~/composables/useAuth'

/** The signed-in player's kit (the default one until they pick colours), and saving it on their account. */
export function useKit() {
  const { user } = useAuth()
  const kit = computed(() => sanitizeKit(user.value?.avatar))

  async function save(next: Kit) {
    const { data } = await $fetch<{ data: AuthUser }>('/api/me/avatar', { method: 'PUT', body: next })
    if (user.value) user.value = { ...user.value, avatar: data.avatar }
  }
  return { kit, save }
}
