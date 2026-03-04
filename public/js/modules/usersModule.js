import {
  getUsers,
  createUser,
  editUser,
  deleteUser,
  createRol,
  getEndpointsByRole,
  editEndpointsByRole,
  getRoles,
  editRol,
  deleteRol,
} from "../api/userApi.js";

import {
  finalLoading,
  displayErrorMessage,
  extractHexColor,
} from "../utils/utils.js";
import Constants from "./constants.js";

const options = Constants.OPTIONS_TABLE;

document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#users-grid-data").innerHTML.trim() == "") {
    initializeUsers();
  }

  document.querySelector(".users").addEventListener("click", function () {
    if (
      document.querySelector("#users-grid-data") != null &&
      document.querySelector("#users-grid-data").innerHTML.trim() == ""
    ) {
      initializeUsers();
    }
  });

  document.querySelector(".roles").addEventListener("click", function () {
    if (
      document.querySelector("#roles-grid-data") != null &&
      document.querySelector("#roles-grid-data").innerHTML.trim() == ""
    ) {
      initializeRoles();
    }
  });

  document.querySelector(".endpoints").addEventListener("click", function () {
    if (
      document.querySelector("#endpoints-grid-data") != null &&
      document.querySelector("#endpoints-grid-data").innerHTML.trim() == ""
    ) {
      initializeEndpoints();
    }
  });
});

async function initializeUsers() {
  finalLoading("#usuariosLoading", "loading");
  const table = document.querySelector("#table-users");
  $(table).bootgrid("clear").bootgrid("destroy");
  const dataUsers = await getUsers();
  updateUsers(dataUsers);
}

async function newUser() {
  const boton = document.getElementById("botonAceptar");
  boton.disabled = true;
  const email = document.getElementById("email").value;
  const selectedRoles = Array.from(
    document.querySelectorAll("#selectedRoles .badge-role-selectable")
  ).map((role) => role.getAttribute("data-role"));

  if (
    !email ||
    !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)
  ) {
    displayErrorMessage(
      { message: "Por favor, introduce un email válido." },
      ".display-errors"
    );
    return;
  }

  if (selectedRoles.length === 0) {
    displayErrorMessage(
      { message: "Por favor, selecciona al menos un rol." },
      ".display-errors"
    );
    return;
  }
  createUser(email, selectedRoles)
    .then((responseCreateUser) => {
      boton.disabled = false;
      if (responseCreateUser.error) {
        displayErrorMessage(responseCreateUser, ".display-errors");
        return;
      }
      cerrarModal();
      initializeUsers();
    })
    .catch((error) => {
      boton.disabled = false;
      console.error("Error al crear el usuario:", error);
    });
}

async function updateUsers(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaUsers");
    return;
  }
  if (document.querySelector(".tablaUsers > .spinner-animation")) {
    document.querySelector(".tablaUsers > .spinner-animation").remove();
  }
  const table = document.querySelector("#table-users");
  $(table).bootgrid({
    ...options,
    formatters: {
      commands: function (column, row) {
        return (
          `<button type='button' class='btn btn-primary command-edit' data-row-id="${row.id}">Editar</button>   ` +
          `<button type='button' class='btn btn-danger command-delete' data-row-id="${row.id}">Eliminar</button>`
        );
      },
    },
  });
  $(table).bootgrid("clear");
  agregarBotonNuevo("table-users-header", "btn-new-user", "Nuevo usuario");
  for (let user of data.usuarios) {
    let roleElement = document.createElement("div");
    for (let role of user["roles"]) {
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
    user.roles = roleElement.innerHTML;
    $(table).bootgrid("append", [user]);
  }

  document
    .querySelector(".btn-new-user")
    .addEventListener("click", async () => {
      let formNewUser = `<form id="newUserForm">
      <div class="form-group row mb-3">
        <div class="col-4">
          <label for="email">Email</label>
        </div>
        <div class="col-8">
          <input type="email" class="form-control" id="email" required>
        </div>
      </div>
      <div class="form-group row mb-3">
        <div class="col-4">
          <label for="roles">Roles disponibles</label>
        </div>
        <div class="col-8">
          <div id="rolesContainer" class="d-flex flex-wrap form-control" style="height: auto; min-height: 40px; border: 1px solid #ced4da; padding: 10px;">
            <div class="spinner-a">${Constants.getLoadingHtml(30)}</div>
          </div>
        </div>
      </div>
      <div class="form-group row mb-3">
        <div class="col-4">
          <label for="selectedRoles">Roles seleccionados</label>
        </div>
        <div class="col-8">
          <div id="selectedRoles" class="form-control" style="height: auto; min-height: 40px; border: 1px solid #ced4da; padding: 10px;"></div>
        </div>
      </div>
    </form><div class="display-errors"></div>`;
      showModalWindow("Nuevo usuario", formNewUser, newUser, "Cerrar", "Crear");

      const rolesContainer = document.getElementById("rolesContainer");
      const spinner = rolesContainer.querySelector(".spinner-a");

      let roles = await getRoles();

      if (spinner) {
        rolesContainer.removeChild(spinner);
      }

      if (roles.error) {
        displayErrorMessage(roles, ".display-errors");
        return;
      }

      const selectedRolesContainer = document.getElementById("selectedRoles");

      populateRoles(roles.roles, rolesContainer, selectedRolesContainer);
    });

  $(table)
    .bootgrid()
    .on("loaded.rs.jquery.bootgrid", function () {
      $(table)
        .find(".command-edit")
        .on("click", function (e) {
          const idUser = this.dataset.rowId;
          const user = data.usuarios.find((user) => user.id == idUser);
          editUserModal(user);
        })
        .end()
        .find(".command-delete")
        .on("click", function (e) {
          const idUser = this.dataset.rowId;
          const user = data.usuarios.find((user) => user.id == idUser);
          deleteUserHandler(user);
        });
    });
  finalLoading("#usuariosLoading", "check");
  $(".tablaUsers").removeClass("mshide");
}

