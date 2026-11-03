<script setup>
import { ref } from 'vue'
import AlertMessage from '@/components/AlertMessage.vue'
import { parseApiError } from '@/services/api'
import { useSystemStore } from '@/stores/system'

const system = useSystemStore()

const result = ref(null)
const error = ref(null)

async function toggle() {
  const next = !system.enabled

  if (!next && !window.confirm('¿Apagar la plataforma? Nadie, salvo las cuentas de desarrollo, podrá usarla.')) {
    return
  }

  result.value = null
  error.value = null

  try {
    result.value = (await system.set(next)).message
  } catch (err) {
    error.value = parseApiError(err).message
  }
}
</script>

<template>
  <section class="card">
    <div class="section-heading">
      <span class="section-badge">9</span>
      <h2 class="text-sm font-semibold text-slate-900">Estado de la plataforma</h2>
    </div>

    <div class="space-y-4 p-5">
      <p class="text-sm leading-relaxed text-slate-600">
        Apagada, todas las páginas muestran "Servicio no disponible, comuníquese con el desarrollador".
        Las cuentas de desarrollo siguen entrando por
        <code class="text-xs">/admin/login</code> para volver a encenderla.
      </p>

      <div class="flex flex-wrap items-center gap-3">
        <span
          class="rounded-full px-3 py-1 text-xs font-semibold"
          :class="system.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
        >
          {{ system.enabled ? 'Encendida' : 'Apagada' }}
        </span>

        <button
          type="button"
          :class="system.enabled ? 'btn-secondary' : 'btn-primary'"
          :disabled="system.saving"
          @click="toggle"
        >
          {{ system.saving ? 'Guardando…' : system.enabled ? 'Apagar plataforma' : 'Encender plataforma' }}
        </button>
      </div>

      <AlertMessage v-if="result" variant="success">{{ result }}</AlertMessage>
      <AlertMessage v-if="error" variant="error">{{ error }}</AlertMessage>
    </div>
  </section>
</template>
