<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import { useOptions } from '@/composables/useOptions'
import AppLayout from '@/layouts/AppLayout.vue'
import { toApiError } from '@/services/http'
import { projectsApi } from '@/services/projects'
import { useCompanyStore } from '@/stores/company'
import type { Paginated, Project } from '@/types/api'

const route = useRoute()
const router = useRouter()
const company = useCompanyStore()
const canManage = computed(() => company.can('projects.manage'))
const { options, load: loadOptions } = useOptions()

const page = ref<Paginated<Project> | null>(null)
const status = ref<string>('active')
const loadError = ref<string | null>(null)
const showForm = ref(false)
const clientFilter = computed(() => (typeof route.query.cliente === 'string' ? route.query.cliente : undefined))

async function load() {
  try {
    page.value = await projectsApi.list({ status: status.value || undefined, client: clientFilter.value })
  } catch (error) {
    loadError.value = toApiError(error).message
  }
}

function progress(project: Project): number {
  const total = (project.open_tasks_count ?? 0) + (project.done_tasks_count ?? 0)
  return total === 0 ? 0 : Math.round(((project.done_tasks_count ?? 0) / total) * 100)
}

async function onSaved(project: Project) {
  showForm.value = false
  await router.push({ name: 'project-board', params: { id: project.id } })
}

watch([status, clientFilter], load)
onMounted(() => Promise.all([load(), loadOptions()]))
</script>

<template>
  <AppLayout>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">Projetos</h1>
      <div v-if="canManage" class="w-40">
        <BaseButton type="button" @click="showForm = true">Novo projeto</BaseButton>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
      <label for="project-filter" class="text-slate-600">Situação</label>
      <select id="project-filter" v-model="status" class="rounded-lg border border-slate-300 px-2 py-1">
        <option value="">Todas</option>
        <option v-for="option in options?.project_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <RouterLink v-if="clientFilter" :to="{ name: 'projects' }" class="text-brand-600 hover:underline">Ver de todos os clientes</RouterLink>
    </div>

    <FormAlert :message="loadError" />

    <p v-if="page && page.data.length === 0" class="text-sm text-slate-500">Nenhum projeto por aqui.</p>

    <div class="grid gap-4 sm:grid-cols-2" data-test="projects">
      <RouterLink
        v-for="project in page?.data ?? []"
        :key="project.id"
        :to="{ name: 'project-board', params: { id: project.id } }"
        class="block rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-500"
      >
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold">{{ project.name }}</p>
            <p class="text-sm text-slate-500">{{ project.client?.name ?? 'Sem cliente' }}</p>
          </div>
          <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ project.status.label }}</span>
        </div>
        <div class="mt-4">
          <div class="h-1.5 rounded-full bg-slate-100">
            <div class="h-1.5 rounded-full bg-brand-600" :style="{ width: `${progress(project)}%` }" />
          </div>
          <p class="mt-1 text-xs text-slate-500">
            {{ project.done_tasks_count ?? 0 }} de {{ (project.open_tasks_count ?? 0) + (project.done_tasks_count ?? 0) }} tarefas concluídas
            <template v-if="project.due_on"> · prazo {{ new Date(`${project.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}</template>
          </p>
        </div>
      </RouterLink>
    </div>

    <ProjectFormModal v-if="showForm" @close="showForm = false" @saved="onSaved" />
  </AppLayout>
</template>
