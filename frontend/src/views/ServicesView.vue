<script setup>
import { computed, onMounted } from 'vue'
import { useBrandingStore } from '@/stores/branding'
import { useGalleryStore } from '@/stores/gallery'

const branding = useBrandingStore()
const gallery = useGalleryStore()

const SERVICES = [
  {
    title: 'Examen médico ocupacional',
    summary: 'Evaluación de ingreso, periódica, de egreso, por cambio de ocupación o de retorno al trabajo.',
    detail:
      'Valora el estado de salud del trabajador frente a las condiciones y riesgos de su puesto. ' +
      'Incluye anamnesis, examen físico completo, revisión por sistemas y el concepto de aptitud, ' +
      'que se emite con firma del médico especialista en seguridad y salud en el trabajo.',
    includes: ['Historia clínica ocupacional', 'Examen físico y signos vitales', 'Concepto de aptitud con recomendaciones'],
  },
  {
    title: 'Examen médico ocupacional para trabajo seguro en alturas',
    summary: 'Para quienes desempeñan tareas con riesgo de caída a distinto nivel.',
    detail:
      'Además de la valoración general, evalúa de forma dirigida el equilibrio, la coordinación, la marcha, ' +
      'la agudeza visual y la audición, y descarta condiciones que contraindiquen el ascenso. ' +
      'El certificado indica el concepto específico para trabajo en alturas, independiente del concepto del cargo.',
    includes: ['Valoración de equilibrio y coordinación', 'Agudeza visual y audiometría', 'Concepto específico para alturas'],
  },
  {
    title: 'Examen médico ocupacional para espacios confinados',
    summary: 'Para el ingreso a recintos con aberturas limitadas de entrada y salida.',
    detail:
      'Orientado a descartar condiciones cardiovasculares, respiratorias o neurológicas que se agraven ' +
      'en atmósferas con deficiencia de oxígeno o presencia de contaminantes, y a verificar la tolerancia ' +
      'al uso prolongado de equipos de protección respiratoria.',
    includes: ['Valoración cardiovascular y respiratoria', 'Espirometría', 'Concepto específico para espacios confinados'],
  },
  {
    title: 'Examen médico ocupacional para operación de maquinaria pesada',
    summary: 'Para operadores de montacargas, retroexcavadoras, grúas y equipos similares.',
    detail:
      'Evalúa agudeza y campo visual, visión cromática, percepción de profundidad, audición y tiempos de ' +
      'reacción, junto con la revisión osteomuscular necesaria para el manejo sostenido de mandos y controles.',
    includes: ['Visiometría completa', 'Valoración osteomuscular', 'Concepto para operación de equipos'],
  },
  {
    title: 'Examen de manipulación de alimentos',
    summary: 'Para el personal que tiene contacto directo con alimentos en cualquier etapa del proceso.',
    detail:
      'Dirigido a quienes participan en la preparación, fabricación, transformación, envasado, almacenamiento, ' +
      'transporte, distribución, venta o servicio de alimentos. Verifica el estado de salud general y descarta ' +
      'condiciones que puedan comprometer la inocuidad.',
    includes: ['Valoración general de salud', 'Revisión de piel y faneras', 'Certificado para manipulación de alimentos'],
  },
]

const SECTORS = [
  { name: 'Trabajo seguro en alturas', note: 'Todas las categorías y niveles de entrenamiento.' },
  { name: 'Construcción pública y privada', note: 'Obra civil, edificaciones e infraestructura.' },
  { name: 'Administrativo y operativo', note: 'Personal de oficina, bodega y planta.' },
  { name: 'Telecomunicaciones y tecnología', note: 'Montaje y mantenimiento de redes y torres.' },
  { name: 'Industria de alimentos', note: 'Producción, distribución y servicio.' },
]

const schedule = computed(() =>
  (branding.center.schedule || '').split('\n').map((line) => line.trim()).filter(Boolean),
)

onMounted(() => {
  branding.load()
  gallery.load()
})
</script>

<template>
  <div class="space-y-10">
    <header class="text-center">
      <p class="text-xs font-semibold tracking-[0.18em] text-brand-700 uppercase">Nuestros servicios</p>
      <h1 class="mt-2 text-3xl font-bold text-slate-900">Exámenes de salud y servicios médicos</h1>
      <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
        Evaluaciones médicas ocupacionales con concepto de aptitud, certificado en PDF y verificación pública
        del documento mediante código QR.
      </p>
    </header>

    <!-- ------------------------------------------------------------- galería -->
    <section v-if="gallery.images.length" aria-label="Fotografías del centro médico">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <figure
          v-for="image in gallery.images"
          :key="image.id"
          class="card group overflow-hidden"
        >
          <img
            :src="image.image"
            :alt="image.title"
            loading="lazy"
            class="h-36 w-full object-cover transition group-hover:scale-105"
          >
          <figcaption class="px-3 py-2">
            <p class="text-xs font-semibold text-slate-900">{{ image.title }}</p>
            <p v-if="image.caption" class="mt-0.5 text-xs text-slate-500">{{ image.caption }}</p>
          </figcaption>
        </figure>
      </div>
    </section>

    <!-- --------------------------------------------------------- los servicios -->
    <section class="space-y-4">
      <article v-for="(service, index) in SERVICES" :key="service.title" class="card overflow-hidden">
        <div class="flex flex-col gap-4 p-6 sm:flex-row">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800"
            aria-hidden="true"
          >
            {{ String(index + 1).padStart(2, '0') }}
          </span>

          <div class="min-w-0 flex-1">
            <h2 class="text-base font-bold text-slate-900">{{ service.title }}</h2>
            <p class="mt-1 text-sm font-medium text-brand-800">{{ service.summary }}</p>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ service.detail }}</p>

            <ul class="mt-4 flex flex-wrap gap-1.5">
              <li v-for="item in service.includes" :key="item" class="chip">{{ item }}</li>
            </ul>
          </div>
        </div>
      </article>
    </section>

    <!-- ------------------------------------------------------------- sectores -->
    <section>
      <h2 class="text-lg font-bold text-slate-900">Convenios</h2>
      <p class="mt-1 text-sm text-slate-600">
        Trabajamos con empresas y centros de entrenamiento de los siguientes sectores.
      </p>

      <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="sector in SECTORS" :key="sector.name" class="card p-4">
          <h3 class="text-sm font-semibold text-slate-900">{{ sector.name }}</h3>
          <p class="mt-1 text-xs text-slate-500">{{ sector.note }}</p>
        </div>
      </div>
    </section>

    <!-- ------------------------------------------------------------- horarios -->
    <section class="card overflow-hidden">
      <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-3">
        <div>
          <h2 class="text-sm font-bold text-slate-900">Horario de atención</h2>
          <ul v-if="schedule.length" class="mt-2 space-y-1 text-sm text-slate-600">
            <li v-for="line in schedule" :key="line">{{ line }}</li>
          </ul>
          <p v-else class="mt-2 text-sm text-slate-500">Consúltenos el horario de atención.</p>
        </div>

        <div>
          <h2 class="text-sm font-bold text-slate-900">Dónde estamos</h2>
          <p class="mt-2 text-sm text-slate-600">{{ branding.center.address || '—' }}</p>
        </div>

        <div>
          <h2 class="text-sm font-bold text-slate-900">Agende su cita</h2>
          <p class="mt-2 text-sm text-slate-600">
            Escríbanos y le confirmamos disponibilidad para su empresa o de forma individual.
          </p>
          <RouterLink :to="{ name: 'public.contact' }" class="btn-primary mt-3">Contáctenos</RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>
