import type { FormError } from '@nuxt/ui'

interface FormLike {
  setErrors: (errors: FormError[]) => void
}

interface LaravelBody {
  message?: string
  errors?: Record<string, string[]>
}

/** Maps a Laravel 422 `{ errors: { field: [msg] } }` onto the form fields; anything else goes to `fallback`. */
export function applyApiErrors(
  e: unknown,
  form: FormLike | null | undefined,
  fallback: (message: string) => void,
) {
  // Nitro wraps the upstream body: { statusCode, message, data: <laravel body> }
  const err = e as {
    statusCode?: number
    data?: { data?: LaravelBody } & LaravelBody
  }
  const body = err.data?.data ?? err.data
  if (err.statusCode === 422 && body?.errors && form) {
    form.setErrors(
      Object.entries(body.errors).map(([name, m]) => ({
        name,
        message: m[0] ?? '',
      })),
    )
    return
  }
  fallback(body?.message ?? String(e))
}
