<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: false })

const { t } = useI18n()
const route = useRoute()
const { login } = useAuth()

const schema = z.object({
  email: z.email(t('auth.invalidEmail')),
  password: z.string().min(1, t('auth.required')),
})
const state = reactive({ email: '', password: '' })
const form = useTemplateRef('form')
const error = ref('')

async function onSubmit(event: FormSubmitEvent<z.output<typeof schema>>) {
  error.value = ''
  try {
    await login(event.data)
    const redirect = route.query.redirect
    await navigateTo(
      typeof redirect === 'string' &&
        redirect.startsWith('/') &&
        !redirect.startsWith('//')
        ? redirect
        : '/intro',
    )
  } catch (e) {
    applyApiErrors(e, form.value, (m) => (error.value = m))
  }
}
</script>

<template>
  <div>
    <h1
      class="mb-4 font-display text-2xl uppercase tracking-wide text-vaud-800"
    >
      {{ $t('auth.loginTitle') }}
    </h1>
    <UForm
      ref="form"
      :schema="schema"
      :state="state"
      class="space-y-4"
      @submit="onSubmit"
    >
      <UAlert
        v-if="error"
        color="error"
        variant="subtle"
        :description="error"
      />
      <UFormField :label="$t('auth.email')" name="email">
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
        />
      </UFormField>
      <UFormField :label="$t('auth.password')" name="password">
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="current-password"
          class="w-full"
        />
      </UFormField>
      <UButton type="submit" block>{{ $t('auth.login') }}</UButton>
    </UForm>
    <div class="mt-4 flex justify-between text-sm">
      <ULink to="/forgot-password" class="underline">{{
        $t('auth.forgot')
      }}</ULink>
      <ULink
        :to="{ path: '/register', query: route.query }"
        class="underline"
        >{{ $t('auth.noAccount') }}</ULink
      >
    </div>
  </div>
</template>
