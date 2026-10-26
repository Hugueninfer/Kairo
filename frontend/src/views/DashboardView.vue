<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { authApi } from '@/services/auth'
import { toApiError } from '@/services/http'
import { tasksApi } from '@/services/projects'
import { useAuthStore } from '@/stores/auth'
import { useCompanyStore } from '@/stores/company'
import type { Task } from '@/types/api'

const auth = useAuthStore()
const company = useCompanyStore()
const notice = ref<string | null>(null)
const myTasks = ref<Task[] | null>(null)

function isLate(task: Task): boolean {
  return task.due_on !== null && task.due_on < new Date().toISOString().slice(0, 10)
}

onMounted(async () => {
  myTasks.value = await tasksApi.mine().catch(() => [])
})

async function resend() {
  try {
    notice.value = await authApi.resendVerification()
  } catch (error) {
    notice.value = toApiError(error).message
  }
}
</script>

<template>
  <AppLayout>
    <div
      v-if="auth.user && !auth.user.email_verified"
      class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800"
      data-test="verify-banner"
    >
      <span>Confirme seu e-mail para liberar todos os recursos.</span>
      <button type="button" class="font-semibold underline" @click="resend">Reenviar e-mail</button>
    </div>
    <FormAlert :message="notice" variant="success" />

    <h1 class="text-2xl font-semibold">Olá, {{ auth.user?.name }}!</h1>
    <p class="text-slate-600">
      Você está em <strong>{{ company.current?.name }}</strong>.
    </p>

    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="mb-4 font-semibold">Minhas tarefas</h2>
      <p v-if="myTasks && myTasks.length === 0" class="text-sm text-slate-500">Nenhuma tarefa aberta com você. 🎉</p>
      <ul class="divide-y divide-slate-100" data-test="my-tasks">
        <li v-for="task in myTasks ?? []" :key="task.id" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
          <div>
            <RouterLink v-if="task.project" :to="{ name: 'project-board', params: { id: task.project.id } }" class="font-medium hover:text-brand-600">
              {{ task.title }}
            </RouterLink>
            <p class="text-slate-500">{{ task.project?.name }} · {{ task.status.label }}</p>
          </div>
          <span v-if="task.due_on" class="text-xs" :class="isLate(task) ? 'font-semibold text-red-600' : 'text-slate-500'">
            {{ isLate(task) ? 'Atrasada · ' : '' }}{{ new Date(`${task.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}
          </span>
        </li>
      </ul>
    </section>
  </AppLayout>
</template>
