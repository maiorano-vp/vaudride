<template>
  <UDropdownMenu v-if="user" :items="items">
    <UButton
      color="neutral"
      variant="ghost"
      :label="user.nickname"
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
    { label: t('intro.menu'), icon: 'i-lucide-bluetooth', to: '/intro' },
    { label: t('rides.menu'), icon: 'i-lucide-history', to: '/rides' },
    { label: t('favorites.menu'), icon: 'i-lucide-star', to: '/favorites' },
    { label: t('kit.menu'), icon: 'i-lucide-shirt', to: '/kit' },
    { label: t('rider.menu'), icon: 'i-lucide-sliders-horizontal', to: '/settings' },
    { label: t('auth.account'), icon: 'i-lucide-settings', to: '/account' },
    {
      label: t('auth.logout'),
      icon: 'i-lucide-log-out',
      onSelect: () => logout(),
    },
  ],
])
</script>
