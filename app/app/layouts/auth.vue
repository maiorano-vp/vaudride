<template>
  <main class="grid min-h-dvh place-items-center bg-[#f3f5f8] px-4 py-8">
    <div class="w-full max-w-md">
      <NuxtLink to="/" class="mb-6 flex items-center justify-center gap-3">
        <img src="/vaud-logo.svg" alt="Vaud" class="h-10" >
        <span
          class="font-display text-3xl uppercase tracking-wide text-vaud-800"
          >{{ $t('app.title') }}</span
        >
      </NuxtLink>
      <UCard>
        <slot />
      </UCard>
      <div class="mt-4 flex justify-center gap-3 text-sm">
        <button
          v-for="l in locales"
          :key="l.code"
          class="font-display uppercase tracking-wide"
          :class="
            l.code === locale ? 'text-vaud-800 underline' : 'text-slate-500'
          "
          @click="setLocale(l.code)"
        >
          {{ l.code }}
        </button>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
const { locale, locales, setLocale } = useI18n()
</script>
