<script setup lang="ts">
import { z } from 'zod'
import type { FormSubmitEvent } from '@nuxt/ui'

definePageMeta({ layout: 'auth', auth: false })

const { t } = useI18n()
const schema = z.object({ email: z.email(t('auth.invalidEmail')) })
const state = reactive({ email: '' })
const form = useTemplateRef('form')
const error = ref('')
const sent = ref(false)

async function onSubmit(event: FormSubmitEvent<z.output<typeof schema>>) {
  error.value = ''
  try {
    await $fetch('/api/auth/forgot-password', {
      method: 'POST',
      body: event.data,
    })
    sent.value = true
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
      {{ $t('auth.forgotTitle') }}
    </h1>
    <UAlert
      v-if="sent"
      color="success"
      variant="subtle"
      :description="$t('auth.forgotSent')"
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
      <p class="text-sm">{{ $t('auth.forgotHelp') }}</p>
      <UFormField :label="$t('auth.email')" name="email">
        <UInput
          v-model="state.email"
          type="email"
          autocomplete="email"
          class="w-full"
        />
      </UFormField>
      <UButton type="submit" block>{{ $t('auth.sendLink') }}</UButton>
    </UForm>
    <p class="mt-4 text-sm">
      <ULink to="/login" class="underline">{{ $t('auth.backToLogin') }}</ULink>
    </p>
  </div>
</template>
