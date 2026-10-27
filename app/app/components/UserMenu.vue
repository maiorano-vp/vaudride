<template>
  <!-- the dropdown of vaud.ch's language selector: a small white card, soft shadow, square corners, the entries stacked
       in the display font, a thin chevron after the trigger -->
  <UDropdownMenu
    v-if="user"
    :items="items"
    :ui="ui"
    :content="{ align: 'end', sideOffset: 8 }"
  >
    <UButton
      color="neutral"
      variant="link"
      icon="i-lucide-user"
      trailing-icon="i-lucide-chevron-down"
      :label="user.nickname"
      class="font-display text-[1.375rem] uppercase text-vaud-800"
      :ui="{ trailingIcon: 'size-4 text-vaud-500' }"
    />
  </UDropdownMenu>
</template>

<script setup lang="ts">
const { user, logout } = useAuth()
const { t } = useI18n()

const ui = {
  content:
    'min-w-48 rounded-none bg-white shadow-[0_2px_12px_rgba(10,51,77,0.08)] ring-0',
  viewport: 'divide-y-0',
  group: 'px-4 py-2',
  label: 'px-0 py-1 font-sans text-sm font-normal normal-case text-slate-600',
  item: 'rounded-none px-0 py-0 data-highlighted:before:bg-transparent',
  itemLabel:
    'font-display text-[1.375rem] uppercase leading-7 text-vaud-800 group-data-highlighted:text-vaud-green-600',
}

// text only, as in vaud.ch's selector: no icons in front of the entries
const items = computed(() => [
  [{ label: user.value?.email, type: 'label' as const }],
  [
    { label: t('intro.menu'), to: '/intro' },
    { label: t('rides.menu'), to: '/rides' },
    { label: t('favorites.menu'), to: '/favorites' },
    { label: t('kit.menu'), to: '/kit' },
    { label: t('passport.menu'), to: '/passport' },
    { label: t('rider.menu'), to: '/settings' },
    { label: t('auth.account'), to: '/account' },
    { label: t('auth.logout'), onSelect: () => logout() },
  ],
])
</script>
