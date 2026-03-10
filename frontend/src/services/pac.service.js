/**
 * Servicio PAC + Plan de Continuidad.
 */
import api from './api.service.js'

const PacService = {

  // ── PAC ───────────────────────────────────────────────────
  getListPac(id) { return api.get('/api/getListPac', { params: { id } }) },
  createPac(data) { return api.post('/api/createPac', data) },
  getSeguimiento(id) { return api.get('/api/getSeguimientoByPacID', { params: { id } }) },
  modEstadoSeguimiento(data) { return api.post('/api/ModEstadoPacSeguimiento', data) },
  editSeguimiento(data) { return api.post('/api/editPacSeguimiento', data) },
  deleteSeguimiento(id) { return api.post('/api/deletePacSeguimiento', { id }) },
  downloadPac(id) { return api.get('/api/downloadPac', { params: { id }, responseType: 'blob' }) },

  // ── Plan Continuidad ──────────────────────────────────────
  getProductosContinuidad(id = null) { return api.get('/api/getProductosContinuidad', { params: id ? { id } : {} }) },
  newPlan(data) { return api.post('/api/newPlan', data) },
  editPlan(data) { return api.post('/api/editPlan', data) },
  deletePlan(id) { return api.post('/api/deletePlan', { id }) },
}

export default PacService