async function initializeRoles() {
  finalLoading("#rolesLoading", "loading");
  const table = document.querySelector("#table-roles");
  $(table).bootgrid("clear").bootgrid("destroy");
  const dataRoles = await getRoles();
  updateRoles(dataRoles);
}

async function updateRoles(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaRoles");
    return;
  }
  if (document.querySelector(".tablaRoles > .spinner-animation")) {
    document.querySelector(".tablaRoles > .spinner-animation").remove();
  }
  const table = document.querySelector("#table-roles");
  $(table).bootgrid({
    ...options,
    formatters: {
      commands: function (column, row) {
        let buttons = "";
        if (row.editable) {
          buttons += `<button type="button" class="btn btn-primary command-edit" data-row-id="${row.id}">Editar</button>   `;
        }
        if (row.deletable === 1) {
          buttons += `<button type='button' class='btn btn-danger command-delete' data-row-id="${row.id}">Eliminar</button>`;
        }
        return buttons;
      },
    },
  });
  $(table).bootgrid("clear");
  agregarBotonNuevo("table-roles-header", "btn-new-rol", "Crear rol");

  const rolesToAppend = [];

  for (let role of data.roles) {
    role.color = `<div class="circle-role" style="background-color:${role.color}"></div>${role.color}`;
    if (role.additional_access == 1) {
      role.additional_access =
        "<label class='badge-11cert badge-role-allow'>Permitido</label>";
    } else {
      role.additional_access =
        "<label class='badge-11cert badge-role-deny'>Denegado</label>";
    }
    rolesToAppend.push(role);
  }
  $(table).bootgrid("append", rolesToAppend);

  document.querySelector(".btn-new-rol").addEventListener("click", () => {
    let formNewRole = `
      <form id="newRoleForm">
        <div class="form-group row mb-3">
          <label for="roleName" class="col-sm-3 col-form-label">Nombre</label>
          <div class="col-sm-9">
            <input type="text" class="form-control" id="roleName" required>
          </div>
        </div>
        <div class="form-group row mb-3">
          <label for="roleColor" class="col-sm-3 col-form-label">Color</label>
          <div class="col-sm-9" style="display: flex; align-items: center;">
            <input type="color" class="form-control" id="roleColor" required>
            <input type="text" class="form-control" id="roleColorHex" placeholder="#000000" required>
          </div>
        </div>
        <div class="form-group row mb-3">
          <label for="roleColor" class="col-sm-3 col-form-label">Accesos adicionales</label>
          <div class="col-sm-9 d-flex align-items-center">
            <input type="checkbox" class="form-check-input" id="additionalAccess" name="additionalAccess">
          </div>
        </div>
      </form><div class="display-errors"></div>`;

    showModalWindow("Crear rol", formNewRole, createRole, "Cerrar", "Crear");

    const roleColorInput = document.getElementById("roleColor");
    const roleColorHexInput = document.getElementById("roleColorHex");

    roleColorInput.addEventListener("input", () => {
      roleColorHexInput.value = roleColorInput.value;
    });

    roleColorHexInput.addEventListener("input", () => {
      if (/^#[0-9A-F]{6}$/i.test(roleColorHexInput.value)) {
        roleColorInput.value = roleColorHexInput.value;
      }
    });
  });

  $(table)
    .bootgrid()
    .on("loaded.rs.jquery.bootgrid", function () {
      $(table)
        .find(".command-edit")
        .on("click", function (e) {
          const idRole = this.dataset.rowId;
          const role = data.roles.find((role) => role.id == idRole);
          editRole(role);
        })
        .end()
        .find(".command-delete")
        .on("click", function (e) {
          const idRole = this.dataset.rowId;
          const role = data.roles.find((role) => role.id == idRole);
          deleteRole(role);
        });
    });

  finalLoading("#rolesLoading", "check");
  $(".tablaRoles").removeClass("mshide");
}

