import { fetchData, postData } from "./api.js";

export async function getPrismaCloud() {
  return fetchData("./api/getPrismaCloud");
}

export async function getPrismaCloudFromTenant(tenantId) {
  return fetchData(`./api/getPrismaCloudFromTenant?tenantId=${tenantId}`);
}

export async function getPrismaAlertByCloud(cloudName) {
  return fetchData(`./api/getPrismaAlertByCloud?cloudName=${cloudName}`);
}

export async function getOsaByType(type) {
  return fetchData(`./api/getOsaByType?type=${type}`);
}

export async function getAlertasRevisionByID(id) {
  return fetchData(`./api/getAlertasRevisionByID?id=${id}`);
}

export async function saveEvalOsa(form) {
  const url = "./api/saveEvalOsa";
  return postData(url, form);
}

export async function dismissPrismaAlert(alerts, comment) {
  const url = "./api/dismissPrismaAlert";
  return postData(url, { alerts, comment });
}

export async function unassignPrismaAlertToReview(alerts, revisionId) {
  const url = "./api/unassignPrismaAlertToReview";
  return postData(url, { alerts, revisionId });
}

export async function getOsaEvalByRevision(revisionId) {
  return fetchData(`./api/getOsaEvalByRevision?id=${revisionId}`);
}

export async function crearRelacionSuscripcion(form) {
  const url = "./api/crearRelacionSuscripcion";
  return postData(url, form);
}

export async function obtainAlertsByReview(revisionId) {
  const url = `./api/getPrismaAlertByReview?id=${revisionId}`;
  return fetchData(url);
}

export async function getPrismaSusInfo(tenantId, cloud) {
  const url = `./api/getPrismaSusInfo?tenantId=${tenantId}&cloud=${cloud}`;
  return fetchData(url);
}

export async function getReviews() {
  const url = "./api/getRevisiones";
  return fetchData(url);
}

export async function getReviewById(id) {
  const url = `./api/getInfoRevisionByID?id=${id}`;
  return fetchData(url);
}

export async function getIssuesEAS(startAt, maxResults) {
  const url = `./api/getIssuesEAS?startAt=${startAt}&maxResults=${maxResults}`;
  return fetchData(url);
}

export async function crearRevision(id, formValues) {
  const url = `./api/crearRevision?idCloud=${id}`;
  return postData(url, formValues);
}

export async function crearRevisionSinActivos(formValues) {
  const url = `./api/crearRevisionSinActivos`;
  return postData(url, formValues);
}

export async function reportJira(issueData) {
  const url = "./api/newIssueArquitectura";
  return postData(url, issueData);
}

export async function getMailsEAS(endpointName, revisionId) {
  const url = `./api/getEmails?endpointName=${endpointName}&revisionId=${revisionId}`;
  return fetchData(url);
}

export async function getRelacionSuscripcion(id) {
  const url = `./api/getRelacionSuscripcion?idSuscripcion=${id}`;
  return fetchData(url);
}

export async function cerrarRevisionEAS(id) {
  const url = `./api/cerrarRevision?id=${id}`;
  return fetchData(url);
}

export async function checkAlertsStatus(id_issue) {
  const url = `./api/checkAlertStatusByIssueId?id_issue=${id_issue}`;
  return fetchData(url);
}
