export default defineNuxtRouteMiddleware((to) => {
  const { loggedIn } = useAuth()
  const isPublic = to.meta.auth === false

  if (!loggedIn.value && !isPublic) {
    // keep the whole target (incl. ?sim=1) so login can send the user back
    return navigateTo({ path: '/login', query: { redirect: to.fullPath } })
  }
  if (loggedIn.value && isPublic && to.path !== '/reset-password')
    return navigateTo('/')
})
