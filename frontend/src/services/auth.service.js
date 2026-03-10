/**
 * Servicio de autenticación — llama a los endpoints de auth del backend FastAPI.
 */
import apiClient from './api.service'

export const authService = {
  /** Verifica si la sesión está activa (la cookie se envía automáticamente). */
  checkSession()              { return apiClient.get('/api/islogged') },

  /** Login local con email + contraseña. */
  loginLocal(credenciales)   { return apiClient.post('/api/login', credenciales) },

  /** Obtiene la URL de autorización de Azure AD para iniciar SSO. */
  obtenerUrlSSO()             { return apiClient.get('/auth/sso/url') },

  /** Cierra la sesión (el backend elimina la cookie). */
  cerrarSesion()              { return apiClient.get('/api/logout') },

  /** Obtiene el perfil del usuario autenticado. */
  obtenerPerfil()             { return apiClient.get('/api/getInfoUser') },

  /** Renueva el token JWT. */
  renovarToken()              { return apiClient.get('/api/refreshToken') },
}
