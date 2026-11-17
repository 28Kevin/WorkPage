import { onBeforeUnmount, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useCatalogStore } from '@/stores/catalogs'

/** Debe coincidir con SANCTUM_IDLE_MINUTES del backend. */
const IDLE_MINUTES = Number(import.meta.env.VITE_IDLE_MINUTES || 15)
const IDLE_MS = IDLE_MINUTES * 60 * 1000

/** Cada cuánto, como mucho, se avisa al servidor de que el usuario sigue trabajando. */
const KEEPALIVE_MS = 4 * 60 * 1000

// Compartido entre pestañas: trabajar en una mantiene viva la sesión de todas.
const ACTIVITY_KEY = 'emo.last-activity'

const EVENTS = ['pointerdown', 'keydown', 'scroll', 'touchstart']

function readActivity() {
  try {
    return Number(localStorage.getItem(ACTIVITY_KEY)) || Date.now()
  } catch {
    return Date.now()
  }
}

function writeActivity(time) {
  try {
    localStorage.setItem(ACTIVITY_KEY, String(time))
  } catch {
    // Sin almacenamiento la cuenta sigue en memoria de esta pestaña.
  }
}

/**
 * Cierra la sesión tras unos minutos sin actividad. El servidor también
 * invalida el token por su cuenta; esto solo evita que el usuario se entere al
 * guardar un formulario. Mientras hay actividad, se toca el servidor de vez en
 * cuando para que el token no caduque aunque no se hagan peticiones.
 */
export function useIdleSession() {
  const auth = useAuthStore()
  const catalogs = useCatalogStore()
  const router = useRouter()

  let lastActivity = Date.now()
  let lastKeepalive = Date.now()
  let timer = null

  function markActivity() {
    const now = Date.now()

    // Basta con registrarla cada pocos segundos.
    if (now - lastActivity < 5000) return

    lastActivity = now
    writeActivity(now)
  }

  async function check() {
    if (!auth.isAuthenticated) return

    const now = Date.now()
    lastActivity = Math.max(lastActivity, readActivity())

    if (now - lastActivity >= IDLE_MS) {
      await auth.logout()
      catalogs.reset()
      router.push({ name: 'login', query: { expired: '1' } })
      return
    }

    if (lastActivity > lastKeepalive && now - lastKeepalive >= KEEPALIVE_MS) {
      lastKeepalive = now

      try {
        await api.get('/auth/me')
      } catch {
        // Un 401 ya lo maneja el interceptor de la API.
      }
    }
  }

  function start() {
    lastActivity = Date.now()
    lastKeepalive = Date.now()
    writeActivity(lastActivity)

    clearInterval(timer)
    timer = setInterval(check, 15 * 1000)
  }

  function stop() {
    clearInterval(timer)
    timer = null
  }

  onMounted(() => {
    EVENTS.forEach((name) => window.addEventListener(name, markActivity, { passive: true }))
    // Al volver a la pestaña tras un rato, se revisa de inmediato.
    document.addEventListener('visibilitychange', check)
  })

  onBeforeUnmount(() => {
    stop()
    EVENTS.forEach((name) => window.removeEventListener(name, markActivity))
    document.removeEventListener('visibilitychange', check)
  })

  watch(
    () => auth.isAuthenticated,
    (authenticated) => (authenticated ? start() : stop()),
    { immediate: true },
  )
}
