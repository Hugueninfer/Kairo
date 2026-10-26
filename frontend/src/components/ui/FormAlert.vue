<script setup lang="ts">
defineProps<{
  message: string | null
  variant?: 'error' | 'success'
}>()
</script>

<template>
  <p
    v-if="message"
    role="alert"
    class="rounded-lg px-3 py-2 text-sm"
    :class="variant === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
    data-test="form-alert"
  >
    {{ message }}
  </p>
</template>
