<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  /** [{ image, eyebrow, title, text }] */
  slides: { type: Array, required: true },
  interval: { type: Number, default: 6000 },
})

const index = ref(0)
const paused = ref(false)

let timer = null

const reducedMotion =
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false

const total = computed(() => props.slides.length)
const current = computed(() => props.slides[index.value])

function go(to) {
  index.value = (to + total.value) % total.value
}

function restart() {
  clearInterval(timer)

  if (reducedMotion || props.interval <= 0 || total.value < 2) return

  timer = setInterval(() => {
    if (!paused.value && !document.hidden) go(index.value + 1)
  }, props.interval)
}

onMounted(restart)
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <section
    class="relative isolate overflow-hidden rounded-2xl bg-slate-900"
    role="region"
    aria-roledescription="carrusel"
    aria-label="Presentación de servicios"
    @mouseenter="paused = true"
    @mouseleave="paused = false"
    @focusin="paused = true"
    @focusout="paused = false"
  >
    <!-- Las imágenes se funden entre sí: tienen proporciones distintas. -->
    <img
      v-for="(slide, position) in slides"
      :key="slide.image"
      :src="slide.image"
      alt=""
      class="absolute inset-0 -z-10 h-full w-full object-cover"
      :class="[
        position === index ? 'opacity-100' : 'opacity-0',
        reducedMotion ? '' : 'transition-opacity duration-700 ease-out',
      ]"
      :style="{ objectPosition: slide.focus || 'center' }"
    >

    <!-- El velo garantiza contraste del texto sobre cualquier zona de la foto. -->
    <div
      class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-slate-900/45"
      aria-hidden="true"
    ></div>

    <div class="flex min-h-[30rem] items-center px-6 py-20 sm:px-14 sm:py-24 lg:min-h-[34rem] lg:py-28">
      <div class="max-w-2xl">
        <p class="text-xs font-semibold tracking-[0.2em] text-sky-300 uppercase">
          {{ current.eyebrow }}
        </p>

        <h1
          class="mt-4 text-4xl leading-[1.03] font-extrabold tracking-tight text-balance text-white sm:text-5xl lg:text-[4.25rem]"
        >
          {{ current.title }}
        </h1>

        <p class="mt-6 max-w-xl text-base leading-relaxed text-slate-200 sm:text-lg">
          {{ current.text }}
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
          <a
            href="#servicios"
            class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
          >
            Ver nuestros servicios
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </a>

          <RouterLink
            :to="{ name: 'public.contact' }"
            class="inline-flex items-center rounded-lg border border-white/40 px-5 py-3 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
          >
            Agendar una cita
          </RouterLink>
        </div>
      </div>
    </div>

    <p class="sr-only" aria-live="polite">{{ current.title }}</p>
  </section>
</template>
