import { fetchData } from "./api.js";

export async function getAssetsWithVulnerabilities() {
  return fetchData(`./api/getAssetsWithVulnerabilities`);
}
