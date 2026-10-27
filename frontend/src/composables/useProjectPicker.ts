import { ref, watch, type Ref } from 'vue'
import { projectsApi, tasksApi } from '@/services/projects'
import type { Project, Task } from '@/types/api'

/**
 * Lista de projetos ativos e das tarefas abertas do projeto escolhido, para selects.
 */
export function useProjectPicker(projectId: Ref<string>) {
  const projects = ref<Project[]>([])
  const tasks = ref<Task[]>([])

  async function loadProjects() {
    projects.value = (await projectsApi.list({ status: 'active', per_page: 100 })).data
  }

  watch(
    projectId,
    async (id) => {
      tasks.value = id ? (await tasksApi.board(id).catch(() => [])).filter((task) => task.status.value !== 'done') : []
    },
    { immediate: true },
  )

  return { projects, tasks, loadProjects }
}
