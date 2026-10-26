<script setup lang="ts">
const route = useRoute()
</script>

<template>
  <div class="mx-auto max-w-3xl p-4">
    <header class="mb-6 flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">
        {{ $t('app.title') }}
      </h1>
      <UserMenu />
    </header>
    <UCard>
      <NuxtLink :to="{ path: '/ride/marchairuz-biere', query: route.query }"
        >Col du Marchairuz depuis Bière</NuxtLink
      >
    </UCard>
  </div>
</template>
