import constants from "../constants.js";

import {
  insertBasicLoadingHtml,
  finalLoading,
  displayErrorMessage,
  displaySuccessMessage,
} from "../../utils/utils.js";

import {
  getPreguntas,
  deleteRelacionMarcoNormativa,
  crearRelacionPreguntas,
  getPreguntasByUSF,
  deleteUSF,
} from "../../api/normativasApi.js";

import {
  setModule,
  setUSFs,
  setTablaRelaciones,
  showCrearPreguntas,
} from "./setModule.js";

async function setPreguntasRelacion() {
  try {
    const data = await getPreguntas();
    if (!data.error) {
      finalLoading("#loadingPreguntasRelacion", "check");
      setTablaPreguntasRelacionadas(data.Preguntas);
    } else {
      finalLoading("#loadingPreguntasRelacion", "error");
      console.log(data.msg);
    }
  } catch (error) {
    finalLoading("#loadingPreguntasRelacion", "error");
    console.error(error);
  }
}

export function eliminarRelacion(relacion) {
  const modal = `
    <div id="display-loading"></div>
    <h5>
        <b>Estas a punto de eliminar para siempre la siguiente relación: </b>
    </h5>
    <br>
    <div class="row">
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Código USF: </b> ${relacion.codigo_usf}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Nombre USF: </b> ${relacion.codigo_usf}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Código control: </b> ${relacion.codigo_control}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Nombre control: </b> ${relacion.nombre_control}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Duda: </b> ${relacion.duda}
            </h3>
        </div>
    </div>
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
    </div>
    <div class="display-errors"></div>`;

  showModalWindow("Eliminar relación", modal, null, null, null, null);
  $(`.btnCancel`).click(function (e) {
    cerrarModal();
  });
  $(`.btnDEL`).click(async function (e) {
    $(".btnDEL").prop("disabled", true);
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    try {
      const data = await deleteRelacionMarcoNormativa(relacion.id);
      if (!data.error) {
        $(`#tablaMarcoNormativa`).bootgrid("remove", [relacion.id.toString()]);
        $(".btnDEL").prop("disabled", false);
        cerrarModal();
      } else {
        document.getElementById("display-loading").innerHTML = "";
        $(".btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      document.getElementById("display-loading").innerHTML = "";
      console.log(e);
      displayErrorMessage(e, ".display-errors");
      $(".btnDEL").prop("disabled", false);
    }
  });
}

async function handleCrearRelacionPreguntas(preguntas, idControl) {
  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  insertBasicLoadingHtml(document.querySelector("#display-loading"));
  $("#botonAceptar").prop("disabled", true);
  try {
    const data = await crearRelacionPreguntas(preguntas, idControl);
    if (!data.error) {
      setTablaRelaciones(data.relaciones);
      await setModule();
      $(".volverOpciones").click();
      $("#botonAceptar").prop("disabled", false);
      displaySuccessMessage("Relación creada con éxito", ".display-check");
      cerrarModal();
    } else {
      $("#botonAceptar").prop("disabled", false);
      displayErrorMessage(data.error, ".display-errors");
    }
  } catch (error) {
    $("#botonAceptar").prop("disabled", false);
    displayErrorMessage(error, ".display-errors");
  }
}

function relacionarPreguntas(preguntas) {
  const rows = $("#RelacionarPreguntas")
    .bootgrid()
    .data(".rs.jquery.bootgrid").rows;
  const idControl = $("#idControl").text();
  let preguntasCompletas = [];
  preguntas.forEach((id) => {
    const row = rows.find((r) => r.id == id);
    if (row) {
      preguntasCompletas.push(row);
    }
  });

  console.log("Hola!");
  let modal = `
    <div id="display-loading"></div>
    <div class="display-check"></div>
    <div id="insertarPreguntas">
      <h4>Vas a relacionar las siguientes preguntas:</h4>
    </div>
    <div class="display-errors"></div>
    `;
  showModalWindow(
    `Relacionar preguntas`,
    modal,
    function () {
      handleCrearRelacionPreguntas(preguntasCompletas, idControl);
    },
    "Cerrar",
    "Relacionar preguntas",
    null
  );

  for (const pregunta of preguntasCompletas) {
    $("#insertarPreguntas").append(`
        <p><b>Pregunta: </b> ${pregunta.Duda} </p>
        `);
  }
  $("#insertarPreguntas").append(`
      <div class="display-errors"></div>
    `);
}

function insertarPreguntasRelacion(preguntas) {
  let arrayPreguntas = [];
  for (const pregunta of preguntas) {
    if (pregunta.relacion.length > 0) {
      arrayPreguntas.push({
        id: pregunta.id.toString(),
        Duda: pregunta.duda,
        Nivel: pregunta.nivel.toString(),
      });
    }
  }
  if (arrayPreguntas.length > 0) {
    $(`#RelacionarPreguntas`).bootgrid("append", arrayPreguntas);
  }
}

function setTablaPreguntasRelacionadas(preguntas) {
  const options = {
    ...constants.OPTIONS_TABLE,
    selection: true,
    multiSelect: true,
    rowSelect: true,
    keepSelection: true,
    rowCount: [10, 25, 50, -1],
  };
  $(`#RelacionarPreguntas`).bootgrid(options);
  $("#RelacionarPreguntas").bootgrid("clear");
  $("#RelacionarPreguntas").removeClass("mshide");
  agregarBotonNuevo(
    "RelacionarPreguntas-header",
    "btn-relacionarPreguntas",
    "Relacionar preguntas seleccionadas"
  );
  $(".btn-relacionarPreguntas").click(function (e) {
    const tableBody = $("#bodyTablaRelacionarPreguntas");
    const rows = tableBody.find("tr");
    if (rows.length > 0) {
      const preguntas = $("#RelacionarPreguntas").bootgrid("getSelectedRows");

      if (preguntas.length > 0) {
        relacionarPreguntas(preguntas);
      }
    }
  });
  insertarPreguntasRelacion(preguntas);
}

export async function eliminarUSF(USF) {
  const modal = `
  <h5>
      <b>Estas a punto de eliminar para siempre el siguiente USF: </b>
  </h5>
  <br>
  <div class="row">
      <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3>
              <b>Código: </b> ${USF.cod}
          </h3>
      </div>
      <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3>
              <b>Nombre: </b> ${USF.nombre}
          </h3>
      </div>
      <div class="col-12 d-flex text-start flex-column align-items-start">
          <h3 class="controlDesc">
              <b>Descripción: </b> ${USF.descripcion}
          </h3>
      </div>
  </div>
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
  showModalWindow("¿Eliminar USF?", modal, null, null, null);
  $(`.btnCancel`).click(function (e) {
    cerrarModal();
  });
  $(`.btnDEL`).click(async function (e) {
    try {
      $("#btnDEL").prop("disabled", true);
      const data = await deleteUSF(USF.id);
      if (!data.error) {
        $(`#tablaCatalogoUSFs`).bootgrid("remove", [USF.id.toString()]);
        $(".btnDEL").prop("disabled", false);
        cerrarModal();
      } else {
        $(".btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      displayErrorMessage(e, ".display-errors");
      $(".btnDEL").prop("disabled", false);
    }
  });
}

