<template>
  <div class="mx-auto max-w-5xl p-4">
    <header class="mb-4 flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('kit.title') }}</h1>
      <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
    </header>

    <KitEditor />
  </div>
</template>
