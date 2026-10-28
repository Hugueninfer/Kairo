<script setup lang="ts">
defineProps<{
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit'
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  /** Por padrão ocupa a largura toda (formulários); "inline" deixa do tamanho do texto. */
  inline?: boolean
}>()
</script>

<template>
  <button
    :type="type ?? 'submit'"
    :disabled="loading || disabled"
    :class="[
      {
        primary: 'btn-primary',
        secondary: 'btn-secondary',
        ghost: 'btn-ghost',
        danger: 'btn-danger',
      }[variant ?? 'primary'],
      inline ? '' : 'w-full',
    ]"
  >
    <svg v-if="loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
      <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
    </svg>
    <span v-if="loading">Aguarde...</span>
    <slot v-else />
  </button>
</template>
