import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthenticatedHandler } from './services/api'
import { useAuthStore } from './stores/auth'
import './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Si el token expira, se limpia la sesión y se vuelve al login.
setUnauthenticatedHandler(() => {
  useAuthStore().clear()

  if (router.currentRoute.value.meta.requiresAuth) {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})

app.mount('#app')
