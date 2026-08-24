import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api, { tokenStorage } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(tokenStorage.get())
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value && user.value))

  async function login(credentials) {
    loading.value = true

    try {
      const { data } = await api.post('/auth/login', { ...credentials, device_name: 'web' })

      tokenStorage.set(data.token)
      token.value = data.token
      user.value = data.user
      initialized.value = true

      return data.user
    } finally {
      loading.value = false
    }
  }

  /** Restaura la sesión al recargar la página. */
  async function restore() {
    if (initialized.value) return

    if (!token.value) {
      initialized.value = true
      return
    }

    try {
      const { data } = await api.get('/auth/me')
      user.value = data.user
    } catch {
      clear()
    } finally {
      initialized.value = true
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // La sesión se limpia localmente aunque el servidor no responda.
    } finally {
      clear()
    }
  }

  function clear() {
    tokenStorage.clear()
    token.value = null
    user.value = null
  }

  return { user, token, loading, initialized, isAuthenticated, login, logout, restore, clear }
})
