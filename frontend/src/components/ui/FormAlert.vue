<script setup lang="ts">
import AppIcon from './AppIcon.vue'

defineProps<{
  message: string | null
  variant?: 'error' | 'success'
}>()
</script>

<template>
  <p
    v-if="message"
    role="alert"
    class="flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm"
    :class="variant === 'success' ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300' : 'border-red-500/25 bg-red-500/10 text-red-300'"
    data-test="form-alert"
  >
    <AppIcon :name="variant === 'success' ? 'checkCircle' : 'alert'" :size="16" class="mt-0.5" />
    <span>{{ message }}</span>
  </p>
</template>
