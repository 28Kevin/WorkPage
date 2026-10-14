<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/components/AlertMessage.vue'
import FormField from '@/components/FormField.vue'
import LoadingSpinner from '@/components/LoadingSpinner.vue'
import api, { parseApiError } from '@/services/api'
import { openBillingPdf } from '@/services/pdf'

const route = useRoute()
const router = useRouter()

const today = new Date().toISOString().slice(0, 10)

const accountId = computed(() => route.params.id || null)
const isEditing = computed(() => Boolean(accountId.value))

const form = reactive({
  issue_place: 'Bogotá',
  issue_date: today,
  company_name: '',
  company_nit: '',
  company_address: '',
  company_city: '',
  items: [emptyItem()],
})

const concepts = ref([])
const companies = ref([])
const numberCode = ref(null)

const errors = ref({})
const message = ref(null)
const ready = ref(false)
const submitting = ref(false)
const previewing = ref(false)

/** Total en letras: lo arma el servidor para que coincida con el impreso. */
const spelled = ref(null)
let spellTimer = null

function emptyItem() {
  return { concept: '', quantity: 1, unit_price: '' }
}

const total = computed(() => form.items.reduce((sum, item) => sum + subtotal(item), 0))

function subtotal(item) {
  const quantity = Number(item.quantity) || 0
  const price = Number(item.unit_price) || 0

  return quantity * price
}

/** "$3’160.000": miles con punto y apóstrofo para los millones. */
function money(amount) {
  const groups = String(Math.abs(Math.round(amount))).split('').reverse().join('')
    .match(/\d{1,3}/g).map((g) => g.split('').reverse().join('')).reverse()

  return `$${groups.reduce((out, group, i) => (
    i === 0 ? group : out + (i === groups.length - 2 ? '’' : '.') + group
  ), '')}`
}

onMounted(async () => {
  try {
    const { data } = await api.get('/billing/form')

    concepts.value = data.concepts
    companies.value = data.companies
    numberCode.value = data.next_number_code

    if (isEditing.value) await loadAccount()
  } catch (err) {
    message.value = parseApiError(err).message
  } finally {
    ready.value = true
  }
})

async function loadAccount() {
  const { data } = await api.get(`/billing/${accountId.value}`)
  const account = data.data

  Object.assign(form, {
    issue_place: account.issue_place,
    issue_date: account.issue_date,
    company_name: account.company.name,
    company_nit: account.company.nit,
    company_address: account.company.address,
    company_city: account.company.city || '',
    items: account.items.map((item) => ({
      concept: item.concept,
      quantity: item.quantity,
      unit_price: item.unit_price,
    })),
  })

  numberCode.value = account.number_code
}

/** Al elegir una empresa guardada se rellenan sus cuatro campos. */
function pickCompany(event) {
  const company = companies.value.find((item) => String(item.id) === event.target.value)

  if (!company) return

  Object.assign(form, {
    company_name: company.name,
    company_nit: company.nit,
    company_address: company.address,
    company_city: company.city || '',
  })
}

function addItem() {
  form.items.push(emptyItem())
}

function removeItem(index) {
  form.items.splice(index, 1)

  // Siempre queda al menos una fila: una cuenta sin ítems no cobra nada.
  if (!form.items.length) form.items.push(emptyItem())
}

// El total en letras se pide al servidor, que es quien lo imprime.
watch(total, (value) => {
  clearTimeout(spellTimer)

  if (!value) {
    spelled.value = null

    return
  }

  spellTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/billing/amount', { params: { total: value } })
      spelled.value = data.total_in_words
    } catch {
      // Sin conexión se muestra solo el número; el PDF lo resuelve igual.
      spelled.value = null
    }
  }, 300)
}, { immediate: true })

function payload() {
  return {
    ...form,
    company_city: form.company_city || null,
    items: form.items.map((item) => ({
      concept: item.concept,
      quantity: Number(item.quantity) || 0,
      unit_price: Number(item.unit_price) || 0,
    })),
  }
}

async function save() {
  const { data } = isEditing.value
    ? await api.put(`/billing/${accountId.value}`, payload())
    : await api.post('/billing', payload())

  return data.data
}

async function preview() {
  previewing.value = true
  errors.value = {}
  message.value = null

  try {
    const account = await save()

    if (!isEditing.value) {
      await router.replace({ name: 'admin.billing.edit', params: { id: account.id } })
    }

    await openBillingPdf(account.id)
  } catch (error) {
    reportError(error)
  } finally {
    previewing.value = false
  }
}

async function submit() {
  submitting.value = true
  errors.value = {}
  message.value = null

  try {
    const account = await save()

    router.push({ name: 'admin.billing', query: { saved: account.number_code } })
  } catch (error) {
    reportError(error)
  } finally {
    submitting.value = false
  }
}

