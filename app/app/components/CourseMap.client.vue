<script setup lang="ts">
import type { LayerGroup, Map as LeafletMap } from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { categoryColor, simplifyRoute, stretch, type RoutePoint } from '~/lib/map/route'
import { poiTitle, type Poi } from '~/lib/pois/nearby'
import { segmentName, type Segment } from '~/lib/segments/tracker'

const props = defineProps<{ points: RoutePoint[]; pois: Poi[]; segments: Segment[] }>()

const { locale, t } = useI18n()
const container = ref<HTMLDivElement>()
let map: LeafletMap | undefined
let placeLayer: LayerGroup | undefined
let L: typeof import('leaflet') | undefined

// the grey map: the colour map's red roads hide the red route
const SWISSTOPO_TILES = 'https://wmts.geo.admin.ch/1.0.0/ch.swisstopo.pixelkarte-grau/default/current/3857/{z}/{x}/{y}.jpeg'

/** The popup of a place, built from DOM nodes: nothing from the catalogue is ever read as HTML. */
function popupFor(poi: Poi): HTMLElement {
  const root = document.createElement('div')
  root.className = 'map-popup'
  if (poi.image_url) {
    const img = document.createElement('img')
    img.src = poi.image_url
    img.alt = poiTitle(poi, locale.value)
    img.loading = 'lazy'
    root.append(img)
  }
  const title = document.createElement('strong')
  title.textContent = poiTitle(poi, locale.value)
  root.append(title)
  if (poi.category) {
    const kind = document.createElement('div')
    kind.textContent = t(`poi.category.${poi.category}`)
    root.append(kind)
  }
  if (poi.url && /^https?:\/\//i.test(poi.url)) {
    const link = document.createElement('a')
    link.href = poi.url
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    link.textContent = t('poi.more')
    root.append(link)
  }
  if (poi.image_credit) {
    const credit = document.createElement('small')
    credit.textContent = poi.image_credit
    root.append(credit)
  }
  return root
}

function drawPlaces() {
  if (!L || !placeLayer) return
  placeLayer.clearLayers()
  for (const poi of props.pois) {
    if (poi.lat == null || poi.lon == null) continue
    L.circleMarker([poi.lat, poi.lon], { radius: 7, color: '#fff', weight: 2, fillColor: categoryColor(poi.category), fillOpacity: 1 })
      .bindPopup(() => popupFor(poi), { maxWidth: 240 })
      .bindTooltip(poiTitle(poi, locale.value))
      .addTo(placeLayer)
  }
}

onMounted(async () => {
  L = (await import('leaflet')).default
  if (!container.value) return
  map = L.map(container.value, { scrollWheelZoom: false })
  L.tileLayer(SWISSTOPO_TILES, { maxZoom: 18, attribution: '© <a href="https://www.swisstopo.admin.ch/" target="_blank" rel="noopener">swisstopo</a>' }).addTo(map)

  const route = simplifyRoute(props.points)
  // a white casing under the red line keeps it readable on any map colour
  L.polyline(route, { color: '#fff', weight: 10, opacity: 0.95 }).addTo(map)
  const line = L.polyline(route, { color: '#e63946', weight: 5 }).addTo(map)
  for (const s of props.segments) {
    L.polyline(stretch(props.points, s.start_m, s.end_m), { color: '#f4a300', weight: 7, opacity: 0.85 })
      .bindTooltip(segmentName(s, locale.value), { sticky: true })
      .addTo(map)
  }

  const endpoint = (at: [number, number], color: string, label: string) =>
    L!.marker(at, { icon: L!.divIcon({ className: 'route-end', html: `<span style="background:${color}"></span>`, iconSize: [18, 18] }), title: label }).addTo(map!)
  if (route.length) {
    endpoint(route[0]!, '#199573', t('map.start'))
    endpoint(route[route.length - 1]!, '#0a334d', t('map.finish'))
  }

  placeLayer = L.layerGroup().addTo(map)
  drawPlaces()
  map.fitBounds(line.getBounds(), { padding: [24, 24] })
})

watch(() => props.pois, drawPlaces)
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="container" class="course-map" role="region" :aria-label="$t('map.label')" />
</template>

<style scoped>
.course-map {
  height: 26rem;
  width: 100%;
  border-radius: 0.5rem;
  overflow: hidden;
  background: #dfe8ef;
}
.course-map :deep(.route-end span) {
  display: block;
  width: 18px;
  height: 18px;
  border: 3px solid #fff;
  border-radius: 50%;
  box-shadow: 0 0 3px rgb(0 0 0 / 50%);
}
.course-map :deep(.map-popup) {
  display: grid;
  gap: 0.2rem;
  font-size: 0.85rem;
}
.course-map :deep(.map-popup img) {
  width: 100%;
  max-height: 8rem;
  object-fit: cover;
  border-radius: 0.3rem;
}
.course-map :deep(.map-popup small) {
  color: #667;
}
</style>
