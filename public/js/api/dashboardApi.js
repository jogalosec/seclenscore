import { fetchData, postData } from "./api.js";

export async function getDashboardAssets() {
  return fetchData("./api/getDashboardActivos");
}

export async function getDashboardBia() {
  return fetchData("./api/getDashboardBia");
}

export async function getProductosAnalisis() {
  return fetchData("./api/getProductosAnalisis");
}

export async function getDashboardEcrAssets() {
  return fetchData("./api/getDashboardEcr");
}

export async function getDashboardPac() {
  return fetchData("./api/getDashboardPac");
}

export async function getDashboardKpms() {
  return fetchData("./api/getAreas");
}

export async function getDashboardErs() {
  return fetchData("./api/getDashboardErs");
}

export async function getSistemas3PS() {
  return fetchData("./api/getSistemas3PS");
}

export async function getProductosContinuidad() {
  return fetchData("./api/getProductosContinuidad");
}

export async function getDashboardGBU() {
  return fetchData("./api/getDashboardGBU");
}

export async function getSistemasTratamientoDatos(start = 0) {
  return fetchData(`./api/getTratamientoDeDatos?start=${start}&total=20`);
}

export async function getServiciosExternos(start = 0, total = 50) {
  return fetchData(`./api/getServiciosExternos?start=${start}&total=${total}`);
}

export async function deleteRelation(suscription_id) {
  return postData(`./api/deleteSuscriptionRelations`, {
    suscription_id: suscription_id,
  });
}

// Antes: editRelation(suscription_id, producto_id, servicio_id)
export async function editRelation(suscription_id, id_activo) {
  return postData(`./api/editSuscriptionRelations`, {
    suscription_id,
    id_activo,
  });
}
export async function getDashboardCriticidadProductos() {
  return fetchData("./api/getDashboardCriticidadProductos");
}