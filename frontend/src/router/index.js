/**
 * Vue Router 4 — rutas de la SPA SecLensCore.
 * Todas las rutas protegidas requieren sesión activa verificada con el servidor.
 */
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

const routes = [
  // ---------------------------------------------------------------
  // Rutas públicas
  // ---------------------------------------------------------------
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { publica: true, titulo: 'Iniciar sesión' },
  },
  {
    path: '/auth/sso/callback',
    name: 'sso-callback',
    component: () => import('@/views/auth/SsoCallbackView.vue'),
    meta: { publica: true, titulo: 'Autenticando...' },
  },
  {
    path: '/terminos',
    name: 'terminos',
    component: () => import('@/views/TerminosView.vue'),
    meta: { publica: true, titulo: 'Términos de uso' },
  },
  {
    path: '/privacidad',
    name: 'privacidad',
    component: () => import('@/views/PrivacidadView.vue'),
    meta: { publica: true, titulo: 'Política de privacidad' },
  },
  {
    path: '/error',
    name: 'error',
    component: () => import('@/views/ErrorView.vue'),
    meta: { publica: true, titulo: 'Error' },
  },

  // ---------------------------------------------------------------
  // Rutas protegidas
  // ---------------------------------------------------------------
  { path: '/', redirect: { name: 'servicios' } },
  {
    path: '/app',
    name: 'servicios',
    component: () => import('@/views/ServiciosView.vue'),
    meta: { requiresAuth: true, titulo: 'Servicios' },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true, titulo: 'Cuadro de mando' },
  },
  {
    path: '/eval',
    name: 'evaluacion',
    component: () => import('@/views/EvaluacionView.vue'),
    meta: { requiresAuth: true, titulo: 'Evaluación ECR' },
  },
  {
    path: '/evalmanager',
    name: 'evalmanager',
    component: () => import('@/views/EvalManagerView.vue'),
    meta: { requiresAuth: true, titulo: 'Gestor de evaluaciones' },
  },
  {
    path: '/evs',
    name: 'evs',
    component: () => import('@/views/EvsView.vue'),
    meta: { requiresAuth: true, titulo: 'Pentest - EVS' },
  },
  {
    path: '/eas',
    name: 'eas',
    component: () => import('@/views/EasView.vue'),
    meta: { requiresAuth: true, titulo: 'Evaluación de Arquitectura' },
  },
  {
    path: '/pac',
    name: 'pac',
    component: () => import('@/views/PacView.vue'),
    meta: { requiresAuth: true, titulo: 'Plan de Acciones Correctivas' },
  },
  {
    path: '/kpms',
    name: 'kpms',
    component: () => import('@/views/KpmsView.vue'),
    meta: { requiresAuth: true, titulo: 'KPMs' },
  },
  {
    path: '/bia',
    name: 'bia',
    component: () => import('@/views/BiaView.vue'),
    meta: { requiresAuth: true, titulo: 'Business Impact Analysis' },
  },
  {
    path: '/continuidad',
    name: 'continuidad',
    component: () => import('@/views/ContinuidadView.vue'),
    meta: { requiresAuth: true, titulo: 'Plan de Continuidad' },
  },
  {
    path: '/plan',
    name: 'plan',
    component: () => import('@/views/PlanView.vue'),
    meta: { requiresAuth: true, titulo: 'Plan' },
  },
  {
    path: '/issueDetail',
    name: 'issue-detail',
    component: () => import('@/views/IssueDetailView.vue'),
    meta: { requiresAuth: true, titulo: 'Detalle de Issue' },
  },
  {
    path: '/repositorioVulns',
    name: 'repositorio-vulns',
    component: () => import('@/views/RepositorioVulnsView.vue'),
    meta: { requiresAuth: true, titulo: 'Repositorio de Vulnerabilidades' },
  },
  {
    path: '/solicitudes',
    name: 'solicitudes',
    component: () => import('@/views/SolicitudesView.vue'),
    meta: { requiresAuth: true, titulo: 'Solicitudes' },
  },
  {
    path: '/pentestRequest',
    name: 'pentest-request',
    component: () => import('@/views/PentestRequestView.vue'),
    meta: { requiresAuth: true, titulo: 'Solicitud de Pentest' },
  },
  {
    path: '/normativas',
    name: 'normativas',
    component: () => import('@/views/NormativasView.vue'),
    meta: { requiresAuth: true, titulo: 'Normativas' },
  },
  {
    path: '/logs',
    name: 'logs',
    component: () => import('@/views/LogsView.vue'),
    meta: { requiresAuth: true, roles: ['admin'], titulo: 'Logs del sistema' },
  },
  {
    path: '/users',
    name: 'usuarios',
    component: () => import('@/views/UsuariosView.vue'),
    meta: { requiresAuth: true, roles: ['admin'], titulo: 'Gestión de usuarios' },
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('@/views/ProfileView.vue'),
    meta: { requiresAuth: true, titulo: 'Mi perfil' },
  },

  // Catch-all → error 404
  { path: '/:pathMatch(.*)*', redirect: { name: 'error', query: { codigo: 404 } } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL || '/'),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 }
  },
})

// ---------------------------------------------------------------
// Navigation Guard — protección de rutas
// ---------------------------------------------------------------
let sessionChecked = false

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Actualizar título del documento
  document.title = to.meta.titulo ? `${to.meta.titulo} | 11CertTool` : '11CertTool'

  // Ruta pública
  if (to.meta.publica) {
    if (to.name === 'login' && authStore.estaAutenticado) {
      return next({ name: 'servicios' })
    }
    return next()
  }

  // Verificar sesión con el servidor una sola vez por carga de página
  if (!sessionChecked) {
    sessionChecked = true
    await authStore.checkSession()
  }

  // Sin sesión → redirigir al login
  if (!authStore.estaAutenticado) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Control de roles
  if (to.meta.roles?.length > 0) {
    const tieneAcceso = to.meta.roles.some(rol => authStore.tieneRol(rol))
    if (!tieneAcceso) {
      return next({ name: 'error', query: { codigo: 403 } })
    }
  }

  next()
})

export default router
