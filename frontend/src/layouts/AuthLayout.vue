<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>()
</script>

<template>
  <main class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
      <p class="mb-8 text-center text-2xl font-bold tracking-tight text-brand-600">Kairo</p>
      <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h1 class="text-lg font-semibold">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
        <div class="mt-6">
          <slot />
        </div>
      </div>
      <div class="mt-6 text-center text-sm text-slate-500">
        <slot name="footer" />
      </div>
    </div>
  </main>
</template>
