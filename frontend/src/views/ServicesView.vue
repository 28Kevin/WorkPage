<script setup>
import { onMounted } from 'vue'
import HeroSection from '@/components/HeroSection.vue'
import GalleryCard from '@/components/GalleryCard.vue'
import ServiceCard from '@/components/ServiceCard.vue'
import ServiceCarousel from '@/components/ServiceCarousel.vue'
import { useDragScroll } from '@/composables/useDragScroll'
import { useBrandingStore } from '@/stores/branding'
import { useGalleryStore } from '@/stores/gallery'

const branding = useBrandingStore()
const gallery = useGalleryStore()

// La galería se recorre con la rueda del mouse o arrastrando.
const { track: galleryTrack, dragging: galleryDragging, handlers: galleryHandlers } = useDragScroll()

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

/** Convenios. 'icon' es el trazo de un ícono de Lucide, que la tarjeta dibuja en grande. */
const SECTORS = [
  {
    name: 'Trabajo seguro en alturas',
    note: 'Todas las categorías y niveles de entrenamiento.',
    icon: 'M2 20h20M5 20V8l7-5 7 5v12M9 20v-6h6v6',
    tone: 'brand',
  },
  {
    name: 'Construcción pública y privada',
    note: 'Obra civil, edificaciones e infraestructura.',
    icon: 'M2 18a1 1 0 0 0 1 1h18a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1zM10 10V5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5M4 15v-3a6 6 0 0 1 6-6M14 6a6 6 0 0 1 6 6v3',
    tone: 'light',
  },
  {
    name: 'Administrativo y operativo',
    note: 'Personal de oficina, bodega y planta.',
    icon: 'M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16M4 6h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z',
    tone: 'soft',
  },
  {
    name: 'Telecomunicaciones y tecnología',
    note: 'Montaje y mantenimiento de redes y torres.',
    icon: 'M4.9 19.1C1 15.2 1 8.8 4.9 4.9M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5M19.1 4.9C23 8.8 23 15.1 19.1 19M12 12h.01',
    tone: 'accent',
  },
  {
    name: 'Industria de alimentos',
    note: 'Producción, distribución y servicio.',
    icon: 'M3 2v7a3 3 0 0 0 6 0V2M6 2v20M21 15V2a5 5 0 0 0-5 5v6h5zm0 0v7',
    tone: 'dark',
  },
]

/** Video del banner principal; si no carga se ve la primera imagen. */
const HERO_VIDEO = 'https://cdn.21st.dev/assets/mirror/4b/4bc542f7d287b18d595da8e796a834608a5cf10ef3618e191bdc524ce712ba67.mp4'

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
    tone: 'brand',
    to: { name: 'public.contact' },
    cta: 'Agendar una cita',
  },
  {
    title: 'Verificable con código QR',
    note: 'Cualquiera puede comprobar la autenticidad del certificado en línea.',
    icon: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z',
    tone: 'soft',
    to: { name: 'public.search' },
    cta: 'Verificar un certificado',
  },
  {
    title: 'Atención a empresas',
    note: 'Programación por grupos y cotización para toda su nómina.',
    icon: 'M17 20h5v-2a3 3 0 0 0-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 0 1 5.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 0 1 9.2 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    tone: 'accent',
    to: { name: 'public.contact' },
    cta: 'Solicitar cotización',
  },
]

/** Cinco convenios en dos filas: dos tarjetas anchas arriba y tres abajo. */
const SECTOR_SPANS = ['lg:col-span-3', 'lg:col-span-3', 'lg:col-span-2', 'lg:col-span-2', 'lg:col-span-2']


onMounted(() => {
  branding.load()
  gallery.load()
})
</script>

<template>
  <div class="space-y-16">
    <!-- ============================================================= encabezado -->
    <HeroSection
      :eyebrow="SLIDES[0].eyebrow"
      :title="SLIDES[0].title"
      :text="SLIDES[0].text"
      :poster="SLIDES[0].image"
      :video="HERO_VIDEO"
      :marquee="SECTORS.map((sector) => sector.name)"
      marquee-label="Atendemos a empresas de"
    />

    <!-- Lo que sigue al banner usa todo el ancho, con margen a los lados. -->
    <div class="space-y-16 px-4 sm:px-8 lg:px-12 2xl:px-20">

    <!-- ------------------------------------------------------------ argumentos -->
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Por qué elegirnos">
      <ServiceCard
        v-for="item in HIGHLIGHTS"
        :key="item.title"
        :title="item.title"
        :description="item.note"
        :icon="item.icon"
        :tone="item.tone"
        :to="item.to"
        :cta="item.cta"
      />
    </section>

    <!-- --------------------------------------------------------- los servicios -->
    <section
      id="servicios"
      class="scroll-mt-24 py-6 sm:py-10"
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

      <!-- Una sola fila que se recorre de lado: admite cualquier cantidad de fotos. -->
      <div
        ref="galleryTrack"
        class="no-scrollbar mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto py-2 md:snap-none"
        :class="galleryDragging ? 'cursor-grabbing select-none' : 'cursor-grab'"
        tabindex="0"
        aria-label="Fotografías de las instalaciones"
        v-on="galleryHandlers"
      >
        <GalleryCard
          v-for="(image, position) in gallery.images"
          :key="image.id"
          class="w-[80%] shrink-0 snap-start sm:w-[calc((100%-1.25rem)/2)] lg:w-[calc((100%-2.5rem)/3)] 2xl:w-[calc((100%-3.75rem)/4)]"
          :image="image.url"
          :title="image.title"
          :caption="image.caption"
          :index="position"
        />
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

      <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <ServiceCard
          v-for="(sector, position) in SECTORS"
          :key="sector.name"
          :class="SECTOR_SPANS[position]"
          :title="sector.name"
          :description="sector.note"
          :icon="sector.icon"
          :tone="sector.tone"
          :to="{ name: 'public.contact' }"
          cta="Solicitar cotización"
        />
      </div>
    </section>

    </div>
  </div>
</template>
