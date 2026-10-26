// Generic proxy: the browser never sees the Sanctum token, this adds it from the httpOnly cookie.
export default defineEventHandler(async (event) => {
  const path = getRouterParam(event, 'path') ?? ''
  const method = event.method
  const hasBody = !['GET', 'HEAD', 'DELETE'].includes(method)
  return callApi(event, path, {
    method,
    query: getQuery(event),
    body: hasBody ? await readBody(event) : undefined,
  })
})
