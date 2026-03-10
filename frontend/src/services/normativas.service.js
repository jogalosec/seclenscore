/**
 * Servicio de Normativas — consume endpoints FastAPI Sprint 3.
 */
import api from './api.service.js'

const NormativasService = {

  // ---------------------------------------------------------------
  // Normativas
  // ---------------------------------------------------------------

  /** Devuelve todas las normativas con controles y relaciones embebidas. */
  getNormativas() {
    return api.get('/api/getNormativas')
  },

  newNormativa(nombre, version) {
    return api.post('/api/newNormativa', { nombre, version })
  },

  editNormativa(idNormativa, nombre, enabled) {
    return api.post('/api/editNormativa', { idNormativa, nombre, enabled })
  },

  deleteNormativa(idNormativa) {
    return api.post('/api/deleteNormativa', { idNormativa })
  },

  // ---------------------------------------------------------------
  // Controles
  // ---------------------------------------------------------------

  newControl(codigo, nombre, descripcion, dominio, idNormativa) {
    return api.post('/api/newControl', { codigo, nombre, descripcion, dominio, idNormativa })
  },

  deleteControl(idControl) {
    return api.post('/api/deleteControl', { idControl })
  },

  getDominiosUnicos() {
    return api.get('/api/getDominiosUnicosControles')
  },

  // ---------------------------------------------------------------
  // USFs
  // ---------------------------------------------------------------

  getUSFs() {
    return api.get('/api/getUSFs')
  },

  /** @param {{ codigo, nombre, descripcion, dominio, tipo, IdPAC }} data */
  newUSF(data) {
    return api.post('/api/newUSF', data)
  },

  deleteUSF(idUSF) {
    return api.post('/api/deleteUSF', { idUSF })
  },

  // ---------------------------------------------------------------
  // Preguntas
  // ---------------------------------------------------------------

  getPreguntas() {
    return api.get('/api/getPreguntas')
  },

  newPregunta(duda, nivel) {
    return api.post('/api/newPregunta', { duda, nivel })
  },

  deletePregunta(idPregunta) {
    return api.post('/api/deletePregunta', { idPregunta })
  },

  // ---------------------------------------------------------------
  // Relaciones Marco
  // ---------------------------------------------------------------

  /**
   * Crea relaciones control-USF-pregunta masivas.
   * @param {number} id - ID del control
   * @param {Array<{idUSF: number, preguntas: Array<{id: number}>}>} relaciones
   */
  crearRelacionCompleta(id, relaciones) {
    return api.post('/api/crearRelacionCompleta', { id, relaciones })
  },

  /**
   * Relaciona un control con preguntas cuyos USFs ya están asociados.
   * @param {Array<{id: number}>} preguntas
   * @param {number} control
   */
  crearRelacionPreguntas(preguntas, control) {
    return api.post('/api/crearRelacionPreguntas', { preguntas, control })
  },

  deleteRelacionMarco(idRelacion) {
    return api.post('/api/deleteRelacionMarcoNormativa', { idRelacion })
  },
}

export default NormativasService
