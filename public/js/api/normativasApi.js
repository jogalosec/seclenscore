import { fetchData, postData } from "./api.js";

/**
 * Obtain all the normatives and its controls.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getNormativas() {
  return fetchData(`./api/getNormativas`);
}

/**
 * Obtiene todas las preguntas.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getPreguntas() {
  return fetchData(`./api/getPreguntas`);
}

/**
 * Obtiene todos los PACs.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getPACs() {
  return fetchData(`./api/getProyectos`);
}

/**
 * Obtiene todos los USFs.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getDominiosUnicosControles() {
  return fetchData(`./api/getDominiosUnicosControles`);
}

/**
 * Obtiene todos los USFs.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getUSFs() {
  return fetchData(`./api/getUSFs`);
}

/**
 * Crea una nueva pregunta.
 *
 * @param {json} infoRelacion - Información.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function crearRelacionCompleta(infoRelacion) {
  const url = "./api/crearRelacionCompleta";
  return postData(url, infoRelacion);
}

/**
 * Crea una nueva pregunta.
 *
 * @param {string} duda - La duda de la pregunta.
 * @param {integer} nivel - El nivel de la pregunta.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function newPregunta(duda, nivel) {
  const url = "./api/newPregunta";
  const data = { duda, nivel };
  return postData(url, data);
}

/**
 * Crea una nueva normativa.
 *
 * @param {string} nombre - El nombre de la normativa.
 * @param {boolean} enabled - Si la normativa está enabled.
 * @param {integer} idNormativa - El ID de la normativa.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function editNormativa(nombre, enabled, idNormativa) {
  const url = "./api/editNormativa";
  const data = { nombre, enabled, idNormativa };
  return postData(url, data);
}

/**
 * Relaciona un control con unas preguntas con USF ya asociado.
 *
 * @param {string} preguntas - Las preguntas a relacionar.
 * @param {string} Control - El nombre del control.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function crearRelacionPreguntas(preguntas, control = "Test") {
  const url = "./api/crearRelacionPreguntas";
  const data = { preguntas, control };
  return postData(url, data);
}

/**
 * Crea una nueva normativa.
 *
 * @param {string} nombre - El nombre de la normativa.
 * @param {integer} version - La versión de la normativa.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function newNormativa(nombre, version) {
  const url = "./api/newNormativa";
  const data = { nombre, version };
  return postData(url, data);
}

/**
 * Crea un nuevo USF.
 *
 * @param {string} codigo - El código del USF.
 * @param {string} nombre - El nombre del USF.
 * @param {string} descripcion - La descripción del USF.
 * @param {string} dominio - El dominio del USF.
 * @param {string} IdPAC - El ID del PAC asociado.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function newUSF(
  codigo,
  nombre,
  descripcion,
  dominio,
  tipo,
  IdPAC
) {
  const url = "./api/newUSF";
  const data = { codigo, nombre, descripcion, dominio, tipo, IdPAC };
  return postData(url, data);
}

/**
 * Crea un nuevo control.
 *
 * @param {string} codigo - El código del control.
 * @param {string} nombre - El nombre del control.
 * @param {string} descripcion - La descripción del control.
 * @param {string} dominio - El dominio del control.
 * @param {string} idNormativa - El id de la normativa.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function newControl(
  codigo,
  nombre,
  descripcion,
  dominio,
  idNormativa
) {
  const url = "./api/newControl";
  const data = { codigo, nombre, descripcion, dominio, idNormativa };
  return postData(url, data);
}

/**
 * Elimina un control de una normativa.
 *
 * @param {string} idControl - El id del control a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteControl(idControl) {
  const url = "./api/deleteControl";
  const data = { idControl };
  return postData(url, data);
}

/**
 * Elimina una normativa.
 *
 * @param {string} idNormativa - El id de la normativa a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteNormativa(idNormativa) {
  const url = "./api/deleteNormativa";
  const data = { idNormativa };
  return postData(url, data);
}

/**
 * Elimina una pregunta.
 *
 * @param {string} idPregunta - El id de la pregunta a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deletePregunta(idPregunta) {
  const url = "./api/deletePregunta";
  const data = { idPregunta };
  return postData(url, data);
}

/**
 * Elimina un USF.
 *
 * @param {string} idPregunta - El id del USF a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteUSF(idUSF) {
  const url = "./api/deleteUSF";
  const data = { idUSF };
  return postData(url, data);
}

/**
 * Elimina una relación.
 *
 * @param {string} idRelacion - El id de la relación a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteRelacionMarcoNormativa(idRelacion) {
  const url = "./api/deleteRelacionMarcoNormativa";
  const data = { idRelacion };
  return postData(url, data);
}

/**
 * Obtiene todos los USFs.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getPreguntasByUSF(idUSF) {
  return fetchData(`./api/getPreguntasByUSF?idUSF=${idUSF}`);
}
