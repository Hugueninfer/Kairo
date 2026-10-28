<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FormAlert from '@/components/ui/FormAlert.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { statusTone } from '@/composables/statusTone'
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
    <PageHeader title="Projetos" subtitle="Acompanhe o andamento e abra o quadro de tarefas." :breadcrumbs="[{ label: 'Geral' }, { label: 'Projetos' }]">
      <template #actions>
        <BaseButton v-if="canManage" type="button" inline @click="showForm = true"><AppIcon name="plus" :size="16" />Novo projeto</BaseButton>
      </template>
    </PageHeader>

    <div class="flex flex-wrap items-center gap-3 text-sm">
      <label for="project-filter" class="text-fg-muted">Situação</label>
      <select id="project-filter" v-model="status" class="field field-sm w-44">
        <option value="">Todas</option>
        <option v-for="option in options?.project_statuses ?? []" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <RouterLink v-if="clientFilter" :to="{ name: 'projects' }" class="link inline-flex items-center gap-1"><AppIcon name="close" :size="14" />Ver de todos os clientes</RouterLink>
    </div>

    <FormAlert :message="loadError" />

    <div v-if="page && page.data.length === 0" class="card">
      <EmptyState icon="folder" title="Nenhum projeto por aqui." description="Crie um projeto para organizar tarefas, horas e cobranças de um cliente." />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-test="projects">
      <RouterLink
        v-for="project in page?.data ?? []"
        :key="project.id"
        :to="{ name: 'project-board', params: { id: project.id } }"
        class="card group flex flex-col p-5 transition-colors hover:border-line-strong hover:bg-surface-2/50"
      >
        <div class="flex items-start gap-3">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-elevated text-brand-400"><AppIcon name="folder" :size="19" /></span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-fg">{{ project.name }}</p>
            <p class="truncate text-[13px] text-fg-muted">{{ project.client?.name ?? 'Sem cliente' }}</p>
          </div>
          <StatusBadge :tone="statusTone('project', project.status.value)">{{ project.status.label }}</StatusBadge>
        </div>
        <div class="mt-6">
          <div class="mb-1.5 flex items-center justify-between text-xs">
            <span class="text-fg-muted">Progresso</span>
            <span class="font-medium text-fg tabular-nums">{{ progress(project) }}%</span>
          </div>
          <div class="h-1.5 rounded-full bg-elevated">
            <div class="h-1.5 rounded-full bg-brand-500" :style="{ width: `${progress(project)}%` }" />
          </div>
          <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-fg-muted">
            <span class="inline-flex items-center gap-1"><AppIcon name="checkCircle" :size="13" />{{ project.done_tasks_count ?? 0 }} de {{ (project.open_tasks_count ?? 0) + (project.done_tasks_count ?? 0) }} tarefas concluídas</span>
            <span v-if="project.due_on" class="inline-flex items-center gap-1"><AppIcon name="calendar" :size="13" />prazo {{ new Date(`${project.due_on}T12:00:00`).toLocaleDateString('pt-BR') }}</span>
          </p>
        </div>
      </RouterLink>
    </div>

    <ProjectFormModal v-if="showForm" @close="showForm = false" @saved="onSaved" />
  </AppLayout>
</template>
