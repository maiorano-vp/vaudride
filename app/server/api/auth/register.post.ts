export default defineEventHandler(async (event) => {
  const { token, user } = await callApi<{ token: string; user: unknown }>(
    event,
    'auth/register',
    {
      method: 'POST',
      body: {
        ...(await readBody<Record<string, unknown>>(event)),
        device_name: 'web',
      },
    },
  )
  setTokenCookie(event, token)
  return { user }
})
