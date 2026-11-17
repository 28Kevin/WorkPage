<script setup>
import { useDragScroll } from '@/composables/useDragScroll'

defineProps({
  services: { type: Array, required: true },
})

// Rueda del mouse y arrastre para recorrer las tarjetas.
const { track, dragging, handlers } = useDragScroll()

const reducedMotion =
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false

/** Inclina la tarjeta hacia el cursor; solo con mouse, en pantallas táctiles no aplica. */
function tilt(event) {
  if (reducedMotion || event.pointerType !== 'mouse') return

  const stage = event.currentTarget
  const box = stage.getBoundingClientRect()
  const x = (event.clientX - box.left) / box.width
  const y = (event.clientY - box.top) / box.height

  stage.style.setProperty('--rx', `${(0.5 - y) * 16}deg`)
  stage.style.setProperty('--ry', `${(x - 0.5) * 16}deg`)
  stage.style.setProperty('--gx', `${x * 100}%`)
  stage.style.setProperty('--gy', `${y * 100}%`)
  stage.classList.add('is-tilting')
}

function untilt(event) {
  const stage = event.currentTarget

  stage.style.setProperty('--rx', '0deg')
  stage.style.setProperty('--ry', '0deg')
  stage.classList.remove('is-tilting')
}
</script>

<template>
  <div class="relative">
    <div
      ref="track"
      class="no-scrollbar flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 md:snap-none"
      :class="dragging ? 'cursor-grabbing select-none' : 'cursor-grab'"
      tabindex="0"
      aria-label="Servicios que ofrecemos"
      v-on="handlers"
    >
      <!-- El contenedor da la perspectiva; la tarjeta se inclina siguiendo el mouse. -->
      <div
        v-for="(service, position) in services"
        :key="service.title"
        class="tilt-stage w-[85%] shrink-0 snap-start py-4 sm:w-[calc((100%-1rem)/2)] lg:w-[calc((100%-2rem)/3)] xl:w-[calc((100%-4rem)/5)]"
        @pointermove="tilt"
        @pointerleave="untilt"
      >
        <article class="tilt-card card relative flex h-full flex-col bg-white p-6">
          <!-- Brillo que sigue al cursor, como luz sobre una superficie. -->
          <span class="tilt-glare" aria-hidden="true"></span>

          <span
            class="layer-3 flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white shadow-lg shadow-brand-500/30"
            aria-hidden="true"
          >
            {{ String(position + 1).padStart(2, '0') }}
          </span>

          <h3 class="layer-2 mt-4 text-base leading-snug font-bold text-balance text-slate-900">
            {{ service.title }}
          </h3>

          <p class="layer-1 mt-2 text-sm font-medium text-brand-800">{{ service.summary }}</p>
          <p class="layer-1 mt-3 line-clamp-5 text-sm leading-relaxed text-slate-600">{{ service.detail }}</p>

          <ul class="layer-2 mt-auto flex flex-wrap gap-1.5 pt-4">
            <li v-for="item in service.includes" :key="item" class="chip">{{ item }}</li>
          </ul>
        </article>
      </div>
    </div>

  </div>
</template>

<style scoped>
.tilt-stage {
  perspective: 1000px;
}

.tilt-card {
  transform: rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
  transform-style: preserve-3d;
  transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1), box-shadow 0.3s;
}

/* Mientras sigue al cursor responde rápido; al salir vuelve suave a su sitio. */
.is-tilting .tilt-card {
  transition: transform 0.1s linear, box-shadow 0.3s;
  box-shadow: 0 30px 60px -20px rgb(15 23 42 / 0.3);
}

/* Cada capa flota a una profundidad distinta. */
.layer-1,
.layer-2,
.layer-3 {
  transition: transform 0.5s cubic-bezier(0.2, 0.7, 0.2, 1);
}

.is-tilting .layer-1 { transform: translateZ(20px); }
.is-tilting .layer-2 { transform: translateZ(40px); }
.is-tilting .layer-3 { transform: translateZ(60px); }

.tilt-glare {
  pointer-events: none;
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: radial-gradient(circle at var(--gx, 50%) var(--gy, 50%), rgb(255 255 255 / 0.6), transparent 55%);
  opacity: 0;
  transition: opacity 0.3s;
}

.is-tilting .tilt-glare { opacity: 1; }
</style>
