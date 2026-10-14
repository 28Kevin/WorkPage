<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Módulos del panel en un solo desplegable. Como enlaces sueltos ya no cabían
 * en la barra y se salían del encabezado.
 *
 * 'prefix' decide cuál queda marcado como actual: gana el más largo que
 * coincida, así '/admin' (exámenes) no se marca estando en '/admin/alimentos'.
 * 'visible' filtra por permisos; el servidor los vuelve a comprobar.
 */
const MODULES = [
  { name: 'admin.exams', prefix: '/admin', label: 'Exámenes ocupacionales' },
  { name: 'admin.food', prefix: '/admin/alimentos', label: 'Manipulación de alimentos' },
  { name: 'admin.certificates', prefix: '/admin/certificados', label: 'Certificados de capacitación' },
  { name: 'admin.billing', prefix: '/admin/cuentas-de-cobro', label: 'Cuentas de cobro' },
  { name: 'admin.messages', prefix: '/admin/mensajes', label: 'Mensajes de contacto' },
  { name: 'admin.branding', prefix: '/admin/configuracion', label: 'Configuración' },
  {
    name: 'admin.usage',
    prefix: '/admin/uso',
    label: 'Uso de la plataforma',
    visible: (auth) => auth.isDeveloper,
  },
]

const emit = defineEmits(['logout'])

const auth = useAuthStore()
const route = useRoute()

const open = ref(false)
const root = ref(null)

const modules = computed(() => MODULES.filter((item) => !item.visible || item.visible(auth)))

/** Módulo actual: el de prefijo más largo que coincida con la ruta. */
const current = computed(() =>
  modules.value
    .filter((item) => route.path === item.prefix || route.path.startsWith(`${item.prefix}/`))
    .sort((a, b) => b.prefix.length - a.prefix.length)[0] ?? null,
)

function toggle() {
  open.value = !open.value

  if (open.value) {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
  } else {
    detach()
  }
}

function close() {
  open.value = false
  detach()
}

function detach() {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
}

function onDocumentClick(event) {
  if (!root.value?.contains(event.target)) close()
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
}

// Al navegar el menú se cierra solo.
watch(() => route.fullPath, close)

onBeforeUnmount(detach)
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white"
      :class="current ? 'bg-white/15 text-white' : ''"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="toggle"
    >
      {{ current?.label ?? 'Módulos' }}

      <svg
        class="h-4 w-4 transition-transform"
        :class="open ? 'rotate-180' : ''"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="m6 9 6 6 6-6" />
      </svg>
    </button>

    <div
      v-if="open"
      class="absolute left-0 z-40 mt-1 w-64 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl sm:left-auto sm:right-0"
      role="menu"
    >
      <RouterLink
        v-for="item in modules"
        :key="item.name"
        :to="{ name: item.name }"
        role="menuitem"
        class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm transition"
        :class="current?.name === item.name
          ? 'bg-brand-50 font-semibold text-brand-900'
          : 'text-slate-700 hover:bg-slate-50'"
      >
        {{ item.label }}

        <svg
          v-if="current?.name === item.name"
          class="h-4 w-4 shrink-0"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
        >
          <path d="m20 6-11 11-5-5" />
        </svg>
      </RouterLink>

      <div class="mt-1 border-t border-slate-200 px-4 py-2.5">
        <p class="truncate text-xs text-slate-500">Sesión de</p>
        <p class="truncate text-sm font-semibold text-slate-900">{{ auth.user?.name }}</p>

        <button
          type="button"
          class="mt-2 text-sm font-semibold text-red-700 transition hover:underline"
          role="menuitem"
          @click="close(); emit('logout')"
        >
          Cerrar sesión
        </button>
      </div>
    </div>
  </div>
</template>
