<script setup>
import { computed, nextTick, ref } from 'vue'
import api, { parseApiError } from '@/services/api'
import { useBrandingStore } from '@/stores/branding'

/*
 * Consulta pública con apariencia de chat. No hay IA detrás: si el mensaje
 * trae un número de cédula se consulta el mismo endpoint de siempre; si no,
 * se responde con textos fijos.
 */

const branding = useBrandingStore()

const draft = ref('')
const loading = ref(false)
const messages = ref([])
const thread = ref(null)
const input = ref(null)

const started = computed(() => messages.value.length > 0)

/** Preguntas sugeridas en la pantalla de bienvenida. */
const SUGGESTIONS = [
  { label: '¿Cómo verifico un examen?', reply: 'how' },
  { label: '¿Qué datos se muestran?', reply: 'privacy' },
  { label: '¿Qué documentos puedo consultar?', reply: 'documents' },
]

const REPLIES = {
  how:
    'Escríbame el número de cédula del trabajador, sin puntos ni comas, y le confirmo si tiene ' +
    'documentos vigentes emitidos por nuestro centro médico.',
  privacy:
    'Por protección de datos personales solo muestro las iniciales del titular y los últimos cuatro ' +
    'dígitos del documento, junto con el tipo de examen, la fecha y el concepto de aptitud.',
  documents:
    'Puedo confirmar exámenes médicos ocupacionales y paquetes de manipulación de alimentos. ' +
    'Para un certificado con código QR, escanéelo: lo lleva directo a su verificación.',
  noNumber:
    'No encontré un número de cédula en su mensaje. Escríbalo solo con dígitos, por ejemplo 1020304050.',
}

let nextId = 0

function push(message) {
  messages.value.push({ id: nextId++, ...message })
  scrollToEnd()
}

async function scrollToEnd() {
  await nextTick()
  thread.value?.scrollTo({ top: thread.value.scrollHeight, behavior: 'smooth' })
}

/** Acepta "1020304050", "1.020.304.050" o "verificar la cédula 1020304050". */
function extractDocument(text) {
  const match = text.replace(/[.\s,-]/g, '').match(/\d{5,15}/)

  return match ? match[0] : null
}

async function send(text = draft.value) {
  const content = text.trim()

  if (!content || loading.value) return

  draft.value = ''
  push({ role: 'user', text: content })

  const documentNumber = extractDocument(content)

  if (!documentNumber) {
    await think(350)
    push({ role: 'assistant', text: REPLIES.noNumber })
    return
  }

  await search(documentNumber)
}

async function ask(suggestion) {
  push({ role: 'user', text: suggestion.label })
  await think(450)
  push({ role: 'assistant', text: REPLIES[suggestion.reply] })
  input.value?.focus()
}

/** Pausa breve con el indicador de "escribiendo", para que la respuesta no aparezca de golpe. */
async function think(ms) {
  loading.value = true
  scrollToEnd()
  await new Promise((resolve) => setTimeout(resolve, ms))
  loading.value = false
}

async function search(documentNumber) {
  loading.value = true
  scrollToEnd()

  try {
    const { data } = await api.get('/public/exams/search', { params: { document_number: documentNumber } })

    push({
      role: 'assistant',
      text: `${data.message} Documento emitido por ${data.issuer}.`,
      results: data.results,
    })
  } catch (err) {
    if (err.response?.status === 404) {
      push({ role: 'assistant', text: err.response.data.message, tone: 'warning' })
    } else {
      push({ role: 'assistant', text: parseApiError(err).message, tone: 'error' })
    }
  } finally {
    loading.value = false
    input.value?.focus()
  }
}

function restart() {
  messages.value = []
  draft.value = ''
  input.value?.focus()
}

