import {
  deleteRelacionReporter,
  crearReporterKPMs,
  getReportersKPMs,
} from "../api/KPMsApi.js";

import { getUsers } from "../api/userApi.js";

import {
  insertBasicLoadingHtml,
  displayErrorMessage,
  displaySuccessMessage,
  finalLoading,
} from "../utils/utils.js";

import {
  setSelectoresActivos,
  obtenerUltimoSelector,
} from "../utils/setSelectoresActivos.js";

import constants from "../modules/constants.js";

async function eliminarReporter(reporter) {
  const modal = `
            <div id="display-loading"></div>
            <div class="display-check"></div>

            <h6>
                El usuario <b> ${reporter.email_usuario}</b> dejará de reportar en <b>${reporter.nombre_activo}</b>
            </h6>
            <br>
            <h5>
                <b>¿Estas seguro?</b>
            </h5>
            <br>
            <div class="row">
                <div class="col-md-6">
                    <button class="btn btn-primary btnCancel">CANCELAR</button>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-primary btnDEL">ELIMINAR</button>
                </div>
            </div><div class="display-errors"></div>`;
  showModalWindow("¿Eliminar reporter?", modal, null, null, null);
  $(`.btnCancel`).click(function (e) {
    cerrarModal();
  });
  $(`.btnDEL`).click(async function (e) {
    try {
      insertBasicLoadingHtml(document.querySelector("#display-loading"));
      $(".btnDEL").prop("disabled", true);
      const data = await deleteRelacionReporter(reporter.id);
      document.getElementById("display-loading").innerHTML = "";
      if (!data.error) {
        displaySuccessMessage("Reporter eliminado con éxito", ".display-check");
        $(`#tablaReportersKPMs`).bootgrid("remove", [reporter.id.toString()]);
        $(".btnDEL").prop("disabled", false);
        cerrarModal();
      } else {
        $(".btnDEL").prop("disabled", false);
        document.getElementById("display-loading").innerHTML = "";
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      document.getElementById("display-loading").innerHTML = "";
      displayErrorMessage(e, ".display-errors");
      $(".btnDEL").prop("disabled", false);
    }
  });
}

function setEliminarReporte(reporters, options) {
  $(`#tablaReportersKPMs`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".delete-reporter")
        .off("click")
        .on("click", function () {
          const idReporter = $(this).data("row-id");
          for (let reporter of reporters) {
            if (reporter.id === idReporter) {
              eliminarReporter(reporter);
              break;
            }
          }
        });
    });
}

export async function setReportersTable() {
  try {
    const data = await getReportersKPMs();
    if (!data.error) {
      const options = {
        ...constants.OPTIONS_TABLE,
      };
      if (data.Admin) {
        const reportersTR = document.getElementById("reportersTR");
        reportersTR.innerHTML += `<th data-column-id="commands" data-header-css-class="editarCol trTable" data-formatter="commands" data-sortable="false" data-searchable="false">Acciones</th>`;
        $(".btn-newReporter").removeClass("mshide");
        options.formatters = {
          commands: function (column, row) {
            return `<div class='d-flex justify-content-center align-items-center'>
                      <button 
                        type='button' 
                        class='btn btn-xs btn-default delete-reporter me-2'
                        data-row-id='${row.ID}'>
                          <img class='icono' src='./img/delete.svg'/>                      
                      </button>
                    </div>`;
          },
        };
        $(`#tablaReportersKPMs`).bootgrid(options);
        agregarBotonNuevo(
          "tablaReportersKPMs-header",
          "btn-newReporter",
          "Nuevo Reporter"
        );
        setBotonesReportersAdmin(data.reporters, options);
      } else {
        $(`#tablaReportersKPMs`).bootgrid(options);
      }
      finalLoading("#loadingReporters", "check");
      for (let reporter of data.reporters) {
        let arrayReporter = [
          {
            ID: reporter.id.toString(),
            Email: reporter.email_usuario,
            Activo: reporter.nombre_activo,
            idActivo: reporter.activo_id.toString(),
            idUser: reporter.usuario_id.toString(),
          },
        ];
        $(`#tablaReportersKPMs`).bootgrid("append", arrayReporter);
      }
    } else {
      finalLoading("#loadingReporters", "error");
      console.log(data.message);
    }
  } catch (e) {
    finalLoading("#loadingReporters", "error");
    console.log(e);
  }
}