function createAccordionUSF(usf) {
  const idUSF = usf.ID;
  const cod = usf.Codigo;
  const nombreUSF = usf.Nombre;
  let accordion = `
            <div class="accordion" id="accordion${idUSF}">
                <div id='accordion${idUSF}-item'>
                    <h1 class="accordion-header" id="heading${idUSF}">
                        <button class="accordion-button collapsed d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${idUSF}" aria-expanded="false" aria-controls="collapse${idUSF}">
                            <span class="iconoAccordion"></span>
                            <span class="tituloAccordion flex-grow-1" id="titulo${idUSF}">
                                <div class="row gx-0">
                                    <div class="col-1">
                                        <b class="text-primary">${cod}</b>
                                    </div>
                                    <div class="col-11">
                                        ${nombreUSF}
                                    </div>
                                </div>
                            </span>
                        </button>
                    </h1>
                    <div id="collapse${idUSF}"
                        class="accordion-collapse collapse"
                        aria-labelledby="heading${idUSF}"
                        data-bs-parent="#accordion${idUSF}-item">
                        <div class="accordionBody mt-2 mb-2 row">
                          <div class='row justify-content-end'>
                            <div class='col-md-6 d-flex align-items-center'>
                                <label class='minititle'>Preguntas del ${cod}</label>
                            </div>
                            <div class='col-md-6 USF${idUSF} d-flex justify-content-end mb-2 align-items-center'>
                                <div class='spinner-animation' id='loading${idUSF}'>
                                    <svg class='spinner-a' height='60' role='img' viewBox='0 0 66 66' width='60'>
                                        <circle class='spinner-circle' cx='33' cy='33' fill='none' r='30' role='presentation' stroke-width='3' stroke='#0d6efd'></circle>
                                    </svg>
                                </div>
                            </div>
                          </div>
                            <table class="table table-condensed table-hover table-striped table-11cert TablasUSFs" id='tablaPreguntasInternasUSF${idUSF}'>
                                <caption>Controles de la USF ${nombreUSF}</caption>
                                <thead>
                                    <tr>
                                      <th data-column-id="ID" data-header-css-class="IDCol" data-visible="false" data-identifier="true">ID</th>
                                      <th data-column-id="Duda" data-header-css-class="dudaCol">Duda</th>
                                      <th data-column-id="Nivel" data-header-css-class="nivelCol">Nivel</th>
                                    </tr>
                                </thead>
                                <tbody id="bodytablaPreguntasInternasUSF${idUSF}">
                                </body>
                            </table>
                            <div class='row justify-content-end'>
                              <div class='col-md-6 d-flex align-items-center'>
                                  <label class='minititle'>Preguntas fuera del USF</label>
                              </div>
                              <div class='col-md-6 USF${idUSF} d-flex justify-content-end mb-2 align-items-center'>
                                  <div class='spinner-animation' id='loading${idUSF}Out'>
                                      <svg class='spinner-a' height='60' role='img' viewBox='0 0 66 66' width='60'>
                                          <circle class='spinner-circle' cx='33' cy='33' fill='none' r='30' role='presentation' stroke-width='3' stroke='#0d6efd'></circle>
                                      </svg>
                                  </div>
                              </div>
                            </div>
                            
                            <table class="table table-condensed table-hover table-striped table-11cert tablaPreguntas-Externas" id='tablaCatalogoPreguntas${idUSF}'>
                                <caption>Preguntas fuera de este USF</caption>
                                <thead>
                                    <tr>
                                        <th data-column-id="ID" data-header-css-class="IDCol" data-visible="false" data-identifier="true">ID</th>
                                        <th data-column-id="Duda" data-header-css-class="dudaCol">Duda</th>
                                        <th data-column-id="Nivel" data-header-css-class="nivelCol">Nivel</th>
                                    </tr>
                                </thead>
                                <tbody id='bodyTablaCatalogoPreguntas${idUSF}'>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;
  document.getElementById("accordionUSFs").innerHTML += accordion;
}

function setPreguntasRelacionByUSF(tabla, preguntas) {
  const options = {
    ...constants.OPTIONS_TABLE,
    selection: true,
    multiSelect: true,
    rowSelect: true,
    keepSelection: true,
    rowCount: [10, 25, 50, -1],
  };
  $(`#${tabla}`).bootgrid(options);
  let arrayPreguntas = [];
  for (const pregunta of preguntas) {
    arrayPreguntas.push({
      ID: pregunta.id.toString(),
      Duda: pregunta.duda,
      Nivel: pregunta.nivel.toString(),
    });
  }
  if (arrayPreguntas.length > 0) {
    $(`#${tabla}`).bootgrid("append", arrayPreguntas);
  }
}

