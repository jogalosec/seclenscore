import {
  getPreguntas,
  getUSFs,
  getNormativas,
  newPregunta,
  newControl,
  deleteControl,
  deletePregunta,
  deleteNormativa,
  editNormativa,
  getDominiosUnicosControles,
} from "../../api/normativasApi.js";

import constants from "../constants.js";

import {
  finalLoading,
  insertBasicLoadingHtml,
  displayErrorMessage,
  displaySuccessMessage,
} from "../../utils/utils.js";

import {
  llenarTablaRelaciones,
  setBotonPreguntasRelacionUSF,
  eliminarUSF,
  eliminarRelacion,
} from "./gestionarControles.js";

export function limpiarCampos(iDFormulario) {
  $(`#${iDFormulario}`)[0].reset();
  $(".textDescription").removeClass("red");
  $(".textDescription").addClass("text-muted");
}

export async function crearPregunta(type) {
  const dudaPregunta = document.getElementById("dudaPregunta").value;
  const nivelPregunta = document.getElementById("nivelPregunta").value;
  const checked = document.getElementById("crearMas").checked;

  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  insertBasicLoadingHtml(document.querySelector("#display-loading"));

  if (!dudaPregunta || !nivelPregunta) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }
  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await newPregunta(dudaPregunta, nivelPregunta);
    document.getElementById("display-loading").innerHTML = "";
    if (!data.error) {
      limpiarCampos("newPreguntaForm");
      let relacion;
      if (data.pregunta.relacion.length === 0) {
        relacion =
          "<label class='rounded-pill estado abierto'>Sin relacionar</label>";
      } else {
        relacion =
          "<label class='rounded-pill estado cerrado'>Relacionado</label>";
      }
      let arrayPregunta = [
        {
          ID: data.pregunta[0].id.toString(),
          Duda: data.pregunta[0].duda,
          Relacion: relacion,
          Nivel: data.pregunta[0].nivel.toString(),
        },
      ];
      if (type != null) {
        console.log("Hola inserto");
        console.log(arrayPregunta);
        $(".tablaPreguntas-Externas").bootgrid("append", arrayPregunta);
      }
      $(`#tablaCatalogoPreguntas`).bootgrid("append", arrayPregunta);
      displaySuccessMessage("Pregunta creada con éxito", ".display-check");
      $("#botonAceptar").prop("disabled", false);
      if (!checked) {
        cerrarModal();
      }
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

export async function showCrearPreguntas(type = null) {
  const opciones = `
  <div id="display-loading"></div>
  <div class="display-check"></div>
  <form id="newPreguntaForm">
      <div class="form-group row mb-3">
          <div class="col-3">
              <label for="dudaPregunta">Duda</label>
          </div>
          <div class="col-9">
              <input type="text" class="form-control" id="dudaPregunta" required>
          </div>
      </div>
      <div class="form-group row mb-5">
          <div class="col-3">
              <label for="nivelPregunta">Nivel</label>
          </div>
          <div class="col-9">
            <select class="form-control" id="nivelPregunta" required>
              <option value="0" selected>0</option>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5">5</option>
            </select>
          </div>
      </div>
  </form>
  <div class="form-group row mb-3">
    <div class="col-12 d-flex justify-content-end align-items-center">
        <span id="textareaHelpBlock" class="form-text text-muted">Añadir mas de una pregunta</span>
        <input type="checkbox" class="form-check-input" id="crearMas">
    </div>
  </div>
  <div class="display-errors"></div>
`;
  showModalWindow(
    "Nueva pregunta",
    opciones,
    function () {
      crearPregunta(type);
    },
    "Cerrar",
    "Crear pregunta",
    null
  );
}

export function setPreguntasButtons() {
  const boton = document.getElementById("btn-newPregunta");
  boton.addEventListener("click", () => {
    showCrearPreguntas();
  });
}

function asociarEventosEliminarRelacion(relaciones) {
  $("#tablaMarcoNormativa")
    .find(".delete-relacion")
    .off("click")
    .on("click", function () {
      const idRelacion = $(this).data("row-id");
      const relacion = relaciones.find((r) => r.id == idRelacion);
      if (relacion) {
        eliminarRelacion(relacion);
      }
    });
}

export function setTablaRelaciones(relaciones) {
  $(`#tablaMarcoNormativa`).bootgrid("clear");

  let arrayRelaciones = [];
  for (let relacion of relaciones) {
    arrayRelaciones.push({
      ID: relacion.id.toString(),
      CodigoCtrl: relacion.codigo_control,
      NombreCtrl: relacion.nombre_control,
      CodigoUSF: relacion.codigo_usf,
      NombreUSF: relacion.nombre_usf,
      Duda: relacion.duda,
      commands: `<button class="btn btn-danger delete-relacion" data-row-id="${relacion.id}">Eliminar</button>`,
    });
  }
  if (arrayRelaciones.length > 0) {
    $("#tablaMarcoNormativa").bootgrid("append", arrayRelaciones);
  }

  $("#tablaMarcoNormativa").on("loaded.rs.jquery.bootgrid", function () {
    asociarEventosEliminarRelacion(relaciones);
  });
}

export function setTablaUSFs(USFs, table, select = false) {
  const options = {
    ...constants.OPTIONS_TABLE,
    selection: true,
    multiSelect: true,
    rowSelect: true,
    keepSelection: true,
    caseSensitive: false,
    rowCount: [10, 25, 50, -1],
    formatters: {
      commands: function (column, row) {
        return (
          "<div class='d-flex justify-content-center align-items-center'>" +
          "<button type='button' class='btn btn-danger btn-xs delete-USF me-2' " +
          "data-row-id='" +
          row.ID +
          "'>Eliminar</button>" +
          "</div>"
        );
      },
    },
  };
  $(`#${table}`).bootgrid(options);
  if (
    table == "RelacionesUSFs" &&
    $("#btn-seleccionarPreguntasUSFs").length == 0
  ) {
    setBotonPreguntasRelacionUSF();
  }
  let arrayUSF = [];
  for (const usf of USFs) {
    let relacion;
    let tipo;
    if (!usf.tipo) {
      tipo = "No definido";
    } else {
      tipo = usf.tipo;
    }
    if (usf.relacion.length === 0) {
      relacion =
        "<label class='rounded-pill estado abierto'>Sin relacionar</label>";
    } else {
      relacion =
        "<label class='rounded-pill estado cerrado'>Relacionado</label>";
    }
    arrayUSF.push({
      ID: usf.id.toString(),
      Codigo: usf.cod,
      Nombre: usf.nombre,
      Descripcion: usf.descripcion,
      Dominio: usf.dominio,
      Relacion: relacion,
      Proyecto: usf.codigo_pac,
      Tipo: tipo,
    });
  }
  if (arrayUSF.length > 0) {
    $(`#${table}`).bootgrid("append", arrayUSF);
  }
  $(`#${table}`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".delete-USF")
        .off("click")
        .on("click", function () {
          const idUSF = $(this).data("row-id");
          for (const USF of USFs) {
            if (USF.id === idUSF) {
              eliminarUSF(USF);
              break;
            }
          }
        });
    });
}

