import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const routes = [
  {
    path: '/',
    redirect: '/servicios',
  },

  // Modulo de consulta publica (acceso externo)
  {
    path: '/consulta',
    name: 'public.search',
    component: () => import('@/views/PublicSearchView.vue'),
    meta: { public: true, title: 'Consulta pública' },
  },
  {
    path: '/verificar/:code',
    name: 'public.verify',
    component: () => import('@/views/VerificationView.vue'),
    meta: { public: true, title: 'Verificación de documento' },
  },
  {
    path: '/certificado/:code',
    name: 'public.certificate',
    component: () => import('@/views/CertificateVerificationView.vue'),
    meta: { public: true, title: 'Verificación de certificado' },
  },
  {
    path: '/servicios',
    name: 'public.services',
    component: () => import('@/views/ServicesView.vue'),
    meta: { public: true, title: 'Servicios' },
  },
  {
    path: '/contacto',
    name: 'public.contact',
    component: () => import('@/views/ContactView.vue'),
    meta: { public: true, title: 'Contáctenos' },
  },

  // Modulo administrativo (acceso restringido)
  {
    path: '/admin/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true, guestOnly: true, title: 'Ingreso administrativo' },
  },
  {
    path: '/admin',
    name: 'admin.exams',
    component: () => import('@/views/ExamListView.vue'),
    meta: { requiresAuth: true, title: 'Exámenes emitidos' },
  },
  {
    path: '/admin/examenes/nuevo',
    name: 'admin.exams.create',
    component: () => import('@/views/ExamFormView.vue'),
    meta: { requiresAuth: true, title: 'Nuevo examen médico' },
  },
  {
    path: '/admin/examenes/:id',
    name: 'admin.exams.show',
    component: () => import('@/views/ExamDetailView.vue'),
    meta: { requiresAuth: true, title: 'Detalle del examen' },
  },
  {
    path: '/admin/examenes/:id/editar',
    name: 'admin.exams.edit',
    component: () => import('@/views/ExamFormView.vue'),
    meta: { requiresAuth: true, title: 'Corregir evaluación' },
  },
  {
    path: '/admin/alimentos',
    name: 'admin.food',
    component: () => import('@/views/FoodHandlerListView.vue'),
    meta: { requiresAuth: true, title: 'Manipulación de alimentos' },
  },
  {
    path: '/admin/alimentos/nuevo',
    name: 'admin.food.create',
    component: () => import('@/views/FoodHandlerFormView.vue'),
    meta: { requiresAuth: true, title: 'Nuevo paquete de alimentos' },
  },
  {
    path: '/admin/alimentos/:id',
    name: 'admin.food.show',
    component: () => import('@/views/FoodHandlerDetailView.vue'),
    meta: { requiresAuth: true, title: 'Detalle del paquete' },
  },
  {
    path: '/admin/alimentos/:id/editar',
    name: 'admin.food.edit',
    component: () => import('@/views/FoodHandlerFormView.vue'),
    meta: { requiresAuth: true, title: 'Corregir paquete' },
  },
  {
    path: '/admin/certificados',
    name: 'admin.certificates',
    component: () => import('@/views/CertificateListView.vue'),
    meta: { requiresAuth: true, title: 'Certificados de capacitación' },
  },
  {
    path: '/admin/certificados/nuevo',
    name: 'admin.certificates.create',
    component: () => import('@/views/CertificateFormView.vue'),
    meta: { requiresAuth: true, title: 'Nuevo certificado' },
  },
  {
    path: '/admin/certificados/:id/editar',
    name: 'admin.certificates.edit',
    component: () => import('@/views/CertificateFormView.vue'),
    meta: { requiresAuth: true, title: 'Corregir certificado' },
  },
  {
    path: '/admin/cuentas-de-cobro',
    name: 'admin.billing',
    component: () => import('@/views/BillingListView.vue'),
    meta: { requiresAuth: true, title: 'Cuentas de cobro' },
  },
  {
    path: '/admin/cuentas-de-cobro/nueva',
    name: 'admin.billing.create',
    component: () => import('@/views/BillingFormView.vue'),
    meta: { requiresAuth: true, title: 'Nueva cuenta de cobro' },
  },
  {
    path: '/admin/cuentas-de-cobro/:id/editar',
    name: 'admin.billing.edit',
    component: () => import('@/views/BillingFormView.vue'),
    meta: { requiresAuth: true, title: 'Corregir cuenta de cobro' },
  },
  {
    path: '/admin/configuracion',
    name: 'admin.branding',
    component: () => import('@/views/BrandingView.vue'),
    meta: { requiresAuth: true, title: 'Configuración' },
  },
  {
    path: '/admin/mensajes',
    name: 'admin.messages',
    component: () => import('@/views/MessagesView.vue'),
    meta: { requiresAuth: true, title: 'Mensajes de contacto' },
  },

  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { public: true, title: 'Página no encontrada' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  await auth.restore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'admin.exams' }
  }

  return true
})

router.afterEach((to) => {
  const appName = useBrandingStore().appName

  document.title = to.meta.title ? `${to.meta.title} · ${appName}` : appName
})

export default router