async function getAllUsers() {
  try {
    const data = await getUsers();
    if (!data.error) {
      const users = data.usuarios;
      let dataList = document.getElementById("usersList");
      dataList.innerHTML = "";
      users.forEach(function (user) {
        let option = document.createElement("option");
        option.value = user.email; // Mostrar solo el email
        option.setAttribute("data-id", user.id); // Almacenar el id en un atributo data
        dataList.appendChild(option);
      });
      $("#userControl").attr("placeholder", "Inserta el usuario");
    } else {
      console.log(data.msg);
    }
  } catch (error) {
    console.error(error);
  }
}

async function handleCrearReporter() {
  const userEmail = document.getElementById("userControl").value;
  const userOption = Array.from(
    document.querySelectorAll("#usersList option")
  ).find((option) => option.value === userEmail);
  const user = userOption ? userOption.getAttribute("data-id") : null;
  const activo = obtenerUltimoSelector("selectoresActivosSelector");

  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  try {
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    $(".btnDEL").prop("disabled", true);
    $("#addActivoSelector").prop("disabled", true);
    $("#removeActivoSelector").prop("disabled", true);
    const data = await crearReporterKPMs(user, activo);
    if (data.error) {
      $("#addActivoSelector").prop("disabled", false);
      $("#removeActivoSelector").prop("disabled", false);
      document.getElementById("display-loading").innerHTML = "";
      displayErrorMessage(
        `Error asignando el nuevo reporter`,
        ".display-errors"
      );
    } else {
      $("#addActivoSelector").prop("disabled", false);
      $("#removeActivoSelector").prop("disabled", false);
      document.getElementById("display-loading").innerHTML = "";
      displaySuccessMessage("Reporter creado con éxito", ".display-check");
      $("#tablaReportersKPMs").bootgrid("destroy");
      setReportersTable();
    }
  } catch (e) {
    $("#addActivoSelector").prop("disabled", false);
    $("#removeActivoSelector").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    displayErrorMessage(`Error asignando el nuevo reporter`, ".display-errors");
    return e;
  }
}

function setBotonNewReporter() {
  const boton = document.querySelector(`.btn-newReporter`);
  const opciones = `
        <div class="display-check"></div>
        <form id="newControlField">
            <div class="form-group row mb-5 d-flex align-items-center">
                <div class="col-3">
                    <label for="userControl">Usuario</label>
                </div>
                <div class="col-9">
                    <input list="usersList" type="text" class="form-control" id="userControl"  placeholder="Obteniendo usuarios..." required>
                    <datalist id="usersList"></datalist>
                </div>
            </div>
          </form>
        <div id="selectoresActivos">
        </div>
        <div id="selectoresActivosdisplay-loading"></div>

        <div id="display-loading"></div>
        <div class="display-errors"></div> 
    `;
  async function handleClick() {
    showModalWindow(
      `Nuevo reporter en 11Cert`,
      opciones,
      handleCrearReporter,
      "Cerrar",
      "Añadir control",
      null
    );
    getAllUsers();
    let activos = [
      {
        Dirección: 124,
        Área: 123,
        Unidad: 122,
        Producto: 67,
      },
    ];
    setSelectoresActivos("selectoresActivos", activos);
  }
  boton.addEventListener("click", handleClick);
}

export function setBotonesReportersAdmin(reporters, options) {
  setEliminarReporte(reporters, options);
  setBotonNewReporter();
}