function createAccordionNormativa(normativa) {
  let nombreNormativa = normativa.nombre;
  let idNormativa = normativa.id;
  let enabled;
  if (normativa.enabled == 0) {
    enabled = "normDisabled";
  } else {
    enabled = "";
  }
  let accordion = `
            <div class="accordion" id="accordion${idNormativa}">
                <div id='accordion${idNormativa}-item'>
                    <h1 class="accordion-header" id="heading${idNormativa}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${idNormativa}" aria-expanded="false" aria-controls="collapse${idNormativa}">
                                <span class="iconoAccordion" ></span>
                                <span class="tituloAccordion ${enabled}" id="titulo${idNormativa}"><b>${nombreNormativa}</b></span>
                        </button>
                    </h1>
                    <div id="collapse${idNormativa}"
                        class="accordion-collapse collapse"
                        aria-labelledby="heading${idNormativa}"
                        data-bs-parent="#accordion${idNormativa}-item">
                        <div class="accordionBody mt-2 mb-2 row">
                        <div class="col-12 mt-3">
                                <button class="btn btn-danger btn-control float-right me-2" id="btn-deleteNormativa${idNormativa}">Eliminar normativa</button>
                                <button class="btn btn-primary btn-control float-right me-2" id="btn-newControl${idNormativa}">Añadir control</button>
                                <button class="btn btn-primary btn-control float-right me-2" id="btn-editNormativa${idNormativa}">Editar normativa</button>
                            </div>
                            <table class="table table-condensed table-hover table-striped table-11cert" id='tablaNormativa${idNormativa}'>
                                <caption>Controles de la normativa ${nombreNormativa}</caption>
                                <thead>
                                    <tr>
                                        <th data-column-id="ID" data-header-css-class="idCol" data-visible="false">ID</th>
                                        <th data-column-id="Codigo" data-header-css-class="codigoCol">Código</th>
                                        <th data-column-id="Nombre" data-header-css-class="nombreCol">Nombre</th>
                                        <th data-column-id="Descripcion" data-header-css-class="descCol">Descripción</th>
                                        <th data-column-id="Relacion" data-header-css-class="relaCol">Relación</th>
                                        <th data-column-id="Dominio" data-header-css-class="dominioCol">Dominio</th>
                                        <th data-column-id="commands" data-formatter="commands" data-header-css-class="editarCol"
                                        data-sortable="false" data-searchable="false">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyNormativa${idNormativa}">
                                </body>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;

  document.getElementById("accordionNormativas").innerHTML += accordion;
  if (normativa.controles.length === 0) {
    document.getElementById(`titulo${idNormativa}`).innerHTML += "❗";
  }
}

export async function setModule() {
  $("#accordionNormativas").empty();
  $("#tablaCatalogoPreguntas").bootgrid("destroy");
  $("#tablaCatalogoUSFs").bootgrid("destroy");
  obtainNormativas();
  setPreguntas();
  setUSFs("tablaCatalogoUSFs", "loadingUSFs");
}

function relacionesControl(control, normativa) {
  $(".mainPage").addClass("mshide");
  $(".relacionarControlPage").removeClass("mshide");
  $("#textoNormativa").text(normativa.nombre);
  $("#textoControl").text(control.cod);
  $("#idControl").text(control.id);
  $("#textoNombre").text(control.nombre);
  if (control.descripcion == "") {
    control.descripcion = "Sin descripción";
  }
  $("#textoDescripcion").text(control.descripcion);
  llenarTablaRelaciones(control);
}

function setTablaPreguntas(preguntas) {
  let relacion;
  const options = {
    ...constants.OPTIONS_TABLE,
    formatters: {
      commands: function (column, row) {
        return (
          "<div class='d-flex justify-content-center align-items-center'>" +
          "<button type='button' class='btn btn-danger btn-xs delete-pregunta me-2' " +
          "data-row-id='" +
          row.ID +
          "'>Eliminar</button>" +
          "</div>"
        );
      },
    },
  };
  $(`#tablaCatalogoPreguntas`).bootgrid(options);

  let arrayPreguntas = [];

  for (const pregunta of preguntas) {
    if (pregunta.relacion.length === 0) {
      relacion =
        "<label class='rounded-pill estado abierto'>Sin relacionar</label>";
    } else {
      relacion =
        "<label class='rounded-pill estado cerrado'>Relacionado</label>";
    }

    arrayPreguntas.push({
      ID: pregunta.id.toString(),
      Duda: pregunta.duda,
      Relacion: relacion,
      Nivel: pregunta.nivel.toString(),
    });
  }
  if (arrayPreguntas.length > 0) {
    $(`#tablaCatalogoPreguntas`).bootgrid("append", arrayPreguntas);
  }
  $(`#tablaCatalogoPreguntas`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".delete-pregunta")
        .off("click")
        .on("click", function () {
          const idPregunta = $(this).data("row-id").toString();

          // Buscar entre todas las filas de la tabla
          $(`#tablaCatalogoPreguntas`)
            .bootgrid("getCurrentRows")
            .forEach((row) => {
              console.log(row);
              if (row.ID == idPregunta) {
                eliminarPregunta(row);
                return false; // Romper el bucle
              }
            });
        });
    });
}