async function createRole() {
  const roleName = document.getElementById("roleName").value;
  const roleColor = document.getElementById("roleColor").value;
  const additionalAccess = document.getElementById("additionalAccess").checked;

  document.querySelector(".display-errors").innerHTML = "";

  if (!roleName || !roleColor) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }
  try {
    const data = await createRol(roleName, roleColor, additionalAccess);
    if (data.error) {
      displayErrorMessage(data, ".display-errors");
      return;
    }
    cerrarModal();
    initializeRoles();
    const existingSelectRoles = document.querySelector(".select-roles");
    if (existingSelectRoles) {
      existingSelectRoles.remove();
    }
    initializeEndpoints();
  } catch (e) {
    console.error(e);
  }
}

function editRole(role) {
  console.log(role);
  let color = extractHexColor(role.color);
  let formEditRole = `
    <form id="editRoleForm">
      <input type="hidden" name="role_id" id="roleId" value="${role.id}">
      <div class="form-group row mb-3">
        <label for="roleName" class="col-sm-3 col-form-label">Nombre</label>
        <div class="col-sm-9">
          <input type="text" class="form-control" id="roleName" name="name" value="${role.name}" required>
        </div>
      </div>
      <div class="form-group row mb-3">
        <label for="roleColor" class="col-sm-3 col-form-label">Color</label>
        <div class="col-sm-9" style="display: flex; align-items: center;">
          <input type="color" class="form-control" id="roleColor" value="${color}">
          <input type="text" class="form-control" id="roleColorHex" name="color" value="${color}">
        </div>
      </div>
      <div class="form-group row mb-3">
          <label for="roleColor" class="col-sm-3 col-form-label">Accesos adicionales</label>
          <div class="col-sm-9 d-flex align-items-center">
            <input type="checkbox" class="form-check-input" id="additionalAccess" name="additionalAccess">
          </div>
        </div>
    </form><div class="display-errors"></div>`;

  showModalWindow("Editar rol", formEditRole, updateRole, "Cerrar", "Guardar");

  const roleColorInput = document.getElementById("roleColor");
  const roleColorHexInput = document.getElementById("roleColorHex");
  const additionalAccessCheckbox = document.getElementById("additionalAccess");

  if (
    role.additional_access ===
    "<label class='badge-11cert badge-role-deny'>Denegado</label>"
  ) {
    additionalAccessCheckbox.checked = false;
  } else {
    additionalAccessCheckbox.checked = true;
  }
  roleColorInput.addEventListener("input", () => {
    roleColorHexInput.value = roleColorInput.value;
  });

  roleColorHexInput.addEventListener("input", () => {
    if (/^#[0-9A-F]{6}$/i.test(roleColorHexInput.value)) {
      roleColorInput.value = roleColorHexInput.value;
    }
  });
}

function deleteRole(role) {
  showModalWindow(
    "<div>Eliminar rol",
    `¿Estás seguro de eliminar el rol ${role.name}?</div><div class="display-errors"></div>`,
    async () => {
      const boton = document.getElementById("botonAceptar");
      boton.disabled = true;
      try {
        const data = await deleteRol(role.id);
        boton.disabled = false;

        if (data.error) {
          displayErrorMessage(data, ".display-errors");
          return;
        }
        cerrarModal();
        initializeRoles();
        initializeUsers();
      } catch (e) {
        boton.disabled = false;
        displayErrorMessage(e, ".display-errors");
      }
    },
    "Cancelar",
    "Eliminar"
  );
}

