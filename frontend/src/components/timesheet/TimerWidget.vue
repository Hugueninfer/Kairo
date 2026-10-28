<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
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
  <div
    v-if="timer.running"
    class="flex min-w-0 items-center gap-2 rounded-lg border border-emerald-500/25 bg-emerald-500/10 py-1 pr-1 pl-2.5 text-sm text-emerald-300"
    data-test="timer-widget"
  >
    <span class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-emerald-400" aria-hidden="true" />
    <span class="hidden max-w-40 truncate sm:inline" :title="timer.running.project?.name">{{ timer.running.task?.title ?? timer.running.project?.name }}</span>
    <span class="font-mono tabular-nums" aria-label="Tempo do timer">{{ formatElapsed(timer.elapsedSeconds) }}</span>
    <button
      type="button"
      class="flex items-center gap-1 rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold hover:bg-emerald-500/25"
      :title="error ?? undefined"
      @click="stop"
    >
      <AppIcon name="stop" :size="12" :stroke="2.5" />Parar
    </button>
  </div>
</template>
