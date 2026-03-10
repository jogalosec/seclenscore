/**
 * Servicio de KPMs — consume endpoints FastAPI Sprint 4.
 */
import api from './api.service.js'

const KpmsService = {

  // ---------------------------------------------------------------
  // KPMs
  // ---------------------------------------------------------------

  /** @param {'madurez'|'metricas'|'csirt'} tipo */
  getKpms(tipo) {
    return api.get('/api/getKpms', { params: { tipo } })
  },

  /** @param {'madurez'|'metricas'|'csirt'} tipo */
  getLastReportKpms(tipo) {
    return api.get('/api/getLastReportKpms', { params: { tipo } })
  },

  getReportAs(csirt = false) {
    return api.get('/api/getReportAs', { params: { csirt } })
  },

  /**
   * @param {string|null} tipo - 'csirt' | 'all' | null (madurez+metricas)
   */
  getPreguntasKpms(tipo = null) {
    const params = tipo ? { tipo } : {}
    return api.get('/api/getPreguntasKpms', { params })
  },

  getReportersKpms() {
    return api.get('/api/getReportersKpms')
  },

  // ---------------------------------------------------------------
  // Acciones masivas
  // ---------------------------------------------------------------

  /**
   * @param {'madurez'|'metricas'|'csirt'} tipo
   * @param {number[]} id - Lista de IDs
   */
  lockKpms(tipo, id) {
    return api.post('/api/lockKpms', { tipo, id })
  },

  unlockKpms(tipo, id) {
    return api.post('/api/unlockKpms', { tipo, id })
  },

  delKpms(tipo, id) {
    return api.post('/api/delKpms', { tipo, id })
  },

  // ---------------------------------------------------------------
  // Edición individual
  // ---------------------------------------------------------------

  /**
   * @param {'madurez'|'metricas'|'csirt'} tipo
   * @param {number} id
   * @param {object} campos - { valor?, comentario?, locked? }
   */
  editKpm(tipo, id, campos) {
    return api.post('/api/editKpm', { tipo, id, campos })
  },

  editKpmDefinicion(id, nombre, descripcion_larga, descripcion_corta, grupo) {
    return api.post('/api/editKpmDefinicion', {
      id, nombre, descripcion_larga, descripcion_corta, grupo,
    })
  },

  // ---------------------------------------------------------------
  // Reporters
  // ---------------------------------------------------------------

  newReporterKpms(userId, idActivo) {
    return api.post('/api/newReporterKpms', { userId, idActivo })
  },

  deleteReporterKpms(idRelacion) {
    return api.post('/api/deleteReporterKpms', { idRelacion })
  },
}

export default KpmsService
