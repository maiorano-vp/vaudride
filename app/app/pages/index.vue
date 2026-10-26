<script setup lang="ts">
const route = useRoute()
</script>

<template>
  <main>
    <h1>{{ $t('app.title') }}</h1>
    <NuxtLink :to="{ path: '/ride/marchairuz-biere', query: route.query }">Col du Marchairuz depuis Bière</NuxtLink>
  </main>
</template>
