<script setup lang="ts">
defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  error?: string
}>()

const model = defineModel<string>({ required: true })
</script>

<template>
  <div>
    <label :for="id" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="w-full rounded-lg border px-3 py-2 text-sm shadow-sm outline-none focus:ring-2 focus:ring-brand-500"
      :class="error ? 'border-red-400' : 'border-slate-300'"
    />
    <p v-if="error" :id="`${id}-error`" class="mt-1 text-xs text-red-600" data-test="field-error">{{ error }}</p>
  </div>
</template>
