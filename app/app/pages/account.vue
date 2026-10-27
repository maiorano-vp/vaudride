<script setup lang="ts">
const { user, logout } = useAuth()
</script>

<template>
  <div class="mx-auto max-w-xl p-4">
    <LocaleSwitcher class="mb-4" />
    <UCard>
      <h1
        class="mb-4 font-display text-2xl uppercase tracking-wide text-vaud-800"
      >
        {{ $t('auth.account') }}
      </h1>
      <dl v-if="user" class="mb-6 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
        <dt class="text-slate-500">{{ $t('auth.nickname') }}</dt>
        <dd>{{ user.nickname }}</dd>
        <dt class="text-slate-500">{{ $t('auth.email') }}</dt>
        <dd>{{ user.email }}</dd>
      </dl>
      <div class="flex justify-between">
        <UButton to="/" variant="ghost" color="neutral">{{
          $t('auth.back')
        }}</UButton>
        <UButton color="error" variant="soft" @click="logout()">{{
          $t('auth.logout')
        }}</UButton>
      </div>
    </UCard>
  </div>
</template>
