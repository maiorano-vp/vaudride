<script setup lang="ts">
const route = useRoute()
const { loggedIn } = useAuth()
</script>

<template>
  <!-- the white bar of vaud.ch: 76 px, a soft shadow, 25 px from the edges of the page -->
  <header class="app-bar">
    <NuxtLink
      to="/"
      class="flex items-center gap-3"
      :aria-label="$t('app.title')"
    >
      <img src="/vaud-logo.svg" alt="Vaud" class="h-10" >
      <!-- the logo already spells "Vaud" -->
      <span class="font-display text-3xl uppercase tracking-wide text-vaud-800"
        >Ride</span
      >
    </NuxtLink>
    <!-- the entries of the menu, in the type of vaud.ch's: shown from tablet width, the user menu has them below -->
    <nav :aria-label="$t('app.menu')" class="main-nav">
      <NuxtLink
        to="/"
        :aria-current="route.path === '/' ? 'page' : undefined"
        >{{ $t('course.itineraries') }}</NuxtLink
      >
      <NuxtLink
        to="/intro"
        :aria-current="route.path === '/intro' ? 'page' : undefined"
        >{{ $t('intro.title') }}</NuxtLink
      >
      <NuxtLink
        v-if="loggedIn"
        to="/passport"
        :aria-current="route.path === '/passport' ? 'page' : undefined"
        >{{ $t('passport.menu') }}</NuxtLink
      >
    </nav>
    <div class="tools">
      <LocaleSwitcher />
      <UserMenu />
    </div>
  </header>
</template>

<style scoped>
.app-bar {
  position: relative;
  z-index: 30;
  display: flex;
  gap: 1rem;
  align-items: center;
  justify-content: space-between;
  min-height: 76px;
  padding: 12px 16px;
  margin: 0.75rem;
  background: #fff;
  box-shadow: 0 2px 12px rgb(10 51 77 / 8%);
}
@media (min-width: 768px) {
  .app-bar {
    padding: 18px 28px 18px 24px;
    margin: 25px;
  }
}
.main-nav {
  display: none;
  flex: 1;
  gap: 1.75rem;
  align-items: center;
  margin-left: 1.5rem;
}
@media (min-width: 768px) {
  .main-nav {
    display: flex;
  }
}
.main-nav a {
  font-family: var(--font-display);
  font-size: 1.375rem;
  line-height: 1;
  color: var(--color-vaud-800);
  text-transform: uppercase;
  text-decoration: none;
}
.main-nav a:hover,
.main-nav a[aria-current='page'] {
  text-decoration: underline;
  text-underline-offset: 0.25em;
}
.tools {
  display: flex;
  gap: 1.25rem;
  align-items: center;
}
</style>
