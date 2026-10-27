<script setup lang="ts">
// A block of a page, in the look of vaud.ch's information table: the light grey of its zebra rows, a title in the display
// font, a green disc for the number of a step.
defineProps<{ title: string; step?: number }>()
</script>

<template>
  <section class="page-section">
    <h2>
      <span v-if="step" class="num" aria-hidden="true">{{ step }}</span>
      {{ title }}
    </h2>
    <slot />
  </section>
</template>

<style scoped>
.page-section {
  padding: 1.5rem 1.75rem 1.75rem;
  background: #f2f5f7;
}
h2 {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  margin: 0 0 1rem;
  font-family: var(--font-display);
  font-size: 1.875rem;
  font-weight: 400;
  line-height: 1.1;
  color: var(--color-vaud-800);
  text-transform: uppercase;
}
.num {
  display: inline-grid;
  flex: none;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  font-size: 1.5rem;
  line-height: 1;
  color: #fff;
  background: var(--color-vaud-green-600);
  border-radius: 9999px;
}
</style>
