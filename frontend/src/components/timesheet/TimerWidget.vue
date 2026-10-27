<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { formatElapsed } from '@/composables/time'
import { toApiError } from '@/services/http'
import { useTimerStore } from '@/stores/timer'

const timer = useTimerStore()
const error = ref<string | null>(null)

async function stop() {
  error.value = null
  try {
    await timer.stop()
  } catch (failure) {
    error.value = toApiError(failure).message
  }
}

onMounted(() => timer.load())
</script>

<template>
  <div v-if="timer.running" class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-1 text-sm text-emerald-800" data-test="timer-widget">
    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true" />
    <span class="max-w-40 truncate" :title="timer.running.project?.name">{{ timer.running.task?.title ?? timer.running.project?.name }}</span>
    <span class="font-mono tabular-nums" aria-label="Tempo do timer">{{ formatElapsed(timer.elapsedSeconds) }}</span>
    <button type="button" class="font-semibold hover:underline" :title="error ?? undefined" @click="stop">Parar</button>
  </div>
</template>
