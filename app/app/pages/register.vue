<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: false })

const { t } = useI18n()
const route = useRoute()
const { register } = useAuth()

const schema = z.object({
  nickname: z.string().min(2, t('auth.nicknameMin')).max(40),
  email: z.email(t('auth.invalidEmail')),
  password: z.string().min(8, t('auth.passwordMin')),
})
const state = reactive({ nickname: '', email: '', password: '' })
const form = useTemplateRef('form')
const error = ref('')

async function onSubmit(event: FormSubmitEvent<z.output<typeof schema>>) {
  error.value = ''
  try {
    await register(event.data)
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
      {{ $t('auth.registerTitle') }}
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
      <UFormField
        :label="$t('auth.nickname')"
        :help="$t('auth.nicknameHelp')"
        name="nickname"
      >
        <UInput
          v-model="state.nickname"
          autocomplete="nickname"
          class="w-full"
        />
      </UFormField>
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
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>
      <UButton type="submit" block>{{ $t('auth.register') }}</UButton>
    </UForm>
    <p class="mt-4 text-sm">
      <ULink :to="{ path: '/login', query: route.query }" class="underline">{{
        $t('auth.haveAccount')
      }}</ULink>
    </p>
  </div>
</template>
