/**
 * Servicio EVS — Pentest + Solicitudes + Revisiones Prisma Cloud + JIRA.
 */
import api from './api.service.js'

const EvsService = {

  // ── Pentest ────────────────────────────────────────────────
  getPentests() { return api.get('/api/obtainActivosPentest') },
  getPentestById(id) { return api.get('/api/getInfoPentestByID', { params: { id } }) },
  crearPentest(data) { return api.post('/api/crearPentest', data) },
  editarPentest(data) { return api.post('/api/editPentest', data) },
  cerrarPentest(id) { return api.get('/api/cerrarPentest', { params: { id } }) },
  reabrirPentest(id) { return api.get('/api/reabrirPentest', { params: { id } }) },
  eliminarPentest(id) { return api.get('/api/eliminarPentest', { params: { id } }) },
  insertarActivosPentest(pentest_id, activos) { return api.post('/api/insertActivosPentest', { pentest_id, activos }) },
  asignarPentester(pentest_id, user_id) { return api.post('/api/asignPentester', { pentest_id, user_id }) },
  exportarPentestsExcel() { return api.post('/api/exportarPentestsExcel', {}, { responseType: 'blob' }) },

  // ── Issues JIRA ────────────────────────────────────────────
  getIssuesPentest(jql = null) { return api.get('/api/obtenerIssuesPentest', { params: jql ? { jql } : {} }) },
  getIssue(key) { return api.get('/api/obtainPentestIssue', { params: { key } }) },
  newIssue(data) { return api.post('/api/newIssue', data) },
  editIssue(data) { return api.post('/api/editIssue', data) },
  createIncident(data) { return api.post('/api/createIncident', data) },

  // ── Solicitudes ────────────────────────────────────────────
  getSolicitudes() { return api.get('/api/getSolicitudesPentest') },
  crearSolicitud(data) { return api.post('/api/pentestRequest', data) },
  aceptarSolicitud(id, comentario = null) { return api.post('/api/aceptarSolicitudPentest', { id, comentario }) },
  rechazarSolicitud(id, comentario = null) { return api.post('/api/rechazarSolicitudPentest', { id, comentario }) },

  // ── Revisiones Prisma ─────────────────────────────────────
  getRevisiones() { return api.get('/api/obtainActivosRevision') },
  getRevisionById(id) { return api.get('/api/getInfoRevisionByID', { params: { id } }) },
  crearRevision(data) { return api.post('/api/crearRevision', data) },
  cerrarRevision(id) { return api.get('/api/cerrarRevision', { params: { id } }) },
  reabrirRevision(id) { return api.get('/api/reabrirRevision', { params: { id } }) },
  eliminarRevision(id) { return api.get('/api/eliminarRevision', { params: { id } }) },
  getAlertasRevision(id) { return api.get('/api/getAlertasRevisionByID', { params: { id } }) },
  assignAlerta(data) { return api.post('/api/assignPrismaAlertToReview', data) },
  unassignAlerta(revision_id, id_alert) { return api.post('/api/unassignPrismaAlertToReview', { revision_id, id_alert }) },

  // ── Prisma Cloud ──────────────────────────────────────────
  getPrismaCloud() { return api.get('/api/getPrismaCloud') },
  getPrismaAlertByCloud(account_id, estado = 'open', limit = 100) { return api.get('/api/getPrismaAlertByCloud', { params: { account_id, estado, limit } }) },
  getPrismaAlertInfo(alert_id) { return api.get('/api/getPrismaAlertInfo', { params: { alert_id } }) },
  dismissPrismaAlert(alert_id, razon = null) { return api.post('/api/dismissPrismaAlert', { alert_id, razon }) },
  reopenPrismaAlert(alert_id) { return api.post('/api/reopenPrismaAlert', { alert_id }) },
}

export default EvsService
