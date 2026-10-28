<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

defineProps<{ title: string; description?: string; icon?: IconName }>()
</script>

<template>
  <div class="flex flex-col items-center px-6 py-12 text-center">
    <span class="grid h-11 w-11 place-items-center rounded-xl border border-line bg-surface-2 text-fg-muted">
      <AppIcon :name="icon ?? 'layers'" :size="20" />
    </span>
    <p class="mt-3 text-sm font-medium text-fg">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-fg-muted">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4"><slot /></div>
  </div>
</template>
