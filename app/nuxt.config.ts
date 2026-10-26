// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/ui', '@nuxtjs/i18n', '@vite-pwa/nuxt', '@nuxt/eslint'],
  app: {
    head: {
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'icon', href: '/favicon.ico', sizes: '32x32' },
      ],
    },
  },
  css: ['~/assets/css/main.css'],
  ui: { colorMode: false }, // light only: no color-mode module, no `dark` class
  // NUXT_API_BASE, NUXT_PUBLIC_PUSHER_KEY (public key; the secret stays on the API),
  // NUXT_PUBLIC_GOOGLE_MAPS_KEY (browser key restricted by HTTP referrer; enables the Google 3D source),
  // NUXT_PUBLIC_CESIUM_ION_TOKEN (read-only ion token limited to the Google asset; enables Google through Cesium ion)
  runtimeConfig: { apiBase: 'http://127.0.0.1:8000', public: { pusherKey: '', pusherCluster: 'eu', googleMapsKey: '', cesiumIonToken: '' } },
  nitro: { preset: 'cloudflare_module', cloudflare: { deployConfig: true, nodeCompat: true, wrangler: { name: 'vaudride', vars: { NUXT_API_BASE: 'https://vaudride.myvaud.dev', NUXT_PUBLIC_PUSHER_KEY: '80f62163e704d42f25a9', NUXT_PUBLIC_CESIUM_ION_TOKEN: process.env.NUXT_PUBLIC_CESIUM_ION_TOKEN ?? '' } } } },
  typescript: { strict: true },
  vite: { define: { CESIUM_BASE_URL: JSON.stringify('/cesium/') } },
  i18n: {
    defaultLocale: 'fr',
    strategy: 'no_prefix',
    locales: [
      { code: 'fr', language: 'fr-CH', file: 'fr.json', name: 'Français' },
      { code: 'de', language: 'de-CH', file: 'de.json', name: 'Deutsch' },
      { code: 'en', language: 'en', file: 'en.json', name: 'English' },
    ],
  },
  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'Vaud Ride',
      short_name: 'Vaud Ride',
      lang: 'fr',
      theme_color: '#0a4069',
      display: 'standalone',
    },
  },
})
