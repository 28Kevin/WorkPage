<script setup>
import { computed, onMounted } from 'vue'
import HeroBanner from '@/components/HeroBanner.vue'
import ServiceCarousel from '@/components/ServiceCarousel.vue'
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

/** Diapositivas del banner. Las imágenes se sirven desde public/images. */
const SLIDES = [
  {
    image: '/images/fondo.jpg',
    focus: 'right center',
    eyebrow: 'Trabajo seguro en alturas',
    title: 'Su equipo, certificado para trabajar en altura',
    text:
      'Evaluaciones médicas ocupacionales con concepto de aptitud, certificado en PDF y verificación ' +
      'pública del documento. Sin filas y sin esperar días por el resultado.',
  },
  {
    image: '/images/imagen1.jpeg',
    focus: 'center',
    eyebrow: 'Nuestras instalaciones',
    title: 'Consultorios equipados para cada valoración',
    text:
      'Recepción, audiometría, visiometría y consulta médica en un mismo lugar, con equipos calibrados ' +
      'y personal especializado en seguridad y salud en el trabajo.',
  },
  {
    image: '/images/imagen2.jpeg',
    focus: 'center',
    eyebrow: 'Alturas y espacios confinados',
    title: 'Todo el proceso, bajo un mismo techo',
    text:
      'Valoramos las condiciones específicas de quienes trabajan con sistemas anticaídas o ingresan ' +
      'a espacios confinados, y emitimos el concepto para cada tarea.',
  },
]

/** Argumentos de venta, en la franja bajo el banner. */
const HIGHLIGHTS = [
  {
    title: 'Certificado el mismo día',
    note: 'El documento queda en PDF apenas termina la valoración.',
    icon: 'M13 2 3 14h7l-1 8 10-12h-7l1-8z',
    tone: 'bg-amber-100 text-amber-700 ring-amber-200',
  },
  {
    title: 'Verificable con código QR',
    note: 'Cualquiera puede comprobar la autenticidad del certificado en línea.',
    icon: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z',
    tone: 'bg-brand-100 text-brand-700 ring-brand-200',
  },
  {
    title: 'Atención a empresas',
    note: 'Programación por grupos y cotización para toda su nómina.',
    icon: 'M17 20h5v-2a3 3 0 0 0-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 0 1 5.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 0 1 9.2 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    tone: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
  },
]

