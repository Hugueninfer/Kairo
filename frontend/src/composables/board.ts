import type { Task, TaskStatus } from '@/types/api'

/** Tarefas de uma coluna, na ordem do quadro. */
export function columnOf(tasks: Task[], status: TaskStatus): Task[] {
  return tasks.filter((task) => task.status.value === status).sort((a, b) => a.position - b.position)
}

/**
 * Move a tarefa para a coluna e posição pedidas e renumera as duas colunas afetadas,
 * do mesmo jeito que a API faz. Usado para atualizar a tela antes da resposta chegar.
 */
export function moveTask(tasks: Task[], taskId: string, status: { value: TaskStatus; label: string }, position: number): Task[] {
  const task = tasks.find((item) => item.id === taskId)
  if (!task) return tasks

  const from = task.status.value
  const source = columnOf(tasks, from).filter((item) => item.id !== taskId)
  const target = from === status.value ? source : columnOf(tasks, status.value)
  const index = Math.max(0, Math.min(position, target.length))
  const moved = { ...task, status }
  const reordered = [...target.slice(0, index), moved, ...target.slice(index)]

  const positions = new Map<string, { position: number; status: typeof status | Task['status'] }>()
  reordered.forEach((item, i) => positions.set(item.id, { position: i, status: item.id === taskId ? status : item.status }))
  if (from !== status.value) {
    source.forEach((item, i) => positions.set(item.id, { position: i, status: item.status }))
  }

  return tasks.map((item) => {
    const next = positions.get(item.id)
    return next ? { ...item, position: next.position, status: next.status } : item
  })
}
