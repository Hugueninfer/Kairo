<script setup lang="ts">
import type Konva from 'konva'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import {
  Arrow as VArrow,
  Ellipse as VEllipse,
  Group as VGroup,
  Layer as VLayer,
  Rect as VRect,
  Stage as VStage,
  Text as VText,
  Transformer as VTransformer,
} from 'vue-konva'
import { BOARD_COLORS, STROKE_COLORS, newArrow, newElement, type BoardDocument } from '@/composables/boardDocument'
import type { RemoteCursor } from '@/composables/useBoardRealtime'
import type { BoardElement, BoardElementType } from '@/types/api'

type Tool = 'select' | BoardElementType

const props = withDefaults(defineProps<{ doc: BoardDocument; editable: boolean; cursors?: RemoteCursor[] }>(), { cursors: () => [] })
const emit = defineEmits<{ change: []; pointer: [point: { x: number; y: number } | null] }>()

const FONT = 'Inter, ui-sans-serif, system-ui, sans-serif'
const tools: { value: Tool; label: string; key: string }[] = [
  { value: 'select', label: 'Selecionar', key: 'v' },
  { value: 'note', label: 'Nota', key: 'n' },
  { value: 'rect', label: 'Retângulo', key: 'r' },
  { value: 'ellipse', label: 'Elipse', key: 'e' },
  { value: 'text', label: 'Texto', key: 't' },
  { value: 'arrow', label: 'Seta', key: 'a' },
]

const container = ref<HTMLDivElement | null>(null)
const stageRef = ref<{ getNode: () => Konva.Stage } | null>(null)
const transformerRef = ref<{ getNode: () => Konva.Transformer } | null>(null)
const size = reactive({ width: 800, height: 600 })
const view = reactive({ x: 0, y: 0, scale: 1 })
const tool = ref<Tool>('select')
const selectedId = ref<string | null>(null)
const arrowFrom = ref<string | null>(null)
const editing = ref<{ id: string; text: string } | null>(null)
const editor = ref<HTMLTextAreaElement | null>(null)

const selected = computed(() => (selectedId.value ? props.doc.get(selectedId.value) : undefined))
const hint = computed(() => {
  if (tool.value === 'arrow') return arrowFrom.value ? 'Agora clique no elemento de destino.' : 'Clique no elemento de origem da seta.'
  if (tool.value !== 'select') return 'Clique no quadro para adicionar.'
  return null
})

const stageConfig = computed(() => ({
  width: size.width,
  height: size.height,
  x: view.x,
  y: view.y,
  scaleX: view.scale,
  scaleY: view.scale,
  draggable: tool.value === 'select',
}))

function stage(): Konva.Stage | undefined {
  return stageRef.value?.getNode()
}

function changed() {
  emit('change')
}

/** Posição do ponteiro em coordenadas do quadro (descontando pan e zoom). */
function pointer(): { x: number; y: number } {
  const position = stage()?.getPointerPosition() ?? { x: size.width / 2, y: size.height / 2 }
  return { x: (position.x - view.x) / view.scale, y: (position.y - view.y) / view.scale }
}

function select(id: string | null) {
  selectedId.value = id
  arrowFrom.value = null
}

function onStageClick(event: Konva.KonvaEventObject<MouseEvent>) {
  if (event.target !== stage()) return
  if (!props.editable || tool.value === 'select' || tool.value === 'arrow') {
    select(null)
    return
  }
  const point = pointer()
  const element = newElement(tool.value, point.x, point.y, props.doc.topZ.value + 1)
  props.doc.upsert(element)
  changed()
  tool.value = 'select'
  selectedId.value = element.id
  if (element.type === 'note' || element.type === 'text') void startEdit(element)
}

function onElementClick(element: BoardElement) {
  if (props.editable && tool.value === 'arrow') {
    if (!arrowFrom.value) {
      arrowFrom.value = element.id
      return
    }
    const from = props.doc.get(arrowFrom.value)
    if (from && from.id !== element.id) {
      const arrow = newArrow(from, element, props.doc.topZ.value + 1)
      props.doc.upsert(arrow)
      changed()
      selectedId.value = arrow.id
    }
    arrowFrom.value = null
    tool.value = 'select'
    return
  }
  select(element.id)
}

function onDragMove(element: BoardElement, event: Konva.KonvaEventObject<DragEvent>) {
  props.doc.update(element.id, { x: Math.round(event.target.x()), y: Math.round(event.target.y()) })
}

function onDragEnd(element: BoardElement, event: Konva.KonvaEventObject<DragEvent>) {
  onDragMove(element, event)
  changed()
}

