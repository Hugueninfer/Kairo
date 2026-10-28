<script setup lang="ts">
/**
 * Faixa de indicadores num cartão só, separados por linhas finas (como o topo das referências).
 * Use com <StatCard plain>; as linhas vêm do fundo aparecendo pelo "gap" de 1px.
 */
defineProps<{ count: number }>()

// Quantas colunas por tela; quando sobra espaço na última linha, o último item estica.
const layouts: Record<number, string> = {
  1: '',
  2: '',
  3: 'lg:grid-cols-3 [&>*:last-child]:sm:max-lg:col-span-2',
  4: 'xl:grid-cols-4',
  5: 'lg:grid-cols-3 xl:grid-cols-5 [&>*:last-child]:sm:max-lg:col-span-2 [&>*:last-child]:lg:max-xl:col-span-2',
}
</script>

<template>
  <div
    class="grid gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-2"
    :class="layouts[count] ?? layouts[3]"
  >
    <slot />
  </div>
</template>
