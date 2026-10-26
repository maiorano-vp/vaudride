<template>
  <UDropdownMenu v-if="user" :items="items">
    <UButton
      color="neutral"
      variant="ghost"
      :label="user.name"
      icon="i-lucide-user"
    />
  </UDropdownMenu>
</template>

<script setup lang="ts">
const { user, logout } = useAuth()
const { t } = useI18n()

const items = computed(() => [
  [{ label: user.value?.email, type: 'label' as const }],
  [
    { label: t('auth.account'), icon: 'i-lucide-settings', to: '/account' },
    {
      label: t('auth.logout'),
      icon: 'i-lucide-log-out',
      onSelect: () => logout(),
    },
  ],
])
</script>
