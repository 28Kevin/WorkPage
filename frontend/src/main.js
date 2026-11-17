import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setSystemDisabledHandler, setUnauthenticatedHandler } from './services/api'
import { useAuthStore } from './stores/auth'
import { useBrandingStore } from './stores/branding'
import { useSystemStore } from './stores/system'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Si el token expira, se limpia la sesión y se vuelve al login.
setUnauthenticatedHandler(() => {
  useAuthStore().clear()

  if (router.currentRoute.value.meta.requiresAuth) {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath, expired: '1' } })
  }
})

// Si la plataforma se apaga mientras alguien la usa, se le muestra el aviso.
setSystemDisabledHandler(() => {
  useSystemStore().markDisabled()
  router.push({ name: 'system.unavailable' })
})

// El tema se aplica antes de montar para no mostrar los colores por defecto y
// repintarlos un instante despues.
useBrandingStore().load().finally(() => app.mount('#app'))
