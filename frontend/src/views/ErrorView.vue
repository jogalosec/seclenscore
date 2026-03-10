<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar :show-menu="authStore.estaAutenticado" />
    <div class="container d-flex flex-column justify-content-center align-items-center flex-grow-1 text-center">
      <h1 class="display-1 text-muted fw-bold">{{ codigo }}</h1>
      <p class="lead mb-4">{{ mensajeTexto }}</p>
      <div class="d-flex gap-2">
        <RouterLink :to="{ name: 'servicios' }" class="btn btn-primary">Volver al inicio</RouterLink>
        <button v-if="authStore.estaAutenticado" class="btn btn-outline-secondary" @click="logout">
          Cerrar sesión
        </button>
      </div>
    </div>
    <AppFooter />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'

const route     = useRoute()
const router    = useRouter()
const authStore = useAuthStore()

const codigo = computed(() => route.query.codigo || 404)
const mensajeTexto = computed(() => {
  if (route.query.mensaje) return route.query.mensaje
  const mensajes = { 403: 'No tienes permisos para acceder a esta página.', 404: 'Página no encontrada.', 500: 'Error interno del servidor.' }
  return mensajes[codigo.value] || 'Ha ocurrido un error inesperado.'
})

async function logout() {
  await authStore.cerrarSesion()
  router.push({ name: 'login' })
}
</script>
