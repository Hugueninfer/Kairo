<script setup lang="ts">
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import AppIcon, { type IconName } from './AppIcon.vue'
import StatusBadge, { type BadgeTone } from './StatusBadge.vue'

defineProps<{
  label: string
  value: string
  hint?: string
  icon?: IconName
  badge?: { text: string; tone: BadgeTone; icon?: IconName }
  to?: RouteLocationRaw
  /** Cor do valor quando ele mesmo é o alerta (ex.: vencido). */
  valueClass?: string
  /** Sem borda própria, para ficar lado a lado dentro de uma faixa (StatStrip). */
  plain?: boolean
}>()
</script>

<template>
  <component
    :is="to ? RouterLink : 'div'"
    :to="to"
    class="group flex items-start gap-4 p-4 transition-colors sm:p-5"
    :class="[plain ? 'bg-surface' : 'card', to ? (plain ? 'hover:bg-surface-2' : 'hover:border-line-strong hover:bg-surface-2/50') : '']"
  >
    <span v-if="icon" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-600 text-white shadow-lg shadow-brand-600/20">
      <AppIcon :name="icon" :size="20" />
    </span>
    <div class="min-w-0 flex-1">
      <div class="flex items-start justify-between gap-2">
        <p class="text-[13px] text-fg-muted">{{ label }}</p>
        <StatusBadge v-if="badge" :tone="badge.tone" :dot="!badge.icon" solid class="-mt-0.5">
          <AppIcon v-if="badge.icon" :name="badge.icon" :size="12" :stroke="2.25" />
          {{ badge.text }}
        </StatusBadge>
      </div>
      <p class="mt-1 truncate text-[22px] leading-tight font-semibold tracking-tight text-fg tabular-nums" :class="valueClass">
        <slot name="value">{{ value }}</slot>
      </p>
      <p v-if="hint || $slots.hint" class="mt-1 text-xs text-fg-muted"><slot name="hint">{{ hint }}</slot></p>
    </div>
  </component>
</template>
