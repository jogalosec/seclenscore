import { fetchData } from "./api.js";

export async function getTablaBIA() {
  return fetchData(`./api/getDatosBia`);
}

export async function getPacsContinuidad() {
  return fetchData(`./api/getSeguimientoByPacID?id=18&modulo=continuidad`);
}
