<script setup lang="ts">
import { renderSVG } from 'uqr'

// drawn here, in the browser: the address is never sent to a QR service
const props = withDefaults(defineProps<{ text: string; label: string; size?: number }>(), { size: 160 })
// the SVG is made by the library from modules only (paths), never from the text itself
const svg = computed(() => renderSVG(props.text, { ecc: 'M', border: 2, whiteColor: '#ffffff', blackColor: '#0a4069' }))
</script>

<template>
  <!-- eslint-disable-next-line vue/no-v-html -->
  <div role="img" :aria-label="label" class="qr" :style="{ width: `${size}px` }" v-html="svg" />
</template>

<style scoped>
.qr :deep(svg) {
  display: block;
  width: 100%;
  height: auto;
}
</style>
