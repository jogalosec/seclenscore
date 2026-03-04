import { fetchData, postData } from "./api.js";

/**
 * Elimina un KPM de la tabla All_metricas.
 *
 * @param {string} idKPM - El id del control a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteKPMTabla(idKPM) {
  const url = "./api/deleteKPMTabla";
  const data = { idKPM };
  return postData(url, data);
}

/**
 * Edita un KPM.
 *
 * @param {string} idControl - El id del control a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function editarKPMTabla(nombre, descripcion_corta, descripcion_larga, grupo, idKPM) {
  const url = "./api/editarKPMTabla";
  const data = { nombre, descripcion_corta, descripcion_larga, grupo, idKPM };
  return postData(url, data);
}

/**
 * Obtiene todos los reporters.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getReportersKPMs() {
  return fetchData(`./api/getReportersKPMs`);
}

/**
 * Crea un reporter de KPMs.
 *
 * @param {string} idRelacion - El id del control a crear.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function crearReporterKPMs(userID, activo) {
  const url = "./api/crearReporterKPMs";
  const data = { userID, activo };
  return postData(url, data);
}

/**
 * Elimina un reporter de KPMs.
 *
 * @param {string} idRelacion - El id del control a eliminar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function deleteRelacionReporter(idRelacion) {
  const url = "./api/deleteRelacionReporter";
  const data = { idRelacion };
  return postData(url, data);
}