<script setup lang="ts">
// FR · DE · EN on every page, also for a visitor who is not signed in: tourists choose their language first
const { locale, locales, setLocale } = useI18n()
const options = computed(() => locales.value.map((l) => (typeof l === 'string' ? { code: l, name: l } : l)))
</script>

<template>
  <nav :aria-label="$t('app.language')" class="flex gap-3 text-sm">
    <button
      v-for="l in options"
      :key="l.code"
      type="button"
      class="font-display uppercase tracking-wide"
      :class="l.code === locale ? 'text-vaud-800 underline' : 'text-slate-500'"
      :lang="l.code"
      :aria-label="l.name"
      :aria-current="l.code === locale ? 'true' : undefined"
      @click="setLocale(l.code)"
    >
      {{ l.code }}
    </button>
  </nav>
</template>
