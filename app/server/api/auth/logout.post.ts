export default defineEventHandler(async (event) => {
  await callApi(event, 'auth/logout', { method: 'POST' }).catch(() => {}) // cookie is dropped either way
  deleteCookie(event, TOKEN_COOKIE, { path: '/' })
  return sendNoContent(event)
})