async function handleGetPreguntasByUSF(usf) {
  try {
    const data = await getPreguntasByUSF(usf.ID);
    if (data.error) {
      finalLoading(`#loading${usf.ID}`, "error");
      finalLoading(`#loading${usf.ID}Out`, "error");
    } else {
      finalLoading(`#loading${usf.ID}`, "check");
      finalLoading(`#loading${usf.ID}Out`, "check");
      setPreguntasRelacionByUSF(
        `tablaCatalogoPreguntas${usf.ID}`,
        data.preguntasNoRelacionadas
      );
      setPreguntasRelacionByUSF(
        `tablaPreguntasInternasUSF${usf.ID}`,
        data.preguntas
      );
      agregarBotonNuevo(
        `tablaCatalogoPreguntas${usf.ID}-header`,
        "btn-nuevaPregunta",
        "Crear pregunta(s)"
      );
      $(".btn-nuevaPregunta").click(async function (e) {
        showCrearPreguntas("New USF");
      });
    }
  } catch (error) {
    finalLoading(`#loading${usf.ID}`, "error");
    finalLoading(`#loading${usf.ID}Out`, "error");
    console.error(error);
  }
}

function setAccordionPreguntasUSFs(seleccionUSFs) {
  $("#accordionUSFs").html("");
  for (const usf of seleccionUSFs) {
    handleGetPreguntasByUSF(usf);
    createAccordionUSF(usf);
  }
}

function getSelectedRowsFromContainer(containerSelector) {
  let selectedRows = [];
  $(containerSelector)
    .find(".tabla-USFs")
    .each(function () {
      const rows = $(this).bootgrid("getSelectedRows");
      selectedRows = selectedRows.concat(rows);
    });
  return selectedRows;
}

