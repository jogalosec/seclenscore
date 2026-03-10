/**
 * Servicio Kiuwan / SDLC — Sprint 7.
 * Cubre análisis de código estático (Kiuwan + SonarQube) y gestión SDLC.
 */
import api from './api.service.js'

const KiuwanService = {

  // ── Kiuwan ────────────────────────────────────────────────────────────────

  /** Aplicaciones almacenadas en BD (caché local). */
  getKiuwanAplication() {
    return api.get('/api/getKiuwanAplication')
  },

  /** Sincroniza con la API de Kiuwan y devuelve aplicaciones actualizadas. */
  getKiuwanApps() {
    return api.get('/api/getKiuwanApps')
  },

  /** Actualiza el campo cumple_kpm de una aplicación Kiuwan. */
  updateCumpleKpm(app_name, cumple_kpm) {
    return api.post('/api/updateCumpleKpm', { app_name, cumple_kpm })
  },

  /** Actualiza el campo cumple_kpm_sonar de un slot SonarQube. */
  updateSonarKPM(slot_sonarqube, cumple_kpm_sonar) {
    return api.post('/api/updateSonarKPM', { slot_sonarqube, cumple_kpm_sonar })
  },

  // ── SDLC ──────────────────────────────────────────────────────────────────

  /** Obtiene registros SDLC. Acepta filtros opcionales: app, id. */
  obtenerSDLC(params = {}) {
    return api.get('/api/obtenerSDLC', { params })
  },

  /** Crea un nuevo registro SDLC (Kiuwan o Sonarqube). */
  crearAppSDLC(data) {
    return api.post('/api/crearAppSDLC', data)
  },

  /** Modifica un registro SDLC existente. */
  modificarAppSDLC(id, data) {
    return api.post('/api/modificarAppSDLC', data, { params: { id } })
  },

  /** Elimina un registro SDLC (y su relación Kiuwan si aplica). */
  eliminarAppSDLC(id, app, kiuwan_id = null) {
    return api.post('/api/eliminarAppSDLC', { id, app, kiuwan_id })
  },

  // ── Suscripciones Azure ───────────────────────────────────────────────────

  /** Comprueba si una suscripción tiene activo relacionado. */
  getRelacionSuscripcion(id_suscripcion) {
    return api.get('/api/getRelacionSuscripcion', { params: { id_suscripcion } })
  },

  /** Lista todas las relaciones suscripción-activo. */
  getSuscriptionRelations() {
    return api.get('/api/getSuscriptionRelations')
  },

  /** Crea nuevas relaciones suscripción-activo. */
  insertSuscriptionRelations(id_activo, subscriptions, subscriptionNames = []) {
    return api.post('/api/insertSuscriptionRelations', { id_activo, subscriptions, subscriptionNames })
  },

  /** Elimina una relación suscripción-activo. */
  deleteSuscriptionRelations(suscription_id) {
    return api.post('/api/deleteSuscriptionRelations', { suscription_id })
  },

  /** Edita una relación suscripción-activo. */
  editSuscriptionRelations(id, data) {
    return api.post('/api/editSuscriptionRelations', { id, ...data })
  },
}

export default KiuwanService
