import { fetchData } from "./api.js";

/**
 * Obtiene el arbol de servicios.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getArbolServicios() {
  return fetchData(`./api/downloadActivosTree`);
}

/**
 * Obtiene los hijos de un tipo.
 *
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getActivosTipo(tipo) {
  return fetchData(`./api/getActivosTipo?tipo=${tipo}`);
}

/**
 * Obtiene los hijos de un tipo.
 * @param {Object} params - Parámetros para la consulta.
 * Puede ser { idPadre, tipo } o { tipo, nombre }
 * @returns {Promise<Object>} Una promesa que resuelve con los datos.
 */
export async function getHijosTipo(nombre, idPadre, tipo) {
  let url = "./api/getHijosTipo?";
  if (idPadre && tipo) {
    url += `idPadre=${encodeURIComponent(idPadre)}&tipo=${encodeURIComponent(
      tipo
    )}`;
  } else if (tipo && nombre) {
    url += `tipo=${encodeURIComponent(tipo)}&nombre=${encodeURIComponent(
      nombre
    )}`;
  }
  return fetchData(url);
}
