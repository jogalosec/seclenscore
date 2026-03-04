import {
  getInfoUser,
  getTokensUser,
  createTokenUser,
  deleteTokenUser,
} from "../api/profileApi.js";

import { displayErrorMessage } from "../utils/utils.js";

document.addEventListener("DOMContentLoaded", () => {
  document.querySelector("#new-token").addEventListener("click", function () {
    let form = `
    <form id="form-new-token" class="form-new-token">
      <div class="form-group row mb-3">
      <div class="col-4">
        <label for="tokenName">Nombre</label>
        </div>
        <div class="col-8">
          <input type="text" class="form-control" id="tokenName" name="tokenName" maxlength="50" />
        </div>
      </div>
      <div class="form-group row mb-3">
       <div class="col-4">
        <label for="tokenExpiration">Expiración</label>
        </div>
        <div class="col-8">
          <select class="form-control" id="tokenExpiration" name="tokenExpiration">
            <option value="30">30 días</option>
            <option value="60">60 días</option>
            <option value="90">90 días</option>
          </select>
        </div>
      </div>
    </form>
  `;
    showModalWindow(
      "Crear nuevo token",
      form,
      createToken,
      "Cancelar",
      "Crear"
    );
  });

  if (document.querySelector("#userEmail").innerHTML.trim() == "") {
    initializeProfile();
  }

  if (document.querySelector("#table-tokens-body").innerHTML.trim() == "") {
    initializeProfileTokens();
  }

  document
    .querySelector("#table-tokens-body")
    .addEventListener("click", function (event) {
      if (event.target.classList.contains("delete-token")) {
        const tokenId = event.target.getAttribute("data-token");
        showModalWindow(
          "Confirmar eliminación",
          "¿Está seguro de que desea eliminar este token?",
          () => deleteToken(tokenId),
          "Cancelar",
          "Eliminar"
        );
      }
    });
});

async function initializeProfile() {
  const dataUsers = await getInfoUser();
  updateProfile(dataUsers);
}

async function initializeProfileTokens() {
  const dataUsers = await getTokensUser();
  updateProfileTokens(dataUsers);
}

async function updateProfile(data) {
  if (data.error) {
    displayErrorMessage(data, "#info-user");
    return;
  }
  const userEmail = document.querySelector("#userEmail");
  const userID = document.querySelector("#userId");
  const userRoles = document.querySelector("#userRoles");

  let roleElement = document.createElement("div");
  for (let role of data.user.roles) {
    let label = document.createElement("label");
    label.classList.add("badge-role");
    label.setAttribute("data-role", role.id);
    if (role.name == null) {
      label.textContent = "Sin rol";
      label.style.backgroundColor = "#9b9b9b";
    } else {
      label.textContent = role.name;
      label.style.backgroundColor = role.color;
    }
    roleElement.appendChild(label);
  }

  userEmail.innerHTML = data.user.email;
  userID.innerHTML = data.user.id;
  userRoles.innerHTML = roleElement.innerHTML;
}

async function updateProfileTokens(data) {
  if (data.error) {
    displayErrorMessage(data, "#table-tokens-container");
    return;
  }
  const tableTokensBody = document.querySelector("#table-tokens-body");
  tableTokensBody.innerHTML = "";
  if (data.tokens !== undefined) {
    data.tokens.forEach((token) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${token.name}</td>
        <td>${token.created}</td>
        <td>${token.expired}</td>
        <td><button class="btn btn-sm btn-danger delete-token" data-token="${token.id}">Eliminar</button></td>
      `;
      tableTokensBody.appendChild(tr);
    });
  }
}

async function createToken() {
  cerrarModal();
  const tokenName = document.querySelector("#tokenName").value;
  const tokenExpiration = document.querySelector("#tokenExpiration").value;

  const data = await createTokenUser(tokenName, tokenExpiration);

  if (data.error) {
    displayErrorMessage(data, "#form-new-token");
    return;
  }

  // mostrar el token generado en un input que no permita editar y con un icono de copiar al porta papeles
  let content = `
    <div class="form-group mb-4">
      <label for="token" class="form-label mb-2">
        Guarde el token en un lugar seguro y asegúrese de copiarlo, ya que no se volverá a mostrar.
      </label>
      <div class="input-group mb-3">
        <input 
          type="text" 
          class="form-control text-center" 
          id="token" 
          value="${data.token}" 
          readonly
          maxlength="100"
        />
      </div>
    </div>
  `;

  showModalWindow(
    "Token creado",
    content,
    null,
    "Aceptar",
    null,
    initializeProfileTokens
  );
}

async function deleteToken(tokenId) {
  cerrarModal();
  const data = await deleteTokenUser(tokenId);
  if (data.error) {
    displayErrorMessage(data, "#cuerpoModal");
    return;
  }

  showModalWindow(
    "Token eliminado",
    "El token ha sido eliminado correctamente.",
    null,
    "Aceptar",
    null,
    initializeProfileTokens
  );
}