function setBotonNewControl(idNormativa, nombreNormativa) {
  const boton = document.getElementById(`btn-newControl${idNormativa}`);
  const opciones = `
          <div id="display-loading"></div>
          <div class="display-check"></div>
          <form id="newControlField">
              <div class="form-group row mb-3">
                  <div class="col-3">
                      <label for="codigoControl">Código</label>
                  </div>
                  <div class="col-9">
                      <input type="text" class="form-control" id="codigoControl" required>
                  </div>
              </div>
              <div class="form-group row mb-3">
                  <div class="col-3">
                      <label for="nombreControl">Nombre</label>
                  </div>
                  <div class="col-9">
                      <input type="text" class="form-control" id="nombreControl" required>
                  </div>
              </div>
              <div class="form-group row mb-3">
                  <div class="col-3">
                      <label for="descripcionControl">Descripción</label>
                  </div>
                  <div class="col-9">
                      <textarea type="textarea" rows=4 class="form-control" id="descripcionControl" required></textarea>
                      <span id="textareaHelpBlock" class="form-text text-muted textDescription">Descripción control (max 250 chars)</span>
                  </div>
              </div>
              <div class="form-group row mb-5">
                  <div class="col-3">
                      <label for="dominioControl">Dominio</label>
                  </div>
                  <div class="col-9">
                      <input list="dominiosList" type="text" class="form-control" id="dominioControl"  placeholder="Obteniendo dominios..." required>
                      <datalist id="dominiosList"></datalist>
                  </div>
              </div>
          </form>
          <div class="form-group row mb-3">
            <div class="col-12 d-flex justify-content-end align-items-center">
                <span id="textareaHelpBlock" class="form-text text-muted">Añadir mas de un control</span>
                <input type="checkbox" class="form-check-input" id="crearMas">
            </div>
          </div>
          <div class="display-errors"></div> `;

  function handleClick() {
    showModalWindow(
      `Nuevo control para la normativa ${nombreNormativa}`,
      opciones,
      function () {
        crearControl(idNormativa);
      },
      "Cerrar",
      "Añadir control",
      null
    );
    getDominiosControles();
  }
  boton.addEventListener("click", handleClick);
}

