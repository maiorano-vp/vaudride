<script setup lang="ts">
import { poiDescription, poiTitle, type Poi } from '~/lib/pois/nearby'

defineProps<{ poi: Poi; aheadM: number; favorite?: boolean }>()
const { locale } = useI18n()

const icons: Record<string, string> = {
  viewpoint: 'i-lucide-binoculars',
  culture: 'i-lucide-landmark',
  history: 'i-lucide-castle',
  nature: 'i-lucide-mountain',
  water: 'i-lucide-droplet',
  wine: 'i-lucide-wine',
  food: 'i-lucide-utensils',
  lodging: 'i-lucide-bed',
  bike: 'i-lucide-wrench',
  station: 'i-lucide-train-front',
  shop: 'i-lucide-shopping-basket',
  activity: 'i-lucide-bike',
  service: 'i-lucide-info',
}
</script>

<template>
  <aside class="poi-card" role="status">
    <img v-if="poi.image_url" :src="poi.image_url" :alt="poiTitle(poi, locale)" class="photo" loading="lazy" >
    <UIcon v-else :name="icons[poi.category ?? ''] ?? 'i-lucide-map-pin'" class="icon" />
    <div>
      <p class="title">{{ poiTitle(poi, locale) }}</p>
      <p class="meta">
        <span v-if="poi.category">{{ $t(`poi.category.${poi.category}`) }} · </span>
        {{ aheadM > 20 ? $t('poi.ahead', { m: Math.round(aheadM / 10) * 10 }) : $t('poi.here') }}
      </p>
      <p v-if="poiDescription(poi, locale)" class="description">{{ poiDescription(poi, locale) }}</p>
      <p class="favorite" :class="{ kept: favorite }">
        <UIcon name="i-lucide-star" class="star" :class="{ filled: favorite }" />
        {{ favorite ? $t('favorites.kept') : $t('favorites.hint') }}
      </p>
      <p class="links">
        <a v-if="poi.url" :href="poi.url" target="_blank" rel="noopener noreferrer">{{ $t('poi.more') }}</a>
        <small v-if="poi.source === 'osm'">© OpenStreetMap contributors</small>
        <small v-if="poi.image_credit">{{ poi.image_credit }}</small>
      </p>
    </div>
  </aside>
</template>

<style scoped>
.poi-card {
  display: flex;
  gap: 0.75rem;
  max-width: 22rem;
  padding: 0.6rem 0.9rem;
  color: #272e3d;
  background: rgb(255 255 255 / 92%);
  border-left: 4px solid #e8a317;
  border-radius: 0.5rem;
}
.photo {
  flex: none;
  width: 5.5rem;
  height: 4.5rem;
  object-fit: cover;
  border-radius: 0.35rem;
}
.icon {
  flex: none;
  width: 1.6rem;
  height: 1.6rem;
  color: #0a4069;
}
.title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.1rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #0a4069;
}
.meta,
.description,
.links {
  margin: 0;
  font-size: 0.85rem;
}
.favorite {
  display: flex;
  gap: 0.35rem;
  align-items: center;
  margin: 0.2rem 0 0;
  font-size: 0.85rem;
  color: #667;
}
.favorite.kept {
  font-weight: 700;
  color: #0a4069;
}
.star {
  width: 1.2rem;
  height: 1.2rem;
}
.star.filled {
  color: #e8a317;
  fill: #e8a317;
}
.links {
  display: flex;
  gap: 0.75rem;
  align-items: baseline;
}
.links a {
  text-decoration: underline;
}
.links small {
  color: #667;
}
</style>