async function editUserModal(user) {
  let formEditUser = `
    <form id="editUserForm">
      <input type="hidden" name="user_id" id="userId" value="${user.id}">
      <div class="form-group row mb-3">
        <label for="email" class="col-sm-3 col-form-label">Email</label>
        <div class="col-sm-9">
          <input type="hidden" class="form-control" name="id" value="${
            user.id
          }" required disabled>
          <input type="email" class="form-control" id="email" name="email" value="${
            user.email
          }" required disabled>
        </div>
      </div>
      <div class="form-group row mb-3">
        <label for="roles" class="col-sm-3 col-form-label">Roles disponibles</label>
        <div class="col-sm-9">
          <div id="rolesContainer" class="d-flex flex-wrap form-control" style="height: auto; min-height: 40px; border: 1px solid #ced4da; padding: 10px;">
            <div class="spinner-a">${Constants.getLoadingHtml(30)}</div>
          </div>
        </div>
      </div>
      <div class="form-group row mb-3">
        <label for="selectedRoles" class="col-sm-3 col-form-label">Roles seleccionados</label>
        <div class="col-sm-9">
          <div id="selectedRoles" class="form-control" style="height: auto; min-height: 40px; border: 1px solid #ced4da; padding: 10px;">
            <div class="spinner-a">${Constants.getLoadingHtml(30)}</div>
          </div>
        </div>
      </div>
    </form><div class="display-errors"></div>`;
  showModalWindow(
    "Editar usuario",
    formEditUser,
    updateUser,
    "Cerrar",
    "Guardar"
  );

  const [roles] = await Promise.all([getRoles()]);
  const rolesContainer = document.getElementById("rolesContainer");
  const selectedRoles = document.getElementById("selectedRoles");

  ocultarSpinner(rolesContainer);
  ocultarSpinner(selectedRoles);

  if (roles.error) {
    displayErrorMessage(roles, ".display-errors");
    return;
  }

  const userRoles = extractLabels(user.roles);
  const selectedRolesContainer = document.getElementById("selectedRoles");
  populateRoles(userRoles, rolesContainer, selectedRolesContainer);

  const filteredRoles = roles.roles.filter((role) => {
    return !userRoles.find((userRole) => userRole.name === role.name);
  });

  populateRoles(filteredRoles, rolesContainer, selectedRolesContainer);
}

async function deleteUserHandler(user) {
  showModalWindow(
    "<div>Eliminar usuario",
    `¿Estás seguro de eliminar el usuario con email ${user.email}?</div><div class="display-errors"></div>`,
    async () => {
      const boton = document.getElementById("botonAceptar");
      boton.disabled = true;
      try {
        const data = await deleteUser(user.id);
        boton.disabled = false;

        if (data.error) {
          displayErrorMessage(data, ".display-errors");
          return;
        }
        cerrarModal();
        initializeUsers();
      } catch (e) {
        boton.disabled = false;
        displayErrorMessage(e, ".display-errors");
      }
    },
    "Cancelar",
    "Eliminar"
  );
}

async function updateUser() {
  const boton = document.getElementById("botonAceptar");
  boton.disabled = true;
  const userId = document.getElementById("userId").value;
  const selectedRoles = Array.from(
    document.querySelectorAll("#selectedRoles .badge-role-selectable")
  ).map((role) => role.getAttribute("data-role"));

  editUser(userId, selectedRoles).then((responseEditUser) => {
    boton.disabled = false;
    if (responseEditUser.error) {
      displayErrorMessage(responseEditUser, ".display-errors");
      return;
    }
    cerrarModal();
    initializeUsers();
  });
}

async function updateRole() {
  const boton = document.getElementById("botonAceptar");
  boton.disabled = true;
  const roleId = document.getElementById("roleId").value;
  const roleName = document.getElementById("roleName").value;
  const roleColor = document.getElementById("roleColor").value;
  const additionalAccess = document.getElementById("additionalAccess").checked;
  // clear errors
  document.querySelector(".display-errors").innerHTML = "";

  if (!roleName || !roleColor) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }
  try {
    const data = await editRol(roleId, roleName, roleColor, additionalAccess);
    boton.disabled = false;
    if (data.error) {
      displayErrorMessage(data, ".display-errors");
      return;
    }
    cerrarModal();
    initializeUsers();
    initializeRoles();
  } catch (e) {
    displayErrorMessage(e, ".display-errors");
  }
}

async function initializeEndpoints() {
  let roleId;
  const selectRolesElement = document.querySelector(".select-roles");
  if (selectRolesElement) {
    roleId = selectRolesElement.value;
  } else {
    roleId = 1;
  }
  setEndpoints(roleId);
}