function setBotonEditControl(normativa) {
  let idNormativa = normativa.id;
  let nombreNormativa = normativa.nombre;
  let checked = "";

  if (normativa.enabled == 1) checked = "checked";
  const boton = document.getElementById(`btn-editNormativa${idNormativa}`);
  const opciones = `
          <form id="newControlField">
              <div class="form-group row mb-3">
                  <div class="col-3">
                      <label for="nombreControl">Nombre</label>
                  </div>
                  <div class="col-9">
                      <input type="text" class="form-control" id="nombreControl" value="${nombreNormativa}" >
                  </div>
              </div>
              <div class="form-group row mb-3">
                  <div class="col-3">
                      <label for="enabledControl">Enabled</label>
                  </div>
                  <div class="col-9 d-flex">
                      <input type="checkbox" class="form-check-input" id="enabledControl" name="enabledControl" ${checked}>
                  </div>
              </div>
          </form><div class="display-errors"></div> `;
  boton.addEventListener("click", () => {
    showModalWindow(
      `Editar la normativa ${nombreNormativa}`,
      opciones,
      function () {
        editarNormativa(idNormativa);
      },
      "Cerrar",
      "Editar normativa",
      null
    );
  });
}

function setBotonDeleteNormativa(normativa) {
  let idNormativa = normativa.id;
  let nombreNormativa = normativa.nombre;
  let versionNormativa = normativa.version;
  const boton = document.getElementById(`btn-deleteNormativa${idNormativa}`);
  const modal = `
    <div id="display-loading"></div>
    <h5>
        <b>Estas a punto de eliminar para siempre la siguiente normativa: </b>
    </h5>
    <br>
    <div class="row">
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Nombre: </b> ${nombreNormativa}
            </h3>
        </div>
        <div class="col-12 d-flex text-start flex-column align-items-start">
            <h3>
                <b>Version: </b> ${versionNormativa}
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
  boton.addEventListener("click", () => {
    showModalWindow(
      "Eliminar normativa",
      modal,
      function () {
        handleEliminarNormativa(idNormativa);
      },
      null,
      null,
      null
    );
    $(`.btnCancel`).click(function (e) {
      cerrarModal();
    });
    $(`.btnDEL`).click(async function (e) {
      try {
        insertBasicLoadingHtml(document.querySelector("#display-loading"));
        $("#btnDEL").prop("disabled", true);
        const data = await deleteNormativa(idNormativa);
        document.getElementById("display-loading").innerHTML = "";
        if (!data.error) {
          $(`#accordion${idNormativa}`).remove();
          $("#btnDEL").prop("disabled", false);
          cerrarModal();
        } else {
          $("#btnDEL").prop("disabled", false);
          displayErrorMessage(data, ".display-errors");
          document.getElementById("display-loading").innerHTML = "";
        }
      } catch (e) {
        document.getElementById("display-loading").innerHTML = "";
        console.log(e);
        displayErrorMessage(e, ".display-errors");
        $("#btnDEL").prop("disabled", false);
      }
    });
  });
}