/** Un acento distinto por convenio, para que la retícula no quede plana. */
const SECTOR_TONES = [
  'from-brand-600 to-accent-500',
  'from-accent-500 to-teal-500',
  'from-indigo-500 to-brand-600',
  'from-teal-500 to-emerald-500',
  'from-amber-500 to-orange-500',
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
  <div class="space-y-16">
    <!-- ============================================================= encabezado -->
    <HeroBanner :slides="SLIDES" />

    <!-- ------------------------------------------------------------ argumentos -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Por qué elegirnos">
      <div v-for="item in HIGHLIGHTS" :key="item.title" class="card flex gap-4 bg-white p-6">
        <span
          class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset"
          :class="item.tone"
          aria-hidden="true"
        >
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path :d="item.icon" />
          </svg>
        </span>

        <div>
          <h2 class="text-base font-bold text-slate-900">{{ item.title }}</h2>
          <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ item.note }}</p>
        </div>
      </div>
    </section>

    <!-- --------------------------------------------------------- los servicios -->
    <section
      id="servicios"
      class="scroll-mt-24 rounded-2xl bg-gradient-to-b from-brand-50 via-accent-50/60 to-white px-5 py-14 sm:px-10 sm:py-20"
    >
      <header class="mx-auto max-w-2xl text-center">
        <p class="text-xs font-semibold tracking-[0.2em] text-brand-700 uppercase">Nuestros servicios</p>
        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-balance text-slate-900 sm:text-4xl">
          Exámenes de salud y servicios médicos
        </h2>
        <p class="mt-4 text-base leading-relaxed text-slate-600">
          Cada evaluación termina en un certificado con concepto de aptitud, firmado y verificable en línea.
        </p>
      </header>

      <div class="mt-10">
        <ServiceCarousel :services="SERVICES" />
      </div>
    </section>

    <!-- ------------------------------------------------------------- galería -->
    <section v-if="gallery.images.length" aria-labelledby="galeria-title">
      <header class="mx-auto max-w-2xl text-center">
        <p class="text-xs font-semibold tracking-[0.2em] text-brand-700 uppercase">Nuestras instalaciones</p>
        <h2 id="galeria-title" class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
          Un espacio pensado para atenderle
        </h2>
      </header>

      <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <figure v-for="image in gallery.images" :key="image.id" class="card group overflow-hidden">
          <div class="overflow-hidden">
            <img
              :src="image.image"
              :alt="image.title"
              loading="lazy"
              class="h-44 w-full object-cover transition duration-500 group-hover:scale-105"
            >
          </div>

          <figcaption class="px-3 py-2.5">
            <p class="text-xs font-bold text-slate-900">{{ image.title }}</p>
            <p v-if="image.caption" class="mt-0.5 text-xs text-slate-500">{{ image.caption }}</p>
          </figcaption>
        </figure>
      </div>
    </section>

    <!-- ------------------------------------------------------------ convenios -->
    <section aria-labelledby="convenios-title">
      <header class="mx-auto max-w-2xl text-center">
        <p class="text-xs font-semibold tracking-[0.2em] text-brand-700 uppercase">Con quién trabajamos</p>
        <h2 id="convenios-title" class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
          Convenios
        </h2>
        <p class="mt-4 text-base leading-relaxed text-slate-600">
          Atendemos empresas y centros de entrenamiento de los siguientes sectores.
        </p>
      </header>

      <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="(sector, position) in SECTORS"
          :key="sector.name"
          class="card overflow-hidden bg-white transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="h-1.5 bg-gradient-to-r" :class="SECTOR_TONES[position % SECTOR_TONES.length]"></div>

          <div class="p-6">
            <h3 class="text-lg font-bold text-slate-900">{{ sector.name }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ sector.note }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ---------------------------------------------------- horarios y llamada -->
    <section class="relative isolate overflow-hidden rounded-2xl bg-slate-900" aria-labelledby="cta-title">
      <img
        src="/images/fondo.jpg"
        alt=""
        class="absolute inset-0 -z-10 h-full w-full object-cover object-center opacity-40"
      >
      <div class="absolute inset-0 -z-10 bg-slate-950/75" aria-hidden="true"></div>

      <div class="grid grid-cols-1 gap-10 px-6 py-16 sm:px-12 lg:grid-cols-3 lg:py-20">
        <div>
          <h2 class="text-lg font-bold text-white">Horario de atención</h2>
          <ul v-if="schedule.length" class="mt-3 space-y-1 text-sm text-slate-200">
            <li v-for="line in schedule" :key="line">{{ line }}</li>
          </ul>
          <p v-else class="mt-3 text-sm text-slate-300">Consúltenos el horario de atención.</p>
        </div>

        <div>
          <h2 class="text-lg font-bold text-white">Dónde estamos</h2>
          <p class="mt-3 text-sm leading-relaxed text-slate-200">{{ branding.center.address || '—' }}</p>
          <p v-if="branding.center.phone" class="mt-1 text-sm text-slate-200">{{ branding.center.phone }}</p>
        </div>

        <div class="lg:text-right">
          <h2 id="cta-title" class="text-lg font-bold text-white">¿Listo para programar?</h2>
          <p class="mt-3 text-sm leading-relaxed text-slate-200">
            Escríbanos y le confirmamos disponibilidad, para su empresa o de forma individual.
          </p>

          <RouterLink
            :to="{ name: 'public.contact' }"
            class="mt-5 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-slate-100"
          >
            Contáctenos
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>
