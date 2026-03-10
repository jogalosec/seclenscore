/**
 * Instancia Axios base para todas las llamadas al backend FastAPI.
 * El JWT está en cookie httpOnly — NO en headers, NO en localStorage.
 * withCredentials: true es OBLIGATORIO para que el navegador envíe la cookie.
 */
import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000',
  timeout: 30000,
  withCredentials: true, // Envía la cookie httpOnly 'sst' automáticamente
  headers: {
    'Content-Type':    'application/json',
    'Accept':          'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// ---------------------------------------------------------------
// Interceptor de RESPONSE: manejo centralizado de errores HTTP
// ---------------------------------------------------------------
apiClient.interceptors.response.use(
  (response) => response,

  (error) => {
    const status = error.response?.status

    if (status === 401) {
      // Sesión expirada: redirigir al login
      // Importamos el router de forma dinámica para evitar dependencia circular
      import('@/router').then(({ default: router }) => {
        const currentPath = router.currentRoute.value.fullPath
        router.push({ name: 'login', query: { redirect: currentPath } })
      })
    }

    if (status === 403) {
      import('@/router').then(({ default: router }) => {
        router.push({ name: 'error', query: { codigo: 403 } })
      })
    }

    if (status >= 500) {
      console.error('[API] Error del servidor:', error.response?.data?.message || error.message)
    }

    return Promise.reject(error)
  }
)

/**
 * Descarga un documento (Word/Excel) del backend.
 * Equivalente a postDataDocument() del api.js original.
 */
export async function descargarDocumento(url, params, nombreArchivo) {
  const response = await apiClient.post(url, params, { responseType: 'blob' })
  const urlBlob  = window.URL.createObjectURL(new Blob([response.data]))
  const enlace   = document.createElement('a')
  enlace.href     = urlBlob
  enlace.download = nombreArchivo
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  window.URL.revokeObjectURL(urlBlob)
}

export default apiClient
