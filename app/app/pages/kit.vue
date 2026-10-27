<template>
  <div class="mx-auto max-w-5xl p-4">
    <header class="mb-4 flex items-center justify-between">
      <h1 class="font-display text-3xl uppercase tracking-wide text-vaud-800">{{ $t('kit.title') }}</h1>
      <div class="flex items-center gap-3">
        <LocaleSwitcher />
        <UButton to="/" variant="ghost" color="neutral">{{ $t('auth.back') }}</UButton>
      </div>
    </header>

    <KitEditor />
  </div>
</template>
