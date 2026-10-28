<script setup lang="ts">
import { computed, ref, useId } from 'vue'

/**
 * Gráfico de área suave, em azul, como o "Graphic Display" das referências.
 * O SVG estica na largura (as linhas não engrossam por causa do non-scaling-stroke);
 * rótulos, pontos e a dica ficam em HTML por cima, posicionados em porcentagem.
 */
const props = withDefaults(
  defineProps<{
    labels: string[]
    series: { name: string; values: number[]; color?: string }[]
    format?: (value: number) => string
    /** Texto para leitores de tela (o gráfico inteiro vira uma imagem). */
    description: string
    height?: number
  }>(),
  { height: 220, format: (value: number) => String(value) },
)

const WIDTH = 600
const HEIGHT = 200
const colors = ['#2a7bff', '#7db0ff', '#a78bfa']
const hover = ref<number | null>(null)
const uid = useId()

const max = computed(() => {
  const highest = Math.max(0, ...props.series.flatMap((serie) => serie.values))
  if (highest === 0) return 1
  // Arredonda o topo para uma escala "redonda" (1, 2, 2,5 ou 5 × 10^n).
  const power = 10 ** Math.floor(Math.log10(highest))
  const step = [1, 2, 2.5, 5, 10].find((candidate) => candidate * power >= highest) ?? 10
  return step * power
})

const ticks = computed(() => [1, 0.75, 0.5, 0.25, 0].map((ratio) => ({ ratio, value: max.value * ratio })))

function x(index: number): number {
  const count = props.labels.length
  return count <= 1 ? WIDTH / 2 : (index / (count - 1)) * WIDTH
}

function y(value: number): number {
  return HEIGHT - (value / max.value) * HEIGHT
}

/** Curva Catmull-Rom convertida em Bézier: suave, sem passar do valor real nos pontos. */
function smooth(points: [number, number][]): string {
  if (points.length === 0) return ''
  if (points.length === 1) return `M0,${points[0]![1]}L${WIDTH},${points[0]![1]}`
  let path = `M${points[0]![0]},${points[0]![1]}`
  for (let index = 0; index < points.length - 1; index++) {
    const p0 = points[index - 1] ?? points[index]!
    const p1 = points[index]!
    const p2 = points[index + 1]!
    const p3 = points[index + 2] ?? p2
    const tension = 0.18
    const c1x = p1[0] + (p2[0] - p0[0]) * tension
    const c1y = Math.min(HEIGHT, Math.max(0, p1[1] + (p2[1] - p0[1]) * tension))
    const c2x = p2[0] - (p3[0] - p1[0]) * tension
    const c2y = Math.min(HEIGHT, Math.max(0, p2[1] - (p3[1] - p1[1]) * tension))
    path += ` C${c1x},${c1y} ${c2x},${c2y} ${p2[0]},${p2[1]}`
  }
  return path
}

const paths = computed(() =>
  props.series.map((serie, index) => {
    const points = serie.values.map((value, position) => [x(position), y(value)] as [number, number])
    const line = smooth(points)
    const area = points.length ? `${line} L${points.length === 1 ? WIDTH : points[points.length - 1]![0]},${HEIGHT} L${points.length === 1 ? 0 : points[0]![0]},${HEIGHT} Z` : ''
    return { name: serie.name, line, area, color: serie.color ?? colors[index % colors.length]!, id: `${uid}-area-${index}` }
  }),
)

function onMove(event: MouseEvent) {
  const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
  if (box.width === 0 || props.labels.length === 0) return
  const ratio = (event.clientX - box.left) / box.width
  hover.value = Math.min(props.labels.length - 1, Math.max(0, Math.round(ratio * (props.labels.length - 1))))
}

/** O primeiro e o último rótulo encostam na borda para não vazar do cartão. */
function labelAlign(index: number): string {
  if (props.labels.length <= 1) return '-translate-x-1/2'
  if (index === 0) return ''
  return index === props.labels.length - 1 ? '-translate-x-full' : '-translate-x-1/2'
}

const percentX = (index: number) => `${(x(index) / WIDTH) * 100}%`
const percentY = (value: number) => `${(y(value) / HEIGHT) * 100}%`
</script>

<template>
  <div class="flex gap-3" role="img" :aria-label="description">
    <!-- Escala do eixo Y -->
    <div class="relative w-14 shrink-0 text-right text-[11px] text-fg-subtle tabular-nums" :style="{ height: `${height}px` }" aria-hidden="true">
      <span v-for="tick in ticks" :key="tick.ratio" class="absolute right-0 -translate-y-1/2 truncate" :style="{ top: `${(1 - tick.ratio) * 100}%` }">
        {{ format(tick.value) }}
      </span>
    </div>

    <div class="min-w-0 flex-1">
      <div class="relative" :style="{ height: `${height}px` }" aria-hidden="true" @mousemove="onMove" @mouseleave="hover = null">
        <svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible">
          <defs>
            <linearGradient v-for="path in paths" :id="path.id" :key="path.id" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" :stop-color="path.color" stop-opacity="0.35" />
              <stop offset="100%" :stop-color="path.color" stop-opacity="0" />
            </linearGradient>
          </defs>
          <line
            v-for="tick in ticks"
            :key="tick.ratio"
            x1="0"
            :x2="WIDTH"
            :y1="(1 - tick.ratio) * HEIGHT"
            :y2="(1 - tick.ratio) * HEIGHT"
            stroke="var(--color-line)"
            stroke-dasharray="3 4"
            vector-effect="non-scaling-stroke"
          />
          <g v-for="path in paths" :key="path.id">
            <path :d="path.area" :fill="`url(#${path.id})`" />
            <path :d="path.line" fill="none" :stroke="path.color" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
          </g>
          <line
            v-if="hover !== null"
            :x1="x(hover)"
            :x2="x(hover)"
            y1="0"
            :y2="HEIGHT"
            stroke="var(--color-line-strong)"
            vector-effect="non-scaling-stroke"
          />
        </svg>

        <template v-if="hover !== null">
          <span
            v-for="(serie, index) in series"
            :key="serie.name"
            class="pointer-events-none absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-surface"
            :style="{ left: percentX(hover), top: percentY(serie.values[hover] ?? 0), background: paths[index]?.color }"
          />
          <div
            class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-[calc(100%+12px)] rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold whitespace-nowrap text-zinc-900 shadow-lg"
            :style="{ left: percentX(hover), top: percentY(Math.max(...series.map((serie) => serie.values[hover!] ?? 0))) }"
          >
            <span v-for="serie in series" :key="serie.name" class="block">
              <template v-if="series.length > 1">{{ serie.name }}: </template>{{ format(serie.values[hover] ?? 0) }}
            </span>
          </div>
        </template>
      </div>

      <div class="relative mt-2 h-4 text-[11px] text-fg-subtle" aria-hidden="true">
        <span
          v-for="(label, index) in labels"
          :key="index"
          class="absolute whitespace-nowrap"
          :class="[labelAlign(index), hover === index ? 'text-fg' : '', index % 2 === 1 && labels.length > 4 ? 'max-sm:hidden' : '']"
          :style="{ left: percentX(index) }"
          >{{ label }}</span
        >
      </div>
    </div>
  </div>
</template>
