<script setup lang="ts">
import AppIcon from './AppIcon.vue'

defineProps<{
  title: string
  subtitle?: string
  /** Trilha acima do título; o último item é a página atual. */
  breadcrumbs?: { label: string; to?: string | object }[]
}>()
</script>

<template>
  <header class="space-y-4">
    <nav v-if="breadcrumbs?.length" class="flex flex-wrap items-center gap-1.5 text-[13px] text-fg-muted" aria-label="Trilha">
      <template v-for="(crumb, index) in breadcrumbs" :key="index">
        <AppIcon v-if="index > 0" name="chevronRight" :size="14" class="text-fg-subtle" />
        <RouterLink v-if="crumb.to" :to="crumb.to" class="hover:text-fg">{{ crumb.label }}</RouterLink>
        <span v-else class="text-brand-400">{{ crumb.label }}</span>
      </template>
    </nav>
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight text-fg sm:text-[26px]">
          <slot name="title">{{ title }}</slot>
        </h1>
        <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-fg-muted">
          <slot name="subtitle">{{ subtitle }}</slot>
        </p>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
    <slot name="tabs" />
  </header>
</template>
