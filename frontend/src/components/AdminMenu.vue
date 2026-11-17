<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
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
// 'icon' es el trazo de un ícono de Lucide (24×24); 'group' agrupa con subtítulo.
const MODULES = [
  {
    name: 'admin.exams', prefix: '/admin', label: 'Exámenes ocupacionales', group: 'Documentos',
    icon: 'M22 12h-4l-3 9L9 3l-3 9H2',
  },
  {
    name: 'admin.food', prefix: '/admin/alimentos', label: 'Manipulación de alimentos', group: 'Documentos',
    icon: 'M3 2v7a3 3 0 0 0 6 0V2M6 2v20M21 15V2a5 5 0 0 0-5 5v6h5zm0 0v7',
  },
  {
    name: 'admin.certificates', prefix: '/admin/certificados', label: 'Certificados de capacitación', group: 'Documentos',
    icon: 'M12 15a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM8.2 13.9 7 22l5-3 5 3-1.2-8.1',
  },
  {
    name: 'admin.billing', prefix: '/admin/cuentas-de-cobro', label: 'Cuentas de cobro', group: 'Documentos',
    icon: 'M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-3 2-3-2-3 2-3-2-3 2zM8 8h8M8 12h8M8 16h5',
  },
  {
    name: 'admin.messages', prefix: '/admin/mensajes', label: 'Mensajes de contacto', group: 'Gestión',
    icon: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  },
  {
    name: 'admin.branding', prefix: '/admin/configuracion', label: 'Configuración', group: 'Gestión',
    icon: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
  },
  {
    name: 'admin.usage',
    prefix: '/admin/uso',
    label: 'Uso de la plataforma',
    group: 'Desarrollo',
    icon: 'M3 3v18h18M7 16l4-4 4 4 5-6',
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

/** Módulos agrupados en el orden de MODULES, cada grupo con su subtítulo. */
const groups = computed(() => {
  const result = []

  for (const item of modules.value) {
    const group = result.find((entry) => entry.name === item.group)

    group ? group.items.push(item) : result.push({ name: item.group, items: [item] })
  }

  return result
})

const initials = computed(() =>
  (auth.user?.name ?? '?').split(/\s+/).slice(0, 2).map((word) => word[0]).join('').toUpperCase(),
)

const panel = ref(null)

function toggle() {
  open.value = !open.value

  if (open.value) {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
    // Con el teclado el foco entra al menú, como en los menús de Material.
    nextTick(() => focusItem(0))
  } else {
    detach()
  }
}

function close() {
  open.value = false
  detach()
}

function items() {
  return [...(panel.value?.querySelectorAll('[role="menuitem"]') ?? [])]
}

function focusItem(index) {
  const list = items()

  if (list.length) list[(index + list.length) % list.length].focus()
}

/** Flechas, Inicio y Fin recorren las opciones. */
function onMenuKeydown(event) {
  const list = items()
  const index = list.indexOf(document.activeElement)
  const moves = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: list.length - 1 }

  if (event.key in moves) {
    event.preventDefault()
    focusItem(moves[event.key])
  }
}

/** Onda que se expande desde el punto del clic. */
function ripple(event) {
  const target = event.currentTarget
  const box = target.getBoundingClientRect()
  const size = Math.max(box.width, box.height) * 2
  const wave = document.createElement('span')

  wave.className = 'menu-ripple'
  wave.style.cssText = `width:${size}px;height:${size}px;left:${event.clientX - box.left - size / 2}px;top:${event.clientY - box.top - size / 2}px`
  target.appendChild(wave)
  wave.addEventListener('animationend', () => wave.remove())
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

    <Transition name="menu">
      <div
        v-if="open"
        ref="panel"
        class="absolute left-0 z-40 mt-2 w-72 max-w-[calc(100vw-2rem)] origin-top-left overflow-hidden rounded-2xl bg-white text-slate-800 shadow-[0_8px_10px_1px_rgb(0_0_0/0.14),0_3px_14px_2px_rgb(0_0_0/0.12),0_5px_5px_-3px_rgb(0_0_0/0.2)] sm:right-0 sm:left-auto sm:origin-top-right"
        role="menu"
        aria-label="Módulos del panel"
        @keydown="onMenuKeydown"
      >
        <!-- Cabecera con la sesión, como en las tarjetas de cuenta de Material. -->
        <div class="flex items-center gap-3 bg-gradient-to-br from-brand-600 to-accent-600 px-4 py-4 text-white">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm font-bold" aria-hidden="true">
            {{ initials }}
          </span>
          <span class="min-w-0">
            <span class="block truncate text-sm font-semibold">{{ auth.user?.name }}</span>
            <span class="block truncate text-xs text-white/75">{{ auth.user?.email }}</span>
          </span>
        </div>

        <div class="max-h-[60vh] overflow-y-auto py-2">
          <template v-for="(group, position) in groups" :key="group.name">
            <div v-if="position > 0" class="my-2 h-px bg-slate-100" role="separator"></div>
            <p class="px-4 pt-1 pb-1.5 text-[11px] font-semibold tracking-[0.12em] text-slate-500 uppercase">{{ group.name }}</p>

            <RouterLink
              v-for="item in group.items"
              :key="item.name"
              :to="{ name: item.name }"
              role="menuitem"
              class="menu-item relative flex items-center gap-3 overflow-hidden px-4 py-2.5 text-sm outline-none"
              :class="current?.name === item.name ? 'bg-brand-50 font-semibold text-brand-800' : 'text-slate-700'"
              :aria-current="current?.name === item.name ? 'page' : undefined"
              @pointerdown="ripple"
            >
              <svg
                class="h-5 w-5 shrink-0"
                :class="current?.name === item.name ? 'text-brand-600' : 'text-slate-500'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path :d="item.icon" />
              </svg>

              <span class="flex-1">{{ item.label }}</span>

              <svg
                v-if="current?.name === item.name"
                class="h-4 w-4 shrink-0 text-brand-600"
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
          </template>
        </div>

        <div class="h-px bg-slate-100" role="separator"></div>

        <button
          type="button"
          role="menuitem"
          class="menu-item relative flex w-full items-center gap-3 overflow-hidden px-4 py-3 text-sm font-semibold text-red-700 outline-none"
          @pointerdown="ripple"
          @click="close(); emit('logout')"
        >
          <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
          </svg>
          Cerrar sesión
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.menu-item {
  transition: background-color 0.15s;
}

.menu-item:hover,
.menu-item:focus-visible {
  background-color: rgb(15 23 42 / 0.05);
}

/* La onda se crea desde JavaScript: necesita :deep para alcanzar el nodo. */
.menu-item :deep(.menu-ripple) {
  position: absolute;
  border-radius: 999px;
  background: currentColor;
  opacity: 0.18;
  transform: scale(0);
  pointer-events: none;
  animation: ripple 0.55s ease-out forwards;
}

@keyframes ripple {
  to { transform: scale(1); opacity: 0; }
}

/* Entrada de Material: crece desde la esquina con un leve fundido. */
.menu-enter-active { transition: opacity 0.15s ease-out, transform 0.2s cubic-bezier(0.2, 0, 0, 1); }
.menu-leave-active { transition: opacity 0.1s ease-in, transform 0.1s ease-in; }

.menu-enter-from,
.menu-leave-to {
  opacity: 0;
  transform: scale(0.85);
}

@media (prefers-reduced-motion: reduce) {
  .menu-enter-active,
  .menu-leave-active { transition: none; }

  .menu-item :deep(.menu-ripple) { display: none; }
}
</style>
