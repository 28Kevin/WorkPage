<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import { pendingRequests } from '@/services/loading'

/** Antes de este tiempo no se muestra: evita el parpadeo en peticiones rápidas. */
const SHOW_DELAY = 400
/** Una vez visible se mantiene un mínimo, para que no desaparezca de golpe. */
const MIN_VISIBLE = 500

const visible = ref(false)

let showTimer = null
let shownAt = 0

watch(pendingRequests, (count) => {
  clearTimeout(showTimer)

  if (count > 0) {
    if (!visible.value) {
      showTimer = setTimeout(() => {
        visible.value = true
        shownAt = Date.now()
      }, SHOW_DELAY)
    }

    return
  }

  const remaining = MIN_VISIBLE - (Date.now() - shownAt)

  showTimer = setTimeout(() => {
    visible.value = false
  }, visible.value ? Math.max(remaining, 0) : 0)
})

onBeforeUnmount(() => clearTimeout(showTimer))
</script>

<template>
  <Transition name="loader-fade">
    <!-- Bloquea la pantalla mientras se guarda: evita envíos dobles. -->
    <div
      v-if="visible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-white/55 backdrop-blur-sm"
      aria-busy="true"
    >
      <div class="rounded-3xl border border-slate-200 bg-white px-10 py-2 shadow-2xl">
        <LoadingSpinner label="Procesando…" />
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.loader-fade-enter-active,
.loader-fade-leave-active { transition: opacity 0.2s ease; }

.loader-fade-enter-from,
.loader-fade-leave-to { opacity: 0; }
</style>
