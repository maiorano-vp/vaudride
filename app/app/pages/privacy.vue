<script setup lang="ts">
// what the app keeps about a player; the texts are in the locale files so DE and EN say the same as FR
const SECTIONS = ['account', 'rides', 'real', 'settings', 'live', 'extras', 'origin', 'hosting', 'rights'] as const
const { privacyContact } = useRuntimeConfig().public
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 p-4">
    <header class="flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('privacy.title') }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>

    <p>{{ $t('privacy.intro') }}</p>

    <section v-for="key in SECTIONS" :key="key">
      <h2 class="mb-1 font-display text-xl uppercase tracking-wide text-vaud-800">{{ $t(`privacy.${key}.title`) }}</h2>
      <p class="whitespace-pre-line">{{ $t(`privacy.${key}.text`) }}</p>
    </section>

    <p v-if="privacyContact">
      {{ $t('privacy.contact') }}
      <a :href="`mailto:${privacyContact}`" class="underline">{{ privacyContact }}</a>
    </p>
    <UButton to="/settings" color="neutral" variant="outline" icon="i-lucide-sliders-horizontal" :label="$t('rider.menu')" />
  </div>
</template>
