// api.js

/**
 * Realiza una petición GET a una URL y devuelve los datos de la respuesta.
 * @param {string} url - La URL del endpoint de la API.
 * @returns {Promise<any>} - Una promesa que resuelve con los datos de la respuesta.
 */
export async function getData(url) {
  const response = await fetch(url);
  return await response.json();
}

/**
 * Makes a POST request to the specified URL with the provided data.
 * @param {string} url - The URL of the API endpoint.
 * @param {Object} data - The data to send in the request body.
 * @returns {Promise<any>} - A promise that resolves with the response data.
 * @throws {Error} - If an error occurs during the request process.
 */
export async function postData(url, data) {
  const response = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  });
  return await response.json();
}

/**
 * Makes a POST request to the specified URL with the provided data and return a document.
 * @param {string} PostUrl - The URL of the API endpoint.
 * @param {Object} data - The data to send in the request body.
 * @returns {Promise<any>} - A promise that resolves with the response data.
 * @throws {Error} - If an error occurs during the request process.
 */
export async function postDataDocument(PostUrl, params, fileName) {
  const response = await fetch(PostUrl, {
    method: 'POST',
    body: params
  });

  if (!response.ok) throw new Error('Error al descargar el documento');

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
}

/**
 * Fetches data from the specified URL.
 *
 * @param {string} url - The URL to fetch data from.
 * @returns {Promise<any>} - A promise that resolves to the fetched data.
 * @throws {Error} - If an error occurs during the data fetching process.
 */
export async function fetchData(url) {
  const data = await getData(url);
  return data;
}
