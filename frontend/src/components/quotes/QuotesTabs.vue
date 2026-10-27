<template>
  <nav class="flex gap-1 border-b border-slate-200 text-sm" aria-label="Orçamentos e contratos">
    <RouterLink
      :to="{ name: 'quotes' }"
      class="-mb-px border-b-2 border-transparent px-3 py-2 text-slate-600 hover:text-brand-600"
      exact-active-class="!border-brand-600 font-semibold text-brand-600"
    >
      Orçamentos
    </RouterLink>
    <RouterLink
      :to="{ name: 'contracts' }"
      class="-mb-px border-b-2 border-transparent px-3 py-2 text-slate-600 hover:text-brand-600"
      exact-active-class="!border-brand-600 font-semibold text-brand-600"
    >
      Contratos
    </RouterLink>
  </nav>
</template>
