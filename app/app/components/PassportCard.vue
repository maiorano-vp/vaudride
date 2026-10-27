<script setup lang="ts">
import {
  creditText,
  kindLabelKey,
  kmText,
  type CourseCardData,
} from '~/lib/course/card'
import { stampDate } from '~/lib/passport'

// A course of the passport. Ridden for real: the photo in colour with a round stamp bearing the day. Not yet: the photo
// in grey and a link to how to ride it. The whole card opens the course sheet at the "ride it for real" block.
const props = defineProps<{ course: CourseCardData; days?: string[] }>()
const { locale } = useI18n()

const name = computed(
  () => props.course.name[locale.value] ?? props.course.name.fr ?? '',
)
const kindKey = computed(() => kindLabelKey(props.course.kind))
const credit = computed(() => creditText(props.course.image_credit))
const done = computed(() => !!props.days?.length)
</script>

<template>
  <article class="passport-card" :class="{ done }">
    <div class="img-wrap">
      <img
        v-if="course.image_url"
        :src="course.image_url"
        alt=""
        loading="lazy"
        decoding="async"
      >
      <div v-else class="no-image"><img src="/vaud-logo.svg" alt="" ></div>
      <div v-if="kindKey" class="kind">
        <span>{{ $t(kindKey) }}</span>
      </div>
      <small v-if="credit && course.image_url" class="credit">{{
        credit
      }}</small>

      <!-- the stamp: a double ring, the word, the day of the last ride; a number when there are several -->
      <div
        v-if="done"
        class="stamp"
        role="img"
        :aria-label="$t('passport.stampLabel', { date: days![0]! })"
      >
        <span class="word">{{ $t('passport.stamp') }}</span>
        <span class="date">{{ stampDate(days![0]!) }}</span>
        <span v-if="days!.length > 1" class="times">×{{ days!.length }}</span>
      </div>
    </div>

    <div class="content">
      <h3>
        <NuxtLink :to="`/courses/${course.slug}#real`" class="stretched">{{
          name
        }}</NuxtLink>
      </h3>
      <p v-if="course.destination" class="place">
        <UIcon name="i-lucide-map-pin" class="pin" />
        {{ course.destination }}
      </p>
      <p class="figures">
        {{ kmText(course.distance_m) }} km · {{ course.elevation_gain_m }} m D+
      </p>
      <p v-if="!done" class="how">
        <UIcon name="i-lucide-bike" /> {{ $t('passport.howTo') }}
      </p>
    </div>
  </article>
</template>

<style scoped>
.passport-card {
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
  transition:
    transform 0.3s,
    filter 0.3s;
}
/* not ridden yet: the photo is waiting, in grey, and wakes up under the pointer */
.passport-card:not(.done) .img-wrap > img {
  filter: grayscale(1) contrast(0.95) brightness(1.05);
}
.passport-card:not(.done):hover .img-wrap > img {
  filter: none;
}
.passport-card:hover .img-wrap > img {
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
  bottom: 0;
  left: 0;
  max-width: 60%;
  padding: 1px 6px;
  font-size: 0.7rem;
  color: #fff;
  pointer-events: none;
  background: rgb(10 64 105 / 85%);
}
.stamp {
  position: absolute;
  right: 1rem;
  bottom: 1rem;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  align-items: center;
  justify-content: center;
  width: 6.5rem;
  height: 6.5rem;
  font-family: var(--font-display);
  line-height: 1;
  color: var(--color-vaud-green-700);
  text-transform: uppercase;
  pointer-events: none;
  background: rgb(255 255 255 / 80%);
  border: 6px double var(--color-vaud-green-700);
  border-radius: 9999px;
  box-shadow: 0 1px 6px rgb(10 51 77 / 25%);
  transform: rotate(-12deg);
}
.stamp .word {
  font-size: 0.95rem;
  letter-spacing: 0.12em;
}
.stamp .date {
  padding: 0.1rem 0.35rem;
  font-size: 1.6rem;
  border-block: 2px solid var(--color-vaud-green-700);
}
.stamp .times {
  font-size: 0.9rem;
}
.content {
  padding: 1rem 0 0;
  margin-bottom: 2.5rem;
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
  content: '';
}
.passport-card:hover .stretched {
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
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  color: var(--color-vaud-600);
}
.how {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  margin: 0;
  font-size: 0.95rem;
  text-decoration: underline;
}
</style>
