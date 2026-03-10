/**
 * Servicio Logs — logs de auditoría.
 */
import api from './api.service.js'

const LogsService = {
  getLogsRelacion(params = {})      { return api.get('/api/getLogsRelacion', { params }) },
  getEvents(params = {})            { return api.get('/api/getEvents', { params }) },
  getLogsActivosProcessed(data = {}) { return api.post('/api/getLogsActivosProcessed', data) },
  getLogsActivosRaw(data = {})      { return api.post('/api/getLogsActivosRaw', data) },
}

export default LogsService
