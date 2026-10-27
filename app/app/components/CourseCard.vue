<script setup lang="ts">
import { creditText, kindLabelKey, kmText, type CourseCardData } from '~/lib/course/card'

// A card in the style of the events on vaud.ch: the photo with the kind of ride over it, the name, the destination.
// The whole card opens the course sheet through the name's link (stretched over it); the ride button sits above it.
const props = defineProps<{ course: CourseCardData }>()
const { locale } = useI18n()
const route = useRoute()

const name = computed(() => props.course.name[locale.value] ?? props.course.name.fr ?? '')
const kindKey = computed(() => kindLabelKey(props.course.kind))
const credit = computed(() => creditText(props.course.image_credit))
</script>

<template>
  <article class="course-card">
    <div class="img-wrap">
      <img v-if="course.image_url" :src="course.image_url" alt="" loading="lazy" decoding="async" >
      <div v-else class="no-image"><img src="/vaud-logo.svg" alt="" ></div>
      <div v-if="kindKey" class="kind"><span>{{ $t(kindKey) }}</span></div>
      <small v-if="credit && course.image_url" class="credit">{{ credit }}</small>
    </div>

    <div class="content">
      <h3>
        <NuxtLink :to="`/courses/${course.slug}`" class="stretched">{{ name }}</NuxtLink>
      </h3>
      <p v-if="course.destination" class="place">
        <UIcon name="i-lucide-map-pin" class="pin" />
        {{ course.destination }}
      </p>
      <p class="figures">{{ kmText(course.distance_m) }} km · {{ course.elevation_gain_m }} m D+</p>
      <div class="actions">
        <UButton size="md" icon="i-lucide-bike" :label="$t('home.ride')" :to="{ path: `/ride/${course.slug}`, query: route.query }" />
        <NuxtLink :to="`/courses/${course.slug}#real`" class="real-link">{{ $t('real.home') }}</NuxtLink>
      </div>
    </div>
  </article>
</template>

<style scoped>
/* flat, like the event cards of vaud.ch: no border, no shadow, no rounding */
.course-card {
  position: relative;
  color: var(--color-vaud-800);
}
.img-wrap {
  position: relative;
  aspect-ratio: 438 / 334;
  overflow: hidden;
  background: var(--color-vaud-100);
}
.img-wrap > img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s;
}
.course-card:hover .img-wrap > img {
  transform: scale(1.03);
}
.no-image {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  background: var(--color-vaud-800);
}
.no-image img {
  width: 40%;
  max-width: 9rem;
  filter: brightness(0) invert(1);
}
.kind {
  position: absolute;
  top: 1rem;
  left: 1rem;
  display: flex;
}
.kind span {
  padding: 3px 6px;
  font-family: var(--font-display);
  font-size: 1.5rem;
  line-height: 1.1;
  color: #fff;
  text-transform: uppercase;
  background: rgb(25 149 115 / 80%);
}
.credit {
  position: absolute;
  right: 0;
  bottom: 0;
  max-width: 100%;
  padding: 1px 6px;
  font-size: 0.7rem;
  color: #fff;
  pointer-events: none;
  background: rgb(10 64 105 / 85%); /* white on it passes AA over any photo */
}
.content {
  padding: 1rem 0 0;
  margin-bottom: 3rem;
}
h3 {
  margin: 0 0 0.5rem;
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 400;
  line-height: 1.1;
}
.stretched {
  color: inherit;
  text-decoration: none;
}
.stretched::after {
  position: absolute;
  inset: 0;
  z-index: 0;
  content: '';
}
.course-card:hover .stretched {
  text-decoration: underline;
}
.stretched:focus-visible {
  outline: none;
}
.stretched:focus-visible::after {
  outline: 2px solid var(--color-vaud-500);
  outline-offset: 2px;
}
.place {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  margin: 0 0 0.25rem;
}
.pin {
  flex: none;
  font-size: 1.1rem;
}
.figures {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  color: var(--color-vaud-600);
}
.actions {
  position: relative; /* above the stretched link, so these stay clickable */
  z-index: 1;
  display: flex;
  gap: 1rem;
  align-items: center;
}
.real-link {
  font-size: 0.9rem;
  text-decoration: underline;
}
</style>