async function updateEndpoints(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaEndpoints");
    return;
  }

  const table = document.querySelector("#table-endpoints");

  let options = {
    ...Constants.OPTIONS_TABLE,
    selection: true,
    multiSelect: true,
    rowSelect: true,
    keepSelection: true,
  };

  $(table).bootgrid(options);
  $(table).bootgrid("clear");
  $(table).bootgrid("deselect");

  const endpointsToAppend = [];
  for (let endpoint of data.endpoints) {
    endpoint.assigned = endpoint.assigned
      ? "<label class='badge-11cert badge-role-assigned'>Permitido</label>"
      : "<label class='badge-11cert badge-role-unassigned'>Denegado</label>";
    endpointsToAppend.push(endpoint);
  }
  $(table).bootgrid("append", endpointsToAppend);
  if (!document.querySelector(".select-roles")) {
    const [roles] = await Promise.all([getRoles()]);
    const rolesOptions = roles.roles.map((role) => ({
      value: role.id,
      text: role.name,
    }));
    agregarSelector(
      "table-endpoints-header",
      "select-roles",
      rolesOptions,
      "select",
      "Rol"
    );
    agregarBotonNuevo(
      "table-endpoints-header",
      "btn-add-endpoints",
      "Permitir"
    );
    agregarBotonNuevo(
      "table-endpoints-header",
      "btn-remove-endpoints",
      "Denegar"
    );
    document
      .querySelector(".select-roles")
      .addEventListener("change", async () => {
        finalLoading("#endpointsLoading", "loading");
        const roleId = document.querySelector(".select-roles").value;
        setEndpoints(roleId);
      });

    document
      .querySelector(".btn-add-endpoints")
      .addEventListener("click", async () => {
        finalLoading("#endpointsLoading", "loading");
        const roleId = document.querySelector(".select-roles").value;
        const selectedRows = $(table).bootgrid("getSelectedRows");
        const data = await editEndpointsByRole(roleId, selectedRows, true);
        if (data.error) {
          showModalWindow("Error", data.message, null, "Cerrar", null);
          return;
        }
        initializeEndpoints();
      });

    document
      .querySelector(".btn-remove-endpoints")
      .addEventListener("click", async () => {
        finalLoading("#endpointsLoading", "loading");
        const roleId = document.querySelector(".select-roles").value;
        const selectedRows = $(table).bootgrid("getSelectedRows");
        const data = await editEndpointsByRole(roleId, selectedRows, false);
        if (data.error) {
          showModalWindow("Error", data.message, null, "Cerrar", null);
          return;
        }
        initializeEndpoints();
      });
  }

  finalLoading("#endpointsLoading", "check");

  $(".tablaEndpoints").removeClass("mshide");
}

async function setEndpoints(roleId) {
  const dataEndpoints = await getEndpointsByRole(roleId, true);
  updateEndpoints(dataEndpoints);
}

function populateRoles(roles, rolesContainer, selectedRolesContainer) {
  const fragment = document.createDocumentFragment();

  roles.forEach((role) => {
    const label = createRoleLabel(role);
    fragment.appendChild(label);
  });

  rolesContainer.appendChild(fragment);

  rolesContainer.addEventListener("click", (event) => {
    const label = event.target.closest(".badge-role-selectable");
    if (label && rolesContainer.contains(label)) {
      rolesContainer.removeChild(label);
      selectedRolesContainer.appendChild(label);
    }
  });

  selectedRolesContainer.addEventListener("click", (event) => {
    const label = event.target.closest(".badge-role-selectable");
    if (label && selectedRolesContainer.contains(label)) {
      selectedRolesContainer.removeChild(label);
      rolesContainer.appendChild(label);
    }
  });
}

function createRoleLabel(role) {
  const label = document.createElement("label");
  label.classList.add("badge-role-selectable");
  label.setAttribute("data-role", role.id);
  label.style.borderColor = role.color;
  label.textContent = role.name;
  return label;
}

function extractLabels(htmlString) {
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = htmlString;

  const labels = tempDiv.querySelectorAll("label");

  const labelData = Array.from(labels)
    .filter((label) => label.textContent !== "Sin rol")
    .map((label) => ({
      name: label.textContent,
      color: label.style.backgroundColor,
      id: label.getAttribute("data-role"),
    }));

  return labelData;
}

function ocultarSpinner(container) {
  const spinner = container.querySelector(".spinner-a");
  if (spinner) {
    container.removeChild(spinner);
  }
}