/** O Transformer estica o grupo com escala; aqui a escala vira largura e altura de verdade. */
function onTransformEnd(element: BoardElement, event: Konva.KonvaEventObject<Event>) {
  const node = event.target
  const width = Math.max(30, Math.round(element.width * node.scaleX()))
  const height = Math.max(20, Math.round(element.height * node.scaleY()))
  node.scale({ x: 1, y: 1 })
  props.doc.update(element.id, { x: Math.round(node.x()), y: Math.round(node.y()), width, height, rotation: Math.round(node.rotation()) })
  changed()
}

/** Cursores de quem está no board, na posição da tela (acompanhando pan e zoom). */
const visibleCursors = computed(() =>
  props.cursors
    .map((cursor) => ({ ...cursor, left: cursor.x * view.scale + view.x, top: cursor.y * view.scale + view.y }))
    .filter((cursor) => cursor.left >= -20 && cursor.top >= -20 && cursor.left <= size.width && cursor.top <= size.height),
)

function onStageDragEnd(event: Konva.KonvaEventObject<DragEvent>) {
  if (event.target !== stage()) return
  view.x = event.target.x()
  view.y = event.target.y()
}

function onWheel(event: Konva.KonvaEventObject<WheelEvent>) {
  event.evt.preventDefault()
  const factor = event.evt.deltaY > 0 ? 1 / 1.1 : 1.1
  zoomAt(stage()?.getPointerPosition() ?? { x: size.width / 2, y: size.height / 2 }, factor)
}

function zoomAt(point: { x: number; y: number }, factor: number) {
  const scale = Math.min(4, Math.max(0.2, view.scale * factor))
  const world = { x: (point.x - view.x) / view.scale, y: (point.y - view.y) / view.scale }
  view.scale = scale
  view.x = point.x - world.x * scale
  view.y = point.y - world.y * scale
}

function zoomBy(factor: number) {
  zoomAt({ x: size.width / 2, y: size.height / 2 }, factor)
}

/** Enquadra todos os elementos na tela. */
function fit() {
  const boxes = props.doc.ordered.value.filter((element) => element.type !== 'arrow')
  if (boxes.length === 0) {
    Object.assign(view, { x: size.width / 2, y: size.height / 2, scale: 1 })
    return
  }
  const minX = Math.min(...boxes.map((element) => element.x))
  const minY = Math.min(...boxes.map((element) => element.y))
  const maxX = Math.max(...boxes.map((element) => element.x + element.width))
  const maxY = Math.max(...boxes.map((element) => element.y + element.height))
  const padding = 60
  const scale = Math.min(1.5, Math.max(0.2, Math.min((size.width - padding * 2) / (maxX - minX || 1), (size.height - padding * 2) / (maxY - minY || 1))))
  view.scale = scale
  view.x = (size.width - (maxX - minX) * scale) / 2 - minX * scale
  view.y = (size.height - (maxY - minY) * scale) / 2 - minY * scale
}

async function startEdit(element: BoardElement) {
  if (!props.editable || element.type === 'arrow') return
  editing.value = { id: element.id, text: element.props.text ?? '' }
  await nextTick()
  editor.value?.focus()
  editor.value?.select()
}

function finishEdit() {
  if (!editing.value) return
  const { id, text } = editing.value
  editing.value = null
  const element = props.doc.get(id)
  if (element && element.props.text !== text) {
    props.doc.update(id, { props: { text } })
    changed()
  }
}

const editorStyle = computed(() => {
  const element = editing.value ? props.doc.get(editing.value.id) : undefined
  if (!element) return {}
  const fontSize = (element.type === 'text' ? (element.props.font_size ?? 24) : element.type === 'note' ? 18 : 16) * view.scale
  return {
    left: `${element.x * view.scale + view.x}px`,
    top: `${element.y * view.scale + view.y}px`,
    width: `${element.width * view.scale}px`,
    height: `${Math.max(element.height, 40) * view.scale}px`,
    fontSize: `${fontSize}px`,
    padding: `${(element.type === 'text' ? 0 : 14) * view.scale}px`,
    textAlign: (element.type === 'rect' || element.type === 'ellipse' ? 'center' : 'left') as 'center' | 'left',
  }
})

function setColor(color: string) {
  const element = selected.value
  if (!element) return
  const key = element.type === 'arrow' ? 'stroke' : 'fill'
  props.doc.update(element.id, { props: { [key]: color } })
  changed()
}

function bringToFront() {
  if (!selected.value) return
  props.doc.bringToFront(selected.value.id)
  changed()
}

function removeSelected() {
  if (!selected.value) return
  props.doc.remove(selected.value.id)
  selectedId.value = null
  changed()
}

