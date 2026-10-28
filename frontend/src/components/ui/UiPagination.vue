<script setup lang="ts">
import AppIcon from './AppIcon.vue'

defineProps<{ current: number; last: number }>()
const emit = defineEmits<{ go: [page: number] }>()
</script>

<template>
  <!-- Rodapé de tabela: anterior, "Página X de Y" e próxima, como nas referências. -->
  <nav class="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-[13px] text-fg-muted" aria-label="Paginação">
    <button type="button" class="btn-ghost btn-sm" :disabled="current <= 1" @click="emit('go', current - 1)">
      <AppIcon name="arrowLeft" :size="15" /> Anterior
    </button>
    <span>Página {{ current }} de {{ last }}</span>
    <button type="button" class="btn-ghost btn-sm" :disabled="current >= last" @click="emit('go', current + 1)">
      Próxima <AppIcon name="arrowRight" :size="15" />
    </button>
  </nav>
</template>
