import { fetchData, postData } from "./api.js";
import { getHijosTipo } from "./serviciosAPI.js";

/**
 * Obtain all the normatives and its controls.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getEvaluacionesSistema(idSistema) {
  return fetchData(`./api/getEvaluacionesSistema?id=${idSistema}`);
}

/**
 * Obtain all the normatives and its controls.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getPreguntasEvaluacion(idEvaluacion) {
  return fetchData(`./api/getPreguntasEvaluacion?id=${idEvaluacion}`);
}

/**
 * Clona una evaluacion en un activo destino.
 *
 * @param {string} IdDestino - ID activo destino.
 * @param {string} IdEvaluacion - ID evaluacion a clonar.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function clonarEvaluacion(IdDestino, IdEvaluacion) {
  const url = "./api/clonarEvaluacion";
  const data = { IdDestino, IdEvaluacion };
  return postData(url, data);
}

/**
 * Inserta activos en una revisión.
 * @param {string} id - ID de la revisión.
 * @param {Object} data - Datos del formulario serializados.
 * @returns {Promise<Object>}
 */
export async function insertarActivosRevisiones(id, data) {
  return postData(`./api/insertActivosRevision`, { ...data, id });
}

/**
 * Inserta activos en un pentest.
 * @param {string} id - ID del pentest.
 * @param {Object} data - Datos del formulario serializados.
 * @returns {Promise<Object>}
 */
export async function insertarActivosPentests(id, data) {
  return postData(`./api/insertActivosPentest`, { ...data, id });
}

/**
 * Obtiene los hijos de un activo por tipo y nombre.
 * @param {string} nombre
 * @param {string} tipo
 * @returns {Promise<Object>}
 */
export async function obtenerActivosHijosTipos(nombre, tipo) {
  try {
    const response = await getHijosTipo(nombre, null, tipo);
    return response;
  } catch (error) {
    console.error("Error al obtener los activos hijos:", error);
    return { Hijos: [] };
  }
}

/**
 * Obtiene los servicios de un pentest.
 * @param {string} tipo
 * @param {string} id
 * @returns {Promise<Object>}
 */
export async function obtenerServiciosPentest(tipo, id) {
  try {
    const activos = await fetchData(`./api/obtainActivosPentest?id=${id}`);
    const response = await getHijosTipo(activos[0].id_activo, null, tipo);
    return response;
  } catch (error) {
    console.error("Error al obtener los servicios de la revisión:", error);
    return { Hijos: [] };
  }
}

/**
 * Obtiene los servicios de una revisión.
 * @param {string} tipo
 * @param {string} id
 * @returns {Promise<Object>}
 */
export async function obtenerServiciosRevision(tipo, id) {
  try {
    const activos = await fetchData(`./api/obtainActivosRevision?id=${id}`);
    const response = await getHijosTipo(activos[0].id_activo, null, tipo);
    return response;
  } catch (error) {
    console.error("Error al obtener los servicios de la revisión:", error);
    return { Hijos: [] };
  }
}

/**
 * Realiza la evaluación de un pentest.
 * @param {string} id
 * @returns {Promise<Object>}
 */
export async function realizarEvaluaciones(id) {
  return postData(`./api/realizarEvaluacion?id=${id}`);
}

/**
 * Realiza la evaluación de una revisión.
 * @param {string} id
 * @returns {Promise<Object>}
 */
export async function realizarEvaluacionRevisiones(id) {
  return postData(`./api/realizarEvaluacionRevision?id=${id}`);
}

/**
 * Obtiene todos los pentest.
 * @returns {Promise<Object>} Una promesa que resuelve con la lista de pentest y sus estadísticas.
 */
export async function obtenerTodosPentest() {
  return fetchData(`./api/obtainAllPentest`);
}

/**
 * Obtiene todas las revisiones.
 * @returns {Promise<Object>} Una promesa que resuelve con la lista de revisiones y sus estadísticas.
 */
export async function obtenerTodasRevisiones() {
  return fetchData(`./api/obtainAllRevisiones`);
}

/**
 * Obtiene los activos de un pentest.
 * @param {string} id - ID del pentest.
 * @returns {Promise<Object[]>}
 */
export async function obtenerActivosPentest(id) {
  return fetchData(`./api/obtenerActivosPentest?id_pentest=${id}`);
}

/**
 * Obtiene los activos de una revisión.
 * @param {string} id - ID de la revisión.
 * @returns {Promise<Object[]>}
 */
export async function obtenerActivosRevision(id) {
  return fetchData(`./api/obtenerActivosRevision?id_revision=${id}`);
}
