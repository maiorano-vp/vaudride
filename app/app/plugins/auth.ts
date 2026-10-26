export default defineNuxtPlugin(async () => {
  const { user, fetchUser } = useAuth()
  if (user.value === null) await fetchUser()
})
