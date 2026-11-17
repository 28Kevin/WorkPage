<script setup>
/*
 * Mapa del mundo hecho de puntos, con rutas animadas que salen de Colombia.
 * Los continentes son elipses aproximadas que recortan una trama de puntos:
 * así no hace falta cargar un mapa real y el dibujo pesa muy poco.
 */

// Coordenadas en un lienzo de 800 × 400 (proyección equirectangular aproximada).
const LAND = [
  { cx: 165, cy: 120, rx: 95, ry: 55 }, // Norteamérica
  { cx: 225, cy: 70, rx: 80, ry: 30 }, // Canadá
  { cx: 305, cy: 45, rx: 30, ry: 20 }, // Groenlandia
  { cx: 205, cy: 190, rx: 25, ry: 20 }, // Centroamérica
  { cx: 255, cy: 265, rx: 50, ry: 70 }, // Suramérica
  { cx: 420, cy: 100, rx: 42, ry: 32 }, // Europa
  { cx: 430, cy: 225, rx: 62, ry: 68 }, // África
  { cx: 580, cy: 110, rx: 140, ry: 55 }, // Asia
  { cx: 600, cy: 185, rx: 50, ry: 28 }, // Sudeste asiático
  { cx: 675, cy: 285, rx: 48, ry: 30 }, // Australia
]

const ORIGIN = { x: 232, y: 212 } // Colombia

const DESTINATIONS = [
  { x: 205, y: 150 }, // Miami
  { x: 150, y: 170 }, // Ciudad de México
  { x: 395, y: 115 }, // Madrid
  { x: 285, y: 300 }, // São Paulo
]

/** Curva hacia arriba entre el origen y cada destino. */
const ROUTES = DESTINATIONS.map((to) => {
  const midX = (ORIGIN.x + to.x) / 2
  const midY = Math.min(ORIGIN.y, to.y) - 40

  return { to, d: `M${ORIGIN.x},${ORIGIN.y} Q${midX},${midY} ${to.x},${to.y}` }
})

const reducedMotion =
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false
</script>

<template>
  <svg viewBox="0 0 800 400" class="h-auto w-full" aria-hidden="true">
    <defs>
      <pattern id="dotmap-dots" width="8" height="8" patternUnits="userSpaceOnUse">
        <circle cx="4" cy="4" r="1.6" class="fill-brand-300" />
      </pattern>
      <mask id="dotmap-land">
        <ellipse v-for="(shape, index) in LAND" :key="index" v-bind="shape" fill="white" />
      </mask>
      <linearGradient id="dotmap-route" x1="0" x2="1">
        <stop offset="0" stop-color="var(--color-brand-500)" />
        <stop offset="1" stop-color="var(--color-accent-500)" />
      </linearGradient>
    </defs>

    <rect width="800" height="400" fill="url(#dotmap-dots)" mask="url(#dotmap-land)" />

    <g v-for="(route, index) in ROUTES" :key="index">
      <path
        :d="route.d"
        fill="none"
        stroke="url(#dotmap-route)"
        stroke-width="2"
        stroke-linecap="round"
        class="route"
        :style="{ animationDelay: `${index * 0.6}s` }"
        pathLength="1"
      />
      <circle :cx="route.to.x" :cy="route.to.y" r="4" class="fill-accent-500" />

      <!-- Punto que viaja por la ruta. -->
      <circle v-if="!reducedMotion" r="3.5" class="fill-brand-600">
        <animateMotion :dur="`${3 + index * 0.5}s`" :begin="`${index * 0.6}s`" repeatCount="indefinite" :path="route.d" />
      </circle>
    </g>

    <circle :cx="ORIGIN.x" :cy="ORIGIN.y" r="6" class="fill-brand-600" />
    <circle v-if="!reducedMotion" :cx="ORIGIN.x" :cy="ORIGIN.y" r="6" class="pulse fill-brand-600" />
  </svg>
</template>

<style scoped>
.route {
  stroke-dasharray: 1;
  stroke-dashoffset: 1;
  animation: draw 2.4s ease-out forwards;
}

@keyframes draw {
  to { stroke-dashoffset: 0; }
}

.pulse {
  transform-box: fill-box;
  transform-origin: center;
  animation: pulse 2s ease-out infinite;
}

@keyframes pulse {
  to { transform: scale(3.5); opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
  .route { animation: none; stroke-dashoffset: 0; }
}
</style>