function formatDate(value) {
  return new Date(`${value}T00:00:00`).toLocaleDateString('es-CO', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}
</script>

<template>
  <!-- Tarjeta compacta: crece con la conversación hasta un tope y luego hace scroll. -->
  <div class="mx-auto flex max-w-2xl flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <!-- ------------------------------------------------------- conversación -->
    <div ref="thread" class="no-scrollbar max-h-[26rem] overflow-y-auto px-1 pb-4" aria-live="polite">
      <!-- Bienvenida: desaparece con el primer mensaje. -->
      <div v-if="!started" class="flex flex-col items-center py-4 text-center">
        <div class="orb mb-4" aria-hidden="true">
          <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z" />
          </svg>
        </div>

        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
          Hola, ¿qué examen desea verificar?
        </h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">
          Escriba el número de cédula del trabajador y le confirmo si tiene documentos vigentes emitidos
          por {{ branding.center.name || 'nuestro centro médico' }}.
        </p>

        <div class="mt-5 flex flex-wrap justify-center gap-2">
          <button
            v-for="suggestion in SUGGESTIONS"
            :key="suggestion.reply"
            type="button"
            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800"
            @click="ask(suggestion)"
          >
            {{ suggestion.label }}
          </button>
        </div>
      </div>

      <ol v-else class="space-y-5 pt-2">
        <li
          v-for="message in messages"
          :key="message.id"
          class="message flex gap-3"
          :class="message.role === 'user' ? 'justify-end' : ''"
        >
          <span v-if="message.role === 'assistant'" class="orb orb-sm mt-1 shrink-0" aria-hidden="true">
            <svg class="h-4 w-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2z" />
            </svg>
          </span>

          <div :class="message.role === 'user' ? 'max-w-[80%]' : 'min-w-0 flex-1'">
            <span class="sr-only">{{ message.role === 'user' ? 'Usted:' : 'Asistente:' }}</span>

            <p
              v-if="message.role === 'user'"
              class="rounded-3xl rounded-br-md bg-brand-700 px-4 py-2.5 text-sm text-white"
            >
              {{ message.text }}
            </p>

            <template v-else>
              <p
                class="text-sm leading-relaxed"
                :class="{
                  'text-slate-800': !message.tone,
                  'text-amber-800': message.tone === 'warning',
                  'text-red-700': message.tone === 'error',
                }"
              >
                {{ message.text }}
              </p>

              <!-- Resultados: una tarjeta por documento encontrado. -->
              <div v-if="message.results" class="mt-4 space-y-3">
                <article
                  v-for="item in message.results"
                  :key="item.order_code"
                  class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                      <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ item.document_type_label }}</p>
                      <p class="font-mono text-sm font-bold text-brand-800">{{ item.order_code }}</p>
                    </div>
                    <span
                      v-if="item.result_label"
                      class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-300 ring-inset"
                    >
                      {{ item.result_label }}
                    </span>
                  </div>

                  <dl class="grid grid-cols-1 gap-x-6 gap-y-3 px-4 py-3 sm:grid-cols-2">
                    <div>
                      <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Titular</dt>
                      <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ item.full_name }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Cédula</dt>
                      <dd class="mt-0.5 font-mono text-sm text-slate-900">{{ item.document_number }}</dd>
                    </div>
                    <div v-if="item.exam_type_label">
                      <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Tipo de examen</dt>
                      <dd class="mt-0.5 text-sm text-slate-900">{{ item.exam_type_label }}</dd>
                    </div>
                    <div>
                      <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha del documento</dt>
                      <dd class="mt-0.5 text-sm text-slate-900">{{ formatDate(item.exam_date) }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                      <dt class="text-xs font-medium tracking-wide text-slate-500 uppercase">Fecha exacta de expedición</dt>
                      <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ item.issue_date_label }}</dd>
                    </div>
                  </dl>

                  <div class="border-t border-slate-100 bg-slate-50 px-4 py-2.5">
                    <RouterLink
                      :to="{ name: 'public.verify', params: { code: item.verification_code } }"
                      class="text-sm font-semibold text-brand-700 hover:underline"
                    >
                      Ver leyenda oficial de verificación →
                    </RouterLink>
                  </div>
                </article>
              </div>
            </template>
          </div>
        </li>

        <!-- Indicador de "escribiendo" mientras consulta. -->
        <li v-if="loading" class="message flex gap-3">
          <span class="orb orb-sm shrink-0" aria-hidden="true"></span>
          <span class="flex items-center gap-1 py-2" role="status" aria-label="Consultando">
            <span class="dot"></span><span class="dot" style="animation-delay: 0.15s"></span><span class="dot" style="animation-delay: 0.3s"></span>
          </span>
        </li>
      </ol>
    </div>

    <!-- --------------------------------------------------------------- caja -->
    <form class="composer" novalidate @submit.prevent="send()">
      <label for="public-query" class="sr-only">Número de cédula</label>
      <input
        id="public-query"
        ref="input"
        v-model="draft"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        maxlength="40"
        class="min-w-0 flex-1 bg-transparent px-2 py-2 text-base text-slate-900 outline-none placeholder:text-slate-400"
        placeholder="Escriba el número de cédula…"
      >

      <button
        v-if="started"
        type="button"
        class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
        aria-label="Nueva consulta"
        title="Nueva consulta"
        @click="restart"
      >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5" />
        </svg>
      </button>

      <button
        type="submit"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-600 to-accent-500 text-white shadow-md transition hover:brightness-110 disabled:opacity-40"
        :disabled="loading || !draft.trim()"
        aria-label="Consultar"
      >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
      </button>
    </form>

    <p class="mt-3 text-center text-xs text-slate-500">
      Por protección de datos personales solo se muestran las iniciales del titular y los últimos cuatro
      dígitos del documento.
    </p>
  </div>
</template>

<style scoped>
/* Esfera con el degradado de marca: la "cara" del asistente. */
.orb {
  display: flex;
  width: 3.25rem;
  height: 3.25rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: conic-gradient(from 0deg, var(--color-brand-500), var(--color-accent-400), var(--color-brand-700), var(--color-brand-500));
  box-shadow: 0 10px 40px -8px color-mix(in srgb, var(--color-brand-500) 60%, transparent);
  animation: spin 8s linear infinite;
}

.orb > svg { animation: spin 8s linear infinite reverse; }

.orb-sm {
  width: 2rem;
  height: 2rem;
  box-shadow: none;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.composer {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-radius: 1.75rem;
  border: 1px solid var(--color-slate-200);
  background: white;
  padding: 0.5rem 0.5rem 0.5rem 1rem;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.composer:focus-within {
  border-color: var(--color-brand-400);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-brand-400) 20%, transparent);
}

.message { animation: rise 0.35s ease-out both; }

@keyframes rise {
  from { opacity: 0; transform: translateY(8px); }
}

.dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 999px;
  background: var(--color-brand-500);
  animation: bounce 1s ease-in-out infinite;
}

@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
  30% { transform: translateY(-5px); opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .orb, .orb > svg, .message, .dot { animation: none; }
}
</style>
