<script setup lang="ts">
export type BadgeTone = 'neutral' | 'blue' | 'green' | 'red' | 'amber' | 'violet' | 'muted'

withDefaults(defineProps<{ tone?: BadgeTone; dot?: boolean; solid?: boolean }>(), { tone: 'neutral', dot: true })
</script>

<template>
  <!-- Pílula de situação, no estilo das referências: cor viva e um ponto à esquerda. -->
  <span
    class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap"
    :class="
      solid
        ? {
            neutral: 'bg-zinc-600 text-white',
            muted: 'bg-zinc-700 text-zinc-300',
            blue: 'bg-brand-600 text-white',
            green: 'bg-emerald-600 text-white',
            red: 'bg-red-600 text-white',
            amber: 'bg-amber-500 text-zinc-950',
            violet: 'bg-violet-600 text-white',
          }[tone]
        : {
            neutral: 'bg-zinc-500/15 text-zinc-300 ring-1 ring-zinc-500/25 ring-inset',
            muted: 'bg-zinc-500/10 text-zinc-500 ring-1 ring-zinc-500/20 ring-inset',
            blue: 'bg-brand-500/15 text-brand-300 ring-1 ring-brand-500/30 ring-inset',
            green: 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30 ring-inset',
            red: 'bg-red-500/15 text-red-300 ring-1 ring-red-500/30 ring-inset',
            amber: 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/30 ring-inset',
            violet: 'bg-violet-500/15 text-violet-300 ring-1 ring-violet-500/30 ring-inset',
          }[tone]
    "
  >
    <span v-if="dot" class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
    <slot />
  </span>
</template>
