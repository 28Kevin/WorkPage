<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Pizarra para que el trabajador firme con el dedo, el mouse o un lápiz óptico.
 * El valor es un PNG con fondo transparente listo para estamparlo en el PDF.
 */
const props = defineProps({
  modelValue: { type: String, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

/** Resolución del trazo: el PDF la imprime a unos 5 cm de ancho. */
const CANVAS_WIDTH = 720
const CANVAS_HEIGHT = 220
const LINE_WIDTH = 3.2
const INK = '#0f172a'

const canvas = ref(null)
const hasStrokes = ref(false)

let context = null
let drawing = false
let lastPoint = null

onMounted(() => {
  context = canvas.value.getContext('2d')

  context.lineWidth = LINE_WIDTH
  context.lineCap = 'round'
  context.lineJoin = 'round'
  context.strokeStyle = INK

  // Al corregir un examen la firma ya guardada vuelve a la pizarra.
  if (props.modelValue) restore(props.modelValue)
})

onBeforeUnmount(stopDrawing)

// Una firma que llega después de montar (el examen se carga por AJAX) se pinta.
watch(
  () => props.modelValue,
  (value) => {
    if (!context || drawing) return

    if (!value) {
      wipe()
    } else if (!hasStrokes.value) {
      restore(value)
    }
  },
)

function restore(dataUrl) {
  const image = new Image()

  image.onload = () => {
    wipe()
    // La firma guardada viene recortada: se centra sin deformarla.
    const scale = Math.min(CANVAS_WIDTH / image.width, CANVAS_HEIGHT / image.height, 1)
    const width = image.width * scale
    const height = image.height * scale

    context.drawImage(image, (CANVAS_WIDTH - width) / 2, (CANVAS_HEIGHT - height) / 2, width, height)
    hasStrokes.value = true
  }

  image.src = dataUrl
}

/** Coordenadas del puntero dentro del canvas, ya escaladas a su resolución. */
function pointFrom(event) {
  const bounds = canvas.value.getBoundingClientRect()

  return {
    x: ((event.clientX - bounds.left) / bounds.width) * CANVAS_WIDTH,
    y: ((event.clientY - bounds.top) / bounds.height) * CANVAS_HEIGHT,
  }
}

function startDrawing(event) {
  if (props.disabled) return

  // Captura el puntero: el trazo sigue aunque el dedo salga del recuadro.
  canvas.value.setPointerCapture(event.pointerId)

  drawing = true
  lastPoint = pointFrom(event)

  // Un toque suelto también deja marca: un punto es una firma válida.
  context.beginPath()
  context.arc(lastPoint.x, lastPoint.y, LINE_WIDTH / 2, 0, Math.PI * 2)
  context.fillStyle = INK
  context.fill()

  hasStrokes.value = true
}

function draw(event) {
  if (!drawing) return

  const point = pointFrom(event)

  context.beginPath()
  context.moveTo(lastPoint.x, lastPoint.y)
  context.lineTo(point.x, point.y)
  context.stroke()

  lastPoint = point
}

function stopDrawing() {
  if (!drawing) return

  drawing = false
  lastPoint = null

  emit('update:modelValue', exportTrimmed())
}

function wipe() {
  context.clearRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT)
  hasStrokes.value = false
}

function clear() {
  wipe()
  emit('update:modelValue', null)
}

/**
 * Devuelve solo el rectángulo que ocupa el trazo. Sin recortar, el margen
 * vacío del canvas encogería la firma al escalarla dentro del certificado.
 */
function exportTrimmed() {
  const { data } = context.getImageData(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT)

  let top = CANVAS_HEIGHT
  let left = CANVAS_WIDTH
  let right = -1
  let bottom = -1

  for (let y = 0; y < CANVAS_HEIGHT; y += 1) {
    for (let x = 0; x < CANVAS_WIDTH; x += 1) {
      // Canal alfa: todo lo que no sea transparente es tinta.
      if (data[(y * CANVAS_WIDTH + x) * 4 + 3] === 0) continue

      if (y < top) top = y
      if (y > bottom) bottom = y
      if (x < left) left = x
      if (x > right) right = x
    }
  }

  if (bottom < 0) return null

  const padding = 8
  left = Math.max(0, left - padding)
  top = Math.max(0, top - padding)
  right = Math.min(CANVAS_WIDTH - 1, right + padding)
  bottom = Math.min(CANVAS_HEIGHT - 1, bottom + padding)

  const trimmed = document.createElement('canvas')

  trimmed.width = right - left + 1
  trimmed.height = bottom - top + 1
  trimmed.getContext('2d').drawImage(
    canvas.value,
    left, top, trimmed.width, trimmed.height,
    0, 0, trimmed.width, trimmed.height,
  )

  return trimmed.toDataURL('image/png')
}
</script>

<template>
  <div>
    <div
      class="relative overflow-hidden rounded-lg border border-slate-300 bg-slate-50"
      :class="{ 'opacity-60': disabled }"
    >
      <canvas
        ref="canvas"
        :width="CANVAS_WIDTH"
        :height="CANVAS_HEIGHT"
        class="block h-40 w-full touch-none"
        :class="disabled ? 'cursor-not-allowed' : 'cursor-crosshair'"
        @pointerdown="startDrawing"
        @pointermove="draw"
        @pointerup="stopDrawing"
        @pointercancel="stopDrawing"
      ></canvas>

      <!-- Renglón guía y marca de agua: solo mientras la pizarra está vacía. -->
      <div v-if="!hasStrokes" class="pointer-events-none absolute inset-x-8 bottom-9 border-b border-dashed border-slate-300"></div>
      <p v-if="!hasStrokes" class="pointer-events-none absolute inset-x-0 bottom-3 text-center text-xs text-slate-400">
        Firme aquí con el mouse, el dedo o un lápiz óptico
      </p>
    </div>

    <div class="mt-2 flex items-center gap-3">
      <button type="button" class="btn-ghost text-xs" :disabled="disabled || !hasStrokes" @click="clear">
        Limpiar firma
      </button>
      <span v-if="hasStrokes" class="text-xs text-emerald-700">Firma capturada</span>
    </div>
  </div>
</template>
