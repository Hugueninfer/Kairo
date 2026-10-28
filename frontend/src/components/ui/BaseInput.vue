<script setup lang="ts">
defineProps<{
  id: string
  label: string
  type?: string
  autocomplete?: string
  error?: string
  placeholder?: string
}>()

// Sempre texto, inclusive em type="number" (o v-model nativo converteria para número).
const model = defineModel<string>({ required: true })
</script>

<template>
  <div>
    <label :for="id" class="field-label">{{ label }}</label>
    <input
      :id="id"
      :value="model"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="field w-full"
      :class="error ? '!border-red-500/70 focus:!ring-red-500/25' : ''"
      @input="model = ($event.target as HTMLInputElement).value"
    />
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-xs text-red-400" data-test="field-error">{{ error }}</p>
  </div>
</template>
