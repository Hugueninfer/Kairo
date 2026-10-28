<script setup lang="ts">
import TabNav from '@/components/ui/TabNav.vue'
</script>

<template>
  <TabNav
    label="Orçamentos e contratos"
    :tabs="[
      { key: 'quotes', label: 'Orçamentos', to: { name: 'quotes' } },
      { key: 'contracts', label: 'Contratos', to: { name: 'contracts' } },
    ]"
  />
</template>
