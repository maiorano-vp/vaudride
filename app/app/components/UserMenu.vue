<script setup lang="ts">
import { userMenuUi } from '~/lib/ui/dropdown'

const { user, logout } = useAuth()
const { t } = useI18n()

const initial = computed(
  () => user.value?.nickname.trim().charAt(0).toUpperCase() ?? '',
)

// grouped by what the rider is after, with icons, so the list can be scanned; signing out stands apart
const items = computed(() => [
  [
    { label: t('menu.ride'), type: 'label' as const },
    { label: t('intro.menu'), icon: 'i-lucide-bluetooth', to: '/intro' },
    { label: t('rides.menu'), icon: 'i-lucide-history', to: '/rides' },
    {
      label: t('passport.menu'),
      icon: 'i-lucide-badge-check',
      to: '/passport',
    },
    { label: t('favorites.menu'), icon: 'i-lucide-star', to: '/favorites' },
  ],
  [
    { label: t('menu.profile'), type: 'label' as const },
    { label: t('kit.menu'), icon: 'i-lucide-shirt', to: '/kit' },
    {
      label: t('rider.menu'),
      icon: 'i-lucide-sliders-horizontal',
      to: '/settings',
    },
    { label: t('auth.account'), icon: 'i-lucide-settings', to: '/account' },
  ],
  [
    {
      label: t('auth.logout'),
      icon: 'i-lucide-log-out',
      onSelect: () => logout(),
    },
  ],
])
</script>

<template>
  <!-- the white card of vaud.ch's language selector, with a header that says who is signed in -->
  <UDropdownMenu
    v-if="user"
    :items="items"
    :ui="userMenuUi"
    :content="{ align: 'end', sideOffset: 8 }"
  >
    <UButton
      color="neutral"
      variant="ghost"
      class="gap-2 px-2"
      :aria-label="user.nickname"
    >
      <span class="avatar" aria-hidden="true">{{ initial }}</span>
      <span
        class="hidden font-display text-[1.375rem] uppercase leading-none text-vaud-800 sm:inline"
        >{{ user.nickname }}</span
      >
      <UIcon name="i-lucide-chevron-down" class="size-4 text-vaud-500" />
    </UButton>

    <template #content-top>
      <div class="flex items-center gap-3 border-b border-[#e6ecf0] px-4 py-3">
        <span class="avatar avatar-lg" aria-hidden="true">{{ initial }}</span>
        <div class="min-w-0">
          <p
            class="m-0 truncate font-display text-xl uppercase leading-tight text-vaud-800"
          >
            {{ user.nickname }}
          </p>
          <p class="m-0 truncate text-sm text-slate-600">{{ user.email }}</p>
        </div>
      </div>
    </template>
  </UDropdownMenu>
</template>

<style scoped>
.avatar {
  display: inline-grid;
  flex: none;
  place-items: center;
  width: 2rem;
  height: 2rem;
  font-family: var(--font-display);
  font-size: 1.25rem;
  line-height: 1;
  color: #fff;
  background: var(--color-vaud-800);
  border-radius: 9999px;
}
.avatar-lg {
  width: 2.75rem;
  height: 2.75rem;
  font-size: 1.6rem;
}
</style>
