import {
  newNormativa,
  newUSF,
  getPACs,
  crearRelacionCompleta,
} from "../../api/normativasApi.js";

import {
  insertBasicLoadingHtml,
  finalLoading,
  displayErrorMessage,
  setTabsModulo,
  displaySuccessMessage,
} from "../../utils/utils.js";

import {
  setModule,
  obtainNormativas,
  setTablaRelaciones,
  setPreguntasButtons,
  limpiarCampos,
} from "./setModule.js";

async function crearNormativa() {
  const nombreNormativa = document.getElementById("nombreNormativa").value;
  const versionNormativa = document.getElementById("versionNormativa").value;

  document.querySelector(".display-errors").innerHTML = "";

  if (!nombreNormativa) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }

  if (!versionNormativa) {
    let error = {
      message: "La versión es requerida y debe ser un valor númerico",
    };
    displayErrorMessage(error, ".display-errors");
    return;
  }

  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await newNormativa(nombreNormativa, versionNormativa);
    if (!data.error) {
      cerrarModal();
      $("#botonAceptar").prop("disabled", false);
      $(".btn-Normativa").addClass("mshide");
      finalLoading("#loadingNormativas", "loading");
      document.getElementById("accordionNormativas").innerHTML = "";
      obtainNormativas();
    } else {
      $("#botonAceptar").prop("disabled", false);
      displayErrorMessage(data, ".display-errors");
    }
  } catch (e) {
    $("#botonAceptar").prop("disabled", false);
    console.log(e);
    displayErrorMessage(e, ".display-errors");
  }
}

async function crearUSF() {
  const codigoUSF = document.getElementById("codigoUSF").value;
  const nombreUSF = document.getElementById("nombreUSF").value;
  const descripcionUSF = document.getElementById("descripcionUSF").value;
  const dominioUSF = document.getElementById("dominioUSF").value;
  const tipoUSF = document.getElementById("tipoUSF").value;
  const PACUSF = document.getElementById("PACUSF").value;
  const checked = document.getElementById("crearMas").checked;

  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  insertBasicLoadingHtml(document.querySelector("#display-loading"));

  if (!codigoUSF || !nombreUSF || !descripcionUSF || !dominioUSF) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }

  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await newUSF(
      codigoUSF,
      nombreUSF,
      descripcionUSF,
      dominioUSF,
      tipoUSF,
      PACUSF
    );

    document.getElementById("display-loading").innerHTML = "";
    if (!data.error) {
      limpiarCampos("newUSFField");
      displaySuccessMessage("USF creado con éxito", ".display-check");
      let relacion =
        "<label class='rounded-pill estado abierto'>Sin relacionar</label>";
      let arrayUSF = [
        {
          ID: data.USF[0].id.toString(),
          Codigo: data.USF[0].cod,
          Nombre: data.USF[0].nombre,
          Descripcion: data.USF[0].descripcion,
          Dominio: data.USF[0].dominio,
          Proyecto: data.USF[0].codigo_pac,
          Relacion: relacion,
          Tipo: data.USF[0].tipo,
        },
      ];
      $(`#tablaCatalogoUSFs`).bootgrid("append", arrayUSF);
      $("#botonAceptar").prop("disabled", false);
      if (!checked) {
        cerrarModal();
      }
    } else {
      document.querySelector(".display-check").innerHTML = "";
      $("#botonAceptar").prop("disabled", false);
      displayErrorMessage(data, ".display-errors");
    }
  } catch (e) {
    document.querySelector(".display-check").innerHTML = "";
    $("#botonAceptar").prop("disabled", false);
    displayErrorMessage(e, ".display-errors");
  }
}

async function setUSFsButtons() {
  $("#btn-newUSF").removeClass("mshide");
  const boton = document.getElementById("btn-newUSF");
  const opciones = `
        <div id="display-loading"></div>
        <div class="display-check"></div>
        <form id="newUSFField">
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="codigoUSF">Código</label>
                </div>
                <div class="col-9">
                    <input type="text" class="form-control" id="codigoUSF" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="nombreUSF">Nombre</label>
                </div>
                <div class="col-9">
                    <input type="text" class="form-control" id="nombreUSF" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="descripcionUSF">Descripción</label>
                </div>
                <div class="col-9">
                    <textarea type="textarea" rows=4 class="form-control" id="descripcionUSF" required></textarea>
                </div>
            </div>
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="dominioUSF">Dominio</label>
                </div>
                <div class="col-9">
                    <input type="text" class="form-control" id="dominioUSF" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="TipoUSF">Tipo</label>
                </div>
                <div class="col-9">
                    <input type="text" class="form-control" id="tipoUSF" required>
                </div>
            </div>
            <div class="form-group row mb-5">
                <div class="col-3">
                    <label for="TipoUSF">Tipo</label>
                </div>
                <div class="col-9">
                  <select class="form-control" id="PACUSF" required>
                    <option value="Ninguno" selected>Cargando PACs..</option>
                  </select>
                </div>
            </div>
        </form>
        <div class="form-group row mb-3">
          <div class="col-12 d-flex justify-content-end align-items-center">
              <span id="textareaHelpBlock" class="form-text text-muted">Añadir mas de un USF</span>
              <input type="checkbox" class="form-check-input" id="crearMas">
          </div>
        </div>
        <div class="display-errors"></div> `;
  boton.addEventListener("click", async () => {
    showModalWindow(
      "Nuevo USF",
      opciones,
      crearUSF,
      "Cerrar",
      "Añadir USF",
      null
    );

    try {
      const data = await getPACs();
      const selectElement = document.getElementById("PACUSF");
      selectElement.innerHTML = "";

      const pacs = data.proyectos;
      pacs.forEach((pac) => {
        const option = document.createElement("option");
        option.value = pac.id;
        option.text = pac.cod + " / " + pac.nombre;
        selectElement.appendChild(option);
      });
    } catch (e) {
      $("#botonAceptar").prop("disabled", false);
      console.log(e);
      displayErrorMessage(e, ".display-errors");
    }
  });
}

