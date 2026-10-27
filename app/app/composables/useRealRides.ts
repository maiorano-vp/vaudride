export interface RealRide {
  id: string
  ridden_on: string
  course: { slug: string; name: Record<string, string> }
}

/** The courses the rider says they rode for real (their passport): theirs alone, on their account. */
export function useRealRides() {
  const { loggedIn } = useAuth()
  const rides = useState<RealRide[]>('real-rides', () => [])

  async function load() {
    if (!loggedIn.value) return
    rides.value = await $fetch<{ data: RealRide[] }>('/api/me/real-rides')
      .then((r) => r.data)
      .catch(() => rides.value)
  }

  /** Throws if the account could not be reached: the caller says so. */
  async function declare(slug: string, riddenOn: string) {
    await $fetch(`/api/me/real-rides/${slug}`, { method: 'PUT', body: { ridden_on: riddenOn } })
    await load()
  }

  async function remove(id: string) {
    await $fetch(`/api/me/real-rides/${id}`, { method: 'DELETE' })
    rides.value = rides.value.filter((r) => r.id !== id)
  }

  const forCourse = (slug: string) => rides.value.filter((r) => r.course.slug === slug)

  return { rides, load, declare, remove, forCourse }
}
