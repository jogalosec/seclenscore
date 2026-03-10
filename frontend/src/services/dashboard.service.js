/**
 * Servicio Dashboard — KPIs y métricas agregadas.
 */
import api from './api.service.js'

const DashboardService = {
  getDashboard()         { return api.get('/api/getDashboard') },
  getDashboardActivos()  { return api.get('/api/getDashboardActivos') },
  getDashboardBia()      { return api.get('/api/getDashboardBia') },
  getDashboardEcr()      { return api.get('/api/getDashboardEcr') },
  getDashboardPentest()  { return api.get('/api/getDashboardPentest') },
  getDashboardPac()      { return api.get('/api/getDashboardPac') },
}

export default DashboardService
