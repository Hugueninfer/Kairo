<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchHealth, type HealthResponse } from '@/services/health'

const health = ref<HealthResponse | null>(null)
const error = ref(false)

onMounted(async () => {
  try {
    health.value = await fetchHealth()
  } catch {
    error.value = true
  }
})
</script>

<template>
  <main class="home">
    <h1>Kairo</h1>
    <p>Gestão para autônomos e freelancers.</p>
    <p v-if="health" data-test="api-ok">API {{ health.status }} · v{{ health.version }}</p>
    <p v-else-if="error" data-test="api-error">Não foi possível conectar à API.</p>
    <p v-else>Verificando a API...</p>
  </main>
</template>

<style scoped>
.home {
  font-family: system-ui, sans-serif;
  max-width: 40rem;
  margin: 4rem auto;
  padding: 0 1rem;
}
</style>
