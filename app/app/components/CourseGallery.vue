<script setup lang="ts">
import { creditText } from '~/lib/course/card'

// a masonry of the photos of the course, each with its copyright over the corner (as the media of a vaud.ch page)
defineProps<{ images: Array<{ url: string; credit: string | null }> }>()
</script>

<template>
  <ul class="gallery">
    <li v-for="image in images" :key="image.url">
      <figure>
        <img :src="image.url" alt="" loading="lazy" decoding="async" >
        <figcaption v-if="creditText(image.credit)">
          {{ creditText(image.credit) }}
        </figcaption>
      </figure>
    </li>
  </ul>
</template>

<style scoped>
.gallery {
  padding: 0;
  margin: 0;
  list-style: none;
  columns: 2;
  column-gap: 0.75rem;
}
li {
  margin-bottom: 0.75rem;
  break-inside: avoid;
}
figure {
  position: relative;
  min-height: 6rem; /* the credit has its corner before the photo has loaded */
  margin: 0;
  background: var(--color-vaud-100);
}
img {
  display: block;
  width: 100%;
  height: auto;
}
figcaption {
  position: absolute;
  right: 0;
  bottom: 0;
  max-width: 100%;
  padding: 1px 6px;
  font-size: 0.7rem;
  color: #fff;
  background: rgb(10 64 105 / 85%); /* white on it passes AA over any photo */
}
@media (max-width: 480px) {
  .gallery {
    columns: 1;
  }
}
</style>
