/**
 * Servicio de Usuarios/Roles/Endpoints — consume endpoints FastAPI Sprint 3.
 */
import api from './api.service.js'

const UsuariosService = {

  // ---------------------------------------------------------------
  // Usuarios
  // ---------------------------------------------------------------

  getUsers() {
    return api.get('/api/getUsers')
  },

  getUser(id) {
    return api.get('/api/getUser', { params: { id } })
  },

  getInfoUser() {
    return api.get('/api/getInfoUser')
  },

  /** Crea usuario con roles. Devuelve contraseña temporal en data.temp_password */
  newUser(email, rol = []) {
    return api.post('/api/newUser', { email, rol })
  },

  /** Actualiza roles de un usuario. */
  editUser(id, rol = []) {
    return api.post('/api/editUser', { id, rol })
  },

  deleteUser(id) {
    return api.post('/api/deleteUser', { id })
  },

  // ---------------------------------------------------------------
  // Roles
  // ---------------------------------------------------------------

  getRoles() {
    return api.get('/api/getRoles')
  },

  newRol(name, color, additionalAccess = false) {
    return api.post('/api/newRol', { name, color, additionalAccess })
  },

  editRol(id, nombre, color, additionalAccess = false) {
    return api.post('/api/editRol', { id, nombre, color, additionalAccess })
  },

  deleteRol(id) {
    return api.post('/api/deleteRol', { id })
  },

  // ---------------------------------------------------------------
  // Endpoints / RBAC
  // ---------------------------------------------------------------

  getEndpoints() {
    return api.get('/api/getEndpoints')
  },

  getEndpointsByRole(id, includeAll = false) {
    return api.get('/api/getEndpointsByRole', { params: { id, includeAll } })
  },

  /**
   * Asigna (allow=true) o revoca (allow=false) endpoints de un rol.
   * @param {number} rol
   * @param {number[]} endpoints
   * @param {boolean} allow
   */
  editEndpointsByRole(rol, endpoints, allow) {
    return api.post('/api/editEndpointsByRole', { rol, endpoints, allow })
  },

  // ---------------------------------------------------------------
  // Tokens API Bearer
  // ---------------------------------------------------------------

  getTokensUser() {
    return api.get('/api/getTokensUser')
  },

  createToken(name, expired) {
    return api.post('/api/createToken', { name, expired })
  },

  deleteToken(tokenId) {
    return api.post('/api/deleteToken', { tokenId })
  },
}

export default UsuariosService