function onKeydown(event: KeyboardEvent) {
  const target = event.target as HTMLElement | null
  if (editing.value || target?.closest('input, textarea, select, [contenteditable]')) return
  if (event.key === 'Escape') {
    select(null)
    tool.value = 'select'
    return
  }
  if (!props.editable) return
  if ((event.key === 'Delete' || event.key === 'Backspace') && selected.value) {
    event.preventDefault()
    removeSelected()
    return
  }
  const shortcut = tools.find((item) => item.key === event.key.toLowerCase())
  if (shortcut && !event.ctrlKey && !event.metaKey && !event.altKey) {
    tool.value = shortcut.value
    arrowFrom.value = null
  }
}

/** Liga o Transformer ao elemento selecionado (setas não têm alças). */
watch([selectedId, () => props.doc.elements.size], async () => {
  await nextTick()
  const transformer = transformerRef.value?.getNode()
  if (!transformer) return
  const element = selected.value
  const node = element && element.type !== 'arrow' && props.editable ? stage()?.findOne(`#${element.id}`) : undefined
  transformer.nodes(node ? [node] : [])
})

const elementColor = (element: BoardElement) => element.props.fill ?? '#FEF08A'

function groupConfig(element: BoardElement) {
  return { id: element.id, x: element.x, y: element.y, rotation: element.rotation, draggable: props.editable && tool.value === 'select' }
}

function textConfig(element: BoardElement, centered: boolean) {
  return {
    text: editing.value?.id === element.id ? '' : (element.props.text ?? ''),
    width: element.width,
    height: element.height,
    padding: element.type === 'note' ? 14 : 10,
    fontSize: element.type === 'note' ? 18 : 16,
    fontFamily: FONT,
    fill: '#1E293B',
    align: centered ? 'center' : 'left',
    verticalAlign: centered ? 'middle' : 'top',
    wrap: 'word',
    ellipsis: true,
  }
}

function arrowConfig(element: BoardElement) {
  const color = element.props.stroke ?? '#334155'
  return {
    id: element.id,
    points: element.props.points ?? [0, 0, 0, 0],
    stroke: color,
    fill: color,
    strokeWidth: selectedId.value === element.id ? 4 : 2.5,
    pointerLength: 12,
    pointerWidth: 12,
    hitStrokeWidth: 16,
    dash: selectedId.value === element.id ? [10, 4] : undefined,
  }
}

let observer: ResizeObserver | undefined
onMounted(() => {
  const resize = () => {
    if (!container.value) return
    size.width = container.value.clientWidth
    size.height = container.value.clientHeight
  }
  resize()
  observer = new ResizeObserver(resize)
  if (container.value) observer.observe(container.value)
  fit()
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  window.removeEventListener('keydown', onKeydown)
})

defineExpose({ fit })
</script>

