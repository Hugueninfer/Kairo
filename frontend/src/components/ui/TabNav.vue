<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

/**
 * Abas sublinhadas. Com "to" viram links de rota; sem "to", são botões e o v-model
 * guarda a aba ativa.
 */
defineProps<{
  tabs: { key: string; label: string; to?: RouteLocationRaw; count?: number; dataTest?: string }[]
  label: string
}>()
const active = defineModel<string>()
</script>

<template>
  <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
    <nav class="flex min-w-max gap-5 border-b border-line text-sm" :aria-label="label" :role="tabs.some((tab) => tab.to) ? undefined : 'tablist'">
      <template v-for="tab in tabs" :key="tab.key">
        <RouterLink
          v-if="tab.to"
          :to="tab.to"
          class="-mb-px flex items-center gap-2 border-b-2 border-transparent pt-1 pb-2.5 text-fg-muted transition-colors hover:text-fg"
          exact-active-class="!border-brand-500 !text-fg font-medium"
          :data-test="tab.dataTest"
        >
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="rounded-md bg-elevated px-1.5 text-[11px] text-fg-soft">{{ tab.count }}</span>
        </RouterLink>
        <button
          v-else
          type="button"
          role="tab"
          :aria-selected="active === tab.key"
          class="-mb-px flex items-center gap-2 border-b-2 pt-1 pb-2.5 transition-colors"
          :class="active === tab.key ? 'border-brand-500 font-medium text-fg' : 'border-transparent text-fg-muted hover:text-fg'"
          :data-test="tab.dataTest"
          @click="active = tab.key"
        >
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="rounded-md bg-elevated px-1.5 text-[11px] text-fg-soft">{{ tab.count }}</span>
        </button>
      </template>
    </nav>
  </div>
</template>