export async function obtainNormativas() {
  try {
    const data = await getNormativas();
    if (!data.error) {
      $(".btn-Normativa").removeClass("mshide");
      finalLoading("#loadingNormativas", "check");
      for (const normativa of data.normativas) {
        createAccordionNormativa(normativa);
      }
      for (const normativa of data.normativas) {
        setControles(normativa, normativa.controles);
        setBotonNewControl(normativa.id, normativa.nombre);
        setBotonEditControl(normativa);
        setBotonDeleteNormativa(normativa);
      }
    } else {
      finalLoading("#loadingNormativas", "error");
      console.log(data.msg);
    }
  } catch (error) {
    finalLoading("#loadingNormativas", "error");
    console.error(error);
  }
}

async function setPreguntas() {
  try {
    const data = await getPreguntas();
    if (!data.error) {
      $(".btn-newPregunta").removeClass("mshide");
      finalLoading("#loadingPreguntas", "check");
      setTablaPreguntas(data.Preguntas);
    } else {
      finalLoading("#loadingPreguntas", "error");
    }
  } catch (error) {
    finalLoading("#loadingPreguntas", "error");
    console.error(error);
  }
}

function setControles(normativa, controles) {
  const idNormativa = normativa.id;
  let Relacion;
  const options = {
    ...constants.OPTIONS_TABLE,
    formatters: {
      commands: function (column, row) {
        return (
          "<div class='d-flex justify-content-center align-items-center gap-2'>" +
          "<button type='button' class='btn btn-secondary btn-xs btn-default delete-control me-2' " +
          "data-row-id='" +
          row.ID +
          "' title='Eliminar'>" +
          "<img class='icono ver-pac' alt='Delete' src='./img/delete_blue.svg'></button>" +
          "<button type='button' class='btn btn-secondary btn-xs btn-default relaciones-control' " +
          "data-row-id='" +
          row.ID +
          "' title='Relaciones'>" +
          "<img class='icono ver-pac' alt='Relaciones' src='./img/relaciones.svg'></button>" +
          "</div>"
        );
      },
    },
  };
  $(`#tablaNormativa${idNormativa}`).bootgrid(options);
  for (const control of controles) {
    if (control.relacion.length === 0) {
      Relacion =
        "<label class='rounded-pill estado abierto'>Sin relacionar</label>";
    } else {
      Relacion =
        "<label class='rounded-pill estado cerrado'>Relacionado</label>";
    }
    let arrayControl = [
      {
        ID: control.id,
        Nombre: control.nombre,
        Codigo: control.cod,
        Descripcion: control.descripcion,
        Relacion: Relacion,
        Dominio: control.dominio,
      },
    ];
    $(`#tablaNormativa${idNormativa}`).bootgrid("append", arrayControl);
  }

  $(`#tablaNormativa${idNormativa}`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".delete-control")
        .off("click")
        .on("click", function () {
          const idControl = $(this).data("row-id");
          for (let control of controles) {
            if (control.id === idControl) {
              eliminarControl(control, normativa);
              break;
            }
          }
        });
    });

  $(`#tablaNormativa${idNormativa}`)
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".relaciones-control")
        .off("click")
        .on("click", function () {
          const idControl = $(this).data("row-id");
          for (let control of controles) {
            if (control.id === idControl) {
              relacionesControl(control, normativa);
              break;
            }
          }
        });
    });
}

