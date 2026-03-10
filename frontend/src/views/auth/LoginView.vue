<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar :show-menu="false" />

    <div class="container d-flex justify-content-center align-items-center flex-grow-1">
      <div class="mk-sm-container text-center">

        <div class="title-app mb-4">11Cert</div>

        <div class="mk-lightgrey-box">

          <!-- Mensaje de error -->
          <div v-if="errorMessage" class="alert alert-danger mb-3" role="alert">
            {{ errorMessage }}
          </div>

          <!-- Botón SSO Microsoft 365 -->
          <div class="mb-3">
            <button
              class="login-sso-btn btn p-0 border-0"
              :disabled="loadingSSO"
              @click="iniciarSSO"
            >
              <LoadingSpinner v-if="loadingSSO" texto="Redirigiendo a Microsoft..." />
              <span v-else class="btn btn-outline-primary px-4 py-2">
                🔐 Iniciar sesión con Microsoft 365
              </span>
            </button>
          </div>

          <!-- Toggle login local -->
          <div
            class="text-muted small mb-3"
            style="cursor: pointer;"
            @click="toggleLocal"
          >
            O inicia sesión con usuario local →
          </div>

          <!-- Formulario local -->
          <Transition name="slide-down">
            <div v-if="mostrarLocal" class="local">
              <div class="mb-3">
                <div class="custom-input">
                  <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="form-control"
                    placeholder=" "
                    autocomplete="email"
                    :disabled="loadingLocal"
                    @keyup.enter="loginLocal"
                  />
                  <label for="email" class="custom-input-label">Email</label>
                </div>
              </div>
              <div class="mb-3">
                <div class="custom-input">
                  <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="form-control"
                    placeholder=" "
                    autocomplete="current-password"
                    :disabled="loadingLocal"
                    @keyup.enter="loginLocal"
                  />
                  <label for="password" class="custom-input-label">Contraseña</label>
                </div>
              </div>
              <button
                class="btn btn-primary w-100"
                :disabled="loadingLocal || !formularioValido"
                @click="loginLocal"
              >
                <span v-if="loadingLocal" class="spinner-border spinner-border-sm me-2" />
                {{ loadingLocal ? 'Verificando...' : 'Iniciar sesión' }}
              </button>
            </div>
          </Transition>

        </div>
      </div>
    </div>

    <AppFooter />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import LoadingSpinner from '@/components/common/LoadingSpinner.vue'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()

const form = ref({ email: '', password: '' })
const mostrarLocal  = ref(false)
const loadingSSO    = ref(false)
const loadingLocal  = ref(false)
const errorMessage  = ref('')

const formularioValido = computed(() => {
  const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)
  const passOk  = form.value.password.length >= 4
  return emailOk && passOk
})

onMounted(async () => {
  // Si ya hay sesión activa, redirigir
  const activo = await authStore.checkSession()
  if (activo) router.replace({ name: 'servicios' })
  // Mostrar error si viene del callback SSO
  if (route.query.error) errorMessage.value = decodeURIComponent(String(route.query.error))
})

function toggleLocal() {
  mostrarLocal.value = !mostrarLocal.value
  errorMessage.value = ''
}

async function iniciarSSO() {
  loadingSSO.value   = true
  errorMessage.value = ''
  try {
    // Redirigir al backend que a su vez redirige a Azure AD
    const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
    window.location.href = `${apiBase}/auth/sso/url?redirect=${encodeURIComponent(window.location.origin + '/auth/sso/callback')}`
  } catch {
    loadingSSO.value   = false
    errorMessage.value = 'Error al conectar con Microsoft. Inténtalo de nuevo.'
  }
}

async function loginLocal() {
  if (!formularioValido.value) return
  loadingLocal.value  = true
  errorMessage.value  = ''
  try {
    await authStore.loginLocal({ email: form.value.email, password: form.value.password })
    const destino = route.query.redirect || { name: 'servicios' }
    router.push(destino)
  } catch (error) {
    loadingLocal.value = false
    const status = error.response?.status
    if (status === 401) errorMessage.value = 'Email o contraseña incorrectos.'
    else if (status === 429) errorMessage.value = 'Demasiados intentos. Espera un momento.'
    else if (status === 501) errorMessage.value = 'Login local en migración. Usa SSO Microsoft.'
    else errorMessage.value = 'Error del servidor. Contacta con el administrador.'
  }
}
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}
.slide-down-enter-from, .slide-down-leave-to { max-height: 0; opacity: 0; }
.slide-down-enter-to, .slide-down-leave-from { max-height: 400px; opacity: 1; }
</style>
