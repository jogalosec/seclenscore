/**
 * Servicio de Activos — consume los endpoints FastAPI del módulo Activos.
 */
import api from './api.service.js'

const ActivosService = {

  // ---------------------------------------------------------------
  // Listado y consultas
  // ---------------------------------------------------------------

  /** Lista activos por tipo y filtros.
   * @param {string} tipo   - '42', '67', '42a', etc.
   * @param {string} archivado - '0' | '1' | 'All'
   * @param {string} filtro    - 'Todos' | 'NoAct' | 'NoECR'
   */
  getActivos(tipo, archivado = '0', filtro = 'Todos') {
    return api.get('/api/getActivos', { params: { tipo, archivado, filtro } })
  },

  /** Hijos directos de un activo (padre + children). */
  getChild(id) {
    return api.get(`/api/getChild/${id}`)
  },

  /** Árbol recursivo completo desde un activo raíz. */
  getTree(id) {
    return api.get(`/api/getTree/${id}`)
  },

  /** Descarga el árbol como Excel (.xlsx). */
  descargarArbol(id) {
    return api.get('/api/downloadTree', {
      params: { id },
      responseType: 'blob',
    }).then(response => {
      const url  = URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href  = url
      link.setAttribute('download', `arbol_activo_${id}.xlsx`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    })
  },

  /** Hermanos de un activo (mismo padre, excluyendo el actual). */
  getBrothers(id, padre) {
    return api.get('/api/getBrothers', { params: { id, padre } })
  },

  /** Todos los tipos de activo disponibles. */
  obtainAllTypeActivos() {
    return api.get('/api/obtainAllTypeActivos')
  },

  /** Activos que pueden ser padre del activo dado. */
  obtainFathersActivo(id) {
    return api.get('/api/obtainFathersActivo', { params: { id } })
  },

  /** Activos con información de exposición. */
  getActivosExposicion() {
    return api.get('/api/getActivosExposicion')
  },

  /** Clases / tipos de activos (catálogo). */
  getClaseActivos() {
    return api.get('/api/getClaseActivos')
  },

  /** Activos filtrados por id de tipo. */
  getActivosTipo(tipo) {
    return api.get('/api/getActivosTipo', { params: { tipo } })
  },

  /** Búsqueda de activos por nombre (LIKE). */
  getActivosByNombre(nombre) {
    return api.get('/api/getActivosByNombre', { params: { nombre } })
  },

  /** Hijos de un activo filtrados por tipo. */
  getHijosTipo(id, tipo) {
    return api.get('/api/getHijosTipo', { params: { id, tipo } })
  },

  /** Personas responsables de un activo. */
  getPersonasActivo(id) {
    return api.get('/api/getPersonasActivo', { params: { id } })
  },

  /** Logs de relaciones de un activo. */
  getLogsRelacion(id = null) {
    return api.get('/api/getLogsRelacion', { params: id ? { id } : {} })
  },

  // ---------------------------------------------------------------
  // Mutaciones
  // ---------------------------------------------------------------

  /** Crea un nuevo activo. */
  newActivo(nombre, clase, padre = null) {
    return api.post('/api/newActivo', { nombre, clase, padre })
  },

  /** Edita un activo existente. */
  editActivo(data) {
    return api.post('/api/editActivo', data)
  },

  /** Elimina un activo. */
  deleteActivo(id) {
    return api.post('/api/deleteActivo', null, { params: { id } })
  },

  /** Clona un activo con todos sus propiedades. */
  cloneActivo(id) {
    return api.post('/api/cloneActivo', null, { params: { id } })
  },

  /** Archiva o desarchiva un activo. */
  updateArchivados(id, archivado) {
    return api.post('/api/updateArchivados', null, { params: { id, archivado } })
  },

  /** Cambia la relación padre de un activo. */
  changeRelacion(activo_id, nuevo_padre_id) {
    return api.post('/api/changeRelacion', { activo_id, nuevo_padre_id })
  },

  /** Elimina la relación padre de un activo. */
  eliminarRelacionActivo(activo_id, padre_id) {
    return api.post('/api/eliminarRelacionActivo', { activo_id, padre_id })
  },

  /** Actualiza personas responsables de un activo. */
  editPersonasActivo(id, personas) {
    return api.post('/api/editPersonasActivo', { id, personas })
  },
}

export default ActivosService