<template>
  <div class="flex h-full flex-col gap-2">
    <div class="flex flex-wrap items-center gap-2 text-sm">
      <div v-if="editable" class="flex overflow-hidden rounded-lg border border-slate-300 bg-white" role="toolbar" aria-label="Ferramentas">
        <button
          v-for="item in tools"
          :key="item.value"
          type="button"
          class="border-r border-slate-200 px-3 py-1.5 last:border-r-0 hover:bg-slate-50"
          :class="tool === item.value ? 'bg-brand-50 font-semibold text-brand-700' : ''"
          :title="`${item.label} (${item.key.toUpperCase()})`"
          :aria-pressed="tool === item.value"
          :data-tool="item.value"
          @click="tool = item.value; arrowFrom = null"
        >
          {{ item.label }}
        </button>
      </div>

      <div v-if="editable && selected" class="flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1" data-test="selection-tools">
        <button
          v-for="color in selected.type === 'arrow' || selected.type === 'text' ? STROKE_COLORS : BOARD_COLORS"
          :key="color"
          type="button"
          class="h-5 w-5 rounded-full ring-1 ring-slate-300"
          :style="{ background: color }"
          :aria-label="`Cor ${color}`"
          @click="setColor(color)"
        />
        <button type="button" class="ml-2 px-1 text-slate-600 hover:text-brand-600" @click="bringToFront">Trazer para frente</button>
        <button type="button" class="px-1 text-red-600 hover:underline" data-test="delete-element" @click="removeSelected">Apagar</button>
      </div>

      <span v-if="hint" class="text-slate-500" data-test="hint">{{ hint }}</span>

      <div class="ml-auto flex items-center overflow-hidden rounded-lg border border-slate-300 bg-white">
        <button type="button" class="px-3 py-1.5 hover:bg-slate-50" aria-label="Diminuir zoom" @click="zoomBy(1 / 1.2)">−</button>
        <span class="w-14 text-center tabular-nums" data-test="zoom">{{ Math.round(view.scale * 100) }}%</span>
        <button type="button" class="px-3 py-1.5 hover:bg-slate-50" aria-label="Aumentar zoom" @click="zoomBy(1.2)">+</button>
        <button type="button" class="border-l border-slate-200 px-3 py-1.5 hover:bg-slate-50" @click="fit">Ajustar</button>
      </div>
    </div>

    <div
      ref="container"
      class="relative min-h-0 flex-1 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
      :class="tool === 'select' ? 'cursor-default' : 'cursor-crosshair'"
      style="background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 24px 24px"
      data-test="board-canvas"
      @mouseleave="emit('pointer', null)"
    >
      <VStage
        ref="stageRef"
        :config="stageConfig"
        @click="onStageClick"
        @tap="onStageClick"
        @wheel="onWheel"
        @dragend="onStageDragEnd"
        @mousemove="emit('pointer', pointer())"
      >
        <VLayer>
          <template v-for="element in doc.ordered.value" :key="element.id">
            <VArrow v-if="element.type === 'arrow'" :config="arrowConfig(element)" @click="select(element.id)" @tap="select(element.id)" />
            <VGroup
              v-else
              :config="groupConfig(element)"
              @click="onElementClick(element)"
              @tap="onElementClick(element)"
              @dblclick="startEdit(element)"
              @dbltap="startEdit(element)"
              @dragstart="select(element.id)"
              @dragmove="onDragMove(element, $event)"
              @dragend="onDragEnd(element, $event)"
              @transformend="onTransformEnd(element, $event)"
            >
              <template v-if="element.type === 'note'">
                <VRect
                  :config="{
                    width: element.width,
                    height: element.height,
                    fill: elementColor(element),
                    cornerRadius: 4,
                    shadowColor: '#0f172a',
                    shadowOpacity: 0.18,
                    shadowBlur: 10,
                    shadowOffsetY: 3,
                    stroke: arrowFrom === element.id ? '#2563EB' : undefined,
                    strokeWidth: 3,
                  }"
                />
                <VText :config="textConfig(element, false)" />
              </template>
              <template v-else-if="element.type === 'rect'">
                <VRect
                  :config="{
                    width: element.width,
                    height: element.height,
                    fill: elementColor(element),
                    stroke: arrowFrom === element.id ? '#2563EB' : (element.props.stroke ?? '#1D4ED8'),
                    strokeWidth: arrowFrom === element.id ? 4 : 2,
                    cornerRadius: 8,
                  }"
                />
                <VText :config="textConfig(element, true)" />
              </template>
              <template v-else-if="element.type === 'ellipse'">
                <VEllipse
                  :config="{
                    x: element.width / 2,
                    y: element.height / 2,
                    radiusX: element.width / 2,
                    radiusY: element.height / 2,
                    fill: elementColor(element),
                    stroke: arrowFrom === element.id ? '#2563EB' : (element.props.stroke ?? '#15803D'),
                    strokeWidth: arrowFrom === element.id ? 4 : 2,
                  }"
                />
                <VText :config="textConfig(element, true)" />
              </template>
              <VText
                v-else
                :config="{
                  text: editing?.id === element.id ? '' : (element.props.text ?? ''),
                  width: element.width,
                  fontSize: element.props.font_size ?? 24,
                  fontFamily: FONT,
                  fontStyle: '600',
                  fill: element.props.fill ?? '#0F172A',
                  wrap: 'word',
                }"
              />
            </VGroup>
          </template>
          <VTransformer ref="transformerRef" :config="{ rotateEnabled: true, ignoreStroke: true, anchorSize: 9, borderStroke: '#2563EB', anchorStroke: '#2563EB' }" />
        </VLayer>
      </VStage>

      <textarea
        v-if="editing"
        ref="editor"
        v-model="editing.text"
        class="absolute resize-none border-0 bg-transparent leading-tight text-slate-800 outline-none ring-2 ring-brand-400"
        :style="editorStyle"
        aria-label="Texto do elemento"
        data-test="element-editor"
        @blur="finishEdit"
        @keydown.escape.prevent="finishEdit"
      />

      <div
        v-for="cursor in visibleCursors"
        :key="cursor.id"
        class="pointer-events-none absolute left-0 top-0 transition-transform duration-75 ease-linear"
        :style="{ transform: `translate(${cursor.left}px, ${cursor.top}px)` }"
        data-test="remote-cursor"
      >
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
          <path d="M1 1l6.5 15 2.2-6.3L16 7.5z" :fill="cursor.color" stroke="white" stroke-width="1.2" stroke-linejoin="round" />
        </svg>
        <span class="ml-3 -mt-1 inline-block whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium text-white" :style="{ background: cursor.color }">
          {{ cursor.name }}
        </span>
      </div>

      <p v-if="doc.elements.size === 0 && editable" class="pointer-events-none absolute inset-x-0 top-1/3 text-center text-sm text-slate-400">
        Board vazio. Escolha Nota, Retângulo ou Texto na barra e clique aqui para começar.
      </p>
    </div>
  </div>
</template>
