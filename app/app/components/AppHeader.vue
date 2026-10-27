<template>
  <header class="mb-6 flex items-center justify-between">
    <NuxtLink
      to="/"
      class="flex items-center gap-3"
      :aria-label="$t('app.title')"
    >
      <img src="/vaud-logo.svg" alt="Vaud" class="h-9" >
      <!-- the logo already spells "Vaud" -->
      <span class="font-display text-3xl uppercase tracking-wide text-vaud-800"
        >Ride</span
      >
    </NuxtLink>
    <div class="flex items-center gap-3">
      <LocaleSwitcher />
      <UserMenu />
    </div>
  </header>
</template>
