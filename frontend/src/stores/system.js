import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

/** Interruptor general de la plataforma (lo maneja una cuenta de desarrollo). */
export const useSystemStore = defineStore('system', () => {
  // Si el backend no responde se asume encendida: el error real lo muestra cada vista.
  const enabled = ref(true)
  const loaded = ref(false)
  const saving = ref(false)

  async function load() {
    if (loaded.value) return enabled.value

    try {
      const { data } = await api.get('/system')
      enabled.value = data.enabled
    } catch {
      // Se mantiene el valor por defecto.
    } finally {
      loaded.value = true
    }

    return enabled.value
  }

  async function set(value) {
    saving.value = true

    try {
      const { data } = await api.put('/system', { enabled: value })
      enabled.value = data.enabled

      return data
    } finally {
      saving.value = false
    }
  }

  /** La API respondió que está apagada: se refleja sin volver a consultar. */
  function markDisabled() {
    enabled.value = false
    loaded.value = true
  }

  return { enabled, loaded, saving, load, set, markDisabled }
})
