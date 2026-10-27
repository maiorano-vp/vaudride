<template>
  <main class="grid min-h-dvh bg-white lg:grid-cols-2">
    <section class="flex flex-col justify-center px-6 py-10 sm:px-12">
      <div class="mx-auto w-full max-w-md">
        <NuxtLink to="/" class="mb-8 flex items-center gap-3">
          <img src="/vaud-logo.svg" alt="Vaud" class="h-9" >
          <!-- the logo already spells "Vaud" -->
          <span class="font-display text-3xl uppercase tracking-wide text-vaud-800">Ride</span>
        </NuxtLink>
        <slot />
        <LocaleSwitcher class="mt-8" />
        <NuxtLink to="/privacy" class="mt-4 inline-block text-sm text-slate-500 underline">{{ $t('privacy.link') }}</NuxtLink>
      </div>
    </section>

    <aside class="relative hidden overflow-hidden lg:block">
      <img src="/auth-hero.jpg" alt="" class="absolute inset-0 size-full object-cover object-[50%_65%]" >
      <div class="absolute inset-0 bg-gradient-to-t from-vaud-900/80 via-vaud-900/10 to-transparent" />
      <p class="absolute inset-x-0 bottom-0 p-12 font-display text-5xl uppercase leading-tight tracking-wide text-white">
        {{ $t('auth.tagline') }}
      </p>
    </aside>
  </main>
</template>

