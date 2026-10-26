import type { H3Event } from 'h3'

export const TOKEN_COOKIE = 'vr_token'
const THIRTY_DAYS = 60 * 60 * 24 * 30 // = SANCTUM_EXPIRATION


/** Calls the Laravel API, relaying its error status and body (422 validation messages) as-is. */
export async function callApi<T>(
  event: H3Event,
  path: string,
  options: { method?: string; query?: unknown; body?: unknown } = {},
): Promise<T> {
  // $fetch's route-typed overloads blow the TS stack on a dynamic URL (and the global only exists at request time)
  const request = $fetch as unknown as (url: string, options: object) => Promise<unknown>
  const token = getCookie(event, TOKEN_COOKIE)
  try {
    return (await request(`${useRuntimeConfig(event).apiBase}/api/v1/${path}`, {
      ...options,
      headers: { Accept: 'application/json', ...(token && { Authorization: `Bearer ${token}` }) },
    })) as T
  } catch (e) {
    const err = e as { statusCode?: number; data?: unknown }
    if (err.statusCode === 401) deleteCookie(event, TOKEN_COOKIE, { path: '/' })
    throw createError({ statusCode: err.statusCode ?? 502, data: err.data })
  }
}

export function setTokenCookie(event: H3Event, token: string) {
  setCookie(event, TOKEN_COOKIE, token, {
    httpOnly: true,
    secure: !import.meta.dev,
    sameSite: 'lax',
    path: '/',
    maxAge: THIRTY_DAYS,
  })
}
