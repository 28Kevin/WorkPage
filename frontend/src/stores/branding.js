import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'
import { applyBranding } from '@/services/theme'

const FALLBACK_NAME = import.meta.env.VITE_APP_NAME || 'Centro Médico Ocupacional'

export const useBrandingStore = defineStore('branding', () => {
  const branding = ref(null)
  const loading = ref(false)
  const saving = ref(false)

  const appName = computed(() => branding.value?.identity?.app_name || FALLBACK_NAME)
  const tagline = computed(() => branding.value?.identity?.tagline || 'Exámenes médicos ocupacionales')
  const logo = computed(() => branding.value?.identity?.logo || null)

  /** Se llama una vez al arrancar la SPA, antes de montar. */
  async function load() {
    if (branding.value || loading.value) return branding.value

    loading.value = true

    try {
      const { data } = await api.get('/branding')
      commit(data.branding)
    } catch {
      // Sin backend la SPA sigue con el tema compilado por defecto.
    } finally {
      loading.value = false
    }

    return branding.value
  }

  async function save(payload) {
    saving.value = true

    try {
      const { data } = await api.put('/branding', payload)
      commit(data.branding)

      return data.message
    } finally {
      saving.value = false
    }
  }

  /** Devuelve el tema guardado, para descartar una vista previa sin aplicar. */
  function restore() {
    applyBranding(branding.value)
  }

  function commit(value) {
    branding.value = value
    applyBranding(value)
  }

  return { branding, loading, saving, appName, tagline, logo, load, save, restore }
})
