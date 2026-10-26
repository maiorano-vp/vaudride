// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  modules: ['@nuxt/ui', '@nuxtjs/i18n', '@vite-pwa/nuxt', '@nuxt/eslint'],
  css: ['~/assets/css/main.css'],
  ui: { colorMode: false }, // light only: no color-mode module, no `dark` class
  runtimeConfig: { apiBase: 'http://127.0.0.1:8000' }, // NUXT_API_BASE
  nitro: { preset: 'cloudflare_module', cloudflare: { deployConfig: true, nodeCompat: true, wrangler: { name: 'vaudride', vars: { NUXT_API_BASE: 'https://vaudride.myvaud.dev' } } } },
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
