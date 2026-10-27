import { mergeFavorites, withFavorite } from '~/lib/pois/favorites'

const KEY = 'poi-favorites'

/**
 * The player's favourite places. Kept in the browser, and on their account when signed in
 * (the two are merged at load; a failed request keeps the local copy).
 */
export function useFavorites() {
  const { loggedIn } = useAuth()
  const ids = useState<string[]>('poi-favorites', () => [])
  // added since this page opened: what the end-of-ride screen lists
  const added = useState<string[]>('poi-favorites-added', () => [])

  const save = () => {
    try {
      localStorage.setItem(KEY, JSON.stringify(ids.value))
    } catch {
      // storage blocked: the favourites last for this visit only
    }
  }

  async function load() {
    let local: string[] = []
    try {
      local = JSON.parse(localStorage.getItem(KEY) ?? '[]')
    } catch {
      // unreadable: start from the account's list
    }
    ids.value = mergeFavorites(ids.value, local)
    if (!loggedIn.value) return
    const remote = await $fetch<{ data: { id: string }[] }>('/api/me/favorites').catch(() => undefined)
    if (!remote) return
    const known = new Set(remote.data.map((p) => p.id))
    ids.value = mergeFavorites(ids.value, [...known])
    save()
    // what was only saved locally goes up to the account
    await Promise.all(ids.value.filter((id) => !known.has(id)).map(push))
  }

  const push = (id: string) => $fetch(`/api/me/favorites/${id}`, { method: 'PUT' }).catch(() => {})

  function add(id: string) {
    if (ids.value.includes(id)) return
    ids.value = withFavorite(ids.value, id)
    added.value = withFavorite(added.value, id)
    save()
    if (loggedIn.value) push(id)
  }

  function remove(id: string) {
    ids.value = ids.value.filter((i) => i !== id)
    added.value = added.value.filter((i) => i !== id)
    save()
    if (loggedIn.value) $fetch(`/api/me/favorites/${id}`, { method: 'DELETE' }).catch(() => {})
  }

  const isFavorite = (id: string) => ids.value.includes(id)
  const toggle = (id: string) => (isFavorite(id) ? remove(id) : add(id))

  return { ids, added, load, add, remove, toggle, isFavorite }
}
