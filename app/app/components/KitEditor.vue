<script setup lang="ts">
import { HELMET_STYLES, JERSEY_STYLES, KIT_COLORS, KIT_PRESETS, WHEEL_STYLES, type Kit } from '~/lib/kit/kit'

withDefaults(defineProps<{ preview?: boolean }>(), { preview: true })

const { kit, save } = useKit()
const { t } = useI18n()
const toast = useToast()

const draft = ref<Kit>({ ...kit.value })
watch(kit, (k) => (draft.value = { ...k }))

const families = [
  { key: 'jerseyStyle', options: JERSEY_STYLES },
  { key: 'helmetStyle', options: HELMET_STYLES },
  { key: 'wheels', options: WHEEL_STYLES },
] as const

const saving = ref(false)
async function onSave() {
  saving.value = true
  try {
    await save(draft.value)
    toast.add({ title: t('kit.saved'), color: 'success' })
  } catch {
    toast.add({ title: t('kit.error'), color: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="grid gap-6" :class="{ 'md:grid-cols-2': preview }">
    <KitPreview v-if="preview" :kit="draft" />

  <UCard>
    <section class="mb-5">
      <h2 class="mb-2 font-display uppercase tracking-wide">{{ $t('kit.presets') }}</h2>
      <div class="flex flex-wrap gap-2">
        <UButton
          v-for="(preset, name) in KIT_PRESETS"
          :key="name"
          size="md"
          color="neutral"
          variant="outline"
          :label="$t(`kit.preset.${name}`)"
          @click="draft = { ...preset }"
        />
      </div>
    </section>

    <section class="mb-5">
      <h2 class="mb-2 font-display uppercase tracking-wide">{{ $t('kit.colors') }}</h2>
      <ul class="grid grid-cols-2 gap-3">
        <li v-for="c in KIT_COLORS" :key="c">
          <label class="flex items-center gap-3">
            <input v-model="draft[c]" type="color" class="h-9 w-12 cursor-pointer rounded border border-slate-300" >
            {{ $t(`kit.color.${c}`) }}
          </label>
        </li>
      </ul>
    </section>

    <section class="mb-6 space-y-3">
      <h2 class="font-display uppercase tracking-wide">{{ $t('kit.styles') }}</h2>
      <label v-for="f in families" :key="f.key" class="flex items-center justify-between gap-3">
        {{ $t(`kit.${f.key}.label`) }}
        <USelect
          v-model="draft[f.key]"
          :items="f.options.map((o) => ({ value: o, label: $t(`kit.${f.key}.${o}`) }))"
          size="sm"
          class="min-w-44"
        />
      </label>
    </section>

    <UButton :loading="saving" :label="$t('kit.save')" @click="onSave" />
  </UCard>
  </div>
</template>
