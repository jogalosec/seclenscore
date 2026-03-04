import { deleteKPMTabla, editarKPMTabla } from "../api/KPMsApi.js";

import { setReportersTable } from "./adminReporters.js";

import { displayErrorMessage, insertBasicLoadingHtml } from "../utils/utils.js";

function añadirKPM(evt) {
  $.ajax({
    type: "POST",
    url: `./api/addKPMFormulario?kpm=${evt.clone.id}`,
    success: function (retorno, textStatus, request) {
      $("#emptyAbiertos").remove();
      if ($("#kpmCerrados-cards").children().length == 0) {
        $("#kpmCerrados-cards").append(
          "<p id='emptyCerrados'>No tienes KPMs en la lista...</p>"
        );
      }
    },
  });
}

function deleteKPM(evt) {
  $.ajax({
    type: "POST",
    url: `./api/eliminarKpmFormulario?kpm=${evt.clone.id}`,
    success: function (retorno, textStatus, request) {
      if ($("#kpmAbiertos-cards").children().length == 0) {
        $("#kpmAbiertos-cards").append(
          "<p id='emptyAbiertos'>No tienes KPMs en la lista...</p>"
        );
      }
      $("#emptyCerrados").remove();
    },
  });
}

function initList() {
  Sortable.create(document.getElementById("kpmAbiertos-cards"), {
    group: "shared",
    animation: 150,
    ghostClass: "sortable-ghost",
    dragClass: "sortable-drag",
    onAdd: añadirKPM,
  });

  Sortable.create(document.getElementById("kpmCerrados-cards"), {
    group: "shared",
    animation: 150,
    ghostClass: "sortable-ghost",
    dragClass: "sortable-drag",
    onAdd: deleteKPM,
  });
}

function truncateText(text, maxLength) {
  if (text.length > maxLength) {
    return text.substring(0, maxLength) + "...";
  } else {
    return text;
  }
}