export function setBotonPreguntasRelacionUSF() {
  agregarBotonNuevo(
    "RelacionesUSFs-header",
    "btn-seleccionarPreguntasUSFs",
    "Seleccionar pregunta(s)"
  );
  $(".btn-seleccionarPreguntasUSFs").click(function (e) {
    const tableBody = $("#bodyRelacionesUSFs");
    const rows = tableBody.find("tr");
    let seleccionUSFs = [];
    if (rows.length > 0) {
      const USFs = $("#RelacionesUSFs").bootgrid("getSelectedRows");

      if (USFs.length > 0) {
        $(".opcionSeleccionarUSFs").addClass("mshide");
        $(".opcionSeleccionarPreguntasUSFs").removeClass("mshide");
        const rows = $("#RelacionesUSFs")
          .bootgrid()
          .data(".rs.jquery.bootgrid").rows;
        USFs.forEach((ID) => {
          const row = rows.find((r) => r.ID == ID);
          if (row) {
            seleccionUSFs.push(row);
          }
        });
        if (seleccionUSFs.length > 0) {
          setAccordionPreguntasUSFs(seleccionUSFs);
        }
      }
    }
  });
}

export function setBotonesGestionControles() {
  $("#btn-volverNormativas").off("click");
  $(".volverOpciones").off("click");
  $(".btn-seleccionarUSFs").off("click");
  $(".btn-seleccionarPreguntas").off("click");
  $("#btn-volverNormativas").click(function (e) {
    $(".mainPage").removeClass("mshide");
    $(".relacionarControlPage").addClass("mshide");
  });

  $(".volverOpciones").click(function (e) {
    $(".opcionSeleccionarPreguntas").addClass("mshide");
    $(".opcionSeleccionarUSFs").addClass("mshide");
    $(".opcionSeleccionarPreguntasUSFs").addClass("mshide");
    $(".opcionesPrincipalesRelacion").removeClass("mshide");
  });

  $(".btn-seleccionarUSFs").click(function (e) {
    $(".opcionesPrincipalesRelacion").addClass("mshide");
    $(".opcionSeleccionarUSFs").removeClass("mshide");
    finalLoading(`#loadingUSFsRelacion`, "loading");
    setUSFs("RelacionesUSFs", "loadingUSFsRelacion", true);
  });

  $(".btn-seleccionarPreguntas").click(function (e) {
    $(".opcionesPrincipalesRelacion").addClass("mshide");
    $(".opcionSeleccionarPreguntas").removeClass("mshide");
    $("#RelacionarPreguntas").addClass("mshide");
    finalLoading("#loadingPreguntasRelacion", "loading");
    setPreguntasRelacion();
  });
}

export function llenarTablaRelaciones(control) {
  const options = {
    ...constants.OPTIONS_TABLE,
    formatters: {
      commands: function (column, row) {
        return (
          "<button type='button' class='btn btn-danger btn-xs delete-relacion me-2' " +
          "data-row-id='" +
          row.ID +
          "'>Eliminar</button>" +
          "</div>"
        );
      },
    },
  };

  $(`#tablaMarcoNormativa`).bootgrid(options);
  agregarBotonNuevo(
    "tablaMarcoNormativa-header",
    "btn-seleccionarUSFs",
    "Seleccionar USF(s)"
  );
  agregarBotonNuevo(
    "tablaMarcoNormativa-header",
    "btn-seleccionarPreguntas mshide",
    "Seleccionar Pregunta(s)"
  );
  setBotonesGestionControles();
  $(`#tablaMarcoNormativa`).bootgrid("clear");
  for (let nuevaRelacion of control.relacion) {
    let arrayRelaciones = [
      {
        ID: nuevaRelacion.id.toString(),
        CodigoCtrl: nuevaRelacion.codigo_control,
        NombreCtrl: nuevaRelacion.nombre_control,
        CodigoUSF: nuevaRelacion.codigo_usf,
        NombreUSF: nuevaRelacion.nombre_usf,
        Duda: nuevaRelacion.duda,
      },
    ];
    $(`#tablaMarcoNormativa`).bootgrid("append", arrayRelaciones);
  }
  finalLoading("#loadingRelaciones", "check");

  $(`#tablaMarcoNormativa`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".delete-relacion")
        .off("click")
        .on("click", function () {
          const idRelacion = $(this).data("row-id");
          for (let relacion of control.relacion) {
            if (relacion.id === idRelacion) {
              eliminarRelacion(relacion);
              break;
            }
          }
        });
    });
}
