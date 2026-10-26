<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: false })

const { t } = useI18n()
const route = useRoute()
const token = computed(() =>
  typeof route.query.token === 'string' ? route.query.token : '',
)

// the link carries only the token; the email is asked again (no personal data in the URL)
const schema = z.object({
  email: z.email(t('auth.invalidEmail')),
  password: z.string().min(8, t('auth.passwordMin')),
})
const state = reactive({ email: '', password: '' })
const form = useTemplateRef('form')
const error = ref('')
const done = ref(false)

async function onSubmit(event: FormSubmitEvent<z.output<typeof schema>>) {
  error.value = ''
  try {
    await $fetch('/api/auth/reset-password', {
      method: 'POST',
      body: { ...event.data, token: token.value },
    })
    done.value = true
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
      {{ $t('auth.resetTitle') }}
    </h1>
    <UAlert
      v-if="done"
      color="success"
      variant="subtle"
      :description="$t('auth.resetDone')"
    />
    <UAlert
      v-else-if="!token"
      color="error"
      variant="subtle"
      :description="$t('auth.resetInvalid')"
    />
    <UForm
      v-else
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
      <UFormField :label="$t('auth.newPassword')" name="password">
        <UInput
          v-model="state.password"
          type="password"
          autocomplete="new-password"
          class="w-full"
        />
      </UFormField>
      <UButton type="submit" block>{{ $t('auth.resetSubmit') }}</UButton>
    </UForm>
    <p class="mt-4 text-sm">
      <ULink to="/login">{{ $t('auth.backToLogin') }}</ULink>
    </p>
  </div>
</template>