async function handleEditarKPMTabla(kpm) {
  let error = false;
  const nombre = $(".numeroKPMInput").val();
  const descripcion_corta = $(".descripcion_cortaInput").val();
  const descripcion_larga = $(".descripcion_largaInput").val();
  const grupo = $(".grupoInput").val();

  if (nombre == "") {
    $(".numeroKpm").addClass("red");
    error = true;
  }
  if (descripcion_corta == "") {
    $(".descripcion_cortaKPM").addClass("red");
    error = true;
  }
  if (descripcion_larga == "") {
    $(".descripcion_largaKPM").addClass("red");
    error = true;
  }
  if (grupo == "") {
    $(".grupoKPM").addClass("red");
    error = true;
  }
  if (!error) {
    try {
      insertBasicLoadingHtml(document.querySelector("#display-loading"));
      $("#btnDEL").prop("disabled", true);
      const data = await editarKPMTabla(
        nombre,
        descripcion_corta,
        descripcion_larga,
        grupo,
        kpm.id
      );
      document.getElementById("display-loading").innerHTML = "";
      if (!data.error) {
        $("#btnDEL").prop("disabled", false);
        mostrarLoading();
        $("#kpmAbiertos-cards").empty();
        $("#kpmCerrados-cards").empty();
        getAllKpms();
      } else {
        document.getElementById("display-loading").innerHTML = "";
        $("#btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      document.getElementById("display-loading").innerHTML = "";
      displayErrorMessage(e, ".display-errors");
      $("#btnDEL").prop("disabled", false);
    }
  }
}

function editarKPM(kpm) {
  let modal = `
        <div id="display-loading"></div>
        <form class="newKpm" id="form-newKpm">
        <div class="col-md-12">
            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="number" class="numeroKpm">Número del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control numeroKPMInput" id="numeroKpm" name="numeroKPM" value="${kpm.nombre}">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="descripcion_corta" class="descripcion_cortaKPM">Descripción corta del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control descripcion_cortaInput" id="descripcion_cortaInput" value="${kpm.descripcion_corta}" name="descripcionCortaKPM">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="descripcion_larga" class="descripcion_largaKPM">Descripción larga del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <textarea type="textarea" rows='5' class="form-control descripcion_largaInput" id="descripcion_largaInput" name="descripcionLargaKPM">${kpm.descripcion_larga}</textarea>
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="grupo" class="grupoKPM">Grupo del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" value="${kpm.grupo}" class="form-control grupoInput" id="grupoInput" name="grupoKPM">
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="display-errors"></div>
    `;
  showModalWindow("Editar KPM", modal, function () {
    handleEditarKPMTabla(kpm);
  });
}

function showInfoKPM(kpm) {
  let disponibilidad = "";
  if (kpm.form_metricas == 1) {
    disponibilidad =
      "<label class='rounded-pill estado cerrado estado-KPMs'>Formulario metricas</label>";
  }

  const modal = `
            <div id="display-loading"></div>
            <div class="row">
                <div class="col-12 mb-3 d-flex text-start flex-column align-items-start">
                    ${disponibilidad}
                </div>
                <div class="col-12 d-flex text-start flex-column align-items-start">
                    <h3>
                        <b>Nombre: </b> ${kpm.nombre}
                    </h3>
                </div>
                <div class="col-12 d-flex text-start flex-column align-items-start">
                    <h3 class="controlDesc">
                        <b>Grupo: </b> ${kpm.grupo}
                    </h3>
                </div>
                <div class="col-12 d-flex text-start flex-column align-items-start">
                    <h3 class="controlDesc">
                        <b>Descripción corta: </b> ${kpm.descripcion_corta}
                    </h3>
                </div>
                <div class="col-12 d-flex text-start flex-column align-items-start">
                    <h3 class="controlDesc">
                        <b>Descripción larga: </b> ${kpm.descripcion_larga}
                    </h3>
                </div>
            </div>
            <br>
            <div class="display-errors"></div>
            <br>
            <h5>
                <b>Acciones</b>
            </h5>
            <br>
            <div class="row">
                <div class="col-md-6">
                    <button class="btn btn-primary btnDEL">ELIMINAR</button>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-primary btnEdit">Editar KPM</button>
                </div>
            </div>`;
  showModalWindow("Info del KPM", modal, null, "Cerrar", null);
  $(`.btnDEL`).click(async function (e) {
    try {
      insertBasicLoadingHtml(document.querySelector("#display-loading"));
      $("#btnDEL").prop("disabled", true);
      const data = await deleteKPMTabla(kpm.id);
      document.getElementById("display-loading").innerHTML = "";
      if (!data.error) {
        $("#btnDEL").prop("disabled", false);
        cerrarModal();
        $(`#${kpm.nombre}`).remove();
        if ($("#kpmAbiertos-cards").children().length == 0) {
          $("#kpmAbiertos-cards").append(
            "<p id='emptyAbiertos'>No tienes KPMs en la lista...</p>"
          );
        }
        if ($("#kpmCerrados-cards").children().length == 0) {
          $("#kpmCerrados-cards").append(
            "<p id='emptyCerrados'>No tienes KPMs en la lista...</p>"
          );
        }
      } else {
        document.getElementById("display-loading").innerHTML = "";
        $("#btnDEL").prop("disabled", false);
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      console.log(e);
      document.getElementById("display-loading").innerHTML = "";
      displayErrorMessage(e, ".display-errors");
      $("#btnDEL").prop("disabled", false);
    }
  });
  $(`.btnEdit`).click(async function (e) {
    editarKPM(kpm);
  });
}

function getAllKpms() {
  $.ajax({
    type: "GET",
    url: `./api/obtainPreguntasKpms?type=all`,
    success: function (retorno, textStatus, request) {
      for (let kpm of retorno) {
        let card = `
                    <div class=" col-md-12 mb-4" id="${kpm["nombre"]}">
                        <div class="card cartaKPM">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6 class="card-title"><b>${
                                          kpm["nombre"]
                                        }</b></h6>
                                        <p class="card-text">${truncateText(
                                          kpm["descripcion_corta"],
                                          100
                                        )}</p>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <button id="${
                                          kpm["nombre"]
                                        }_btn" class="btn btn-primary ms-auto" type="button">Acciones</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
        if (kpm["form_metricas"] == 1) $("#kpmAbiertos-cards").append(card);
        else $("#kpmCerrados-cards").append(card);
        $(`#${kpm["nombre"]}_btn`).click(function () {
          showInfoKPM(kpm);
        });
      }
      if ($("#kpmAbiertos-cards").children().length == 0) {
        $("#kpmAbiertos-cards").append(
          "<p id='emptyAbiertos'>No tienes KPMs en la lista...</p>"
        );
      }
      if ($("#kpmCerrados-cards").children().length == 0) {
        $("#kpmCerrados-cards").append(
          "<p id='emptyCerrados'>No tienes KPMs en la lista...</p>"
        );
      }
      cerrarModal();
    },
  });
}

function getForm(form) {
  form = form[0];
  let formData = new FormData(form);

  let formObject = {};
  formData.forEach((value, key) => {
    formObject[key] = value;
  });
  return formObject;
}

function crearKPM() {
  let error = false;
  let numeroKpm = $(".numeroKPMInput").val();
  let descripcionCorta = $(".descripcion_cortaInput").val();
  let descripcionLarga = $(".descripcion_largaInput").val();
  let grupo = $(".grupoInput").val();

  if (numeroKpm == "") {
    $(".numeroKpm").addClass("red");
    error = true;
  }
  if (!numeroKpm.startsWith("KPM")) {
    $(".numeroKpm").addClass("red");
    error = true;
  }
  if (descripcionCorta == "") {
    $(".descripcion_cortaKPM").addClass("red");
    error = true;
  }
  if (descripcionLarga == "") {
    $(".descripcion_largaKPM").addClass("red");
    error = true;
  }
  if (grupo == "") {
    $(".grupoKPM").addClass("red");
    error = true;
  }
  if (!error) {
    let form = getForm($(".newKpm"));
    mostrarLoading();
    fetch("./api/newKPM", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(form),
    })
      .then((response) => response.json())
      .then((retorno) => {
        $("#kpmAbiertos-cards").empty();
        $("#kpmCerrados-cards").empty();
        getAllKpms();
      })
      .catch((error) => {
        console.error("Error al crear el KPM:", error);
      });
  }
}

