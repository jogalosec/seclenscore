<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="/app">11CertTool</a>

      <template v-if="showMenu">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
          <span class="navbar-toggler-icon" />
        </button>
        <div id="navMenu" class="collapse navbar-collapse">
          <ul class="navbar-nav me-auto">
            <li class="nav-item">
              <RouterLink class="nav-link" :to="{ name: 'servicios' }">Servicios</RouterLink>
            </li>
            <li class="nav-item">
              <RouterLink class="nav-link" :to="{ name: 'dashboard' }">Dashboard</RouterLink>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Evaluaciones</a>
              <ul class="dropdown-menu">
                <li><RouterLink class="dropdown-item" :to="{ name: 'evaluacion' }">ECR</RouterLink></li>
                <li><RouterLink class="dropdown-item" :to="{ name: 'eas' }">EAS</RouterLink></li>
                <li><RouterLink class="dropdown-item" :to="{ name: 'pac' }">PAC</RouterLink></li>
                <li><RouterLink class="dropdown-item" :to="{ name: 'bia' }">BIA</RouterLink></li>
              </ul>
            </li>
            <li class="nav-item">
              <RouterLink class="nav-link" :to="{ name: 'evs' }">Pentest</RouterLink>
            </li>
            <li class="nav-item">
              <RouterLink class="nav-link" :to="{ name: 'kpms' }">KPMs</RouterLink>
            </li>
          </ul>
          <ul class="navbar-nav">
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                {{ authStore.nombreUsuario || 'Usuario' }}
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><RouterLink class="dropdown-item" :to="{ name: 'profile' }">Mi perfil</RouterLink></li>
                <li v-if="authStore.esAdmin">
                  <RouterLink class="dropdown-item" :to="{ name: 'usuarios' }">Usuarios</RouterLink>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="#" @click.prevent="cerrarSesion">Cerrar sesión</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </template>
    </div>
  </nav>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'

defineProps({ showMenu: { type: Boolean, default: true } })

const router    = useRouter()
const authStore = useAuthStore()

async function cerrarSesion() {
  await authStore.cerrarSesion()
  router.push({ name: 'login' })
}
</script>
