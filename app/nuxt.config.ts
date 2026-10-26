// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxtjs/i18n', '@vite-pwa/nuxt', '@nuxt/eslint'],
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
      theme_color: '#1a1a1a',
      display: 'standalone',
    },
  },
})
