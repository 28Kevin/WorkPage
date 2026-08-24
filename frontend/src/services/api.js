import axios from 'axios'

const TOKEN_KEY = 'emo.token'

export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

// Se registra desde main.js para poder redirigir al login sin importar el router aquí.
let onUnauthenticated = () => {}

export function setUnauthenticatedHandler(handler) {
  onUnauthenticated = handler
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status

    // Las rutas públicas devuelven 404 legítimos; solo el 401 cierra la sesión.
    if (status === 401 && tokenStorage.get()) {
      tokenStorage.clear()
      onUnauthenticated()
    }

    return Promise.reject(error)
  },
)

/** Normaliza los errores de la API a un formato único para los formularios. */
export function parseApiError(error) {
  const response = error.response

  if (!response) {
    return {
      message: 'No fue posible conectar con el servidor. Verifique que el backend esté disponible.',
      errors: {},
    }
  }

  if (response.status === 422) {
    return {
      message: response.data?.message || 'Revise los datos ingresados.',
      errors: response.data?.errors || {},
    }
  }

  return {
    message: response.data?.message || `Error ${response.status} al procesar la solicitud.`,
    errors: {},
  }
}

export default api
