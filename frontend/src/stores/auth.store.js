/**
 * Store Pinia de autenticación.
 * IMPORTANTE: el JWT está en cookie httpOnly — no es accesible desde JS.
 * El store solo guarda los datos del usuario (sin tokens).
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authService } from '@/services/auth.service'

export const useAuthStore = defineStore('auth', () => {
  // Estado — solo datos del usuario, nunca el token JWT
  const usuario          = ref(null)
  const roles            = ref([])
  const _sessionVerified = ref(false) // si ya se verificó con el servidor

  // ---------------------------------------------------------------
  // Getters
  // ---------------------------------------------------------------
  const estaAutenticado = computed(() => _sessionVerified.value && usuario.value !== null)
  const esAdmin         = computed(() => roles.value.includes('admin'))
  const tieneRol        = (rol) => roles.value.includes(rol)
  const nombreUsuario   = computed(() => usuario.value?.nombre || usuario.value?.email || '')

  // ---------------------------------------------------------------
  // Acciones
  // ---------------------------------------------------------------

  /**
   * Verifica si la sesión sigue activa preguntando al servidor.
   * El servidor lee la cookie httpOnly — nosotros solo procesamos la respuesta.
   */
  async function checkSession() {
    try {
      const { data } = await authService.checkSession()
      if (!data.error) {
        _sessionVerified.value = true
        // Cargar perfil completo si la sesión es válida
        await cargarPerfil()
        return true
      }
    } catch {
      // 401 → no hay sesión activa
      _sessionVerified.value = false
      usuario.value = null
      roles.value = []
    }
    return false
  }

  /** Login con credenciales locales. */
  async function loginLocal(credenciales) {
    const { data } = await authService.loginLocal(credenciales)
    if (!data.error) {
      _sessionVerified.value = true
      usuario.value = data.user || null
      roles.value   = data.user?.roles || []
    }
    return data
  }

  /** Carga el perfil completo del usuario autenticado. */
  async function cargarPerfil() {
    try {
      const { data } = await authService.obtenerPerfil()
      if (!data.error) {
        usuario.value = data.usuario || data.user || null
        roles.value   = usuario.value?.roles || []
      }
    } catch {
      // Si falla obtener perfil, la sesión sigue activa pero sin datos
    }
  }

  /** Cierra la sesión: el servidor elimina la cookie, nosotros limpiamos el estado. */
  async function cerrarSesion() {
    try {
      await authService.cerrarSesion()
    } catch {
      // Ignorar errores al cerrar sesión en el servidor
    } finally {
      usuario.value          = null
      roles.value            = []
      _sessionVerified.value = false
    }
  }

  return {
    // Estado
    usuario, roles,
    // Getters
    estaAutenticado, esAdmin, tieneRol, nombreUsuario,
    // Acciones
    checkSession, loginLocal, cargarPerfil, cerrarSesion,
  }
}, {
  persist: {
    key:     'seclenscore_user',
    storage: sessionStorage,      // sessionStorage: se limpia al cerrar la pestaña
    paths:   ['usuario', 'roles'], // NUNCA persistir tokens
  },
})