function reportError(error) {
  const parsed = parseApiError(error)

  errors.value = parsed.errors
  message.value = parsed.message
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function itemError(index, field) {
  return errors.value[`items.${index}.${field}`]?.[0]
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">
          {{ isEditing ? 'Corregir cuenta de cobro' : 'Nueva cuenta de cobro' }}
        </h1>
        <p class="mt-1 text-sm text-slate-600">
          Los datos del emisor salen de Configuración y aparecen en todas las cuentas.
        </p>
      </div>

      <div v-if="numberCode" class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-right">
        <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Cuenta de cobro No.</p>
        <p class="font-mono text-sm font-bold text-brand-900">{{ numberCode }}</p>
      </div>
    </div>

    <AlertMessage v-if="message" variant="error" class="mb-4">{{ message }}</AlertMessage>

    <LoadingSpinner v-if="!ready" label="Cargando formulario…" />

    <form v-else class="space-y-5" @submit.prevent="submit">
      <!-- ================================================== Expedición -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">A</span>
          <h2 class="text-sm font-bold text-slate-900">Expedición</h2>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <FormField v-slot="{ id, hasError }" label="Lugar de expedición" :error="errors.issue_place" required>
            <input :id="id" v-model="form.issue_place" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Fecha de expedición" :error="errors.issue_date" required>
            <input :id="id" v-model="form.issue_date" type="date" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>
        </div>
      </section>

      <!-- ===================================================== Empresa -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">B</span>
          <h2 class="text-sm font-bold text-slate-900">Empresa</h2>
        </header>

        <div class="grid gap-4 p-5 sm:grid-cols-2">
          <div v-if="companies.length" class="sm:col-span-2">
            <label for="saved-company" class="field-label">Empresa guardada</label>
            <select id="saved-company" class="field-input" @change="pickCompany">
              <option value="">Escribir una empresa nueva…</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }} — NIT {{ company.nit }}
              </option>
            </select>
            <p class="field-hint">Al elegirla se rellenan los cuatro campos; puede ajustarlos.</p>
          </div>

          <FormField v-slot="{ id, hasError }" label="Razón social" :error="errors.company_name" required>
            <input :id="id" v-model="form.company_name" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="NIT" :error="errors.company_nit" required>
            <input :id="id" v-model="form.company_nit" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" placeholder="900867292-1" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Dirección" :error="errors.company_address" required>
            <input :id="id" v-model="form.company_address" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }" required>
          </FormField>

          <FormField v-slot="{ id, hasError }" label="Ciudad" :error="errors.company_city">
            <input :id="id" v-model="form.company_city" type="text" class="field-input"
                   :class="{ 'field-input-error': hasError }">
          </FormField>
        </div>
      </section>

      <!-- ======================================================= Ítems -->
      <section class="card">
        <header class="section-heading">
          <span class="section-badge">C</span>
          <h2 class="text-sm font-bold text-slate-900">Ítems</h2>
          <button type="button" class="btn-ghost ml-auto text-xs" @click="addItem">+ Agregar ítem</button>
        </header>

        <div class="p-5">
          <p v-if="errors.items" class="field-error mb-3">{{ errors.items[0] }}</p>

          <div class="space-y-3">
            <div
              v-for="(item, index) in form.items"
              :key="index"
              class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-12"
            >
              <div class="sm:col-span-6">
                <label :for="`concept-${index}`" class="field-label">Concepto</label>
                <select :id="`concept-${index}`" v-model="item.concept" class="field-input" required>
                  <option value="" disabled>Elija un concepto…</option>
                  <option v-for="concept in concepts" :key="concept.id" :value="concept.name">
                    {{ concept.name }}
                  </option>
                  <!-- Un concepto retirado del panel sigue visible al corregir. -->
                  <option v-if="item.concept && !concepts.some((c) => c.name === item.concept)" :value="item.concept">
                    {{ item.concept }}
                  </option>
                </select>
                <p v-if="itemError(index, 'concept')" class="field-error">{{ itemError(index, 'concept') }}</p>
              </div>

              <div class="sm:col-span-2">
                <label :for="`qty-${index}`" class="field-label">Cantidad</label>
                <input :id="`qty-${index}`" v-model="item.quantity" type="number" min="1" class="field-input" required>
                <p v-if="itemError(index, 'quantity')" class="field-error">{{ itemError(index, 'quantity') }}</p>
              </div>

              <div class="sm:col-span-2">
                <label :for="`price-${index}`" class="field-label">Precio unitario</label>
                <input :id="`price-${index}`" v-model="item.unit_price" type="number" min="0" step="1"
                       class="field-input" placeholder="1000000" required>
                <p v-if="itemError(index, 'unit_price')" class="field-error">{{ itemError(index, 'unit_price') }}</p>
              </div>

              <div class="flex items-end justify-between gap-2 sm:col-span-2">
                <div>
                  <span class="field-label">Subtotal</span>
                  <p class="font-mono text-sm font-semibold text-slate-900">{{ money(subtotal(item)) }}</p>
                </div>

                <button
                  v-if="form.items.length > 1"
                  type="button"
                  class="btn-ghost text-xs text-red-700"
                  :aria-label="`Quitar ítem ${index + 1}`"
                  @click="removeItem(index)"
                >
                  Quitar
                </button>
              </div>
            </div>
          </div>

          <!-- Total en vivo: se actualiza al agregar o cambiar ítems. -->
          <div class="mt-5 rounded-lg border border-brand-200 bg-brand-50 p-4 text-right">
            <p class="text-xs font-medium tracking-wide text-brand-700 uppercase">Total</p>
            <p class="font-mono text-2xl font-bold text-brand-900">{{ money(total) }}</p>
            <p v-if="spelled" class="mt-1 text-sm text-brand-800">{{ spelled }}</p>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap items-center justify-end gap-3">
        <RouterLink :to="{ name: 'admin.billing' }" class="btn-secondary">Cancelar</RouterLink>

        <button type="button" class="btn-secondary" :disabled="previewing || submitting" @click="preview">
          {{ previewing ? 'Generando…' : 'Guardar y previsualizar' }}
        </button>

        <button type="submit" class="btn-primary" :disabled="submitting || previewing">
          <template v-if="submitting">Guardando…</template>
          <template v-else>{{ isEditing ? 'Guardar correcciones' : 'Generar cuenta de cobro' }}</template>
        </button>
      </div>
    </form>
  </div>
</template>
