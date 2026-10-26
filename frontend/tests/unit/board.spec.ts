import { describe, expect, it } from 'vitest'
import { columnOf, moveTask } from '@/composables/board'
import type { Task, TaskStatus } from '@/types/api'

const labels: Record<TaskStatus, string> = { todo: 'A fazer', in_progress: 'Em andamento', review: 'Em revisão', done: 'Concluída' }

function task(id: string, status: TaskStatus, position: number): Task {
  return {
    id,
    title: id,
    description: null,
    status: { value: status, label: labels[status] },
    priority: { value: 'medium', label: 'Média' },
    position,
    estimate_minutes: null,
    due_on: null,
    completed_at: null,
  }
}

const titles = (tasks: Task[], status: TaskStatus) => columnOf(tasks, status).map((item) => `${item.id}${item.position}`)

describe('moveTask', () => {
  const board = [task('A', 'todo', 0), task('B', 'todo', 1), task('C', 'todo', 2), task('X', 'done', 0)]

  it('move entre colunas e renumera as duas', () => {
    const result = moveTask(board, 'B', { value: 'done', label: 'Concluída' }, 0)

    expect(titles(result, 'todo')).toEqual(['A0', 'C1'])
    expect(titles(result, 'done')).toEqual(['B0', 'X1'])
  })

  it('reordena na mesma coluna', () => {
    const result = moveTask(board, 'C', { value: 'todo', label: 'A fazer' }, 0)

    expect(titles(result, 'todo')).toEqual(['C0', 'A1', 'B2'])
  })

  it('posição além do fim vira a última', () => {
    const result = moveTask(board, 'A', { value: 'done', label: 'Concluída' }, 50)

    expect(titles(result, 'done')).toEqual(['X0', 'A1'])
  })

  it('não muda nada para tarefa desconhecida', () => {
    expect(moveTask(board, 'Z', { value: 'done', label: 'Concluída' }, 0)).toBe(board)
  })
})
