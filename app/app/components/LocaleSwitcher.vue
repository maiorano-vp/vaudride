<script setup lang="ts">
import { languageMenuUi } from '~/lib/ui/dropdown'

// "FR ▾" on every page, also for a visitor who is not signed in (tourists choose their language first): the selector of
// vaud.ch, a card with the other languages under the current one
const { locale, locales, setLocale } = useI18n()
const options = computed(() =>
  locales.value.map((l) => (typeof l === 'string' ? { code: l, name: l } : l)),
)
const current = computed(
  () => options.value.find((l) => l.code === locale.value) ?? options.value[0]!,
)
const items = computed(() => [
  options.value
    .filter((l) => l.code !== locale.value)
    .map((l) => ({
      label: l.code.toUpperCase(),
      name: l.name,
      lang: l.code,
      onSelect: () => setLocale(l.code),
    })),
])
const named = (item: unknown) =>
  item as { label: string; name: string; lang: string }
</script>

<template>
  <UDropdownMenu
    :items="items"
    :ui="languageMenuUi"
    :content="{ align: 'end', sideOffset: 8 }"
  >
    <UButton
      color="neutral"
      variant="link"
      :label="current.code.toUpperCase()"
      trailing-icon="i-lucide-chevron-down"
      :aria-label="`${$t('app.language')}: ${current.name}`"
      class="px-1 font-display text-[1.375rem] uppercase text-vaud-800"
      :ui="{ trailingIcon: 'size-4 text-vaud-500' }"
    />
    <template #item-label="{ item }">
      <!-- the code is what is shown; the name of the language is what a screen reader says -->
      <span aria-hidden="true">{{ named(item).label }}</span>
      <span class="sr-only" :lang="named(item).lang">{{
        named(item).name
      }}</span>
    </template>
  </UDropdownMenu>
</template>
