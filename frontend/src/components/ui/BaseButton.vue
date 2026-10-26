<script setup lang="ts">
defineProps<{
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit'
}>()
</script>

<template>
  <button
    :type="type ?? 'submit'"
    :disabled="loading || disabled"
    class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
  >
    <span v-if="loading">Aguarde...</span>
    <slot v-else />
  </button>
</template>