function setBtnNewKpm() {
  $("#newKpm").click(function (e) {
    let modal = `
            <form class="newKpm" id="form-newKpm">
        <div class="col-md-12">
            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="number" class="numeroKpm">Número del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control numeroKPMInput" id="numeroKpm" name="numeroKPM" placeholder="KPM...">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="descripcion_corta" class="descripcion_cortaKPM">Descripción corta del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control descripcion_cortaInput" id="descripcion_cortaInput" name="descripcionCortaKPM">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="descripcion_larga" class="descripcion_largaKPM">Descripción larga del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control descripcion_largaInput" id="descripcion_largaInput" name="descripcionLargaKPM">
                    </div>
                </div>
            </div>

            <div class="form-group mb-4 row">
                <div class="col-md-6 d-flex align-items-center">
                    <b><label for="grupo" class="grupoKPM">Grupo del KPM</label></b>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-group">
                        <input type="text" class="form-control grupoInput" id="grupoInput" name="grupoKPM">
                    </div>
                </div>
            </div>
        </div>
    </form>
        `;
    showModalWindow("Crear nuevo KPM", modal, crearKPM);

    $(".numeroKPMInput").change(function () {
      $(".numeroKpm").removeClass("red");
    });
    $(".descripcion_cortaInput").change(function () {
      $(".descripcion_cortaKPM").removeClass("red");
    });
    $(".descripcion_largaInput").change(function () {
      $(".descripcion_largaKPM").removeClass("red");
    });
    $(".grupoInput").change(function () {
      $(".grupoKPM").removeClass("red");
    });
  });
}

$(document).ready(function () {
  initList();
  getAllKpms();
  setBtnNewKpm();
  setReportersTable();
});
