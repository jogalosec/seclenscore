<template>
  <div class="d-flex flex-column min-vh-100 justify-content-center align-items-center">
    <LoadingSpinner texto="Autenticando con Microsoft..." />
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'

const route     = useRoute()
const router    = useRouter()
const authStore = useAuthStore()

onMounted(async () => {
  // Error devuelto por Azure AD
  if (route.query.error) {
    router.push({ name: 'login', query: { error: route.query.error_description || route.query.error } })
    return
  }

  // El backend ya procesó el callback y estableció la cookie httpOnly.
  // Solo necesitamos verificar la sesión.
  const activo = await authStore.checkSession()
  if (activo) {
    const destino = sessionStorage.getItem('sso_redirect') || '/app'
    sessionStorage.removeItem('sso_redirect')
    router.push(destino)
  } else {
    router.push({ name: 'login', query: { error: 'No se pudo establecer la sesión' } })
  }
})
</script>
