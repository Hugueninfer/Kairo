<script setup lang="ts">
import AppLogo from '@/components/ui/AppLogo.vue'

defineProps<{ title: string; subtitle?: string }>()
</script>

<template>
  <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12">
    <!-- Brilho azul discreto atrás do cartão. -->
    <div
      class="pointer-events-none absolute top-[-20%] left-1/2 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-brand-600/15 blur-[120px]"
      aria-hidden="true"
    />
    <div class="relative w-full max-w-[400px]">
      <div class="mb-8 flex justify-center">
        <AppLogo :height="34" />
      </div>
      <div class="card p-6 shadow-2xl shadow-black/40 sm:p-8">
        <h1 class="text-xl font-semibold tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm text-fg-muted">{{ subtitle }}</p>
        <div class="mt-6">
          <slot />
        </div>
      </div>
      <div class="mt-6 text-center text-sm text-fg-muted">
        <slot name="footer" />
      </div>
    </div>
  </main>
</template>
