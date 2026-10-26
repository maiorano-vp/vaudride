export interface AuthUser {
  id: string
  nickname: string
  email: string
  /** The player's 3D kit, null until they customise it (see lib/kit/kit.ts). */
  avatar?: unknown
}

export function useAuth() {
  const user = useState<AuthUser | null>('auth.user', () => null)
  const loggedIn = computed(() => user.value !== null)

  // useRequestFetch forwards the browser's cookie during SSR
  async function fetchUser() {
    user.value = await useRequestFetch()<{ data: AuthUser }>('/api/me')
      .then((r) => r.data)
      .catch(() => null)
  }

  async function login(body: { email: string; password: string }) {
    user.value = (
      await $fetch<{ user: AuthUser }>('/api/auth/login', {
        method: 'POST',
        body,
      })
    ).user
  }

  async function register(body: {
    nickname: string
    email: string
    password: string
  }) {
    user.value = (
      await $fetch<{ user: AuthUser }>('/api/auth/register', {
        method: 'POST',
        body,
      })
    ).user
  }

  async function logout() {
    await $fetch('/api/auth/logout', { method: 'POST' }).catch(() => {})
    user.value = null
    await navigateTo('/login')
  }

  return { user, loggedIn, fetchUser, login, register, logout }
}