function setNormativaButtons() {
  const boton = document.getElementById("btn-newNormativa");
  const opciones = `
        <form id="newNormativaForm">
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="nombreNormativa">Nombre</label>
                </div>
                <div class="col-9">
                    <input type="text" class="form-control" id="nombreNormativa" required>
                </div>
            </div>
            <div class="form-group row mb-3">
                <div class="col-3">
                    <label for="versionNormativa">Versión</label>
                </div>
                <div class="col-9">
                    <input type="number" class="form-control" id="versionNormativa" required>
                </div>
            </div>
        </form><div class="display-errors"></div>
    `;
  boton.addEventListener("click", () => {
    showModalWindow(
      "Nueva Normativa",
      opciones,
      crearNormativa,
      "Cerrar",
      "Crear normativa",
      null
    );
  });
}

async function handleCrearRelacionCompleta(control) {
  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  insertBasicLoadingHtml(document.querySelector("#display-loading"));
  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await crearRelacionCompleta(control);
    if (data.error) {
      $("#botonAceptar").prop("disabled", false);
      console.log(e);
      displayErrorMessage(e, ".display-errors");
      document.getElementById("display-loading").innerHTML = "";
    } else {
      setTablaRelaciones(data.relaciones);
      await setModule();
      $(".volverOpciones").click();
      $("#botonAceptar").prop("disabled", false);
      displaySuccessMessage("Relación creada con éxito", ".display-check");
      cerrarModal();
    }
  } catch (e) {
    $("#botonAceptar").prop("disabled", false);
    console.log(e);
    displayErrorMessage(e, ".display-errors");
    document.getElementById("display-loading").innerHTML = "";
  }
}

function mostrarModalRelaciones(control) {
  let modal = `
    <div id="display-loading"></div>
    <div class="display-check"></div>
    <h5>
        <b>Vas a relacionar el siguiente control: </b>
    </h5>
    <div class="row">
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Código: </b> ${control.cod}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Nombre: </b> ${control.nombre}
            </h3>
        </div>
    </div>
    <h5 class="mt-3">
      <b>Con los siguientes USFs: </b>
    </h5>
    <hr>
    <div id="RelacionUSF"></div>
    <div class="display-errors"></div>`;
  showModalWindow(
    `Nueva relación`,
    modal,
    function () {
      handleCrearRelacionCompleta(control);
    },
    "Cerrar",
    "Crear relación",
    null
  );
  for (const relacion of control.relaciones) {
    let preguntas = ``;

    for (const pregunta of relacion.preguntas) {
      preguntas += `
            <p class="mb-2">- ${pregunta.duda} </p>
      `;
    }
    let USF = `
      <div class="row">
        <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3>
            <b>Código: </b> ${relacion.codUSF}
          </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3>
            <b>Nombre: </b> ${relacion.nombreUSF}
          </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3>
            <b>Preguntas: </b>
          </h3>
          ${preguntas}
        </div>
        <hr>
      </div>
    `;
    document.getElementById("RelacionUSF").innerHTML += USF;
  }
}

function setBotonCrearRelacionControles() {
  $(".crearRelacion").click(async function (e) {
    let relaciones = [];
    let idControl = $("#idControl").text();
    let codControl = $("#textoControl").text();
    let nombreControl = $("#textoNombre").text();
    const tablasUSFs = document.querySelectorAll(".TablasUSFs");
    tablasUSFs.forEach((tabla) => {
      let codUSF = "";
      let nombreUSF = "";
      let idUSFMatch = /\d+/.exec(tabla.id);
      let idUSF = idUSFMatch ? idUSFMatch[0] : "";
      const rowsUSFs = $("#RelacionesUSFs")
        .bootgrid()
        .data(".rs.jquery.bootgrid").rows;
      rowsUSFs.forEach((row) => {
        if (row.ID == idUSF) {
          codUSF = row.Codigo;
          nombreUSF = row.Nombre;
        }
      });

      let preguntas = [];
      const selectedRows = $(`#${tabla.id}`).bootgrid("getSelectedRows");
      const selectedRowsSecondTable = $(
        `#tablaCatalogoPreguntas${idUSF}`
      ).bootgrid("getSelectedRows");
      if (selectedRows.length === 0 && selectedRowsSecondTable.length === 0) {
        return;
      }
      let rows = $(`#${tabla.id}`).bootgrid().data(".rs.jquery.bootgrid").rows;
      selectedRows.forEach((id) => {
        const row = rows.find((r) => r.ID == id);
        if (row) {
          preguntas.push({
            id: row.ID,
            duda: row.Duda,
          });
        }
      });
      rows = $(`#tablaCatalogoPreguntas${idUSF}`)
        .bootgrid()
        .data(".rs.jquery.bootgrid").rows;
      selectedRowsSecondTable.forEach((id) => {
        const row = rows.find((r) => r.ID == id);
        if (row) {
          preguntas.push({
            id: row.ID,
            duda: row.Duda,
          });
        }
      });
      relaciones.push({
        codUSF: codUSF,
        nombreUSF: nombreUSF,
        idUSF: idUSF,
        idTablaUSF: tabla.id,
        preguntas: preguntas,
      });
    });
    let control = {
      id: idControl,
      cod: codControl,
      nombre: nombreControl,
      relaciones: relaciones,
    };
    mostrarModalRelaciones(control);
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  setTabsModulo();
  setModule();
  setNormativaButtons();
  setUSFsButtons();
  setPreguntasButtons();
  setBotonCrearRelacionControles();
});
