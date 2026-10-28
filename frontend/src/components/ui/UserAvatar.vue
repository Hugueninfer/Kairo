<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ name?: string | null; size?: number }>(), { size: 32 })

// Cor estável por nome, para cada pessoa ter sempre o mesmo tom.
const palette = ['#2a7bff', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16']

const initials = computed(() =>
  (props.name ?? '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join(''),
)

const color = computed(() => {
  const text = props.name ?? ''
  let hash = 0
  for (let index = 0; index < text.length; index++) hash = (hash * 31 + text.charCodeAt(index)) | 0
  return palette[Math.abs(hash) % palette.length]
})
</script>

<template>
  <span
    class="inline-grid shrink-0 place-items-center rounded-full font-semibold text-white ring-2 ring-surface"
    :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.38)}px`, background: color }"
    aria-hidden="true"
    >{{ initials }}</span
  >
</template>
