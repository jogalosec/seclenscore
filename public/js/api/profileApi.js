import { fetchData, postData } from "./api.js";

export async function getInfoUser() {
  return fetchData("./api/getInfoUser");
}

export async function getTokensUser() {
  return fetchData("./api/getTokensUser");
}

export async function createTokenUser(tokenName, tokenExpiration) {
  const url = "./api/createToken";
  const data = { tokenName, tokenExpiration };
  return postData(url, data);
}

export async function deleteTokenUser(tokenId) {
  const url = "./api/deleteToken";
  const data = { tokenId };
  return postData(url, data);
}
