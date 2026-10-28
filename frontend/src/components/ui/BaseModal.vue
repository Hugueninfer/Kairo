<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import AppIcon from './AppIcon.vue'

withDefaults(defineProps<{ title: string; subtitle?: string; size?: 'md' | 'lg' | 'xl' }>(), { size: 'md' })
const emit = defineEmits<{ close: [] }>()

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center overflow-y-auto bg-black/70 backdrop-blur-[2px] sm:items-center sm:p-4"
    @click.self="emit('close')"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      class="w-full rounded-t-2xl border border-line bg-surface shadow-2xl shadow-black/50 sm:rounded-2xl"
      :class="{ md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' }[size]"
    >
      <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
        <div>
          <h2 class="text-base font-semibold text-fg">{{ title }}</h2>
          <p v-if="subtitle" class="mt-0.5 text-sm text-fg-muted">{{ subtitle }}</p>
        </div>
        <button type="button" class="-m-1 rounded-lg p-1.5 text-fg-muted hover:bg-surface-2 hover:text-fg" aria-label="Fechar" @click="emit('close')">
          <AppIcon name="close" />
        </button>
      </div>
      <div class="max-h-[80vh] overflow-y-auto px-5 py-5">
        <slot />
      </div>
    </div>
  </div>
</template>