async function eliminarControl(control, normativa) {
  const modal = `<h5>
                <b>Estas a punto de eliminar para siempre el control: </b>
            </h5>
            <br>
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
                <div class="col-12 d-flex text-start flex-column align-items-start">
                    <h3 class="controlDesc">
                        <b>Descripción: </b> ${control.descripcion}
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
  showModalWindow("¿Eliminar control?", modal, null, null, null);
  $(`.btnCancel`).click(function (e) {
    cerrarModal();
  });
  $(`.btnDEL`).click(async function (e) {
    try {
      $("#btnDEL").prop("disabled", true);
      const data = await deleteControl(control.id);
      if (!data.error) {
        cerrarModal();
        $(".btn-Normativa").addClass("mshide");
        finalLoading("#loadingNormativas", "loading");
        document.getElementById("accordionNormativas").innerHTML = "";
        obtainNormativas();
        $("#btnDEL").prop("disabled", false);
      } else {
        $("#btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      displayErrorMessage(e, ".display-errors");
      $("#btnDEL").prop("disabled", false);
    }
  });
}

async function getDominiosControles() {
  try {
    const data = await getDominiosUnicosControles();
    if (!data.error) {
      let dominios = [];
      for (const dominio of data.dominios) {
        dominios.push(dominio.dominio);
      }
      let dataList = document.getElementById("dominiosList");
      dataList.innerHTML = "";
      dominios.forEach(function (dominio) {
        let option = document.createElement("option");
        option.value = dominio;
        dataList.appendChild(option);
      });
      $("#dominioControl").attr("placeholder", "Inserta el dominio");
    } else {
      console.log(data.msg);
    }
  } catch (error) {
    console.error(error);
  }
}

function handleEliminarNormativa(idNormativa) {
  console.log(idNormativa);
}

async function editarNormativa(idNormativa) {
  const nombreNormativa = document.getElementById("nombreControl").value;
  const enabled = document.getElementById("enabledControl").checked;

  if (!nombreNormativa) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }
  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await editNormativa(nombreNormativa, enabled, idNormativa);
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
    console.log(e);
    $("#botonAceptar").prop("disabled", false);
    displayErrorMessage(e, ".display-errors");
  }
}

async function crearControl(idNormativa) {
  $(".textDescription").addClass("text-muted");
  const codigoControl = document.getElementById("codigoControl").value;
  const nombreControl = document.getElementById("nombreControl").value;
  const descripcionControl =
    document.getElementById("descripcionControl").value;
  const dominioControl = document.getElementById("dominioControl").value;
  const checked = document.getElementById("crearMas").checked;

  document.querySelector(".display-errors").innerHTML = "";
  document.querySelector(".display-check").innerHTML = "";
  insertBasicLoadingHtml(document.querySelector("#display-loading"));

  if (
    !codigoControl ||
    !nombreControl ||
    !descripcionControl ||
    !dominioControl
  ) {
    let error = { message: "Todos los campos son requeridos" };
    displayErrorMessage(error, ".display-errors");
    return;
  }

  if (descripcionControl.length > 250) {
    $(".textDescription").removeClass("text-muted");
    $(".textDescription").addClass("red");
    return;
  }

  try {
    $("#botonAceptar").prop("disabled", true);
    const data = await newControl(
      codigoControl,
      nombreControl,
      descripcionControl,
      dominioControl,
      idNormativa
    );

    document.getElementById("display-loading").innerHTML = "";
    if (!data.error) {
      limpiarCampos("newControlField");
      $("#botonAceptar").prop("disabled", false);
      displaySuccessMessage("Control creado con éxito", ".display-check");
      if (!checked) {
        cerrarModal();
      }
      $(".btn-Normativa").addClass("mshide");
      finalLoading("#loadingNormativas", "loading");
      document.getElementById("accordionNormativas").innerHTML = "";
      obtainNormativas();
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

async function eliminarPregunta(pregunta) {
  const modal = `
        <h5>
            <b>Estas a punto de eliminar para siempre la pregunta: </b>
        </h5>
        <br>
        <div class="row">
            <div class="col-12 d-flex text-start flex-column align-items-start">
                <h3 class="controlDesc">
                    "${pregunta.Duda}"
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
  showModalWindow("¿Eliminar pregunta?", modal, null, null, null);
  $(`.btnCancel`).click(function (e) {
    cerrarModal();
  });
  $(`.btnDEL`).click(async function (e) {
    try {
      $("#btnDEL").prop("disabled", true);
      const data = await deletePregunta(pregunta.ID);
      if (!data.error) {
        $(`#tablaCatalogoPreguntas`).bootgrid("remove", [
          pregunta.ID.toString(),
        ]);
        $("#btnDEL").prop("disabled", false);
        cerrarModal();
      } else {
        $("#btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      displayErrorMessage(e, ".display-errors");
      $("#btnDEL").prop("disabled", false);
    }
  });
}

export async function setUSFs(table, loading, select = false) {
  try {
    $(`#${table}`).bootgrid("destroy");
    const data = await getUSFs();
    if (!data.error) {
      finalLoading(`#${loading}`, "check");
      setTablaUSFs(data.USFs, table, select);
    } else {
      finalLoading(`#${loading}`, "error");
      console.log(data.msg);
    }
  } catch (error) {
    finalLoading(`#${loading}`, "error");
    console.error(error);
  }
}
