import { fetchData, postData } from "./api.js";

/**
 * Retrieves users from the API.
 * @returns {Promise<any>} A promise that resolves with the fetched users.
 */
export async function getUsers() {
  return fetchData("./api/getUsers");
}

/**
 * Fetches user data based on the provided user ID.
 *
 * @param {number|string} id - The ID of the user to fetch.
 * @returns {Promise<Object>} A promise that resolves to the user data.
 */
export async function getUser(id) {
  return fetchData(`./api/getUser?id=${id}`);
}

/**
 * Retrieves the roles from the server.
 * @returns {Promise} A promise that resolves with the roles data.
 */
export async function getRoles() {
  return fetchData("./api/getRoles");
}

/**
 * Fetches endpoints based on the provided role ID.
 *
 * @param {string} roleId - The ID of the role to fetch endpoints for.
 * @param {boolean} includeAll - Whether to include all endpoints or only those assigned to the role.
 * @returns {Promise} A promise that resolves to the fetched data.
 */
export async function getEndpointsByRole(roleId, includeAll = false) {
  const url = `./api/getEndpointsByRole?id=${roleId}&includeAll=${includeAll}`;
  return fetchData(url);
}

/**
 * Edits the role endpoints by sending a POST request to the server.
 *
 * @param {string} roleId - The ID of the role to edit.
 * @param {Array<string>} endpoints - The list of endpoints to be edited.
 * @param {boolean} allow - Flag indicating whether the endpoints are allowed or not.
 * @returns {Promise<Object>} The response from the server.
 */
export async function editEndpointsByRole(roleId, endpoints, allow) {
  const url = "./api/editEndpointsByRole";
  const data = { roleId, endpoints, allow };
  return postData(url, data);
}

/**
 * Retrieves the endpoints from the server.
 * @returns {Promise} A promise that resolves with the fetched data.
 */
export async function getEndpoints() {
  return fetchData("./api/getEndpoints");
}

/**
 * Creates a new user.
 *
 * @param {string} email - The email of the user.
 * @param {string} rol - The role of the user.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function createUser(email, rol) {
  const url = "./api/newUser";
  const data = { email, rol };
  return postData(url, data);
}

/**
 * Creates a new role.
 * @param {string} name - The name of the role.
 * @param {string} color - The color of the role.
 * @returns {Promise} A promise that resolves with the result of the API call.
 */
export async function createRol(name, color, additionalAccess) {
  const url = "./api/newRol";
  const data = { name, color, additionalAccess };
  return postData(url, data);
}

/**
 * Edits the role of a user.
 * @param {string} id - The ID of the user.
 * @param {string} name - The new name of the role.
 * @param {string} color - The new color of the role.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function editRol(id, name, color, additionalAccess) {
  const url = "./api/editRol";
  const data = { id, name, color, additionalAccess };
  return postData(url, data);
}

/**
 * Deletes a role by its ID.
 *
 * @param {number} id - The ID of the role to delete.
 * @returns {Promise<any>} A promise that resolves with the response of the delete operation.
 */
export async function deleteRol(id) {
  const url = "./api/deleteRol";
  const data = { id };
  return postData(url, data);
}

/**
 * Deletes a user by their ID.
 *
 * @param {string} id - The ID of the user to delete.
 * @returns {Promise<any>} A promise that resolves with the response of the delete operation.
 */
export async function deleteUser(id) {
  const url = "./api/deleteUser";
  const data = { id };
  return postData(url, data);
}

/**
 * Edit User by ID.
 *
 * @param {string} id - The ID of the user to edit.
 * @param {string} rol - The role of the user.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function editUser(id, rol) {
  const url = "./api/editUser";
  const data = { id, rol };
  return postData(url, data);
}

/**
 * Obtain the Jira issue.
 *
 * @param {string} key - The key of the jira issue.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function obtenerIssue(key) {
  const url = `./api/obtenerIssue?jiraKey=${key}`;
  return fetchData(url);
}

/**
 * Obtain the Jira comments.
 *
 * @param {string} key - The key of the jira issue.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function obtenerComentarios(key) {
  const url = `./api/obtenerComentarios?jiraKey=${key}`;
  return fetchData(url);
}

/**
 * Obtain all the Jira issues.
 *
 * @param {string} start - The start point of the pagination.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function mostrarIssues(start) {
  const url = `./api/mostrarIssues?start=${start}`;
  return fetchData(url);
}

/**
 * Create a comment in a jira issue.
 *
 * @param {string} key - The jira key.
 * @param {string} comentario - The comment of the jira key.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function enviarComentario(key, comentario) {
  const url = `./api/enviarComentario?jiraKey=${key}&comentario=${comentario}`;
  return fetchData(url);
}

/**
 * Update the status of a issue.
 *
 * @param {string} action - The new state of the jira issue.
 * @param {string} key - The jira issue key.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function actualizarStatus(accion, key) {
  const url = `./api/actualizarStatus?accion=${accion}&key=${key}`;
  return fetchData(url);
}

/**
 * Obtain the jira users of a project.
 *
 * @param {string} proyecto - The jira project.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function obtainUsers(proyecto) {
  const url = `./api/obtainUsers?proyecto=${proyecto}`;
  return fetchData(url);
}

/**
 * Obtain the jira users of a project.
 *
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function obtenerCampos() {
  const url = `./api/obtenerCampos`;
  return fetchData(url);
}

/**
 * Delete a jira issue.
 *
 * @param {string} key - The jira issue key.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function delIssue(key) {
  const url = `./api/delIssue?key=${key}`;
  return fetchData(url);
}

/**
 * Obtain a reporter ID.
 *
 * @param {string} reporterName - The name of the reporter.
 * @returns {Promise} A promise that resolves with the response data.
 */
export async function obtenerReporterID(reporterName) {
  const url = `./api/obtenerReporterID?reporterName=${reporterName}`;
  return fetchData(url);
}
