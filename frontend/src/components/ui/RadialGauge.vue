<script setup lang="ts">
import { computed } from 'vue'

/** Meio anel de progresso (0 a 100), como o "My Stats" das referências. */
const props = defineProps<{ percent: number; label: string }>()

const radius = 80
const length = Math.PI * radius
const offset = computed(() => length * (1 - Math.min(100, Math.max(0, props.percent)) / 100))
</script>

<template>
  <div class="relative mx-auto w-full max-w-[220px]">
    <svg viewBox="0 0 200 110" class="w-full" role="img" :aria-label="`${label}: ${percent}%`">
      <path d="M20 100a80 80 0 0 1 160 0" fill="none" stroke="var(--color-elevated)" stroke-width="16" stroke-linecap="round" />
      <path
        d="M20 100a80 80 0 0 1 160 0"
        fill="none"
        stroke="var(--color-brand-500)"
        stroke-width="16"
        stroke-linecap="round"
        :stroke-dasharray="length"
        :stroke-dashoffset="offset"
        class="transition-[stroke-dashoffset] duration-700"
      />
    </svg>
    <div class="absolute inset-x-0 bottom-0 text-center">
      <p class="text-xs text-fg-muted">{{ label }}</p>
      <p class="text-2xl font-semibold text-fg tabular-nums">{{ percent }}%</p>
    </div>
  </div>
</template>
