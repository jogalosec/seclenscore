/**
 * Servicio de Evaluaciones — consume endpoints FastAPI Sprint 4.
 */
import api from './api.service.js'

const EvaluacionesService = {

  // ---------------------------------------------------------------
  // BIA
  // ---------------------------------------------------------------

  getBia(id) {
    return api.get('/api/getBia', { params: { id } })
  },

  saveBia(activo_id, respuestas) {
    return api.post('/api/saveBia', { activo_id, respuestas })
  },

  // ---------------------------------------------------------------
  // Evaluaciones
  // ---------------------------------------------------------------

  getEvaluaciones(id, tipo = null) {
    const params = { id }
    if (tipo) params.tipo = tipo
    return api.get('/api/getEvaluaciones', { params })
  },

  getEvaluacion(id) {
    return api.get('/api/getEvaluacion', { params: { id } })
  },

  /**
   * Guarda las respuestas de una evaluación.
   * @param {number} activo_id
   * @param {object} datos - Respuestas del formulario
   * @param {string} [meta_key='preguntas']
   */
  saveEvaluacion(activo_id, datos, meta_key = 'preguntas') {
    return api.post('/api/saveEvaluacion', { datos }, { params: { activo_id, meta_key } })
  },

  /**
   * Crea una nueva versión de una evaluación existente.
   * @param {object} data - { evaluate, comment, fecha, version, editEval }
   * @param {number|null} eval_id
   * @param {number|null} version_id
   */
  editEvaluacion(data, eval_id = null, version_id = null) {
    const params = {}
    if (eval_id !== null) params.eval_id = eval_id
    if (version_id !== null) params.version_id = version_id
    return api.post('/api/editEvaluacion', data, { params })
  },

  getFechaEvaluaciones(id, allVersions = false) {
    return api.get('/api/getFechaEvaluaciones', { params: { id, allVersions } })
  },

  getEvaluacionesSistema(id) {
    return api.get('/api/getEvaluacionesSistema', { params: { id } })
  },

  getPreguntasEvaluacion(id, esVersion = false) {
    return api.get('/api/getPreguntasEvaluacion', { params: { id, esVersion } })
  },

  // ---------------------------------------------------------------
  // OSA
  // ---------------------------------------------------------------

  saveEvalOsa(revision_id, datos) {
    return api.post('/api/saveEvalOsa', { revision_id, datos })
  },

  // ---------------------------------------------------------------
  // PAC
  // ---------------------------------------------------------------

  getPacEval(id, fecha) {
    return api.get('/api/getPacEval', { params: { id, fecha } })
  },
}

export default EvaluacionesService
