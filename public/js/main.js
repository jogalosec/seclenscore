/*!
 * main-11Cert v1.0
 * Copyright (c) 2023 Raúl Llamas
 */

/* ----------------------------------------------------
* FUNCIONES AL CARGAR EL HOME
-------------------------------------------------------*/
const BREADCRUMB = ".breadcrumb-item";
const INFORMACION = "Información";
const SISTEMA_EVAL = "sistemaEval";
const FECHA_EVAL = "fechaEval";
const BREADLIST = "breadlist";
const SELECT_SISTEMA = "selectSistema";
const PARAM_NORM = "&normativa=";
const ERROR = "Error";
const eTagStorage = {};

jQuery(document).ready(function ($) {
  $(".b-home").click(function () {
    clearBreadcrumb(true);
  });

  $(".btn-riesgos").click(function () {
    if ($(".tarjeta").hasClass("mshide")) {
      $(".tarjeta").removeClass("mshide");
      $(".heatmap").addClass("mshide");
    } else {
      $(".tarjeta").addClass("mshide");
      $(".heatmap").removeClass("mshide");
    }
  });

  $(".logout").click(function () {
    $.ajax({
      type: "GET",
      url: `./api/logout`,
      success: function (retorno, textStatus, request) {
        localStorage.clear();
        showModalWindow(
          INFORMACION,
          retorno.message,
          null,
          "Cerrar",
          null,
          goLogin
        );
      },
    });
  });

  $(".issue").click(function () {
    issueReport();
  });

  $(".home").click(function () {
    window.location.href = `./app`;
  });

  $(".btn-ers").click(function () {
    let activo = $(`.${SISTEMA_EVAL}`).val();
    let fecha = $(`.${FECHA_EVAL}`).val();
    let edicion = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
    if (fecha !== undefined) {
      showModalWindow(
        INFORMACION,
        "Esta tarea puede tardar unos segundos, espere mientras se genera el documento.",
        null,
        "Aceptar",
        null,
        null
      );

      let url;
      if (edicion != undefined) {
        url = `./downloadErs?fecha=${fecha}&activo=${activo}&version=${edicion}`;
      } else {
        url = `./downloadErs?fecha=${fecha}&activo=${activo}`;
      }

      let link = document.createElement("a");
      link.href = url;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      showModalWindow(
        "Error",
        "Ha ocurrido un error contacte con el administrador.",
        null,
        "Cerrar",
        null,
        null
      );
    }
  });

  $(".btn-pac").click(function () {
    let fecha = $(`.${FECHA_EVAL}`).val();
    let activo = $(`.${SISTEMA_EVAL}`).val();
    let url = "";
    if (fecha !== undefined) {
      showModalWindow(
        INFORMACION,
        "Esta tarea puede tardar unos segundos, espere mientras se genera el documento.",
        null,
        "Aceptar",
        null,
        null
      );
      let version = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
      if (version === undefined) {
        url = `./downloadPac?fecha=${fecha}&activo=${activo}`;
      } else {
        url = `./downloadPac?fecha=${fecha}&version=${version}&activo=${activo}`;
      }
      let link = document.createElement("a");
      link.href = url;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      showModalWindow(
        "Error",
        "Ha ocurrido un error contacte con el administrador.",
        null,
        "Cerrar",
        null,
        null
      );
    }
  });

  $(".btn-ecr").click(function () {
    let fecha = $(`.${FECHA_EVAL}`).val();
    let version = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
    let activo = $(`.${SISTEMA_EVAL}`).val();
    if (fecha !== undefined) {
      showModalWindow(
        INFORMACION,
        "Esta tarea puede tardar unos segundos, espere mientras se genera el documento.",
        null,
        "Aceptar",
        null,
        null
      );
      let url = "";
      if (edicion != undefined) {
        url = `./downloadEcr?fecha=${fecha}&activo=${activo}&version=${version}`;
      } else {
        url = `./downloadErs?fecha=${fecha}&activo=${activo}`;
      }

      let link = document.createElement("a");
      link.href = url;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      showModalWindow(
        "Error",
        "Ha ocurrido un error contacte con el administrador.",
        null,
        "Cerrar",
        null,
        null
      );
    }
  });

  $("div").click(function (e) {
    if (e.target.className == "icono mas") {
      $(".collapse").removeClass("show");
    }
  });

  $(".show-pro").click(function () {
    let btnpro = $(".show-pro");
    let pro = $(".activo > .pro");
    if (!btnpro.hasClass("activado")) {
      $(btnpro).addClass("activado");
      $(".tarjeta").addClass("mshide");
      $(".activo > .pro").each(function (i) {
        $(pro[i]).parent().parent().removeClass("mshide");
      });
    } else {
      $(".tarjeta").removeClass("mshide");
      $(btnpro).removeClass("activado");
    }
  });

  $(".prevBtn").click(function () {
    let selector = "";
    if (!$(".form-metricas").hasClass("mshide")) {
      selector = "metricas";
    } else if (!$(".form-madurez").hasClass("mshide")) {
      selector = "madurez";
    } else {
      selector = "csirt";
    }

    let step = $(".step");
    let tap = $("." + selector + " > .tab");
    for (let i = 0; i < tap.length; i++) {
      if (!$(tap[i]).hasClass("mshide") && i == 0) {
        $(".form-" + selector).addClass("mshide");
        $(".nav-tabs").removeClass("mshide");
        $(".block-inicio").removeClass("mshide");
        $(".divtable").removeClass("mshide");
        $(".step").first().removeClass("active");
      }

      if (!$(tap[i]).hasClass("mshide") && i - 1 >= 0) {
        $(".nextBtn").removeClass("mshide");
        $(".saveBtn").addClass("mshide");
        $(tap[i]).addClass("mshide");
        $(tap[i - 1]).removeClass("mshide");
        $(step[i - 1]).addClass("active");
        $(step[i]).removeClass("active");
      }
    }
  });

  $(".saveBtn").click(() => {
    let selector = "";
    if ($(".form-metricas").hasClass("mshide")) {
      if ($(".form-madurez").hasClass("mshide")) selector = "csirt";
      else selector = "madurez";
    } else selector = "metricas";

    checkinputempty(selector);

    mostrarLoading();
    const form = $(`.form-${selector}`);
    const now = new Date().toISOString().slice(0, 19).replace("T", " ");
    $("input[name='fecha']").val(now);

    $.ajax({
      type: "POST",
      url: `./api/savereportkpms?reporte=${selector}`,
      xhrFields: {
        withCredentials: true,
      },
      data: form.serialize(),
      success: (retorno, textStatus, request) => {
        if (retorno.error) {
          showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
        } else {
          $(".saveBtn").addClass("mshide");
          $(".nextBtn").removeClass("mshide");
          $(`.step`).removeClass("active");
          showModalWindow(
            INFORMACION,
            retorno.message,
            null,
            "Aceptar",
            null,
            getkpms
          );
        }
      },
    });
  });

  $(".btn-savebia").click(function () {
    const bia9 = parseInt($('[name="9"]').val(), 10);
    const bia10 = parseInt($('[name="10"]').val(), 10);

    if (bia10 < bia9) {
      showModalWindow(
        "Advertencia",
        "No se ha podido guardar el cuestionario, el valor de tiempo de la pregunta 10 no puede ser inferior al de la pregunta 9.",
        null,
        "Aceptar",
        null,
        null
      );
      return;
    }

    saveBIA();
  });

  $(".showlocal").click(function () {
    $(".local").removeClass("mshide");
  });

  $(".toggleNav").click(function () {
    toggleNav();
  });

  $(".btn-newactivo").click(function () {
    nuevoActivo();
  });

  $(".next-update").click(function () {
    let toast = $(".toast-body");
    let actual;
    $(".toast-body").each(function (i) {
      if (!$(toast[i]).hasClass("mshide")) {
        $(toast[i]).addClass("mshide");
        actual = i + 1;
      } else if (actual == i) {
        $(".fecha-update").html(toast[i].firstElementChild.innerHTML);
        $(toast[i]).removeClass("mshide");
      }
      if (toast.length == actual + 1) {
        $(".next-update").addClass("mshide");
      }
    });
    if (actual !== 0) {
      $(".back-update").removeClass("mshide");
    }
    $(".level").html(`${actual + 1}/${toast.length}`);
  });

  $(".back-update").click(function () {
    let toast = $(".toast-body");
    let actual = 0;
    $(".toast-body").each(function (i) {
      if (!$(toast[i]).hasClass("mshide")) {
        actual = i - 1;
        $(toast[i]).addClass("mshide");
        $(toast[i - 1]).removeClass("mshide");
        $(".fecha-update").html(toast[i - 1].firstElementChild.innerHTML);
      }
    });
    if (actual == 0) {
      $(".back-update").addClass("mshide");
    }
    $(".next-update").removeClass("mshide");
    $(".level").html(`${actual + 1}/${toast.length}`);
  });

  $(".btn-exportarCuestionario").click(function () {
    exportExcelEvalRiesgos();
  });

  $(".btn-importar").click(function () {
    $(`.inputExcel`).click();
  });

  $(".btn-next").click(function () {
    let normativa = getValueChecked("groupcheck");
    let normativachecks = $(".normativas");
    if (normativa !== "" || normativachecks[0].children.length == 0) {
      enviarEvalServicio();
    }
  });

  $(".btn-save").click(function () {
    saveEvalServicio();
  });

  $(".downloadEsquema").click(function () {
    downloadEsquema();
  });

  $(".downloadTree").click(function () {
    mostrarLoading();
    downloadTree();
  });

  $(".generate-password").click(function (e) {
    generarcontraseña(e);
  });

  let item = $("div");
  let input = $(".excelEval");
  let inputServ = $(".excelActivos");

  if (item.hasClass("activos")) {
    getActivos("42", "Todos");
  }

  if (item.hasClass("token-sso")) {
    window.location.href = "./home";
  }

  if (item.hasClass("plan")) {
    getPlan();
  }

  if (item.hasClass("check-admin")) {
    checkAdmin();
  }

  if (item.hasClass("pacservicio")) {
    getPacServicio();
  }

  if ($("span").hasClass("mediaEval")) {
    obtainMedia($(".servicioId").attr("id"));
  }

  if (item.hasClass("historialservicio")) {
    fechaSistemasEvaluados();
  }

  if (item.hasClass("info-bia")) {
    getBia();
  }

  if (item.hasClass("changelog-updates")) {
    if ($(".toast-body:first > .fecha").html() == "sin_fecha") {
      $(".toast-body:first").remove();
    }
    $(".fecha-update").html($(".toast-body:first > .fecha").html());
    $(".toast-body:first").removeClass("mshide");
    let total = $(".toast-body").length;
    $(".level").html(`1/${total}`);
    $(".toast").toast("show");
  }

  if (item.hasClass("info-kpms-metricas")) {
    getkpms();
  }

  $(".select-riesgo").change(function () {
    updateRiesgosServicio($(`.${SISTEMA_EVAL}`).val());
  });

  function info_filtro(tipo, upd) {
    if (tipo == "42") $(".tipo-filtrado").text("Filtrando por: Servicios");
    else if (tipo == "42a")
      $(".tipo-filtrado").text("Filtrando por: Servicios archivados");
    else if (tipo == "67") $(".tipo-filtrado").text("Filtrando por: Productos");
    else if (tipo == "94")
      $(".tipo-filtrado").text("Filtrando por: Organización");

    if (tipo == "42" || tipo == "42a") {
      if (upd == "Todos") $(".tipo-servicio").text("Mostrando: Todos");
      else if (upd == "NoAct")
        $(".tipo-servicio").text("Mostrando: BIA no actualizado");
      else if (upd == "NoECR")
        $(".tipo-servicio").text("Mostrando: ECR incompleto");
    } else $(".tipo-servicio").text("Mostrando: Todos");
  }

  function ejecutarfiltros() {
    let tipo = $(".select-filtro").val();
    let upd = $(".select-update").val();
    info_filtro(tipo, upd);
    cerrarModal();
    getActivos(tipo, upd);
    $(".visual").addClass("mshide");
    clearBreadcrumb(false);
  }

  function changeSelects() {
    $(".select-update").removeClass("mshide");
    let opcionSeleccionada = "";
    if ($(".tipo-filtrado").text() == "Filtrando por: Servicios")
      opcionSeleccionada = $(".select-filtro option[value='42']");
    if ($(".tipo-filtrado").text() == "Filtrando por: Productos")
      opcionSeleccionada = $(".select-filtro option[value='67']");
    if ($(".tipo-filtrado").text() == "Filtrando por: Servicios archivados")
      opcionSeleccionada = $(".select-filtro option[value='42a']");
    if ($(".tipo-filtrado").text() == "Filtrando por: Organización") {
      $(".select-update").addClass("mshide");
      opcionSeleccionada = $(".select-filtro option[value='94']");
    }
    opcionSeleccionada.prop("selected", true);

    if ($(".tipo-servicio").text() == "Mostrando: Todos")
      opcionSeleccionada = $(".select-update option[value='Todos']");
    if ($(".tipo-servicio").text() == "Mostrando: BIA no actualizado")
      opcionSeleccionada = $(".select-update option[value='NoAct']");
    if ($(".tipo-servicio").text() == "Mostrando: ECR incompleto")
      opcionSeleccionada = $(".select-update option[value='NoECR']");
    opcionSeleccionada.prop("selected", true);
  }

  $(".show-filtros").click(function () {
    let selects = `<div class='form-group mb-3'>
                      <select class='form-select-custom w-100 noblock select-filtro' name='filtro'>
                        <option value='42' selected>Servicios</option>
                        <option value='67' selected>Productos</option>
                        <option value='94'>Organización</option>
                        <option value='42a'>Archivados</option>
                      </select>
                    </div>
                    <div class='form-group'>
                      <select class='form-select-custom w-100 noblock select-update' name='filtro'>
                        <option value='Todos' selected>Todos</option>
                        <option value='NoAct'>BIA desactualizado / sin BIA</option>
                        <option value='NoECR'>ECR incompleto</option>
                      </select>
                    </div>`;
    showModalWindow(
      "Filtrar por:",
      selects,
      ejecutarfiltros,
      "Cerrar",
      "Aceptar",
      null,
      false
    );
    changeSelects();
    $(".select-filtro").change(function () {
      if ($(".select-filtro").val() == "124") {
        $(".select-update").addClass("mshide");
      } else {
        $(".select-update").removeClass("mshide");
      }
    });
  });

  $("#vista-plan").change(function () {
    selectorPlanCambio();
  });

  $(".verbajas").click(function () {
    let table = $(".divtable");
    let table2 = $(".divtable2");

    if (table.hasClass("mshide")) {
      $(".verbajas").html("ver bajas");
      table.removeClass("mshide");
      table2.addClass("mshide");
    } else {
      $(".verbajas").html("ver activos");
      table2.removeClass("mshide");
      table.addClass("mshide");
    }
  });

  $(".nuevo-plan").click(function () {
    let form =
      '<form class="formNew"><div class="text-start row">' +
      '<div class="col-sm-6"><label>Dirección</label>' +
      '<input name="direccion" class="form-control" id="direccion1" placeholder="Dirección"></div>' +
      '<div class="col-sm-6"><label>Area</label>' +
      '<input name="area" class="form-control" id="area1" placeholder="Area"></div>' +
      '<div class="col-sm-6"><label>Unidad</label>' +
      '<input name="unidad" class="form-control" id="unidad1" placeholder="unidad"></div>' +
      '<div class="col-sm-6"><label>criticidad</label>' +
      '<input name="criticidad" class="form-control" id="criticidad1" placeholder="criticidad"></div>' +
      '<div class="col-sm-6"><label>prioridad</label>' +
      '<input name="prioridad" class="form-control" id="prioridad1" placeholder="prioridad"></div>' +
      '<div class="col-sm-6"><label>servicio</label>' +
      '<input name="servicio" class="form-control" id="servicio1" placeholder="servicio"></div>' +
      '<div class="col-sm-6"><label>estado</label>' +
      '<input name="estado" class="form-control" id="estado1" placeholder="estado"></div>' +
      '<div class="col-sm-6"><label>elevencert</label>' +
      '<input name="elevencert" class="form-control" id="elevencert1" placeholder="elevencert"></div>' +
      '<div class="col-sm-6"><label>eprivacy</label>' +
      '<input name="eprivacy" class="form-control" id="eprivacy1" placeholder="eprivacy"></div>' +
      '<div class="col-sm-6"><label>JefeProyecto</label>' +
      '<input name="JefeProyecto" class="form-control" id="JefeProyecto1" placeholder="JefeProyecto"></div>' +
      '<div class="col-sm-6"><label>SecretoEmpresarial</label>' +
      '<input name="SecretoEmpresarial" class="form-control" id="SecretoEmpresarial1" placeholder="SecretoEmpresarial"></div>' +
      '<div class="col-sm-6"><label>Entorno</label>' +
      '<input name="Entorno" class="form-control" id="Entorno1" placeholder="Entorno"></div>' +
      '<div class="col-sm-6"><label>Tenable</label>' +
      '<input name="Tenable" class="form-control" id="Tenable1" placeholder="Tenable"></div>' +
      '<div class="col-sm-6"><label>DOME9</label>' +
      '<input name="DOME9" class="form-control" id="DOME91" placeholder="DOME9"></div>' +
      '<div class="col-sm-6"><label>UsuarioAcceso</label>' +
      '<input name="UsuarioAcceso" class="form-control" id="UsuarioAcceso1" placeholder="UsuarioAcceso"></div>' +
      '<div class="col-sm-6"><label>Revisiones</label>' +
      '<input name="Revisiones" class="form-control" id="Revisiones1" placeholder="Revisiones"></div>' +
      '<div class="col-sm-6"><label>q1</label>' +
      '<input name="q1" class="form-control" id="q1" placeholder="q1"></div>' +
      '<div class="col-sm-6"><label>q2</label>' +
      '<input name="q2" class="form-control" id="q2" placeholder="q2"></div>' +
      '<div class="col-sm-6"><label>q3</label>' +
      '<input name="q3" class="form-control" id="q3" placeholder="q3"></div>' +
      '<div class="col-sm-6"><label>q4</label>' +
      '<input name="q4" class="form-control" id="q4" placeholder="q4"></div>' +
      "</div></form>";
    showModalWindow(
      "Crear nuevo plan",
      `${form}`,
      crearplan,
      "Cancelar",
      "Crear"
    );
  });

  $(".editar-eval").click(function () {
    let sys = $(`.sistemaEval`).val();
    let fecha = $(`.${FECHA_EVAL}`).val();
    let servicioId = $(".servicioId").attr("id");
    let version = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
    if (fecha !== null) {
      if (version === undefined) {
        window.location.href = `./evaluarservicio?id=${servicioId}&sys=${sys}&fecha=${fecha}`;
      } else {
        window.location.href = `./evaluarservicio?id=${servicioId}&sys=${sys}&version=${version}`;
      }
    } else {
      showModalWindow(
        "Error",
        "No se puede editar ninguna evaluación ya que no existe ninguna para este servicio.",
        null,
        "Cerrar",
        null,
        null
      );
    }
  });

  $(".download-eval").click(function () {
    let fecha = $(`.${FECHA_EVAL}`).val();
    if (fecha !== undefined) {
      let url = `./downloadEval?fecha=${fecha}`;
      let link = document.createElement("a");
      link.download = `${fecha}.xlsx`;
      link.href = url;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      showModalWindow(
        "Error",
        "Ha ocurrido un error contacte con el administrador.",
        null,
        "Cerrar",
        null,
        null
      );
    }
  });

  $(".btn-verpac").click(function () {
    let servicioId = $(".servicioId").attr("id");
    window.location.href = `./pac?id=${servicioId}`;
  });

  input.change(function () {
    let normativas = getValueChecked("groupcheck");
    let servicioId = $(`.${SELECT_SISTEMA}`).val();
    let url = `./api/importEval?id=${servicioId}${PARAM_NORM}${normativas}`;
    inputCambiado(input, url);
  });

  let target = $(`.${BREADLIST}`)[0];
  if (target !== undefined) {
    let config = { childList: true };
    let callback = function (mutationsList, observerlocal) {
      for (const mutation of mutationsList) {
        if (mutation.type === "childList") {
          if ($(`${BREADCRUMB}`).length > 1) {
            $(".select-filtro").prop("disabled", true);
          }
        }
      }
    };

    const observer = new MutationObserver(callback);
    observer.observe(target, config);
  }

  inputServ.change(function () {
    inputCambiado(inputServ, "./api/importActivos");
  });

  $(".groupcheck,.selectchange").change(function () {
    let normativa = PARAM_NORM;
    normativa += getValueChecked("groupcheck");
    if (normativa.includes("3ps")) {
      clearResponse();
      $(".groupcheck").prop("checked", false);
      $(".groupcheck").prop("disable", true);
      $("[value='3ps']").prop("checked", true);
      $(".totalPreguntas").html("");
      $(".menu-bottom").removeClass("mshide");
      $(".menu-bottom .3ps").removeClass("mshide");
      hideItem(".btn-importar", false);
      hideItem(".btn-exportarCuestionario", true);
      hideItem(".btn-next", true);
      hideItem(".btn-save", true);
    } else {
      let id = $(`.${SELECT_SISTEMA}`).val();
      if (normativa !== PARAM_NORM) {
        hideItem(".menu-bottom", false);
        hideItem(".btn-importar", false);
        hideItem(".btn-exportarCuestionario", false);
        hideItem(".btn-next", false);
        hideItem(".btn-save", false);
        updateEvalServicio(id, normativa);
      }
      if (getValueChecked("groupcheck") === "") {
        $(".totalPreguntas").addClass("mshide");
        $(".menu-bottom .3ps").addClass("mshide");
        hideItem(".menu-bottom", true);
        hideItem(".btn-next", true);
        hideItem(".btn-save", true);
        clearResponse();
      }
    }
  });

  $(`.${SISTEMA_EVAL}`).change(function () {
    fechaSistemasEvaluados();
  });

  $(`.${FECHA_EVAL}, .selectSistema`).change(function () {
    if ($(".historialservicio").length !== 0) {
      updateHistorialServicio($(`.${SISTEMA_EVAL}`).val());
      updateRiesgosServicio($(`.${SISTEMA_EVAL}`).val());
    } else {
      let normativa = PARAM_NORM;
      normativa += getValueChecked("groupcheck");
      if (normativa !== PARAM_NORM) {
        fechaSistemasEvaluados();
      }
    }
  });

  $(".form-login").submit(function (e) {
    e.preventDefault();
    refreshToken();
  });
});

/* ----------------------------------------------------
* SERVICIOS CON TODAS LAS FUNCIONES ASOCIADAS
-------------------------------------------------------*/

function issueReport() {
  let form = `
    <form class="issueForm" id="issueReportForm">
      <div class="form-group mb-4 row">
        <label for="issueType" class="col-4 col-form-label">Tipo de incidencia*</label>
        <div class="col-8">
          <select class="form-control" id="issueType" name="issueType" required>
            <option value="" disabled selected>Seleccione el tipo de incidencia</option>
            <option value="Incidencia">Incidencia</option>
            <option value="User story">Sugerencia</option>
          </select>
          <small id="issueTypeError" class="form-text text-danger"></small>
        </div>
      </div>
      <div class="form-group mb-4 row">
        <label for="issueSummary" class="col-4 col-form-label">Resumen*</label>
        <div class="col-8">
          <input type="text" class="form-control" id="issueSummary" name="issueSummary" placeholder="Resumen del problema" required>
          <small id="issueSummaryError" class="form-text text-danger"></small>
        </div>
      </div>
      <div class="form-group mb-4 row">
        <label for="issueDescription" class="col-4 col-form-label">Descripción*</label>
        <div class="col-8">
          <textarea class="form-control" id="issueDescription" name="issueDescription" placeholder="Descripción detallada del problema" rows="4" required></textarea>
          <small id="issueDescriptionError" class="form-text text-danger"></small>
        </div>
      </div>
      <div class="form-group mb-4 row">
        <label for="issueAttachment" class="col-4 col-form-label">Adjuntar archivo</label>
        <div class="col-8">
          <input type="file" class="form-control" id="issueAttachment" name="issueAttachment" accept="image/*">
          <small id="issueAttachmentError" class="form-text text-danger"></small>
        </div>
      </div>
    </form>
  `;

  showModalWindow(
    "Crear un reporte",
    form,
    function () {
      let formElement = document.getElementById("issueReportForm");
      if (!formElement.checkValidity()) {
        formElement.reportValidity();
        return;
      }

      let summary = document.getElementById("issueSummary").value;
      let description = document.getElementById("issueDescription").value;
      let issueType = document.getElementById("issueType").value;
      let fileInput = document.getElementById("issueAttachment");

      let formData = new FormData();
      formData.append("summary", summary);
      formData.append("description", description);
      formData.append("issueType", issueType);

      if (fileInput.files.length > 0) {
        formData.append("issueAttachment", fileInput.files[0]);
      }

      fetch("./api/createIncident", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.Error) {
            showModalWindow(
              "Error",
              "Hubo un error al crear la incidencia.",
              null,
              "Cerrar",
              null,
              null
            );
          } else {
            showModalWindow(
              "Información",
              "La incidencia se ha creado correctamente. Clave: " +
              data.Execution,
              null,
              "Cerrar",
              null,
              null
            );
          }
        })
        .catch((err) => {
          showModalWindow(
            "Error",
            "Error al comunicarse con la API.",
            null,
            "Cerrar",
            null,
            null
          );
        });

      cerrarModal();
    },
    "Cerrar",
    "Aceptar",
    null
  );
}

function fechaSistemasEvaluados() {
  let sistemaId;
  if ($(`.${SISTEMA_EVAL}`).length > 0) {
    sistemaId = $(`.${SISTEMA_EVAL}`).val();
  }
  if (sistemaId !== null) {
    $.ajax({
      type: "GET",
      url: `./api/getFechasEvaluacion?id=${sistemaId}`,
      xhrFields: {
        withCredentials: true,
      },

      success: function (retorno, textStatus, request) {
        if (retorno.error !== true) {
          if (retorno.fechas.length !== 0) {
            $(`.${FECHA_EVAL}`).empty();
            let numeval = 1;
            for (let value of retorno.fechas) {
              // Crear el grupo principal
              let mainGroup = document.createElement("optgroup");
              mainGroup.label = "Evaluación " + numeval;
              mainGroup.className = "main-group";

              // Verificar si hay versiones para esta fecha/evaluación
              let mainOption = document.createElement("option");
              mainOption.value = value.id;
              mainOption.textContent = value.fecha;
              mainGroup.appendChild(mainOption);
              if (value.version && value.version.length > 0) {
                // ordenar versiones
                for (let i = 0; i < value.version.length; i++) {
                  let version = value.version[i];

                  // Crear la opción de versión
                  let versionOption = document.createElement("option");
                  versionOption.value = value.id;
                  versionOption.textContent =
                    version.fecha +
                    " - " +
                    version.nombre +
                    " " +
                    version.version;
                  versionOption.dataset.evaluacion = version.id;

                  // Verificar si es la versión más nueva o evaluación más actual
                  if (i + 1 === value.version.length) {
                    versionOption.selected = true; // Seleccionar por defecto la primera opción
                  }

                  // Agregar la opción al grupo principal
                  mainGroup.prepend(versionOption);
                }
              }

              // Agregar el grupo principal al elemento select
              $(`.${FECHA_EVAL}`).append(mainGroup);
              numeval++;
            }
            if ($("select").hasClass(`${SISTEMA_EVAL}`)) {
              updateHistorialServicio($(`.${SISTEMA_EVAL}`).val());
              updateRiesgosServicio($(`.${SISTEMA_EVAL}`).val());
            }
          } else {
            $(`.${FECHA_EVAL}`).empty();
          }
        } else {
          showModalWindow(
            ERROR,
            retorno.message,
            null,
            "Cerrar",
            null,
            goLogin
          );
        }
      },
    });
  } else {
    $("#graficoLineal").text("No hay evaluaciones realizadas.");
  }
}

function formatArrayText(text) {
  try {
    const array = JSON.parse(text);
    if (Array.isArray(array)) {
      return array.join(", ");
    }
  } catch (e) {
    console.error("Error parsing JSON in formatArrayText:", e);
    return text;
  }
  return text;
}

function getPlan() {
  mostrarLoading();
  let btnnuevo = $(".nuevo");
  if (btnnuevo.length > 3) {
    btnnuevo.splice(3, 3);
  }
  $(".response").prepend(btnnuevo);
  $("#table-plan").bootgrid("destroy");
  $("#table-plan2").bootgrid("destroy");
  $("#table-plan-data").html("");
  $("#table-plan-data2").html("");
  $.ajax({
    url: `./api/getPlan`,
    method: "GET",
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        cerrarModal();
        showModalWindow(
          "Error",
          "Esta cuenta no está autorizada para visualizar este apartado.",
          null,
          "Cerrar",
          null,
          goHome
        );
      } else {
        let table = document.getElementById("table-plan-data");
        let table2 = document.getElementById("table-plan-data2");
        let tabla;
        for (let plan of retorno.plan) {
          let formattedEstado = formatArrayText(plan.estado);
          let formattedElevencert = formatArrayText(plan.elevencert);
          let formattedEprivacy = formatArrayText(plan.eprivacy);
          let formattedJefeproyecto = formatArrayText(plan.jefeproyecto);

          if (formattedEstado.toLowerCase() == "baja") {
            tabla = table2;
          } else {
            tabla = table;
          }
          tabla.insertRow().innerHTML =
            "<td>" +
            plan.id +
            "</td>" +
            "<td>" +
            plan.direccion +
            "</td>" +
            "<td>" +
            plan.area +
            "</td>" +
            "<td>" +
            plan.unidad +
            "</td>" +
            "<td>" +
            plan.criticidad +
            "</td>" +
            "<td>" +
            plan.prioridad +
            "</td>" +
            "<td>" +
            plan.servicio +
            "</td>" +
            "<td>" +
            formattedEstado +
            "</td>" +
            "<td>" +
            formattedElevencert +
            "</td>" +
            "<td>" +
            formattedEprivacy +
            "</td>" +
            "<td>" +
            formattedJefeproyecto +
            "</td>" +
            "<td>" +
            plan.secretoempresarial +
            "</td>" +
            "<td>" +
            plan.entorno +
            "</td>" +
            "<td>" +
            plan.tenable +
            "</td>" +
            "<td>" +
            plan.dome9 +
            "</td>" +
            "<td>" +
            plan.usuarioacceso +
            "</td>" +
            "<td>" +
            plan.revisiones +
            "</td>" +
            "<td>" +
            plan.q1 +
            "</td>" +
            "<td>" +
            plan.q2 +
            "</td>" +
            "<td>" +
            plan.q3 +
            "</td>" +
            "<td>" +
            plan.q4 +
            "</td>";
        }

        let options = {
          rowSelect: true,
          labels: {
            noResults: "No se han encontrado resultados.",
            infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
            search: "Buscar",
          },
          formatters: {
            commands: function (column, row) {
              return (
                "<button type='button' class='btn btn-xs btn-default command-edit'><img class='icono' src='./img/edit.svg' /></button>" +
                "<button type='button' class='btn btn-xs btn-default command-delete'><img class='icono' src='./img/delete.svg' /></button>"
              );
            },
          },
        };

        let grid = $("#table-plan").bootgrid(options);
        let grid2 = $("#table-plan2").bootgrid(options);

        $(".actionBar").prepend(btnnuevo);
        grid.on("loaded.rs.jquery.bootgrid", function () {
          grid.find(".command-edit").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-plan").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (e) {
                return e.id;
              })
              .indexOf(parseInt(idrow));
            let form = `<form class="formEdit"><div class="text-start row"> \
            <input class="mshide" name="id" value="${idrow}">
            <div class="col-sm-6"><label>Dirección</label> \
            <input name="direccion" class="form-control" id="direccion1" placeholder="Dirección" value="${rows[pos].direccion}"></div>\
            <div class="col-sm-6"><label>Area</label> \
            <input name="area" class="form-control" id="area1" placeholder="Area" value="${rows[pos].area}"></div>\
            <div class="col-sm-6"><label>Unidad</label> \
            <input name="unidad" class="form-control" id="unidad1" placeholder="unidad" value="${rows[pos].unidad}"></div>\
            <div class="col-sm-6"><label>criticidad</label> \
            <input name="criticidad" class="form-control" id="criticidad1" placeholder="criticidad" value="${rows[pos].criticidad}"></div>\
            <div class="col-sm-6"><label>prioridad</label> \
            <input name="prioridad" class="form-control" id="prioridad1" placeholder="prioridad" value="${rows[pos].prioridad}"></div>\
            <div class="col-sm-6"><label>servicio</label> \
            <input name="servicio" class="form-control" id="servicio1" placeholder="servicio" value="${rows[pos].servicio}"></div>\
            <div class="col-sm-6"><label>estado</label> \
            <input name="estado" class="form-control" id="estado1" placeholder="estado" value="${rows[pos].formattedEstado}"></div>\
            <div class="col-sm-6"><label>elevencert</label> \
            <input name="elevencert" class="form-control" id="elevencert1" placeholder="elevencert" value="${rows[pos].formattedElevencert}"></div>\
            <div class="col-sm-6"><label>eprivacy</label> \
            <input name="eprivacy" class="form-control" id="eprivacy1" placeholder="eprivacy" value="${rows[pos].formattedEprivacy}"></div>\
            <div class="col-sm-6"><label>JefeProyecto</label> \
            <input name="jefeproyecto" class="form-control" id="JefeProyecto1" placeholder="JefeProyecto" value="${rows[pos].formattedJefeproyecto}"></div>\
            <div class="col-sm-6"><label>SecretoEmpresarial</label> \
            <input name="secretoempresarial" class="form-control" id="SecretoEmpresarial1" placeholder="SecretoEmpresarial" value="${rows[pos].secretoempresarial}"></div>\
            <div class="col-sm-6"><label>Entorno</label> \
            <input name="entorno" class="form-control" id="Entorno1" placeholder="Entorno" value="${rows[pos].entorno}"></div>\
            <div class="col-sm-6"><label>Tenable</label> \
            <input name="tenable" class="form-control" id="Tenable1" placeholder="Tenable" value="${rows[pos].tenable}"></div>\
            <div class="col-sm-6"><label>DOME9</label> \
            <input name="dome9" class="form-control" id="DOME91" placeholder="DOME9" value="${rows[pos].dome9}"></div>\
            <div class="col-sm-6"><label>UsuarioAcceso</label> \
            <input name="usuarioacceso" class="form-control" id="UsuarioAcceso1" placeholder="UsuarioAcceso" value="${rows[pos].usuarioacceso}"></div>\
            <div class="col-sm-6"><label>Revisiones</label> \
            <input name="revisiones" class="form-control" id="Revisiones1" placeholder="Revisiones" value="${rows[pos].revisiones}"></div>\
            <div class="col-sm-6"><label>q1</label> \
            <input name="q1" class="form-control" id="q1" placeholder="q1" value="${rows[pos].q1}"></div>\
            <div class="col-sm-6"><label>q2</label> \
            <input name="q2" class="form-control" id="q2" placeholder="q2" value="${rows[pos].q2}"></div>\
            <div class="col-sm-6"><label>q3</label> \
            <input name="q3" class="form-control" id="q3" placeholder="q3" value="${rows[pos].q3}"></div>\
            <div class="col-sm-6"><label>q4</label> \
            <input name="q4" class="form-control" id="q4" placeholder="q4" value="${rows[pos].q4}"></div>\
          </div></form>`;
            showModalWindow(
              "Editar plan",
              form,
              editarplan,
              "Cancelar",
              "Guardar"
            );
          });

          grid.find(".command-delete").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            showModalWindow(
              "Borrar plan",
              `<form class="formDel"><input name="id" class="mshide" value="${idrow}">¿Desea borrar la fila con id ${idrow}?</form>`,
              borrarplan,
              "Cancelar",
              "Borrar"
            );
          });
        });
        grid2.on("loaded.rs.jquery.bootgrid", function () {
          grid2.find(".command-edit").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-plan2").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (e) {
                return e.id;
              })
              .indexOf(parseInt(idrow));
            let form = `<form class="formEdit"><div class="text-start row"> \
              <input class="mshide" name="id" value="${idrow}">
              <div class="col-sm-6"><label>Dirección</label> \
              <input name="direccion" class="form-control" id="direccion1" placeholder="Dirección" value="${rows[pos].direccion}"></div>\
              <div class="col-sm-6"><label>Area</label> \
              <input name="area" class="form-control" id="area1" placeholder="Area" value="${rows[pos].area}"></div>\
              <div class="col-sm-6"><label>Unidad</label> \
              <input name="unidad" class="form-control" id="unidad1" placeholder="unidad" value="${rows[pos].unidad}"></div>\
              <div class="col-sm-6"><label>criticidad</label> \
              <input name="criticidad" class="form-control" id="criticidad1" placeholder="criticidad" value="${rows[pos].criticidad}"></div>\
              <div class="col-sm-6"><label>prioridad</label> \
              <input name="prioridad" class="form-control" id="prioridad1" placeholder="prioridad" value="${rows[pos].prioridad}"></div>\
              <div class="col-sm-6"><label>servicio</label> \
              <input name="servicio" class="form-control" id="servicio1" placeholder="servicio" value="${rows[pos].servicio}"></div>\
              <div class="col-sm-6"><label>estado</label> \
              <input name="estado" class="form-control" id="estado1" placeholder="estado" value="${rows[pos].formattedEstado}"></div>\
              <div class="col-sm-6"><label>elevencert</label> \
              <input name="elevencert" class="form-control" id="elevencert1" placeholder="elevencert" value="${rows[pos].formattedElevencert}"></div>\
              <div class="col-sm-6"><label>eprivacy</label> \
              <input name="eprivacy" class="form-control" id="eprivacy1" placeholder="eprivacy" value="${rows[pos].formattedEprivacy}"></div>\
              <div class="col-sm-6"><label>JefeProyecto</label> \
              <input name="jefeproyecto" class="form-control" id="JefeProyecto1" placeholder="JefeProyecto" value="${rows[pos].formattedJefeproyecto}"></div>\
              <div class="col-sm-6"><label>SecretoEmpresarial</label> \
              <input name="secretoempresarial" class="form-control" id="SecretoEmpresarial1" placeholder="SecretoEmpresarial" value="${rows[pos].secretoempresarial}"></div>\
              <div class="col-sm-6"><label>Entorno</label> \
              <input name="entorno" class="form-control" id="Entorno1" placeholder="Entorno" value="${rows[pos].entorno}"></div>\
              <div class="col-sm-6"><label>Tenable</label> \
              <input name="tenable" class="form-control" id="Tenable1" placeholder="Tenable" value="${rows[pos].tenable}"></div>\
              <div class="col-sm-6"><label>DOME9</label> \
              <input name="dome9" class="form-control" id="DOME91" placeholder="DOME9" value="${rows[pos].dome9}"></div>\
              <div class="col-sm-6"><label>UsuarioAcceso</label> \
              <input name="usuarioacceso" class="form-control" id="UsuarioAcceso1" placeholder="UsuarioAcceso" value="${rows[pos].usuarioacceso}"></div>\
              <div class="col-sm-6"><label>Revisiones</label> \
              <input name="revisiones" class="form-control" id="Revisiones1" placeholder="Revisiones" value="${rows[pos].revisiones}"></div>\
              <div class="col-sm-6"><label>q1</label> \
              <input name="q1" class="form-control" id="q1" placeholder="q1" value="${rows[pos].q1}"></div>\
              <div class="col-sm-6"><label>q2</label> \
              <input name="q2" class="form-control" id="q2" placeholder="q2" value="${rows[pos].q2}"></div>\
              <div class="col-sm-6"><label>q3</label> \
              <input name="q3" class="form-control" id="q3" placeholder="q3" value="${rows[pos].q3}"></div>\
              <div class="col-sm-6"><label>q4</label> \
              <input name="q4" class="form-control" id="q4" placeholder="q4" value="${rows[pos].q4}"></div>\
            </div></form>`;
            showModalWindow(
              "Editar plan",
              form,
              editarplan,
              "Cancelar",
              "Guardar"
            );
          });

          grid2.find(".command-delete").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            showModalWindow(
              "Borrar plan",
              `<form class="formDel"><input name="id" class="mshide" value="${idrow}">¿Desea borrar la fila con id ${idrow}?</form>`,
              borrarplan,
              "Cancelar",
              "Borrar"
            );
          });
        });
        if ($("#table-plan2").bootgrid("getCurrentRows").length == 0) {
          $(".divtable2").addClass("mshide");
        }
        $(".divtable").removeClass("mshide");
        selectorPlanCambio();
        cerrarModal();
      }
    },
  });
}

function savePacSeguimiento() {
  mostrarLoading();
  let servicioId = $(".servicioId").attr("id");
  let form = $("#regForm");

  $.ajax({
    type: "POST",
    url: `./api/savePacSeguimiento?id=${servicioId}`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(
          "ERROR",
          "Falta información para poder crear el seguimiento de PAC",
          null,
          "Aceptar",
          null,
          null
        );
      } else {
        showModalWindow(
          INFORMACION,
          "Plan de seguimiento guardado correctamente",
          null,
          "Aceptar",
          null,
          getPacServicio
        );
      }
    },
  });
}

function selectProyecto(selector, form) {
  if (selector.value !== "undefined") {
    $.ajax({
      type: "GET",
      url: `./api/getSystemsWithoutProject?proyect_id=${selector.value}&servicio_id=${form.servicioId.value}`,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        if (retorno.sistemas.length > 0) {
          $("#sistemasPac").empty();
          $("#sistemasPac").prop("disabled", false);
          $.each(retorno.sistemas, function (i, item) {
            $("#sistemasPac").append(
              $("<option>", {
                value: item.id,
                text: item.nombre,
              })
            );
          });
        } else {
          $("#sistemasPac").empty();
          $("#sistemasPac").append(
            $("<option>", {
              value: "undefined",
              text: "Ninguno...",
            })
          );
          $("#sistemasPac").prop("disabled", true);
        }
      },
    });
  } else {
    $("#sistemasPac").empty();
    $("#sistemasPac").append(
      $("<option>", {
        value: "undefined",
        text: "Ninguno...",
      })
    );
    $("#sistemasPac").prop("disabled", true);
  }
}

function newPacServicio() {
  let id = $(".servicioId").attr("id");
  $.ajax({
    type: "GET",
    url: `./api/getProyectosNoCreados?id=${id}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      let options = '<option value="undefined" selected>Ninguno</option>';
      for (let pac of retorno.proyectos) {
        options += `<option value="${pac.id}" >${pac.cod} - ${pac.nombre}</option>`;
      }
      let form =
        `<form id="newproyect"><div class="form-group mb-4 row">
        <input class="mshide" name="servicioId" value="${$(".servicioId").attr(
          "id"
        )}">
      <label class="col-4 col-form-label proyecto-label">Proyecto</label>
      <div class="col-8">
          <select id="proyecto" name="proyecto" class="form-select-custom proyectoInput" required="required">` +
        options +
        `</select>
      </div>
      <label class="col-4 col-form-label proyecto-label">Sistemas</label>
      <div class="col-8">
      <select id="sistemasPac" name="sistema" class="form-select" aria-label="Disabled" disabled>
        <option value="undefined" selected>Ninguno...</option>
      </select>
      </div>
      <div class="form-group row sistemaBloqueGeneral justify-content-end mshide">
      <button type="button" class="btn btn-transparent añadirBtn">
          <img src="./img/añadir.svg" alt="Imagen" class="img-fluid" />
      </button>
      <button type="button" class="btn btn-transparent quitarBtn">
          <img src="./img/restar.svg" alt="Imagen" class="img-fluid" />
      </button>
  </div>
  <div class="form-group mb-4 row sistemaBloqueGeneral sistemaBloque mshide">
      <label for="select7" class="col-4 col-form-label sistema-label">Sistema</label>
      <div class="col-8">
          <select id="sistema" name="Sistema" class="form-select-custom sistemaInput sistemaInput0" required="required">
              <option value="Ninguno">Ninguno</option>
          </select>
      </div>
  </div>
  </div></form>`;
      showModalWindow(
        "Nuevo seguimiento de PAC",
        form,
        createNewPac,
        "Cancelar",
        "Crear"
      );
      let selector = document.getElementById("proyecto");
      form = document.getElementById("newproyect");
      selector.addEventListener("change", function () {
        selectProyecto(selector, form);
      });
    },
  });
}

function getPacServicio() {
  mostrarLoading();
  let servicioId = $(".servicioId").attr("id");
  $("#table-seguimientopac").bootgrid("destroy");
  $("#table-seguimientopac-data").html("");
  $.ajax({
    type: "GET",
    url: `./api/getListPac?id=${servicioId}`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        cerrarModal();
        localStorage.clear();
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null, goLogin);
      } else {
        let table = document.getElementById("table-seguimientopac-data");
        let index = 0;
        for (let sistema of retorno.seguimientos) {
          for (let seguimiento of sistema) {
            table.insertRow().innerHTML =
              "<td>" +
              seguimiento.id +
              "</td>" +
              "<td>" +
              seguimiento.activo_id +
              "</td>" +
              "<td>" +
              seguimiento.proyecto_id +
              "</td>" +
              "<td>" +
              seguimiento.evaluacion_id +
              "</td>" +
              "<td>" +
              retorno.sistemas[index].nombre +
              "</td>" +
              "<td>" +
              seguimiento.nombrepac +
              "</td>" +
              "<td>" +
              (seguimiento.prioridad ? seguimiento.prioridad : "") +
              "</td>" +
              "<td>" +
              seguimiento.responsable +
              "</td>" +
              "<td>" +
              seguimiento.estado +
              "</td>" +
              "<td>" +
              seguimiento.inicio +
              "</td>" +
              "<td>" +
              seguimiento.fin +
              "</td>" +
              `<td>` +
              seguimiento.creado +
              "</td>" +
              "<td>" +
              seguimiento.comentarios +
              "</td>" +
              "<td>" +
              seguimiento.adjuntos +
              "</td>";
          }
          index++;
        }

        let options = {
          rowSelect: true,
          labels: {
            noResults: "No se han encontrado resultados.",
            infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
            search: "Buscar",
          },
          formatters: {
            commands: function (column, row) {
              let commands = "";
              commands +=
                "<button type='button' class='btn btn-xs btn-default command-edit' title='Editar PAC'><img class='icono' src='./img/edit.svg' /></button>";
              if (row.estado == "Descartado") {
                commands +=
                  "<button type='button' class='btn btn-xs btn-default command-docasuncion'><img class='icono' src='./img/docasuncion.svg' /></button>";
              }
              return commands;
            },
          },
        };
        let grid = $("#table-seguimientopac").bootgrid(options);
        grid.on("loaded.rs.jquery.bootgrid", function () {
          grid.find(".command-edit").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-seguimientopac").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (ie) {
                return ie.id;
              })
              .indexOf(parseInt(idrow));
            let options =
              '<option value="No iniciado">No iniciado</option>' +
              '<option value="No Aplica">No Aplica</option>' +
              '<option value="Iniciado">Iniciado</option>' +
              '<option value="En Progreso">En Progreso</option>' +
              '<option value="Finalizado">Finalizado</option>' +
              '<option value="Descartado">Descartado</option>';
            let optionsselect = options.replace(
              `"${rows[pos].estado}"`,
              `"${rows[pos].estado}" selected`
            );
            let form =
              `<label class="mshide sysName">${rows[pos].sistema}</label>` +
              `<form class="formEdit needs-validation"><div class="text-start row">` +
              `<input class="mshide" name="id" value="${rows[pos].id}">` +
              `<div class="col-sm-6 bloc-inicio"><label>Fecha inicio</label>` +
              `<input name="inicio" type="date" class="form-control" id="inicio" placeholder="inicio" value="${rows[pos].inicio}" min="2022-1-1"></div>` +
              `<div class="col-sm-6 bloc-fin"><label>Fecha fin</label>` +
              `<input name="fin" type="date" class="form-control" id="fin" placeholder="fin" value="${rows[pos].fin}" min="2022-1-1"></div>` +
              `<div class="col-sm-6"><label>Responsable</label>` +
              `<input name="responsable" class="form-control" id="responsable" placeholder="responsables" value="${rows[pos].responsable}"></div>` +
              `<div class="col-sm-6"><label>Estado</label>` +
              `<select name="estado" id="estado" class="form-select">` +
              `${optionsselect}` +
              `</select></div>` +
              `<div class="col-sm-6"><label>Comentarios</label>` +
              `<textarea id='comentarios' maxlength="250" class='form-control' rows='3' name="comentarios">${rows[pos].comentarios}</textarea>` +
              `</div>` +
              `</form><div class="validation-text"></div>`;
            showModalWindow(
              `Editar proyecto ${rows[pos].proyecto} del sistema ${rows[pos].sistema}`,
              form,
              editarseguimiento,
              "Cancelar",
              "Guardar"
            );

            if (
              $("#estado").val() === "No iniciado" ||
              $("#estado").val() === "No Aplica"
            ) {
              $(".bloc-inicio").addClass("mshide");
              $(".bloc-fin").addClass("mshide");
            }

            $("#estado").change(function () {
              if (
                $("#estado").val() === "No iniciado" ||
                $("#estado").val() === "No Aplica"
              ) {
                $(".bloc-inicio").addClass("mshide");
                $("#inicio").prop("required", false);
                $(".bloc-fin").addClass("mshide");
                $("#fin").prop("required", false);
              } else {
                $("#inicio").prop("required", true);
                $(".bloc-inicio").removeClass("mshide");
                $("#fin").prop("required", true);
                $(".bloc-fin").removeClass("mshide");
              }
            });
          });

          grid.find(".command-docasuncion").on("click", function (e) {
            let row = $(this).closest("tr");
            let seguimientoId = row.data("row-id");
            if (seguimientoId !== undefined) {
              let url = `./downloadAsuncionSeguimiento?id=${seguimientoId}`;
              let link = document.createElement("a");
              link.href = url;
              document.body.appendChild(link);
              link.click();
              document.body.removeChild(link);
            } else {
              showModalWindow(
                "Error",
                "Ha ocurrido un error contacte con el administrador.",
                null,
                "Cerrar",
                null,
                null
              );
            }
          });

          $(".download-group").remove();
          $(".actions").prepend(
            '<div class="btn btn-default download-group"><img class="icono downloadTabla" src="./img/download.svg" alt="descargar" title="Descargar tabla"></div>'
          );

          $(".refresh-pac").remove();
          $(".actions").prepend(
            '<div class="btn btn-default refresh-pac"><img class="icono refresh-tabla" src="./img/Refrescar.svg" alt="Actualizar" title="Actualizar tabla"></div>'
          );

          $(".new-pac").remove();
          $(".actionBar").prepend(
            '<div class="new-pac search form-group"><button class="btn btn-primary">Nuevo PAC</button></div>'
          );

          $(".refresh-pac").click(function () {
            getPacServicio();
          });

          $(".new-pac").click(function () {
            newPacServicio();
          });

          grid.find(".command-delete").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-seguimientopac").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (ie) {
                return ie.id;
              })
              .indexOf(parseInt(idrow));
            showModalWindow(
              `Borrar proyecto ${rows[pos].proyecto} del sistema ${rows[pos].sistema}`,
              `<form class="formDel"><input class="mshide" name="id" value="${rows[pos].id}">¿Desea borrar el seguimiento del PAC?</form>`,
              borrarseguimiento,
              "Cancelar",
              "Borrar"
            );
          });
        });
        cerrarModal();
      }
    },
  });
}

function getActivos(tipo, upd) {
  mostrarLoading();
  let archivado = "";
  if (tipo !== undefined) {
    if (tipo == "42a") {
      tipo = "42a";
      archivado = "&archivado=1";
    }
    $.ajax({
      url: `./api/getActivos?tipo=${tipo}${archivado}&filtro=${upd}`,
      cache: true,
      method: "GET",
      xhrFields: {
        withCredentials: true,
      },
      beforeSend: function (request) {
        const url = this.url;
        if (eTagStorage[url]) {
          request.setRequestHeader("If-None-Match", eTagStorage[url]);
        }
      },
      success: function (retorno, textStatus, request) {
        if (request.status === 304) {
          retorno = JSON.parse(sessionStorage.getItem(this.url));
        } else {
          sessionStorage.setItem(this.url, JSON.stringify(retorno));
        }
        if (
          retorno.servicios !== null &&
          typeof retorno.servicios === "object"
        ) {
          if (request.getResponseHeader("ETag") !== null) {
            eTagStorage[this.url] = request.getResponseHeader("ETag");
          }

          $(".alertas").empty();
          let biaoutdated = false;
          let ecrtotal = false;
          let biaempty = false;
          let ecrincomplete = false;
          let alert_msg = "";
          let avisoimgbiarojo = `<img class="icono aviso-rojo" alt="icono aviso rojo bia" src="./img/bia.svg">`;
          let avisoimgbianaranja = `<img class="icono aviso-naranja" alt="icono aviso naranja bia" src="./img/bia.svg">`;
          let html_ini = `<div class="alert text-center alert-warning">`;
          let html_fin = `</div>`;
          let avisoimgecrrojo = `<img class="icono aviso-rojo" alt="icono aviso rojo ecr" src="./img/history.svg">`;
          let avisoimgecrnaranja = `<img class="icono aviso-naranja" alt="icono aviso naranja ecr" src="./img/history.svg">`;
          if (retorno.error !== true) {
            let funcion = new Object();
            clearResponse();

            for (let servicio of retorno.servicios) {
              funcion["edit"] = servicio.tipo;
              funcion["del_relation"] = true;
              funcion["Change_parents"] = true;
              if (tipo !== "42") {
                funcion["del"] = false;
                funcion["eval"] = false;
                funcion["history"] = false;
                funcion["bia"] = false;
                funcion["clone"] = false;
                funcion["personas"] = false;
              } else {
                funcion["del"] = true;
                funcion["eval"] = true;
                funcion["history"] = true;
                funcion["bia"] = true;
                funcion["clone"] = true;
                funcion["personas"] = true;
              }
              let fila = newTarjeta(
                ".activos > .response",
                servicio.nombre,
                servicio.id,
                funcion
              );
              if (servicio.nombre.endsWith("_PRE")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-success pre'>PRE</span>"
                );
              }
              if (servicio.nombre.endsWith("_PRO")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-primary pro'>PRO</span>"
                );
              }
              if (servicio.nombre.endsWith("_DEV")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-secondary dev'>DEV</span>"
                );
              }
              if (servicio.nombre.endsWith("_CERT")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-success cert'>CERT</span>"
                );
              }
              if (servicio.nombre.endsWith("_INT")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-success int'>INT</span>"
                );
              }
              if (servicio.nombre.endsWith("_QA")) {
                $(fila.firstChild).prepend(
                  "<span class='badge bg-success qa'>QA</span>"
                );
              }

              if (servicio.descripcion !== null) {
                $(fila).attr("data-toggle", "tooltip");
                $(fila).attr("data-placement", "bottom");
                fila.title = servicio.descripcion;
              }
              if (
                parseInt(servicio.tipo) == 42 &&
                servicio.bia &&
                servicio.biaoutdated
              ) {
                $(fila.lastChild.firstChild).addClass("aviso-naranja");
              } else if (parseInt(servicio.tipo) == 42 && !servicio.bia) {
                $(fila.lastChild.firstChild).addClass("aviso-rojo");
              }

              if (
                parseInt(servicio.tipo) == 42 &&
                (servicio.ecr == 0 || servicio.ecr == undefined)
              ) {
                $(fila.lastChild.children[1]).addClass("aviso-rojo");
              } else if (servicio.ecr < servicio.total) {
                $(fila.lastChild.children[1]).addClass("aviso-naranja");
              }

              if (
                (!servicio.bia && !biaempty && parseInt(servicio.tipo) == 42) ||
                (servicio.biaoutdated && !biaoutdated)
              ) {
                const color = servicio.biaoutdated
                  ? avisoimgbianaranja
                  : avisoimgbiarojo;
                alert_msg += `Servicio sin BIA ${servicio.biaoutdated ? "actualizado" : "subido"
                  }, localízalo con ${color} `;
                if (servicio.biaoutdated) {
                  biaoutdated = true;
                } else {
                  biaempty = true;
                }
              }

              if (
                (servicio.ecr < servicio.total && !ecrtotal) ||
                (parseInt(servicio.tipo) == 42 &&
                  !servicio.ecr &&
                  !ecrincomplete)
              ) {
                const color =
                  servicio.ecr < servicio.total
                    ? avisoimgecrnaranja
                    : avisoimgecrrojo;
                alert_msg += `Cuestionario ${servicio.ecr < servicio.total
                    ? "sin completar"
                    : "sin realizar"
                  }, localízalo con ${color} `;
                // Determinar estado del cuestionario ECR
                let estadoEcr = servicio.ecr < servicio.total;
                if (estadoEcr) {
                  ecrtotal = true;
                } else {
                  ecrincomplete = true;
                }
              }

              if (servicio.externo == 1) {
                $(fila).addClass("ext");
              }
              if (servicio.expuesto == 1) {
                $(fila).addClass("exp");
              }
              if (servicio.archivado == 1) {
                $(fila).addClass("arch");
              }
            }
            if (alert_msg !== "") {
              setTimeout(() => {
                $(".alert").alert("close");
              }, 10000);
              $(".alertas").prepend(html_ini + alert_msg + html_fin);
            }
          }
          $('[data-toggle="tooltip"]').tooltip();
          cerrarModal();
        } else {
          cerrarModal();
          localStorage.clear();
          showModalWindow(
            ERROR,
            retorno.message,
            null,
            "Cerrar",
            null,
            goLogin
          );
        }
        handler();
      },
    });
  }
}

function getEvalNoEvaluados(norma, idEvaluacion, idVersion) {
  mostrarLoading();
  $.ajax({
    type: "GET",
    url: `./api/getEvalNoEvaluados?norma=${norma}&fecha=${idEvaluacion}&idVersion=${idVersion}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (retorno.error !== true) {
        norma = norma.replace(/\(/g, "\\(").replace(/\)/g, "\\)");
        crearFormCuestionario(
          retorno.preguntas,
          `.preguntas-ne-${norma} > .completeNe`
        );

        let preguntasContainer = $(`.preguntas-ne-${norma} > .completeNe`);
        let buttonsContainer = $('<div class="text-end"></div>');

        if (retorno.preguntas.length > 0) {
          buttonsContainer.append(
            '<button type="button" class="btn btn-primary btn-cancelar-ne">Cancelar</button>' +
            '<button type="button" class="btn btn-primary btn-enviar-ne">Enviar</button>'
          );
        } else {
          buttonsContainer.append(
            '<button type="button" class="btn btn-primary btn-cancelar-ne">Cancelar</button>'
          );
        }

        preguntasContainer.append(buttonsContainer);

        $(".btn-cancelar-ne").click(clearResponse);

        $(".btn-enviar-ne").click(function () {
          let form = preguntasContainer;
          updateEvalNe(idEvaluacion, idVersion, form);
        });

        cerrarModal();
      } else {
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null);
      }
    },
  });
}

function obtainMedia(id) {
  $.ajax({
    type: "GET",
    url: `./api/getMedia?id=${id}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      $(".mediaEval").text("(" + retorno["media"] + ")");
    },
  });
}

function updateEvalNe(idEvaluacion, idVersion, form) {
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/updateEvalNe?fecha=${idEvaluacion}&idVersion=${idVersion}`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error !== true) {
        location.reload();
      } else {
        cerrarModal();
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null);
      }
    },
  });
}

function updateEvalServicio(id, normativa) {
  mostrarLoading();
  $.ajax({
    type: "GET",
    url: `./api/getEvalServicio?id=${id}${normativa}`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error !== true) {
        crearFormCuestionario(retorno.preguntas, ".evalservicio > .eval");
      } else {
        cerrarModal();
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null);
      }
    },
  });
}

async function getNormativasEnabled() {
  const response = await fetch("./api/getNormativas", {
    credentials: "include",
  });
  const normativasData = await response.json();
  return (normativasData.normativas || [])
    .filter((n) => n.enabled === 1)
    .map((n) => n.nombre);
}

function updateHistorialServicio(id) {
  if ($(".sistemaEval").val() !== null) {
    mostrarLoading();
    let fechaId = $(`.${FECHA_EVAL}`).val();
    let edicion = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
    let url;
    if (edicion != undefined) {
      url = `./api/getHistoryServicio?id=${id}&fecha=${fechaId}&version=${edicion}`;
    } else {
      url = `./api/getHistoryServicio?id=${id}&fecha=${fechaId}`;
    }

    $(".historialservicio > .tarjeta").remove();
    if (fechaId !== null) {
      getNormativasEnabled().then((normativasEnabled) => {
        let fetchOptions = {
          method: "GET",
          credentials: "include",
          headers: {},
        };
        if (eTagStorage[url]) {
          fetchOptions.headers["If-None-Match"] = eTagStorage[url];
        }
        fetch(url, fetchOptions)
          .then(async (response) => {
            const etag = response.headers.get("ETag");
            if (etag) {
              eTagStorage[url] = etag;
            }
            const retorno = await response.json();
            if (retorno.eval !== null && typeof retorno.eval === "object") {
              let norm = Object.entries(retorno.eval);
              for (let i = 0; i < norm.length; i++) {
                if (
                  norm[i][1].length != 0 &&
                  normativasEnabled.includes(norm[i][0])
                ) {
                  let norma = norm[i][0];
                  newTarjeta(".historialservicio", `${norma}`, `normativa${i}`);
                  let tarjeta = $(`#normativa${i}`).parent()[0];
                  let contenido =
                    "<div class='barras col-6 d-flex justify-content-center'></div><div class='tarta col-6 d-flex justify-content-center'></div>";
                  $(tarjeta).append(contenido);
                  let totalNE = norm[i][1].reduce(
                    (acumulador, objeto) => acumulador + objeto.NE,
                    0
                  );
                  if (totalNE > 0) {
                    $(tarjeta).append(
                      `<div class="preguntas-ne-${norm[i][0]}"><form class='completeNe col-12'></form></div><small class="text-muted">Para cumplimentar los controles No Evaluados(NE) pulsa sobre la zona gris del grafico de donut</small>`
                    );
                  }

                  pintarGrafica(tarjeta, "corechart", pintarTarta, norm[i]);
                  pintarGrafica(
                    tarjeta,
                    "corechart",
                    pintarBarrasAnidadas,
                    norm[i]
                  );
                  $(tarjeta).css("width", "100%");
                }
              }
            }
            cerrarModal();
          })
          .catch((error) => {
            cerrarModal();
            showModalWindow(
              ERROR,
              "Error al obtener el historial del servicio.",
              null,
              "Cerrar",
              null,
              null
            );
          });
      });
    }
  } else {
    $("#graficoLineal").text("No hay evaluaciones realizadas.");
  }
}

function rellenarPac(datos, amenazas) {
  $(".pac").empty();
  const conValue = $(".con").val();
  const intValue = $(".int").val();
  const disValue = $(".dis").val();
  if (datos.length != 0) {
    $.each(datos, function (i, val) {
      let rows = "";
      let media = 0;
      let totalSum = 0;
      let valusf;
      let totalUSF = 0;
      let mediaUSF = 0;
      if (val.usf !== undefined) {
        valusf = val.usf;
      } else {
        valusf = val.ps;
      }
      let riesgoMasAlto = 0;
      let denominador = 0;
      $.each(valusf, function (x, valor) {
        let riesgoSuma = 0;
        let riesgoActual = 0;
        let riesgoActualLiteral = "";
        const riesgoLiteral = ["Leve", "Bajo", "Moderado", "Alto", "Crítico"];

        let amenazasEncontradas = amenazas.filter((amenaza) => {
          if (valusf == val.usf) {
            amenaza = amenaza.usf;
          } else {
            amenaza = amenaza.ps;
          }
          return Object.keys(amenaza).includes(x);
        });
        $.each(amenazasEncontradas, function (i, amenaza) {
          let riesgo = calcularRiesgo(amenaza, conValue, intValue, disValue);
          if (riesgoSuma < riesgo.valorNumerico) {
            riesgoSuma = riesgo.valorNumerico;
          }
        });
        riesgoActual = riesgoSuma;
        denominador += 1;
        let riesgoResidual = riesgoActual * (1 - valor.ctm / 100);
        if (riesgoMasAlto < riesgoResidual) {
          riesgoMasAlto = riesgoResidual;
        }
        riesgoActualLiteral = riesgoLiteral[riesgoActual];
        rows =
          rows +
          `<tr><td>${x}</td><td>${valor.ctm} %</td><td>${riesgoActualLiteral}</td><td>${riesgoResidual}</td></tr>`;
        media += valor.ct;
        totalSum += valor.total;
        totalUSF += valor.ctm;
        mediaUSF = (totalUSF / denominador).toFixed(2);
      });

      let prioridad = 0;
      prioridad = riesgoMasAlto;
      if (prioridad <= 1) {
        prioridad = "Baja";
      } else if (prioridad <= 2) {
        prioridad = "Media";
      } else if (prioridad <= 3) {
        prioridad = "Alta";
      } else if (prioridad <= 4) {
        prioridad = "Crítica";
      } else {
        prioridad = "Desconocida";
      }

      if (totalSum !== 0) {
        media = (media / totalSum) * 100;
      } else {
        media = 0;
      }

      if (media !== 100) {
        newTarjeta(
          ".pac",
          `
      <div class='row color-blue padding-10px'>
        <div class='col-6'>${i}</div><div class='col-6'>${val.nombre}</div>
      </div>
      <div class='row border-1px'>
        <div class='col-2 color-blue text-center'>Descripción </div><div class='col-10 padding-10px'>${val.descripcion}</div>
      </div>
      <div class='row border-1px'>
        <div class='col-2 color-blue text-center'>Prioridad </div><div class='col-10 padding-10px'>${prioridad}</div>
      </div>
      <div class='row border-1px'>
        <div class='col-2 color-blue text-center'>Controles</div>
        <div class='col-10'>
          <table id="table-${i}" class="table table-condensed table-hover table-striped table-borderless">
            <thead>
                <tr>
                    <th data-column-id="codUSF">USF</th>
                    <th data-column-id="domUSF">Cumplimiento USF (${mediaUSF}%) </th>
                    <th data-column-id="domUSF">Riesgo Actual</th>
                    <th data-column-id="domUSF">Mitigación</th>
                </tr>
            </thead>
            <tbody id='table-${i}-data'>
              ${rows}
            </tbody>
          </table>
        </div>
      </div>
      <div class='row border-1px'>
        <div class='col-2 color-blue text-center'>Tareas</div><div class='col-10 padding-10px'>${val.tareas}</div>
      </div>`,
          i
        );
      }
    });
  } else {
    hideItem(".pac", true);
  }
  if ($(".pac").html() == "") {
    newTarjeta(
      ".pac",
      '<div class="text-center">Debido al cumplimiento de todos los controles para esta evaluación y habiendo analizado todos los activos, el resultado es que no es necesario aplicar ningún Plan de Acciones Correctivas.</div>',
      "no-pac"
    );
  }
}

/**
 * Calcula el nivel de riesgo basado en la amenaza y los valores de impacto
 * @param {Object} amenaza - Objeto con información de la amenaza
 * @param {number} conValue - Valor de confidencialidad
 * @param {number} intValue - Valor de integridad
 * @param {number} disValue - Valor de disponibilidad
 * @returns {Object} Objeto con información de riesgo calculado
 */
function calcularRiesgo(amenaza, conValue, intValue, disValue) {
  const posicionX = calcularPosicionX(amenaza);
  const posicionY = calcularPosicionY(amenaza, conValue, intValue, disValue);
  const resultado = obtenerNivelRiesgo(posicionX, posicionY);

  return {
    xnow: posicionX,
    y: posicionY,
    nivelRiesgo: resultado.nivel,
    valorNumerico: resultado.valor,
  };
}

function calcularPosicionX(amenaza) {
  let x = parseInt(amenaza.probabilidad) - 1;
  if (x < 0) {
    x = 0;
  }

  let xAjustado = x + parseInt(amenaza.ajustada.x);
  if (xAjustado < 0) {
    xAjustado = 0;
  }
  if (xAjustado > 4) {
    xAjustado = 4;
  }

  return xAjustado;
}

function calcularPosicionY(amenaza, conValue, intValue, disValue) {
  const impactoBase = calcularImpactoBase(
    amenaza,
    conValue,
    intValue,
    disValue
  );

  const ejeY = [4, 3, 2, 1, 0];
  let yAjustado = ejeY[impactoBase] - parseInt(amenaza.ajustada.y);

  if (yAjustado > 4) {
    yAjustado = 4;
  }
  if (yAjustado < 0) {
    yAjustado = 0;
  }

  return yAjustado;
}

function calcularImpactoBase(amenaza, conValue, intValue, disValue) {
  let impacto = 0;

  if (parseInt(amenaza.confidencialidad) === 1) {
    impacto = Math.max(parseInt(conValue), impacto);
  }

  if (parseInt(amenaza.integridad) === 1) {
    impacto = Math.max(parseInt(intValue), impacto);
  }

  if (parseInt(amenaza.disponibilidad) === 1) {
    impacto = Math.max(parseInt(disValue), impacto);
  }

  return impacto;
}

function obtenerNivelRiesgo(posicionX, posicionY) {
  const matrizRiesgos = [
    ["Moderado", "Alto", "Crítico", "Crítico", "Crítico"], // y=0
    ["Bajo", "Moderado", "Alto", "Crítico", "Crítico"], // y=1
    ["Leve", "Bajo", "Moderado", "Alto", "Crítico"], // y=2
    ["Leve", "Leve", "Bajo", "Moderado", "Alto"], // y=3
    ["Leve", "Leve", "Leve", "Bajo", "Moderado"], // y=4
  ];

  const nivel = matrizRiesgos[posicionY][posicionX];

  const valorNumerico = {
    Leve: 0,
    Bajo: 1,
    Moderado: 2,
    Alto: 3,
    Crítico: 4,
  }[nivel];

  return { nivel, valor: valorNumerico };
}

/**
 * Rellena las grids de datos con la información proporcionada
 * @param {Object} datos - Datos a mostrar en las tablas
 * @param {Array} matriz - Matriz de evaluación de riesgos
 */
function rellenarGrid(datos, matriz) {
  inicializarTablas();
  procesarAmenazas(datos, matriz);
  procesarDatosUSF(datos);
  configurarTablas();
  agregarBotonesDescarga();
  configurarEventosBotones();
}

function inicializarTablas() {
  $("#grid-basic").bootgrid("destroy");
  $("#grid-basic2").bootgrid("destroy");
  $("#grid-data").html("");
  $("#grid-data2").html("");
}

function procesarAmenazas(datos, matriz) {
  const conValue = $(".con").val();
  const intValue = $(".int").val();
  const disValue = $(".dis").val();
  const probabilidad = ["Muy bajo", "Bajo", "Medio", "Alto", "Muy Alto"];
  const impacto = ["Muy Alto", "Alto", "Medio", "Bajo", "Muy Bajo"];

  for (let activo of datos.activos) {
    let amenazas = Object.keys(activo.amenazas);

    for (let amenaza of amenazas) {
      let riesgo = calcularRiesgo(
        activo.amenazas[amenaza],
        conValue,
        intValue,
        disValue
      );

      const { respuesta, num } = obtenerNivelRiesgoDesdeMatriz(matriz, riesgo);

      agregarFilaAmenaza(
        activo,
        amenaza,
        probabilidad[riesgo.xnow],
        impacto[riesgo.y],
        respuesta,
        num
      );
    }
  }
}

function obtenerNivelRiesgoDesdeMatriz(matriz, riesgo) {
  let respuesta = "";
  let num = 0;

  let index = findIndexCor(matriz, riesgo.xnow, riesgo.y);

  switch (matriz[index].color) {
    case "#548237bb":
      respuesta = "Leve";
      num = 1;
      break;
    case "#8ac833c4":
      respuesta = "Bajo";
      num = 2;
      break;
    case "#ffb32fc5":
      respuesta = "Moderado";
      num = 3;
      break;
    case "#ec673bc4":
      respuesta = "Alto";
      num = 4;
      break;
    case "#911927c0":
      respuesta = "Crítico";
      num = 5;
      break;
  }

  return { respuesta, num };
}

function agregarFilaAmenaza(
  activo,
  amenaza,
  probabilidadTexto,
  impactoTexto,
  respuesta,
  num
) {
  let row = `<tr>\
    <td>${activo.familia}</td>\
    <td>${activo.nombre}</td>\
    <td>${activo.amenazas[amenaza].padre}</td>\
    <td>${activo.amenazas[amenaza].nombre}</td>\
    <td>${probabilidadTexto}</td>\
    <td>${impactoTexto}</td>\
    <td>${respuesta}</td>\
    <td>${num}</td>\
    </tr>`;
  $("#grid-data").append(row);
}

function procesarDatosUSF(datos) {
  let usfArray =
    datos.usf != null ? Object.entries(datos.usf) : Object.entries(datos.ps);

  for (let usf of usfArray) {
    const usfstring = determinarEstadoUSF(usf[1]["ctm"]);
    const mediausf = usf[1]["ctm"].toFixed(2).replace(/\.00$/, "");

    agregarFilaUSF(usf, mediausf, usfstring);
  }
}

function determinarEstadoUSF(ctm) {
  if (ctm > 85) {
    return "CT";
  } else if (ctm < 50) {
    return "NC";
  } else {
    return "CP";
  }
}

function agregarFilaUSF(usf, mediausf, usfstring) {
  let row2 = `<tr>\
    <td>${usf[0]}</td>\
    <td>${usf[1].dominio}</td>\
    <td>${usf[1].descripcion}</td>\
    <td>${mediausf}</td>\
    <td>${usfstring}</td>\
    <td>${usf[1].proyecto}</td>\
  </tr>`;
  $("#grid-data2").append(row2);
}

function configurarTablas() {
  let options = {
    labels: {
      noResults: "No se han encontrado resultados.",
      infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
      search: "Buscar",
    },
    statusMapping: {
      1: "riesgoMuyBajo",
      2: "riesgoBajo",
      3: "riesgoMedio",
      4: "riesgoAlto",
      5: "riesgoMuyAlto",
    },
  };

  $("#grid-basic2").html();
  $("#grid-basic").html();
  $("#grid-basic2").bootgrid(options);
  $("#grid-basic").bootgrid(options);
}

function agregarBotonesDescarga() {
  $(".tablausf .actions").prepend(
    '<div class="btn btn-default download-group downloadTabla"><img class="icono" src="./img/download.svg" alt="descargar" title="Descargar tabla"></div>'
  );
  $(".tablaAmenazas .actions").prepend(
    '<div class="btn btn-default download-group download-all"><img class="icono" src="./img/download.svg" alt="descargar" title="Descargar tabla"></div>'
  );
}

function configurarEventosBotones() {
  $(".downloadTabla").click(function () {
    const esTablaUSF =
      $(this).parent().parent().parent().parent().parent()[0].id !==
      "grid-basic-header";
    const objectotabla = esTablaUSF ? "grid-basic2" : "grid-basic";
    const objetonombre = esTablaUSF ? "usf_" : "amenazas_";

    let sis = $(".sistemaEval option:selected").text().trim();
    ExportTabla(objectotabla, `${objetonombre}${sis}`, getTablaOptions());
  });

  $(".download-all").click(function () {
    let sis = $(".sistemaEval option")
      .map(function () {
        return $(this).text();
      })
      .get();
    tablaSistemas(sis);
  });
}

function getTablaOptions() {
  return {
    labels: {
      noResults: "No se han encontrado resultados.",
      infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
      search: "Buscar",
    },
    statusMapping: {
      1: "riesgoMuyBajo",
      2: "riesgoBajo",
      3: "riesgoMedio",
      4: "riesgoAlto",
      5: "riesgoMuyAlto",
    },
  };
}

function tablaSistemas(sis) {
  let tabla =
    '<table id="tablaActivos" class="table"><thead><tr><th>' +
    '<input type="checkbox" id="selectAll" name="checkAll" value="selectAll">' +
    "</th><th>Datos</th></tr></thead><tbody></tbody></table>";

  showModalWindow(
    "Descargar sistemas",
    tabla,
    downloadSistemas,
    "Cerrar",
    "Aceptar",
    null,
    false
  );
  tabla = $("#tablaActivos tbody");
  $.each(sis, function (index, value) {
    let fila = $("<tr>");
    $("<td>")
      .append(
        "<input type='checkbox' name='activo" +
        index +
        "' value='" +
        value +
        "'>"
      )
      .appendTo(fila);
    $("<td>").text(value).appendTo(fila);
    fila.appendTo(tabla);
  });
  $(document).ready(function () {
    $("#selectAll").change(function () {
      $('#tablaActivos tbody input[type="checkbox"]').prop(
        "checked",
        $(this).prop("checked")
      );
    });
  });
}

function downloadSistemas() {
  cerrarModal();
  let servicioId = $(".servicioId").attr("id");
  let nombreServicio = $(".servicioId").text().trim();
  let valores_seleccionados = $("#tablaActivos tbody :checkbox:checked")
    .map(function () {
      return $(this).val();
    })
    .get();

  let apiUrl = `./api/getRiesgos?sistemas=${valores_seleccionados}&serv=${servicioId}`;
  mostrarLoading();
  $.ajax({
    type: "GET",
    url: apiUrl,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      let datos = [
        { x: 0, y: 0, value: "", color: "#ffb32fc5" },
        { x: 0, y: 1, value: "", color: "#8ac833c4" },
        { x: 0, y: 2, value: "", color: "#548237bb" },
        { x: 0, y: 3, value: "", color: "#548237bb" },
        { x: 0, y: 4, value: "", color: "#548237bb" },

        { x: 1, y: 0, value: "", color: "#ec673bc4" },
        { x: 1, y: 1, value: "", color: "#ffb32fc5" },
        { x: 1, y: 2, value: "", color: "#8ac833c4" },
        { x: 1, y: 3, value: "", color: "#548237bb" },
        { x: 1, y: 4, value: "", color: "#548237bb" },

        { x: 2, y: 0, value: "", color: "#911927c0" },
        { x: 2, y: 1, value: "", color: "#ec673bc4" },
        { x: 2, y: 2, value: "", color: "#ffb32fc5" },
        { x: 2, y: 3, value: "", color: "#8ac833c4" },
        { x: 2, y: 4, value: "", color: "#548237bb" },

        { x: 3, y: 0, value: "", color: "#911927c0" },
        { x: 3, y: 1, value: "", color: "#911927c0" },
        { x: 3, y: 2, value: "", color: "#ec673bc4" },
        { x: 3, y: 3, value: "", color: "#ffb32fc5" },
        { x: 3, y: 4, value: "", color: "#8ac833c4" },

        { x: 4, y: 0, value: "", color: "#911927c0" },
        { x: 4, y: 1, value: "", color: "#911927c0" },
        { x: 4, y: 2, value: "", color: "#911927c0" },
        { x: 4, y: 3, value: "", color: "#ec673bc4" },
        { x: 4, y: 4, value: "", color: "#ffb32fc5" },
      ];
      let datosProcesados = tratarAmenazas(retorno, datos);
      jsonToXlsx(datosProcesados, nombreServicio, valores_seleccionados);
      cerrarModal();
    },
  });
}

function jsonToXlsx(myJson, fileName, sistemas) {
  myJson = JSON.parse(myJson);
  const workbook = XLSX.utils.book_new();
  for (let i = 0; i < sistemas.length; i++) {
    if (sistemas[i].length > 30) {
      sistemas[i] = sistemas[i].slice(0, 27) + "(" + i + ")";
    }
    XLSX.utils.book_append_sheet(
      workbook,
      XLSX.utils.json_to_sheet(myJson[i]),
      sistemas[i]
    );
  }
  XLSX.writeFile(workbook, fileName + ".xlsx");
}

function tratarAmenazas(datos, matriz) {
  let bia = datos[0];
  let finalArray = [];
  let amenazaArray = [];
  for (let i = 1; i < datos.length; i++) {
    let activos = datos[i];
    amenazaArray = [];
    for (let activo of activos) {
      let amenazas = Object.keys(activo.amenazas);
      for (let amenaza of amenazas) {
        let amenazaDic = {
          FAMILIA_ACTIVO: "",
          NOMBRE: "",
          "COD.AMENAZA": "",
          AMENAZA: "",
          PROBABILIDAD: "",
          IMPACTO: "",
          RIESGO: "",
        };
        let ejeY = [4, 3, 2, 1, 0];
        let x = parseInt(activo.amenazas[amenaza].probabilidad) - 1;
        let xnow = x + parseInt(activo.amenazas[amenaza].ajustada.x);
        if (x < 0) {
          x = 0;
        }
        if (xnow < 0) {
          xnow = 0;
        }
        let y = 0;
        if (parseInt(activo.amenazas[amenaza].confidencialidad) === 1) {
          y = Math.max(parseInt(bia["Con"]["Max"]), y);
        }
        if (parseInt(activo.amenazas[amenaza].integridad) === 1) {
          y = Math.max(parseInt(bia["Int"]["Max"]), y);
        }
        if (parseInt(activo.amenazas[amenaza].disponibilidad) === 1) {
          y = Math.max(parseInt(bia["Dis"]["Max"]), y);
        }
        y = ejeY[y] - parseInt(activo.amenazas[amenaza].ajustada.y);
        if (y > 4) {
          y = 4;
        }
        let index = findIndexCor(matriz, xnow, y);
        let respuesta = "";
        if (matriz[index].color == "#548237bb") {
          respuesta = "Leve";
        } else if (matriz[index].color == "#8ac833c4") {
          respuesta = "Bajo";
        } else if (matriz[index].color == "#ffb32fc5") {
          respuesta = "Moderado";
        } else if (matriz[index].color == "#ec673bc4") {
          respuesta = "Alto";
        } else if (matriz[index].color == "#911927c0") {
          respuesta = "Crítico";
        }
        let probabilidad = ["Muy bajo", "Bajo", "Medio", "Alto", "Muy Alto"];
        let impacto = ["Muy Alto", "Alto", "Medio", "Bajo", "Muy Bajo"];
        amenazaDic["FAMILIA_ACTIVO"] = activo.familia;
        amenazaDic["NOMBRE"] = activo.nombre;
        amenazaDic["COD.AMENAZA"] = activo.amenazas[amenaza].padre;
        amenazaDic["AMENAZA"] = activo.amenazas[amenaza].nombre;
        amenazaDic["PROBABILIDAD"] = probabilidad[xnow];
        amenazaDic["IMPACTO"] = impacto[y];
        amenazaDic["RIESGO"] = respuesta;
        amenazaArray.push(amenazaDic);
      }
    }
    finalArray.push(amenazaArray);
  }
  let finalJson = JSON.stringify(finalArray);
  return finalJson;
}

function updateRiesgosServicio(id) {
  mostrarLoading();
  let fechaId = $(`.${FECHA_EVAL}`).val();
  let edicion = $(`.${FECHA_EVAL} option:selected`).data("evaluacion");
  let url;
  if (edicion != undefined) {
    url = `./api/getRiesgosServicio?id=${id}&fecha=${fechaId}&version=${edicion}`;
  } else {
    url = `./api/getRiesgosServicio?id=${id}&fecha=${fechaId}`;
  }

  if (fechaId !== null) {
    $.ajax({
      type: "GET",
      url: url,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        let datos = [
          { x: 0, y: 0, value: "", color: "#ffb32fc5" },
          { x: 0, y: 1, value: "", color: "#8ac833c4" },
          { x: 0, y: 2, value: "", color: "#548237bb" },
          { x: 0, y: 3, value: "", color: "#548237bb" },
          { x: 0, y: 4, value: "", color: "#548237bb" },

          { x: 1, y: 0, value: "", color: "#ec673bc4" },
          { x: 1, y: 1, value: "", color: "#ffb32fc5" },
          { x: 1, y: 2, value: "", color: "#8ac833c4" },
          { x: 1, y: 3, value: "", color: "#548237bb" },
          { x: 1, y: 4, value: "", color: "#548237bb" },

          { x: 2, y: 0, value: "", color: "#911927c0" },
          { x: 2, y: 1, value: "", color: "#ec673bc4" },
          { x: 2, y: 2, value: "", color: "#ffb32fc5" },
          { x: 2, y: 3, value: "", color: "#8ac833c4" },
          { x: 2, y: 4, value: "", color: "#548237bb" },

          { x: 3, y: 0, value: "", color: "#911927c0" },
          { x: 3, y: 1, value: "", color: "#911927c0" },
          { x: 3, y: 2, value: "", color: "#ec673bc4" },
          { x: 3, y: 3, value: "", color: "#ffb32fc5" },
          { x: 3, y: 4, value: "", color: "#8ac833c4" },

          { x: 4, y: 0, value: "", color: "#911927c0" },
          { x: 4, y: 1, value: "", color: "#911927c0" },
          { x: 4, y: 2, value: "", color: "#911927c0" },
          { x: 4, y: 3, value: "", color: "#ec673bc4" },
          { x: 4, y: 4, value: "", color: "#ffb32fc5" },
        ];
        rellenarGrid(retorno, datos);
        rellenarPac(retorno.pac, retorno.amenazas);
        let datosnow = JSON.parse(JSON.stringify(datos));
        let datosres = JSON.parse(JSON.stringify(datos));
        if (retorno.amenazas !== "") {
          let ejeY = [4, 3, 2, 1, 0];
          let amenazaunica = [];
          for (let amenaza of retorno.amenazas) {
            if (amenazaunica.indexOf(amenaza.id) < 0) {
              let x = parseInt(amenaza.probabilidad) - 1;
              let xnow = x + parseInt(amenaza.ajustada.x);
              let ynow = 0;
              let yres = 0;
              let xres = 0;

              if (x < 0) {
                x = 0;
              }

              if (xnow < 0) {
                xnow = 0;
              }

              let y = 0;
              if (parseInt(amenaza.confidencialidad) === 1) {
                y = Math.max(parseInt($(".con").val()), y);
              }

              if (parseInt(amenaza.integridad) === 1) {
                y = Math.max(parseInt($(".int").val()), y);
              }

              if (parseInt(amenaza.disponibilidad) === 1) {
                y = Math.max(parseInt($(".dis").val()), y);
              }

              ynow = ejeY[y] - parseInt(amenaza.ajustada.y);

              if (ynow > 4) {
                ynow = 4;
              }

              if (amenaza.tiposUsf.Proactivo == undefined) {
                xres = xnow;
              }

              if (amenaza.tiposUsf.Reactivo == undefined) {
                yres = ynow;
              } else {
                yres = ejeY[yres];
              }

              let index = findIndexCor(datos, x, ejeY[y]);
              let indexnow = findIndexCor(datosnow, xnow, ynow);
              let indexres = findIndexCor(datosres, xres, yres);

              datos[index].value++;
              datosnow[indexnow].value++;
              datosres[indexres].value++;
              amenazaunica.push(amenaza.id);
            }
          }
          let promedionow = calcularPromedioRiestoActual(datosnow);
          $("#heatmap-inherit").empty();
          $("#heatmap-now").empty();
          $("#heatmap-residual").empty();
          heatMap("heatmap-inherit", datos);
          heatMap("heatmap-now", datosnow);
          heatMap("heatmap-residual", datosres);
          let insertar = `<div class="row"><div class="riesgo-muybajo font-11">${promedionow.leve
            }(${((promedionow.leve * 100) / promedionow.total).toFixed(
              2
            )}%)</div><div class="riesgo-bajo font-11">${promedionow.medio}(${(
              (promedionow.medio * 100) /
              promedionow.total
            ).toFixed(2)}%)</div><div class="riesgo-medio font-11">${promedionow.moderado
            }(${((promedionow.moderado * 100) / promedionow.total).toFixed(
              2
            )}%)</div><div class="riesgo-alto font-11">${promedionow.alto}(${(
              (promedionow.alto * 100) /
              promedionow.total
            ).toFixed(2)}%)</div><div class="riesgo-muyalto font-11">${promedionow.critico
            }(${((promedionow.critico * 100) / promedionow.total).toFixed(
              2
            )}%)</div></div>`;
          $("#heatmap-now").append(insertar);
          let copiatitle = $(".highcharts-title")[1].firstChild.data;
          let lvl = ["Leve", "Medio", "Moderado", "Alto", "Crítico"];
          let ajustar = 0;
          let a = (promedionow.leve * 100) / promedionow.total;
          let b = (promedionow.medio * 100) / promedionow.total;
          let ab = a + b;

          if (ab <= 20) {
            ajustar = 4;
          }
          if (ab >= 90) {
            ajustar = 0;
          }

          if (ab > 20 && ab <= 40) {
            ajustar = 3;
          }
          if (ab > 40 && ab <= 80) {
            ajustar = 2;
          }

          if (ab > 80 && ab <= 90) {
            ajustar = 1;
          }
          copiatitle = copiatitle + ` (${lvl[ajustar]})`;
          $(".highcharts-title")[1].firstChild.data = copiatitle;
        }
        cerrarModal();
      },
    });
  }
}

function findIndexCor(array, x, y) {
  for (let i = 0; i < array.length; i++) {
    if (array[i].x == parseInt(x) && array[i].y == parseInt(y)) {
      return i;
    }
  }
}

function exportExcelEvalRiesgos() {
  let id = $(`.${SELECT_SISTEMA}`).val();
  let normativa = PARAM_NORM;
  normativa += getValueChecked("groupcheck");
  mostrarLoading();
  $.ajax({
    type: "GET",
    xhrFields: {
      responseType: "blob",
      withCredentials: true,
    },
    url: `./api/getCSVCumplimiento?id=${id}${normativa}`,

    success: function (retorno, textStatus, request) {
      cerrarModal();
      let a = document.createElement("a");
      let url = window.URL.createObjectURL(retorno);
      a.href = url;
      a.download = "Cuestionario.xlsx";
      document.body.append(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
      showModalWindow(
        INFORMACION,
        "Se ha descargado el excel para rellenar el cumplimiento.",
        null,
        "Aceptar",
        null,
        null
      );
      $(".btn-exportarCuestionario").prop("disabled", false);
    },
  });
}

function getChild(id) {
  if (!isNullUndefined(id)) {
    mostrarLoading();
    $(".visual").addClass("mshide");
    $.ajax({
      type: "GET",
      url: `./api/getChild/${id}`,
      xhrFields: {
        withCredentials: true,
      },
      beforeSend: function (request) {
        const url = this.url;
        if (eTagStorage[url]) {
          request.setRequestHeader("If-None-Match", eTagStorage[url]);
        }
      },
      success: function (retorno, textStatus, request) {
        if (retorno.error) {
          cerrarModal();
          localStorage.clear();
          showModalWindow(
            ERROR,
            retorno.message,
            null,
            "Cerrar",
            null,
            goLogin
          );
        } else {
          if (retorno.padre[0].tipo === "42") {
            $(`.visual`).attr("id", retorno.padre[0].id);
          }

          let funcion = new Object();
          clearResponse();
          if (retorno.child.length !== 0) {
            for (let value of retorno.child) {
              if (value.tipo !== "42" || value.tipo !== "123") {
                funcion["del"] = false;
                funcion["eval"] = false;
                funcion["history"] = false;
                funcion["bia"] = false;
                funcion["clone"] = false;
                funcion["personas"] = false;
              } else {
                funcion["del"] = true;
                funcion["eval"] = true;
                funcion["history"] = true;
                funcion["bia"] = true;
                funcion["clone"] = true;
                funcion["personas"] = true;
              }

              funcion["edit"] = value.tipo;
              funcion["del"] = true;
              funcion["Change_parents"] = true;
              funcion["del_relation"] = true;

              const filtrado = $(".tipo-filtrado").text().trim();
              if (
                value.archivado !== 1 &&
                filtrado !== "Filtrando por: Servicios archivados"
              ) {
                let tarjeta = newTarjeta(
                  ".activos > .response",
                  value.nombre,
                  value.id,
                  funcion
                );
                if (parseInt(value.archivado) !== 0) {
                  $(tarjeta).addClass("arch");
                }

                if (parseInt(value.expuesto) !== 0) {
                  $(tarjeta).addClass("exp");
                }
              } else if (filtrado === "Filtrando por: Servicios archivados") {
                newTarjeta(
                  ".activos > .response",
                  value.nombre,
                  value.id,
                  funcion
                );
              }
            }
          }
          updateBreadCrumb(retorno.padre[0].nombre, 1, id);
          if (
            ($(BREADCRUMB).length > 1 &&
              $(".tipo-filtrado").text() == "Filtrando por: Servicios") ||
            ($(BREADCRUMB).length > 2 &&
              $(".tipo-filtrado").text() == "Filtrando por: Organización")
          ) {
            $(".visual").removeClass("mshide");
            pintarEsquema(id);
          }

          cerrarModal();
          handler();
        }
      },
      error: function (retorno, textStatus, request) {
        cerrarModal();
        showModalWindow(
          ERROR,
          retorno.responseJSON.message,
          null,
          "Cerrar",
          null,
          null
        );
      },
    });
  }
}

function nuevoActivo() {
  $.ajax({
    type: "GET",
    url: `./api/getClaseActivos`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      let options = "<select class='form-select-custom' name='tipo'>";
      for (let clase of retorno.claseActivos) {
        options += `<option value="${clase.id}" >${clase.perspectiva} - ${clase.nombre}</option>`;
      }
      options += "</select>";
      let idp = $(".breadcrumb-item :last").attr("id");
      let form = `<form class="formNew"><div class='row g-3 align-items-center'><div class='col-auto'><label for='newobject' class="col-form-label">Nombre</label></div><div class="col-9 mb-3"><input id="newobject" class='newActivo form-control' type='text' name="nombre"/></div></div></div><div class='row g-3 align-items-center'><div class='col-auto'><label for='relation' class='col-form-label'>Relación</label></div><div class="col-auto"><input id="relation" class="relation form-control" type="text" name="dependencia"/></div></div><div class="row g-3 align-items-center"><div class='col-auto'><label for='tipoSelect' class="col-form-label">Tipo</label></div><div class="col-10 mb-3">${options}</div></div></div><input class="mshide" name="padre_id" value="${idp}"/></form>`;
      showModalWindow("Nuevo activo", form, nuevoAjax);

      let bloqueinput = $("#newobject");
      let bloqueinputparent = $("#newobject").parent();
      $('select[name="tipo"]').change(function () {
        if ($('select[name="tipo"]').val() == 45) {
          bloqueinput.remove();
          $.ajax({
            url: `./api/getActivos?tipo=45`,
            method: "GET",
            xhrFields: {
              withCredentials: true,
            },

            success: function (retorno, textStatus, request) {
              let options = "";

              for (let ubi of retorno.servicios) {
                options += `<option value="${ubi.nombre}" >${ubi.nombre}</option>`;
              }
              bloqueinputparent.prepend(
                `<select id='newobject' class='newActivo form-select-custom' name='nombre'>${options}</select>`
              );
            },
          });
        } else {
          bloqueinputparent.prepend(bloqueinput);
          $('select[name="nombre"]').remove();
        }
      });

      $("#relation").keyup(function () {
        if ($("#relation").val().length >= 3) {
          buscarActivos($("#relation").val());
        } else {
          $(".activoParent").empty();
        }
      });
    },
  });
}

function getBia() {
  let id = $(".servicioId").attr("value");
  if (!/^\d+$/.test(id)) {
    return showModalWindow(ERROR, "Id inválido", null, "Aceptar", null, null);
  }
  let encodedId = encodeURIComponent(id.replace(/[^\d]/g, ""));
  let lvl = ["Leve", "Bajo", "Moderado", "Alto", "Crítico"];

  $.ajax({
    type: "GET",
    url: `./api/getBia?id=${encodedId}`,
    xhrFields: {
      withCredentials: true,
    },
    beforeSend: function (request) {
      if (eTagStorage[this.url]) {
        request.setRequestHeader("If-None-Match", eTagStorage[this.url]);
      }
    },
  })
    .done(function (retorno, textStatus, request) {
      if (retorno.bia !== null && typeof retorno.bia === "object") {
        const { email, retencion, ...biaData } = retorno.bia;
        if (email !== null) {
          $(".email-bia").text(email);
        }

        $(".retencion-logs").text(
          `Recuerda que la retención de logs debe ser de ${retencion} días.`
        );

        $.each(biaData, function (x, valor) {
          $.each(valor, function (y, val) {
            let item = $(`.${x}-${y}`);
            $(item).text(lvl[val]);
            $(item).removeAttr("class");
            $(item).attr("class", `${x}-${y}`);
            $($(item)).addClass(`bia-${val}`);
          });
        });
      }
    })
    .fail(function (retorno, textStatus, errorThrown) {
      showModalWindow(
        ERROR,
        retorno.responseJSON.message,
        null,
        "Aceptar",
        null,
        null
      );
    });
}

function obtainPreguntasKpmsCsirt() {
  mostrarLoading();
  return new Promise((resolve) => {
    $.ajax({
      type: "GET",
      url: `./api/obtainPreguntasKpmsCsirt`,
      success: function (retorno, textStatus, request) {
        resolve(retorno);
      },
    });
  });
}

function obtainPreguntasKpms() {
  mostrarLoading();
  return new Promise((resolve) => {
    $.ajax({
      type: "GET",
      url: `./api/obtainPreguntasKpms`,
      success: function (retorno, textStatus, request) {
        resolve(retorno);
      },
    });
  });
}

function getkpms() {
  mostrarLoading();
  let selector = "";
  if (!$(".form-metricas").hasClass("mshide")) {
    selector = "metricas";
  } else if (!$(".form-madurez").hasClass("mshide")) {
    selector = "madurez";
  } else {
    selector = "csirt";
  }

  $(`.${selector} > .tab`).last().addClass("mshide");
  $(`.${selector} > .tab`).first().removeClass("mshide");
  $(".block-inicio").removeClass("mshide");
  $(".form-" + selector).addClass("mshide");
  $(".nav-tabs").removeClass("mshide");
  $(".info-kpms-metricas").removeClass("mshide");
  $(".info-kpms-madurez").removeClass("mshide");
  $(".info-kpms-csirt").removeClass("mshide");
  $.ajax({
    type: "GET",
    url: `./api/getKpms`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      cerrarModal();
      if (retorno.kpms !== null) {
        $("#table-metricas").bootgrid("destroy");
        $("#table-madurez").bootgrid("destroy");
        $("#table-metricas-csirt").bootgrid("destroy");
        $("#table-metrica-data").html("");
        $("#table-madurez-data").html("");
        $("#table-metricas-csirt-data").html("");
        let table1 = document.getElementById("table-metrica-data");
        let table2 = document.getElementById("table-madurez-data");
        if (retorno.metricas.length > 0) {
          for (let item of retorno.metricas) {
            let newRow = table1.insertRow();
            newRow.insertCell().textContent = item.id;
            newRow.insertCell().textContent = item.email;
            newRow.insertCell().textContent = item.update;
            newRow.insertCell().textContent = item.fecha;
            newRow.insertCell().textContent = item.reporte;
            newRow.insertCell().textContent = item.area;
            newRow.insertCell().textContent = item.direccion;
            newRow.insertCell().textContent = item.cuarto;
            newRow.insertCell().textContent = item.auditoria;
            newRow.insertCell().textContent = item.KPM04;
            newRow.insertCell().textContent = item.KPM61;
            newRow.insertCell().textContent = item.KPM31;
            newRow.insertCell().textContent = item.KPM06;
            newRow.insertCell().textContent = item.KPM41;
            newRow.insertCell().textContent = item.KPM43;
            newRow.insertCell().textContent = item.KPM55;
            newRow.insertCell().textContent = item.KPM24;
            newRow.insertCell().textContent = item.KPM26;
            newRow.insertCell().textContent = item.KPM01;
            newRow.insertCell().textContent = item.KPM02;
            newRow.insertCell().textContent = item.KPM03;
            newRow.insertCell().textContent = item.KPM28;
            newRow.insertCell().textContent = item.KPM37;
            newRow.insertCell().textContent = item.KPM33;
            newRow.insertCell().textContent = item.KPM12;
            newRow.insertCell().textContent = item.KPM14;
            newRow.insertCell().textContent = item.KPM16;
            newRow.insertCell().textContent = item.KPM18;
            newRow.insertCell().textContent = item.KPM50;
            newRow.insertCell().textContent = item.KPM54;
            newRow.insertCell().textContent = item.KPM05;
            newRow.insertCell().textContent = item.KPM07;
            newRow.insertCell().textContent = item.KPM09;
            newRow.insertCell().textContent = item.KPM11;
            newRow.insertCell().textContent = item.KPM08;
            newRow.insertCell().textContent = item.KPM10;
            newRow.insertCell().textContent = item.KPM13;
            newRow.insertCell().textContent = item.KPM15;
            newRow.insertCell().textContent = item.KPM17;
            newRow.insertCell().textContent = item.KPM19;
            newRow.insertCell().textContent = item.KPM32;
            newRow.insertCell().textContent = item.KPM20;
            newRow.insertCell().textContent = item.KPM22;
            newRow.insertCell().textContent = item.KPM25;
            newRow.insertCell().textContent = item.KPM27;
            newRow.insertCell().textContent = item.KPM21;
            newRow.insertCell().textContent = item.KPM23;
            newRow.insertCell().textContent = item.KPM29;
            newRow.insertCell().textContent = item.KPM30;
            newRow.insertCell().textContent = item.KPM34;
            newRow.insertCell().textContent = item.KPM35;
            newRow.insertCell().textContent = item.KPM36;
            newRow.insertCell().textContent = item.KPM38;
            newRow.insertCell().textContent = item.KPM39;
            newRow.insertCell().textContent = item.KPM40;
            newRow.insertCell().textContent = item.KPM42;
            newRow.insertCell().textContent = item.KPM44;
            newRow.insertCell().textContent = item.KPM46;
            newRow.insertCell().textContent = item.KPM47;
            newRow.insertCell().textContent = item.KPM71;
            newRow.insertCell().textContent = item.KPM48;
            newRow.insertCell().textContent = item.KPM51;
            newRow.insertCell().textContent = item.KPM52;
            newRow.insertCell().textContent = item.KPM56;
            newRow.insertCell().textContent = item.KPM57;
            newRow.insertCell().textContent = item.KPM53;
            newRow.insertCell().textContent = item.KPM58;
            newRow.insertCell().textContent = item.KPM59A;
            newRow.insertCell().textContent = item.KPM59B;
            newRow.insertCell().textContent = item.KPM60A;
            newRow.insertCell().textContent = item.KPM60B;
            newRow.insertCell().textContent = item.KPM62;
            newRow.insertCell().textContent = item.KPM63;
            newRow.insertCell().textContent = item.KPM64;
            newRow.insertCell().textContent = item.KPM70;
            newRow.insertCell().textContent = item.KPM72;
            newRow.insertCell().textContent = item.KPM73;
            newRow.insertCell().textContent = item.KPM73_1;
            newRow.insertCell().textContent = item.KPM73_2;
            newRow.insertCell().textContent = item.KPM73_3;
            newRow.insertCell().textContent = item.KPM73_4;
            newRow.insertCell().textContent = item.KPM74;
            newRow.insertCell().textContent = item.comentario;
            newRow.insertCell().textContent = item.sugerencia;
            newRow.insertCell().textContent = item.bloqueado;
          }
        }

        if (retorno.madurez.length > 0) {
          for (let item of retorno.madurez) {
            let newRow = table2.insertRow();
            newRow.insertCell().textContent = item.id;
            newRow.insertCell().textContent = item.email;
            newRow.insertCell().textContent = item.fecha;
            newRow.insertCell().textContent = item.reporte;
            newRow.insertCell().textContent = item.area;
            newRow.insertCell().textContent = item.direccion;
            newRow.insertCell().textContent = item.cuarto;
            newRow.insertCell().textContent = item.auditoria;
            newRow.insertCell().textContent = item.M01_1;
            newRow.insertCell().textContent = item.M01_2;
            newRow.insertCell().textContent = item.M01_3;
            newRow.insertCell().textContent = item.M02_1;
            newRow.insertCell().textContent = item.M02_2;
            newRow.insertCell().textContent = item.M02_3;
            newRow.insertCell().textContent = item.M03_1;
            newRow.insertCell().textContent = item.M03_2;
            newRow.insertCell().textContent = item.M03_3;
            newRow.insertCell().textContent = item.M03_4;
            newRow.insertCell().textContent = item.M04_1;
            newRow.insertCell().textContent = item.M04_2;
            newRow.insertCell().textContent = item.M04_3;
            newRow.insertCell().textContent = item.M04_4;
            newRow.insertCell().textContent = item.M05_1;
            newRow.insertCell().textContent = item.M05_2;
            newRow.insertCell().textContent = item.M06_1;
            newRow.insertCell().textContent = item.M06_2;
            newRow.insertCell().textContent = item.M06_7;
            newRow.insertCell().textContent = item.M07_1;
            newRow.insertCell().textContent = item.M07_2;
            newRow.insertCell().textContent = item.M07_3;
            newRow.insertCell().textContent = item.M08_1;
            newRow.insertCell().textContent = item.M08_2;
            newRow.insertCell().textContent = item.M09_1;
            newRow.insertCell().textContent = item.M09_2;
            newRow.insertCell().textContent = item.M09_3;
            newRow.insertCell().textContent = item.M10_1;
            newRow.insertCell().textContent = item.M10_2;
            newRow.insertCell().textContent = item.M10_3;
            newRow.insertCell().textContent = item.M11_1;
            newRow.insertCell().textContent = item.M11_2;
            newRow.insertCell().textContent = item.M11_3;
            newRow.insertCell().textContent = item.M11_4;
            newRow.insertCell().textContent = item.M12_1;
            newRow.insertCell().textContent = item.M12_2;
            newRow.insertCell().textContent = item.M12_3;
            newRow.insertCell().textContent = item.M12_4;
            newRow.insertCell().textContent = item.M13_1;
            newRow.insertCell().textContent = item.M13_2;
            newRow.insertCell().textContent = item.M13_3;
            newRow.insertCell().textContent = item.M13_4;
            newRow.insertCell().textContent = item.M14_1;
            newRow.insertCell().textContent = item.M14_2;
            newRow.insertCell().textContent = item.M14_3;
            newRow.insertCell().textContent = item.comentario;
            newRow.insertCell().textContent = item.sugerencia;
            newRow.insertCell().textContent = item.bloqueado;
          }
        }

        if (retorno.csirt.length > 0) {
          for (let item of retorno.csirt) {
            let newRow = document
              .getElementById("table-metricas-csirt-data")
              .insertRow();
            newRow.insertCell().textContent = item.id;
            newRow.insertCell().textContent = item.usuario_id;
            newRow.insertCell().textContent = item.fecha;
            newRow.insertCell().textContent = item.actualizado;
            newRow.insertCell().textContent = item.reporte;
            newRow.insertCell().textContent = item.area;
            newRow.insertCell().textContent = item.direccion;
            newRow.insertCell().textContent = item.cuarto;
            newRow.insertCell().textContent = item.KPM48;
            newRow.insertCell().textContent = item.KPM50;
            newRow.insertCell().textContent = item.KPM51;
            newRow.insertCell().textContent = item.KPM52;
            newRow.insertCell().textContent = item.KPM58;
            newRow.insertCell().textContent = item.KPM59A;
            newRow.insertCell().textContent = item.KPM59B;
            newRow.insertCell().textContent = item.KPM60A;
            newRow.insertCell().textContent = item.KPM60B;
            newRow.insertCell().textContent = item.comentario;
            newRow.insertCell().textContent = item.sugerencia;
            newRow.insertCell().textContent = item.bloqueado;
          }
        }

        let options = {
          caseSensitive: false,
          selection: true,
          multiSelect: true,
          keepSelection: true,
          labels: {
            noResults: "No se han encontrado resultados.",
            infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
            search: "Buscar",
          },
          formatters: {
            commands: function (column, row) {
              if (row.bloqueado == 0) {
                return (
                  "<button type='button' class='btn btn-xs btn-default command-edit'><img class='icono' src='./img/edit.svg' /></button>" +
                  "<button type='button' class='btn btn-xs btn-default command-delete'><img class='icono' src='./img/delete.svg' /></button>"
                );
              }
            },
          },
        };

        let gridmetricas = $("#table-metricas").bootgrid(options);
        let gridmadurez = $("#table-madurez").bootgrid(options);
        let gridmetricasCSIRT = $("#table-metricas-csirt").bootgrid(options);

        gridmetricasCSIRT.on("loaded.rs.jquery.bootgrid", function () {
          gridmetricasCSIRT.find(".command-edit").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-metricas-csirt").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (e) {
                return e.id;
              })
              .indexOf(parseInt(idrow));
            obtainPreguntasKpmsCsirt().then((data) => {
              let comentarioValue = "";
              if (rows[pos] && rows[pos].comentario !== undefined) {
                comentarioValue = rows[pos].comentario;
              }
              let sugerenciaValue = "";
              if (rows[pos] && rows[pos].sugerencia !== undefined) {
                sugerenciaValue = rows[pos].sugerencia;
              }
              let preguntas = ``;
              for (let pregunta of data) {
                let preguntaValue = "";
                if (rows[pos] && rows[pos][pregunta.nombre] !== undefined) {
                  preguntaValue = rows[pos][pregunta.nombre];
                }
                preguntas += `<div class="col-sm-6"><label>${pregunta.nombre}</label>
                                <input name="${pregunta.nombre}" class="form-control" id="${pregunta.nombre}" type="number" max="99999" min="0" placeholder="${pregunta.nombre}" value="${preguntaValue}">
                              </div>`;
              }
              let form = `<form class="formEdit"><div class="text-start row">
                <input class="mshide" name="id" value="${idrow}">
                <input class="mshide" name="tipo" value="csirt">
                ${preguntas}
                <div class="col-sm-6"><label>comentario</label>
                  <input name="comentario" class="form-control" id="comentario" placeholder="comentario" value="${comentarioValue}">
                </div>
                <div class="col-sm-6"><label>sugerencia</label>
                  <input name="sugerencia" class="form-control" id="sugerencia" placeholder="sugerencia" value="${sugerenciaValue}">
                </div>
              </form>`;
              showModalWindow(
                "Editar métricas",
                form,
                editarkpms,
                "Cancelar",
                "Guardar"
              );
            });
          });

          gridmetricasCSIRT.find(".command-delete").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            showModalWindow(
              "Borrar reporte",
              `<form class="formDel"><input name="tipo" class="mshide" value="csirt"><input name="id[]" class="mshide" value="${idrow}">¿Desea borrar el reporte de métricas con id ${idrow}?</form>`,
              deletekpm,
              "Cancelar",
              "Borrar",
              null
            );
          });
        });

        gridmetricas.on("loaded.rs.jquery.bootgrid", function () {
          gridmetricas.find(".command-edit").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-metricas").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (e) {
                return e.id;
              })
              .indexOf(parseInt(idrow));
            obtainPreguntasKpms().then((data) => {
              let preguntas = ``;
              for (let pregunta of data) {
                preguntas += `<div class="col-sm-6"><label>${pregunta.nombre
                  }</label>
                                <input name="${pregunta.nombre
                  }" class="form-control" id="${pregunta.nombre
                  }" type="number" max="99999" min="0" placeholder="${pregunta.nombre
                  }" value="${rows[pos][pregunta.nombre]}">
                              </div>`;
              }
              let form = `<form class="formEdit"><div class="text-start row">
                <input class="mshide" name="id" value="${idrow}">
                <input class="mshide" name="tipo" value="metricas">
                ${preguntas}
                <div class="col-sm-6"><label>comentario</label>
                  <input name="comentario" class="form-control" id="comentario" placeholder="comentario" value="${rows[pos].comentario}">
                </div>
                <div class="col-sm-6"><label>sugerencia</label>
                  <input name="sugerencia" class="form-control" id="sugerencia" placeholder="sugerencia" value="${rows[pos].sugerencia}">
                </div>
              </form>`;
              showModalWindow(
                "Editar métricas",
                form,
                editarkpms,
                "Cancelar",
                "Guardar"
              );
            });
          });

          gridmetricas.find(".command-delete").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            showModalWindow(
              "Borrar reporte",
              `<form class="formDel"><input name="tipo" class="mshide" value="metricas"><input name="id[]" class="mshide" value="${idrow}">¿Desea borrar el reporte de métricas con id ${idrow}?</form>`,
              deletekpm,
              "Cancelar",
              "Borrar",
              null
            );
          });
        });

        gridmadurez.on("loaded.rs.jquery.bootgrid", function () {
          gridmadurez.find(".command-edit").on("click", function () {
            let idrow = $(this).parent().parent().attr("data-row-id");
            let rows = $("#table-madurez").bootgrid("getCurrentRows");
            let pos = rows
              .map(function (e) {
                return e.id;
              })
              .indexOf(parseInt(idrow));
            let form = `<form class="formEdit"><div class="text-start row">
              <input class="mshide" name="id" value="${idrow}">
              <input class="mshide" name="tipo" value="madurez">
              <div class="col-sm-6"><label>M01_1</label>
                <input name="M01_1" class="form-control" id="M01_1" placeholder="M01_1" value="${rows[pos].M01_1}">
              </div>
              <div class="col-sm-6"><label>M01_2</label>
                <input name="M01_2" class="form-control" id="M01_2" placeholder="M01_2" value="${rows[pos].M01_2}">
              </div>
              <div class="col-sm-6"><label>M01_3</label>
                <input name="M01_3" class="form-control" id="M01_3" placeholder="M01_3" value="${rows[pos].M01_3}">
              </div>
              <div class="col-sm-6"><label>M02_1</label>
                <input name="M02_1" class="form-control" id="M02_1" placeholder="M02_1" value="${rows[pos].M02_1}">
              </div>
              <div class="col-sm-6"><label>M02_2</label>
                <input name="M02_2" class="form-control" id="M02_2" placeholder="M02_2" value="${rows[pos].M02_2}">
              </div>
              <div class="col-sm-6"><label>M02_3</label>
                <input name="M02_3" class="form-control" id="M02_3" placeholder="M02_3" value="${rows[pos].M02_3}">
              </div>
              <div class="col-sm-6"><label>M03_1</label>
                <input name="M03_1" class="form-control" id="M03_1" placeholder="M03_1" value="${rows[pos].M03_1}">
              </div>
              <div class="col-sm-6"><label>M03_2</label>
                <input name="M03_2" class="form-control" id="M03_2" placeholder="M03_2" value="${rows[pos].M03_2}">
              </div>
              <div class="col-sm-6"><label>M03_3</label>
                <input name="M03_3" class="form-control" id="M03_3" placeholder="M03_3" value="${rows[pos].M03_3}">
              </div>
              <div class="col-sm-6"><label>M03_4</label>
                <input name="M03_4" class="form-control" id="M03_4" placeholder="M03_4" value="${rows[pos].M03_4}">
              </div>
              <div class="col-sm-6"><label>M04_1</label>
                <input name="M04_1" class="form-control" id="M04_1" placeholder="M04_1" value="${rows[pos].M04_1}">
              </div>
              <div class="col-sm-6"><label>M04_2</label>
                <input name="M04_2" class="form-control" id="M04_2" placeholder="M04_2" value="${rows[pos].M04_2}">
              </div>
              <div class="col-sm-6"><label>M04_3</label>
                <input name="M04_3" class="form-control" id="M04_3" placeholder="M04_3" value="${rows[pos].M04_3}">
              </div>
              <div class="col-sm-6"><label>M04_4</label>
                <input name="M04_4" class="form-control" id="M04_4" placeholder="M04_4" value="${rows[pos].M04_4}">
              </div>
              <div class="col-sm-6"><label>M05_1</label>
                <input name="M05_1" class="form-control" id="M05_1" placeholder="M05_1" value="${rows[pos].M05_1}">
              </div>
              <div class="col-sm-6"><label>M05_2</label>
                <input name="M05_2" class="form-control" id="M05_2" placeholder="M05_2" value="${rows[pos].M05_2}">
              </div>
              <div class="col-sm-6"><label>M06_1</label>
                <input name="M06_1" class="form-control" id="M06_1" placeholder="M06_1" value="${rows[pos].M06_1}">
              </div>
              <div class="col-sm-6"><label>M06_2</label>
                <input name="M06_2" class="form-control" id="M06_2" placeholder="M06_2" value="${rows[pos].M06_2}">
              </div>
              <div class="col-sm-6"><label>M06_7</label>
                <input name="M06_7" class="form-control" id="M06_7" placeholder="M06_7" value="${rows[pos].M06_7}">
              </div>
              <div class="col-sm-6"><label>M07_1</label>
                <input name="M07_1" class="form-control" id="M07_1" placeholder="M07_1" value="${rows[pos].M07_1}">
              </div>
              <div class="col-sm-6"><label>M07_2</label>
                <input name="M07_2" class="form-control" id="M07_2" placeholder="M07_2" value="${rows[pos].M07_2}">
              </div>
              <div class="col-sm-6"><label>M07_3</label>
                <input name="M07_3" class="form-control" id="M07_3" placeholder="M07_3" value="${rows[pos].M07_3}">
              </div>
              <div class="col-sm-6"><label>M08_1</label>
                <input name="M08_1" class="form-control" id="M08_1" placeholder="M08_1" value="${rows[pos].M08_1}">
              </div>
              <div class="col-sm-6"><label>M08_2</label>
                <input name="M08_2" class="form-control" id="M08_2" placeholder="M08_2" value="${rows[pos].M08_2}">
              </div>
              <div class="col-sm-6"><label>M09_1</label>
                <input name="M09_1" class="form-control" id="M09_1" placeholder="M09_1" value="${rows[pos].M09_1}">
              </div>
              <div class="col-sm-6"><label>M09_2</label>
                <input name="M09_2" class="form-control" id="M09_2" placeholder="M09_2" value="${rows[pos].M09_2}">
              </div>
              <div class="col-sm-6"><label>M09_3</label>
                <input name="M09_3" class="form-control" id="M09_3" placeholder="M09_3" value="${rows[pos].M09_3}">
              </div>
              <div class="col-sm-6"><label>M10_1</label>
                <input name="M10_1" class="form-control" id="M10_1" placeholder="M10_1" value="${rows[pos].M10_1}">
              </div>
              <div class="col-sm-6"><label>M10_2</label>
                <input name="M10_2" class="form-control" id="M10_2" placeholder="M10_2" value="${rows[pos].M10_2}">
              </div>
              <div class="col-sm-6"><label>M10_3</label>
                <input name="M10_3" class="form-control" id="M10_3" placeholder="M10_3" value="${rows[pos].M10_3}">
              </div>
              <div class="col-sm-6"><label>M11_1</label>
                <input name="M11_1" class="form-control" id="M11_1" placeholder="M11_1" value="${rows[pos].M11_1}">
              </div>
              <div class="col-sm-6"><label>M11_2</label>
                <input name="M11_2" class="form-control" id="M11_2" placeholder="M11_2" value="${rows[pos].M11_2}">
              </div>
              <div class="col-sm-6"><label>M11_3</label>
                <input name="M11_3" class="form-control" id="M11_3" placeholder="M11_3" value="${rows[pos].M11_3}">
              </div>
              <div class="col-sm-6"><label>M11_4</label>
                <input name="M11_4" class="form-control" id="M11_4" placeholder="M11_4" value="${rows[pos].M11_4}">
              </div>
              <div class="col-sm-6"><label>M12_1</label>
                <input name="M12_1" class="form-control" id="M12_1" placeholder="M12_1" value="${rows[pos].M12_1}">
              </div>
              <div class="col-sm-6"><label>M12_2</label>
                <input name="M12_2" class="form-control" id="M12_2" placeholder="M12_2" value="${rows[pos].M12_2}">
              </div>
              <div class="col-sm-6"><label>M12_3</label>
                <input name="M12_3" class="form-control" id="M12_3" placeholder="M12_3" value="${rows[pos].M12_3}">
              </div>
              <div class="col-sm-6"><label>M12_4</label>
                <input name="M12_4" class="form-control" id="M12_4" placeholder="M12_4" value="${rows[pos].M12_4}">
              </div>
              <div class="col-sm-6"><label>M13_1</label>
                <input name="M13_1" class="form-control" id="M13_1" placeholder="M13_1" value="${rows[pos].M13_1}">
              </div>
              <div class="col-sm-6"><label>M13_2</label>
                <input name="M13_2" class="form-control" id="M13_2" placeholder="M13_2" value="${rows[pos].M13_2}">
              </div>
              <div class="col-sm-6"><label>M13_3</label>
                <input name="M13_3" class="form-control" id="M13_3" placeholder="M13_3" value="${rows[pos].M13_3}">
              </div>
              <div class="col-sm-6"><label>M13_4</label>
                <input name="M13_4" class="form-control" id="M13_4" placeholder="M13_4" value="${rows[pos].M13_4}">
              </div>
              <div class="col-sm-6"><label>M14_1</label>
                <input name="M14_1" class="form-control" id="M14_1" placeholder="M14_1" value="${rows[pos].M14_1}">
              </div>
              <div class="col-sm-6"><label>M14_2</label>
                <input name="M14_2" class="form-control" id="M14_2" placeholder="M14_2" value="${rows[pos].M14_2}">
              </div>
              <div class="col-sm-6"><label>M14_3</label>
                <input name="M14_3" class="form-control" id="M14_3" placeholder="M14_3" value="${rows[pos].M14_3}">
              </div>
              <div class="col-sm-6"><label>comentario</label>
                <input name="comentario" class="form-control" id="comentario" placeholder="comentario" value="${rows[pos].comentario}">
              </div>
              <div class="col-sm-6"><label>sugerencia</label>
                <input name="sugerencia" class="form-control" id="sugerencia" placeholder="sugerencia" value="${rows[pos].sugerencia}">
              </div>
            </form>`;
            showModalWindow(
              "Editar madurez",
              form,
              editarkpms,
              "Cancelar",
              "Guardar"
            );
          });

          gridmadurez.find(".command-delete").on("click", function (e) {
            let idrow = $(this).parent().parent().attr("data-row-id");
            showModalWindow(
              "Borrar reporte",
              `<form class="formDel"><input name="tipo" class="mshide" value="madurez"><input name="id[]" class="mshide" value="${idrow}">¿Desea borrar el reporte de métrica con id ${idrow}?</form>`,
              deletekpm,
              "Cancelar",
              "Borrar",
              null
            );
          });
        });
        $("#table-metricas").removeClass("mshide");
        $("#table-madurez").removeClass("mshide");
      }
      $(".actions").prepend(
        '<div class="btn btn-default download-group"><img class="icono downloadTabla" src="./img/download.svg" alt="descargar" title="Descargar tabla"></div>'
      );
      $(".actions").prepend(
        '<div class="btn btn-default lock-report"><img class="icono" src="./img/lock.svg" alt="descargar" title="Bloquear reporte"></div>'
      );
      $(".actions").prepend(
        '<div class="btn btn-default unlock-report"><img class="icono" src="./img/unlock.svg" alt="descargar" title="Desbloquear reporte"></div>'
      );

      agregarBotonNuevo("table-metricas-header", "btn-metricas");
      agregarBotonNuevo("table-madurez-header", "btn-madurez");
      agregarBotonNuevo("table-metricas-csirt-header", "btn-metricas-csirt");

      $(".btn-metricas").click(function () {
        $(".nextBtn").off();
        obtainPreguntasKpms().then((data) => {
          $(".preguntas").remove();
          cerrarModal();
          let len = data.length;
          let pages = 5;
          let j = 0;
          for (let i = 1; i <= pages; i++) {
            let tab = `<div class="tab mshide preguntas" id="tab${i}"></div>`;
            $(".cuestionarioFinal").before(tab);
            while (j < (len / pages) * i) {
              let numero = data[j].nombre.match(/KPM(\d+)/);
              let pregunta = `
              <div class="form-group mb-4">
                <label class="KPMLabel KPMLabel${numero[1]}">${data[j].nombre} ${data[j].grupo}:</label>
                <lavel class="${data[j].nombre}"></lavel>
                <small class="form-text text-muted">${data[j].descripcion_corta}</small>
                <img class="icono info info${data[j].nombre} text-end" src="./img/info.svg" alt="Info" title="${data[j].descripcion_larga}">
                <input type="number" class="form-control KPMInput" max="99999" min="0" name="${data[j].nombre}">
                <!-- <small class="form-text text-muted">${data[j].descripcion_larga}</small> -->
              </div>
              `;
              $(`#tab${i}`).append(pregunta);
              j++;
            }
          }
          getReportAs(data);
          $(".nav-tabs").addClass("mshide");
          getlastreportekpms("metricas");
          $(".divtable").addClass("mshide");
          $(".form-metricas").removeClass("mshide");
          $(".block-inicio").addClass("mshide");
          ponerstep();
        });
      });

      $(".btn-metricas-csirt").click(function () {
        $(".nextBtn").off();
        obtainPreguntasKpmsCsirt().then((data) => {
          $(".preguntas").remove();
          let len = data.length;
          let i = 1;
          let tab = `<div class="tab mshide preguntas" id="tab${i}"></div>`;
          $(".cuestionarioFinal-csirt").before(tab);
          let j = 0;
          for (j; j < len; j++) {
            let numero = data[j].nombre.match(/KPM(\d+)/);
            let pregunta = `
            <div class="form-group mb-4">
              <label class="KPMLabel KPMLabel${numero[1]}">${data[j].nombre} ${data[j].grupo}:</label>
              <lavel class="${data[j].nombre}"></lavel>
              <small class="form-text text-muted">${data[j].descripcion_corta}</small>
              <img class="icono info info${data[j].nombre} text-end" src="./img/info.svg" alt="Info" title="${data[j].descripcion_larga}">
              <input type="number" class="form-control KPMInput" max="99999" min="0" name="${data[j].nombre}">
              <!-- <small class="form-text text-muted">${data[j].descripcion_larga}</small> -->
            </div>
            `;
            $(`#tab${i}`).append(pregunta);
          }
          getReportAsCsirt(data);
          $(".nav-tabs").addClass("mshide");
          getlastreportekpms("csirt");
          $(".divtable").addClass("mshide");
          $(".form-metricas-csirt").removeClass("mshide");
          $(".block-inicio").addClass("mshide");
          ponerstep();
        });
      });

      $(".btn-madurez").click(function () {
        $(".nextBtn").off();
        getReportAs();
        $(".nav-tabs").addClass("mshide");
        getlastreportekpms("madurez");
        $(".divtable").addClass("mshide");
        $(".form-madurez").removeClass("mshide");
        $(".block-inicio").addClass("mshide");
        ponerstep();
      });

      $(".lock-report").click(lockkpms);
      $(".unlock-report").click(unlockkpms);
      $(".downloadTabla").click(function () {
        let options = {
          rowSelect: true,
          labels: {
            noResults: "No se han encontrado resultados.",
            infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
            search: "Buscar",
          },
          formatters: {
            commands: function (column, row) {
              return (
                "<button type='button' class='btn btn-xs btn-default command-edit'><img class='icono' src='./img/edit.svg' /></button>" +
                "<button type='button' class='btn btn-xs btn-default command-delete'><img class='icono' src='./img/delete.svg' /></button>"
              );
            },
          },
        };
        let div = $(this).parent().parent().parent().parent().parent().parent();
        let tabla = $(div).closest("div").find("table");
        ExportTabla(tabla.attr("id"), tabla.attr("id"), options);
      });
    },
  });
}

function getPersonas(val, id) {
  $.ajax({
    type: "GET",
    url: `./api/getPersonasActivo?id=${id}`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null, goLogin);
      } else {
        if (retorno["responsables"]["product_owner"] == null) {
          retorno["responsables"]["product_owner"] = "";
        }

        if (retorno["responsables"]["r_desarrollo"] == null) {
          retorno["responsables"]["r_desarrollo"] = "";
        }

        if (retorno["responsables"]["r_kpms"] == null) {
          retorno["responsables"]["r_kpms"] = "";
        }

        if (retorno["responsables"]["r_operaciones"] == null) {
          retorno["responsables"]["r_operaciones"] = "";
        }

        if (retorno["responsables"]["r_seguridad"] == null) {
          retorno["responsables"]["r_seguridad"] = "";
        }

        let id = retorno["responsables"]["activo_id"];
        let productowner = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label for='${id}' class="col-form-label">Product Owner</label></div><div class="col-6"><input id='${id}' class='editActivo form-control' type='text' name="product_owner" value="${retorno["responsables"]["product_owner"]}"/></div></div></div>`;
        let r_desarrollo = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label for='${id}' class="col-form-label">R.Desarrollo</label></div><div class="col-6"><input id='${id}' class='editActivo form-control' type='text' name="r_desarrollo" value="${retorno["responsables"]["r_desarrollo"]}"/></div></div></div>`;
        let r_kmps = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label for='${id}' class="col-form-label">R.KPMS</label></div><div class="col-6"><input id='${id}' class='editActivo form-control' type='text' name="r_kpms" value="${retorno["responsables"]["r_kpms"]}"/></div></div></div>`;
        let r_operaciones = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label for='${id}' class="col-form-label">R.Operaciones</label></div><div class="col-6"><input id='${id}' class='editActivo form-control' type='text' name="r_operaciones" value="${retorno["responsables"]["r_operaciones"]}"/></div></div></div>`;
        let r_seguridad = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label for='${id}' class="col-form-label">R.Seguridad</label></div><div class="col-6"><input id='${id}' class='editActivo form-control' type='text' name="r_seguridad" value="${retorno["responsables"]["r_seguridad"]}"/></div></div></div>`;
        let form = `<form class="formEdit">${productowner}${r_desarrollo}${r_kmps}${r_operaciones}${r_seguridad}<input class='editId mshide' name='id' type='text' value='${id}' required/></form>`;
        showModalWindow("Editar Personas", form, editPersonas);
      }
    },
  });
}

function editPersonas() {
  cerrarModal();
  let form = $(`.formEdit`);
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/editPersonasActivo`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          retorno.message,
          null,
          "Aceptar",
          null,
          null
        );
      }
    },
  });
}

function editActivo(val, id) {
  let name = $(val).closest("div").prev()[0].lastChild.data.trim();
  $.ajax({
    type: "GET",
    url: `./api/getClaseActivos?id=${id}`,
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      let desc = "";
      let descripcion = "";
      let archivado = "";
      let externo = "";
      let expuesto = "";
      let options =
        "<select id='tipoSelect' class='form-select-custom' name='activo_id'>";
      let checked = "";
      let checkedext = "";
      let checkedexp = "";
      for (let clase of retorno.claseActivos) {
        let selected = "";
        if (clase.id == retorno.activoSelect.tipo) {
          selected = "selected";
        }
        options += `<option value="${clase.id}" ${selected}>${clase.perspectiva} - ${clase.nombre}</option>`;
      }
      if ($(val).closest("div").prev().parent().hasClass("arch")) {
        checked = "checked";
      }

      if ($(`#${id}`).parent().hasClass("ext")) {
        checkedext = "checked";
      }
      if ($(`#${id}`).parent().hasClass("exp")) {
        checkedexp = "checked";
      }

      if (retorno.activoSelect.tipo == 33) {
        archivado = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='archivado' class="col-form-label">Archivado</label></div><div class='col-auto'><input class='form-check-custom' type='checkbox' id='archivado' name="archivado" ${checked}/><label for="archivado" class="check-custom"></label></div></div></div>`;
      }

      expuesto = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='expuesto' class="col-form-label">Expuesto a internet</label></div><div class='col-auto'><input class='form-check-custom' type='checkbox' id='expuesto' name="expuesto" ${checkedexp}/><label for="expuesto" class="check-custom"></label></div></div></div>`;

      if (retorno.activoSelect.tipo == 67) {
        archivado = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='archivado' class="col-form-label">Archivado</label></div><div class='col-auto'><input class='form-check-custom' type='checkbox' id='archivado' name="archivado" ${checked}/><label for="archivado" class="check-custom"></label></div></div></div>`;
      }

      if (retorno.activoSelect.tipo == 42) {
        archivado = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='archivado' class="col-form-label">Archivado</label></div><div class='col-auto'><input class='form-check-custom' type='checkbox' id='archivado' name="archivado" ${checked}/><label for="archivado" class="check-custom"></label></div></div></div>`;
        externo = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='externo' class='col-form-label'>Externo</label></div><div class='col-auto'><input class='form-check-custom' type='checkbox' id='externo' name="externo" ${checkedext}/><label for="externo" class="check-custom"></label></div></div></div>`;

        if (retorno.activoSelect.descripcion !== null) {
          descripcion = retorno.activoSelect.descripcion;
        }
        desc = `<div class ='row g-3 align-items-center'><div class='col-auto'><label for='descArea' class='col-form-label'>Descripción</label></div><div class='col-auto'><textarea id='textArea' maxlength="250" class='form-control' rows='3' name="descripcion"/>${descripcion}</textarea></div></div></div>`;
      }
      options += "</select>";

      let form = `<form class="formEdit"><div class='row g-3 align-items-center'><div class='col-auto'><label for='${id}' class="col-form-label">Nombre</label></div><div class="col-auto"><input id='${id}' class='editActivo form-control' type='text' name="nombre" value="${name}"/></div></div></div><input class='editId mshide' name='id' type='text' value='${id}' required/><div class="row g-3 align-items-center"><div class='col-auto'><label for='tipoSelect' class="col-form-label">Tipo</label></div><div class="col-10 mb-3">${options}</div></div></div>${expuesto}${archivado}${externo}${desc}</form>`;
      showModalWindow("Editar Activo", form, edicion);
    },
  });
}

function clonarActivo(val, id) {
  let name = $(val).closest("div").prev().html().trim();
  let form = `<form class="formClone"><div class='row g-3 align-items-center'><div class='col-auto'><label for='${id}' class="col-form-label">${name}_</label></div><div class="col-auto"><input id='${id}' class='cloneActivo form-control' type='text' name="sufijo"/></div><div><input class='editId mshide' name='id' type='text' value='${id}' required/></div><div class='row g-3 align-items-center'><div class='col-auto'><label for='bia' class="col-form-label">Copiar BIA</label></div><div class="col-auto"><input class='form-check-custom' type='checkbox' id='bia' name="bia"/><label for="bia" class="check-custom"></label></div></div><div class='row g-3 align-items-center'><div class='col-auto'><label for='ecr' class="col-form-label">Copiar ultimo ECR</label></div><div class="col-auto"><input class='form-check-custom' type='checkbox' id='ecr' name="ecr"/><label for="ecr" class="check-custom"></label></div></div></form>`;
  showModalWindow("Nuevo nombre del clonado", form, clonado);
}

function editServicio(val, id) {
  let name = $(val).closest("div").prev().html().trim();
  let form = `<form class="formEdit">Nombre <input id='${id}' class='editActivo' type='text' name="nombre" value="${name}"/></div><div><input class='editId mshide' name='id' type='text' value='${id}' required/></div></form>`;
  showModalWindow("Editar Activo", form, edicion);
}

function delActivo(id) {
  let form = `<form class="formDel"><div>Se va a proceder al borrado del activo y de todas sus dependencias. ¿Está seguro de querer borrarlo?<div id="delobject" class='deleteActivo'></div><input class='deleteSistema mshide' name='id' type='text' value='${id}' required/></div></form>`;
  showModalWindow("Borrar activo", form, checkborrado, "Cancelar", "Borrar");
}

function edicion() {
  cerrarModal();
  let form = $(`.formEdit`);
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/editActivo`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          retorno.message,
          lastItemb,
          null,
          "Aceptar"
        );
      }
    },
  });
}

function clonado() {
  let form = $(`.formClone`);
  let input = $(".cloneActivo")[0].value;
  if (input !== "") {
    mostrarLoading();
    $.ajax({
      type: "POST",
      url: `./api/cloneActivo`,
      xhrFields: {
        withCredentials: true,
      },
      data: form.serialize(),

      success: function (retorno, textStatus, request) {
        if (retorno.error) {
          showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
        } else {
          showModalWindow(
            INFORMACION,
            retorno.message,
            lastItemb,
            null,
            "Aceptar"
          );
        }
      },
    });
  } else {
    showModalWindow(
      "ERROR",
      "Debe introducir un sufijo para clonar el activo.",
      null,
      "Aceptar",
      null,
      null
    );
  }
}

function nuevoAjax() {
  let edit = $(`#newobject`).attr("class");
  let classArr = edit.split(/\s+/);
  let form = $(`.formNew`);
  $.ajax({
    type: "POST",
    url: `./api/${classArr[0]}`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          retorno.message,
          lastItemb,
          null,
          "Aceptar"
        );
      }
    },
  });
}

function borrarplan() {
  cerrarModal();
  let form = $(`.formDel`);
  $.ajax({
    type: "POST",
    url: `./api/deletePlan`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          "Borrado correctamente.",
          null,
          "Aceptar",
          getPlan,
          null
        );
      }
    },
  });
}

function crearplan() {
  let form = $(`.formNew`);

  cerrarModal();

  $.ajax({
    type: "POST",
    url: `./api/newPlan`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(
          "ERROR",
          "No tienes permisos para crear planes.",
          null,
          "Aceptar",
          null,
          null
        );
      } else {
        showModalWindow(
          "Información",
          "Creado correctamente",
          getPlan,
          null,
          "Aceptar"
        );
      }
    },
  });
}

function editarkpms() {
  cerrarModal();
  let form = $(`.formEdit`);
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/editKpms`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          retorno.message,
          null,
          "Aceptar",
          null,
          getkpms
        );
      }
    },
  });
}

function getErrorMessages(data) {
  let errorMessages = {};
  for (const item of data) {
    errorMessages[item.nombre] = item.descripcion_corta;
  }
  return errorMessages;
}

function condicionesFormulario(data) {
  let valor01;
  let valor02;
  let valor03;
  let valor04;
  let valor05;
  let valor06;
  let valor07;
  let valor08;
  let valor09;
  let valor10;
  let valor11;
  let valor12;
  let valor13;
  let valor14;
  let valor15;
  let valor16;
  let valor17;
  let valor18;
  let valor19;
  let valor20;
  let valor21;
  let valor22;
  let valor23;
  let valor24;
  let valor25;
  let valor26;
  let valor27;
  let valor28;
  let valor29;
  let valor30;
  let valor31;
  let valor32;
  let valor33;
  let valor34;
  let valor36;
  let valor37;
  let valor38;
  let valor39;
  let valor40;
  let valor41;
  let valor42;
  let valor46;
  let valor47;
  let valor50;
  let valor51;
  let valor52;
  let valor56;
  let valor61;
  let valor62;
  let valor63;
  let valor64;
  let valor71;
  let valor72;
  let valor73;
  let valor731;
  let valor732;
  let valor733;
  let valor734;
  let valor74;
  let errorMessages = {};
  if (data) errorMessages = getErrorMessages(data);
  $(`.KPMInput`).on(`input`, function () {
    $(`.KPMLabel`).removeClass(`redLabel`);
  });

  let campoNumero = $('.form-control[name="KPM01"]');
  let tab = campoNumero.closest(`.tab`);
  if (!tab.hasClass(`mshide`)) {
    valor01 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM26"]');
    valor26 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM24"]');
    valor24 = parseInt(campoNumero.val(), 10);

    if (valor01 < valor26 || valor01 < valor24 || valor01 < valor24 + valor26) {
      showModalWindow(
        ERROR,
        `El valor del (KPM01 - ${errorMessages.KPM01}) no puede ser menor que (KPM24 - ${errorMessages.KPM24}), (KPM26 - ${errorMessages.KPM26}), o la suma de ambos`,
        null,
        `Aceptar`,
        null,
        null
      );
      $(".KPMLabel01").addClass("redLabel");
      $(".KPMLabel26").addClass("redLabel");
      $(".KPMLabel24").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM50"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor50 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM51"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor51 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor50 < valor51) {
      showModalWindow(
        ERROR,
        `El valor del (KPM50 - ${errorMessages.KPM50}) no puede ser inferior al (KPM51 - ${errorMessages.KPM51})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel50").addClass("redLabel");
      $(".KPMLabel51").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM04"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor04 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM05"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor05 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor04 < valor05) {
      showModalWindow(
        ERROR,
        `El valor del (KPM05 - ${errorMessages.KPM51}) no puede ser mayor que el (KPM04 - ${errorMessages.KPM04})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel04").addClass("redLabel");
      $(".KPMLabel05").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM06"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor06 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM07"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor07 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor06 < valor07) {
      showModalWindow(
        ERROR,
        `El valor del (KPM07 - ${errorMessages.KPM07}) no puede ser mayor que el (KPM06 - ${errorMessages.KPM06})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel06").addClass("redLabel");
      $(".KPMLabel07").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM31"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor31 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM09"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor09 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor31 < valor09) {
      showModalWindow(
        ERROR,
        `El valor del (KPM09 - ${errorMessages.KPM09}) no puede ser mayor que el (KPM31 - ${errorMessages.KPM31})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel31").addClass("redLabel");
      $(".KPMLabel09").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM31"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor31 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM11"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor11 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor31 < valor11) {
      showModalWindow(
        ERROR,
        `El valor del (KPM11 - ${errorMessages.KPM11}) no puede ser mayor que el (KPM31 - ${errorMessages.KPM31})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel11").addClass("redLabel");
      $(".KPMLabel31").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM01"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor01 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM08"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor08 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor01 < valor08) {
      showModalWindow(
        ERROR,
        `El valor del (KPM08 - ${errorMessages.KPM08}) no puede ser mayor que el (KPM01 - ${errorMessages.KPM01})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel01").addClass("redLabel");
      $(".KPMLabel08").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM01"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor01 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM10"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor10 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor01 < valor10) {
      showModalWindow(
        ERROR,
        `El valor del (KPM10 - ${errorMessages.KPM10}) no puede ser mayor que el (KPM01 - ${errorMessages.KPM01})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel01").addClass("redLabel");
      $(".KPMLabel10").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM12"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor12 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM13"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor13 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor12 < valor13) {
      showModalWindow(
        ERROR,
        `El valor del (KPM13 - ${errorMessages.KPM13}) no puede ser mayor que el (KPM12 - ${errorMessages.KPM12})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel12").addClass("redLabel");
      $(".KPMLabel13").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM14"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor14 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM15"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor15 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor14 < valor15) {
      showModalWindow(
        ERROR,
        `El valor del (KPM15 - ${errorMessages.KPM15}) no puede ser mayor que el (KPM14 - ${errorMessages.KPM14})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel14").addClass("redLabel");
      $(".KPMLabel15").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM16"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor16 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM17"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor17 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor16 < valor17) {
      showModalWindow(
        ERROR,
        `El valor del (KPM17 - ${errorMessages.KPM17}) no puede ser mayor que el (KPM16 - ${errorMessages.KPM16})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel16").addClass("redLabel");
      $(".KPMLabel17").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM18"]');
  if (campoNumero.length !== 0) {
    tab = campoNumero.closest(".tab");
    if (!tab.hasClass("mshide")) {
      valor18 = parseInt(campoNumero.val(), 10);
    }
  }

  campoNumero = $('.form-control[name="KPM19"]');
  if (campoNumero.length !== 0) {
    tab = campoNumero.closest(".tab");
    if (!tab.hasClass("mshide")) {
      valor19 = parseInt(campoNumero.val(), 10);
    }
  }
  if (!tab.hasClass("mshide")) {
    if (valor18 !== undefined && valor19 !== undefined) {
      if (valor18 < valor19) {
        showModalWindow(
          ERROR,
          `El valor del (KPM19 - ${errorMessages.KPM19}) no puede ser mayor que el (KPM18 - ${errorMessages.KPM18})`,
          null,
          "Aceptar",
          null,
          null
        );
        $(".KPMLabel18").addClass("redLabel");
        $(".KPMLabel19").addClass("redLabel");
        return 1;
      } else if (valor19 != 0) {
        showModalWindow(
          INFORMACION,
          `El valor del (KPM19 - ${errorMessages.KPM19}) por norma general es 0, si no estás 100% seguro de que este valor sea correcto, revísalo.`,
          null,
          "Aceptar",
          null,
          null
        );
      }
    }
  }

  campoNumero = $('.form-control[name="KPM31"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor31 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM32"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor32 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor31 < valor32) {
      showModalWindow(
        ERROR,
        `El valor del (KPM32 - ${errorMessages.KPM32}) no puede ser mayor que el (KPM31 - ${errorMessages.KPM31})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel31").addClass("redLabel");
      $(".KPMLabel32").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM01"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor01 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM20"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor20 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor01 < valor20) {
      showModalWindow(
        ERROR,
        `El valor del (KPM20 - ${errorMessages.KPM20}) no puede ser mayor que el (KPM01 - ${errorMessages.KPM01})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel01").addClass("redLabel");
      $(".KPMLabel20").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM01"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor01 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM22"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor22 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor01 < valor22) {
      showModalWindow(
        ERROR,
        `El valor del (KPM22 - ${errorMessages.KPM22}) no puede ser mayor que el (KPM01 - ${errorMessages.KPM01})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel22").addClass("redLabel");
      $(".KPMLabel01").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM24"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor24 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM25"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor25 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor24 < valor25) {
      showModalWindow(
        ERROR,
        `El valor del (KPM25 - ${errorMessages.KPM25}) no puede ser mayor que el (KPM24 - ${errorMessages.KPM24})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel24").addClass("redLabel");
      $(".KPMLabel25").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM26"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor26 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM27"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor27 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor26 < valor27) {
      showModalWindow(
        ERROR,
        `El valor del (KPM27 - ${errorMessages.KPM27}) no puede ser mayor que el (KPM26 - ${errorMessages.KPM26})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel26").addClass("redLabel");
      $(".KPMLabel27").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM02"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor02 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM21"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor21 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor02 < valor21) {
      showModalWindow(
        ERROR,
        `El valor del (KPM21 - ${errorMessages.KPM21}) no puede ser mayor que el (KPM02 - ${errorMessages.KPM02})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel02").addClass("redLabel");
      $(".KPMLabel21").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM02"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor02 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM23"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor23 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor02 < valor23) {
      showModalWindow(
        ERROR,
        `El valor del (KPM23 - ${errorMessages.KPM23}) no puede ser mayor que el (KPM02 - ${errorMessages.KPM02})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel02").addClass("redLabel");
      $(".KPMLabel23").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM28"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor28 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM29"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor29 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor28 < valor29) {
      showModalWindow(
        ERROR,
        `El valor del (KPM29 - ${errorMessages.KPM29}) no puede ser mayor que el (KPM28 - ${errorMessages.KPM28})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel28").addClass("redLabel");
      $(".KPMLabel29").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM28"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor28 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM30"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor30 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor28 < valor30) {
      showModalWindow(
        ERROR,
        `El valor del (KPM30 - ${errorMessages.KPM30}) no puede ser mayor que el (KPM28 - ${errorMessages.KPM28})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel28").addClass("redLabel");
      $(".KPMLabel30").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM33"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor33 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM34"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor34 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor33 < valor34) {
      showModalWindow(
        ERROR,
        `El valor del (KPM34 - ${errorMessages.KPM34}) no puede ser mayor que el (KPM33 - ${errorMessages.KPM33})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel33").addClass("redLabel");
      $(".KPMLabel34").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM03"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor03 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM36"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor36 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor03 < valor36) {
      showModalWindow(
        ERROR,
        `El valor del (KPM36 - ${errorMessages.KPM36}) no puede ser mayor que el (KPM03 - ${errorMessages.KPM03})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel03").addClass("redLabel");
      $(".KPMLabel36").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM37"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor37 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM38"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor38 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor37 < valor38) {
      showModalWindow(
        ERROR,
        `El valor del (KPM38 - ${errorMessages.KPM38}) no puede ser mayor que el (KPM37 - ${errorMessages.KPM37})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel37").addClass("redLabel");
      $(".KPMLabel38").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM37"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor37 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM39"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor39 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor37 < valor39) {
      showModalWindow(
        ERROR,
        `El valor del (KPM39 - ${errorMessages.KPM39}) no puede ser mayor que el (KPM37 - ${errorMessages.KPM37})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel37").addClass("redLabel");
      $(".KPMLabel39").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM31"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor31 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM40"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor40 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor31 < valor40) {
      showModalWindow(
        ERROR,
        `El valor del (KPM40 - ${errorMessages.KPM40}) no puede ser mayor que el (KPM31 - ${errorMessages.KPM31})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel31").addClass("redLabel");
      $(".KPMLabel40").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM41"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor41 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM42"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor42 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor41 < valor42) {
      showModalWindow(
        ERROR,
        `El valor del (KPM42 - ${errorMessages.KPM42}) están integrados no puede ser mayor que el (KPM41 - ${errorMessages.KPM41})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel41").addClass("redLabel");
      $(".KPMLabel42").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM01"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor01 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM46"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor46 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor01 < valor46) {
      showModalWindow(
        ERROR,
        `El valor del (KPM46 - ${errorMessages.KPM46}) no puede ser mayor que el (KPM01 - ${errorMessages.KPM01})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel01").addClass("redLabel");
      $(".KPMLabel46").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM74"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor74 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM73"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor73 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor73 < valor74) {
      showModalWindow(
        ERROR,
        `El valor del (KPM74 - ${errorMessages.KPM74}) no puede ser mayor que el (KPM73 - ${errorMessages.KPM73})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel74").addClass("redLabel");
      $(".KPMLabel73").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM73"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor73 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM73.1"]');
    valor731 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM73.2"]');
    valor732 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM73.3"]');
    valor733 = parseInt(campoNumero.val(), 10);
    campoNumero = $('.form-control[name="KPM73.4"]');
    valor734 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (
      (valor73 &&
        (valor731 == "NaN" ||
          valor732 == "NaN" ||
          valor733 == "NaN" ||
          valor734 == "NaN")) ||
      (valor73 && valor73 != valor731 + valor732 + valor733 + valor734)
    ) {
      showModalWindow(
        ERROR,
        `El valor del (KPM73 - ${errorMessages.KPM73}), no puede ser diferente que la suma de: (KPM73.1 - ${errorMessages["KPM73.1"]}), \
          (KPM73.2 - ${errorMessages["KPM73.2"]}), (KPM73.3 - ${errorMessages["KPM73.3"]}) \
          y (KPM73.4 - ${errorMessages["KPM73.4"]})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel73").addClass("redLabel");
      $(".KPMLabel731").addClass("redLabel");
      $(".KPMLabel732").addClass("redLabel");
      $(".KPMLabel733").addClass("redLabel");
      $(".KPMLabel734").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM03"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor03 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM47"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor47 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor03 < valor47) {
      showModalWindow(
        ERROR,
        `El valor del (KPM47 - ${errorMessages.KPM47}) no puede ser mayor que el (KPM03 -${errorMessages.KPM03})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel03").addClass("redLabel");
      $(".KPMLabel47").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM02"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor02 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM71"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor71 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor02 < valor71) {
      showModalWindow(
        ERROR,
        `El valor del (KPM71 - ${errorMessages.KPM71}) no puede ser mayor que el (KPM02 - ${errorMessages.KPM02})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel02").addClass("redLabel");
      $(".KPMLabel71").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM50"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor50 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM52"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor52 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor50 < valor52) {
      showModalWindow(
        ERROR,
        `El valor del (KPM52 - ${errorMessages.KPM52}) pendientes de resolver) no puede ser mayor que el (KPM50 - ${errorMessages.KPM50})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel50").addClass("redLabel");
      $(".KPMLabel52").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM50"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor50 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM72"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor72 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor50 < valor72) {
      showModalWindow(
        ERROR,
        `El valor del (KPM72 - ${errorMessages.KPM72}) no puede ser mayor que el (KPM50 - ${errorMessages.KPM50})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel50").addClass("redLabel");
      $(".KPMLabel72").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM50"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor50 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM56"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor56 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor50 < valor56) {
      showModalWindow(
        ERROR,
        `El valor del (KPM56 - ${errorMessages.KPM56}) no puede ser mayor que el (KPM50 - ${errorMessages.KPM50})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel50").addClass("redLabel");
      $(".KPMLabel56").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM61"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor61 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM62"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor62 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor61 < valor62) {
      showModalWindow(
        ERROR,
        `El valor del (KPM62 - ${errorMessages.KPM62}) no puede ser mayor que el (KPM61 - ${errorMessages.KPM61})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel61").addClass("redLabel");
      $(".KPMLabel62").addClass("redLabel");
      return 1;
    }
  }

  campoNumero = $('.form-control[name="KPM63"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor63 = parseInt(campoNumero.val(), 10);
  }
  campoNumero = $('.form-control[name="KPM64"]');
  tab = campoNumero.closest(".tab");
  if (!tab.hasClass("mshide")) {
    valor64 = parseInt(campoNumero.val(), 10);
  }
  if (!tab.hasClass("mshide")) {
    if (valor63 < valor64) {
      showModalWindow(
        ERROR,
        `El valor del (KPM64 - ${errorMessages.KPM64}) no puede ser mayor que el (KPM63 - ${errorMessages.KPM63})`,
        null,
        "Aceptar",
        null,
        null
      );
      $(".KPMLabel63").addClass("redLabel");
      $(".KPMLabel64").addClass("redLabel");
      return 1;
    }
  }
}

function ExportTabla(objeto, nombre, options) {
  $(`#${objeto}`).bootgrid("destroy");
  let type = "xlsx";
  let data = document.getElementById(objeto);
  let file = XLSX.utils.table_to_book(data, { sheet: "sheet1" });
  XLSX.write(file, { bookType: type, bookSST: true, type: "base64" });
  XLSX.writeFile(file, `${nombre}.` + type);
  $(`#${objeto}`).bootgrid(options);
  $(`#${objeto}`).removeClass("mshide");
  let actions = $(`#${objeto}-header .actions`);
  actions.prepend(
    `<div class="btn btn-default download-group"><img class="icono downloadTabla" src="./img/download.svg" alt="descargar" title="Descargar tabla"></div>`
  );
  $(`#${objeto}-header .download-group`).click(function () {
    ExportTabla(objeto, `${nombre}`, options);
  });
}

function move_form(data) {
  $("html, body").animate({ scrollTop: 0 }, "slow");
  let selector = "";
  if (!$(".form-metricas").hasClass("mshide")) {
    selector = ".metricas > .tab";
  } else if (!$(".form-madurez").hasClass("mshide")) {
    selector = ".madurez > .tab";
  } else {
    selector = ".csirt > .tab";
  }
  let step = $(".step");
  let tap = $(selector);
  let hide = false;
  $(".KPMLabel").addClass("bold");

  //Aqui hago las condiciones para que le formulario falle si hay algun valor que no le gusta

  if (condicionesFormulario(data) == 1) return;

  for (let i = 0; i < tap.length; i++) {
    if (hide) {
      $(tap[i]).removeClass("mshide");
      $(step[i]).addClass("active");
      $(step[i - 1]).removeClass("active");
      if (tap.length == i + 1) {
        $(".nextBtn").addClass("mshide");
        $(".saveBtn").removeClass("mshide");
      }
      break;
    }
    if (!$(tap[i]).hasClass("mshide")) {
      if (tap.length !== i + 1) {
        $(tap[i]).addClass("mshide");
        hide = true;
      }
    }
  }
}

function setTimer() {
  function finalContador() {
    addClass("#alargarSesion", "mshide");
    removeClass("#abrirLogin", "mshide");
  }

  function insertTimer() {
    let timer = `<div id="myToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                  <div class="toast-header d-flex justify-content-between align-items-center">
                      <strong class="mr-auto textToast">La sesión va a expirar en poco tiempo</strong>
                  </div>
                  <div class="toast-body">
                      La sesión de 11Cert se cerrará automáticamente en 15 minutos.
                      <hr>
                      <div class="d-flex justify-content-between align-items-center mb-1">
                          <div id="countdown" class="d-flex align-items-center ml-auto mt-2">Tiempo restante: 15:00</div>
                          <button class="btn btn-primary mt-auto btn-toast" id="alargarSesion">Extender sesión</button>
                          <button class="btn btn-primary mt-auto btn-toast mshide" id="abrirLogin">Iniciar sesión</button>
                      </div>
                  </div>
              </div>`;
    append(".toast-container", timer);
  }

  function startCountdown(duration) {
    $("#myToast").toast({ autohide: false }).toast("show");
    let countdownElement = document.getElementById("countdown");
    let timer = duration,
      minutes,
      seconds;

    let countdownIntervalId = setInterval(function () {
      let Element = document.getElementById("countdown");
      if (!Element) {
        clearInterval(countdownIntervalId);
      }

      minutes = parseInt(timer / 60, 10);
      seconds = parseInt(timer % 60, 10);

      minutes = minutes < 10 ? "0" + minutes : minutes;
      seconds = seconds < 10 ? "0" + seconds : seconds;

      countdownElement.textContent =
        "Tiempo restante: " + minutes + ":" + seconds;

      if (--timer < 0) {
        timer = 0;
        finalContador();
        clearInterval(countdownIntervalId);
      }
    }, 1000);
  }

  function startTimer() {
    insertTimer();
    document
      .getElementById("alargarSesion")
      .addEventListener("click", refreshTimer);
    document
      .getElementById("abrirLogin")
      .addEventListener("click", function () {
        window.location.href = "./login";
      });
    setTimeout(function () {
      startCountdown(15 * 60);
    }, 45 * 60 * 1000);
  }

  function refreshTimer() {
    $("#alargarSesion").prop("disabled", true);
    fetch("./api/refreshToken")
      .then((response) => response.json())
      .then((events) => {
        empty(".toast-container");
        startTimer();
      })
      .catch((error) => {
        console.error("Error al refrescar el token: ", error);
      });
  }

  startTimer();
}

function insertarMetricas(areas) {
  mostrarLoading();
  $.ajax({
    method: "POST",
    url: `./api/getMetricasKpms`,
    xhrFields: {
      withCredentials: true,
    },
    data: { areas },
    success: function (retorno, textStatus, request) {
      setTimer();
      if (!retorno.error) {
        $(".KPM04").text(
          "(Valor existente ciso: " +
          retorno.metricas[0]["SisInformacion"] +
          ")"
        );
        $(".KPM05").text(
          "(Valor existente ciso: " +
          retorno.metricas[0]["SisInformacionAct"] +
          ")"
        );
        $(".KPM06").text(
          "(Valor existente ciso: " + retorno.metricas[0]["Aplicaciones"] + ")"
        );
        $(".KPM32").text(
          "(Valor existente ciso: " + retorno.metricas[0]["NoCriticos"] + ")"
        );
        $(".KPM54").text(
          "(Valor existente ciso: " + retorno.metricas[0]["CritNoExp"] + ")"
        );
        $(".KPM55").text(
          "(Valor existente ciso: " +
          retorno.metricas[0]["AppCriticaNoExp"] +
          ")"
        );
        cerrarModal();
      } else {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, goHome);
      }
    },
  });
}

function getMetricasAreaSeleccionada() {
  $(".reportAs").change(function () {
    let areasSeleccionadas = [$(".reportAs").val()];
    insertarMetricas(areasSeleccionadas);
    $(".nextBtn").off("click", getMetricasSiguiente);
  });
}

function getMetricasSiguiente() {
  let areasSeleccionadas = [$(".reportAs").val()];
  insertarMetricas(areasSeleccionadas);
  $(".nextBtn").off("click", getMetricasSiguiente);
}

function showNextError() {
  showModalWindow(
    ERROR,
    "No tienes ningún área para reportar, si se trata de un error contacta con el administrador.",
    null,
    "Aceptar",
    null,
    null
  );
}

function getReportAsCsirt(data = null) {
  $.ajax({
    type: "GET",
    url: `./api/getReportAsCsirt`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (!retorno["reporte"] || retorno["reporte"].length === 0) {
        $(".nextBtn").addClass("error_button");
        $(".nextBtn").on("click", showNextError);
        $(".nextBtn").off("click", function () {
          move_form(data);
        });
      } else {
        getMetricasAreaSeleccionada();
        $(".nextBtn").off("click", function () {
          move_form(data);
        });
        $(".nextBtn").off("click", showNextError);
        $(".nextBtn").on("click", getMetricasSiguiente);
        $(".nextBtn").on("click", function () {
          move_form(data);
        });
      }
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        let selectElement = $("select.reportAs");
        selectElement.empty();
        let reported = "";
        $.each(retorno["reporte"], function (key, value) {
          if (value.Reported) {
            reported = "✅";
          } else {
            reported = " ✘";
          }
          let newOption = $("<option>")
            .val(value.id)
            .html(reported + " " + value.nombre);
          selectElement.append(newOption);
        });
      }
    },
  });
}

function getReportAs(data = null) {
  $.ajax({
    type: "GET",
    url: `./api/getReportAs`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (!retorno["reporte"] || retorno["reporte"].length === 0) {
        $(".nextBtn").addClass("error_button");
        $(".nextBtn").on("click", showNextError);
        $(".nextBtn").off("click", function () {
          move_form(data);
        });
      } else {
        getMetricasAreaSeleccionada();
        $(".nextBtn").off("click", function () {
          move_form(data);
        });
        $(".nextBtn").off("click", showNextError);
        $(".nextBtn").on("click", getMetricasSiguiente);
        $(".nextBtn").on("click", function () {
          move_form(data);
        });
      }
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        let selectElement = $("select.reportAs");
        selectElement.empty();
        $.each(retorno["reporte"], function (key, value) {
          let newOption = $("<option>").val(value.id).html(value.nombre);
          selectElement.append(newOption);
        });
      }
    },
  });
}

function getlastreportekpms(tipo, direccion = null, area = null) {
  mostrarLoading();
  let params = $.param({ tipo: tipo, direccion: direccion, area: area });
  $.ajax({
    type: "GET",
    url: `./api/getLastKpms?${params}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      cerrarModal();
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        $.each(retorno[0], function (key, value) {
          $('[name="' + key + '"]').val(value);
        });
      }
    },
  });
}

function deletekpm() {
  cerrarModal();
  let form = $(`.formDel`);
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/deleteKpms`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          INFORMACION,
          retorno.message,
          null,
          "Aceptar",
          null,
          getkpms
        );
      }
    },
  });
}

function editarplan() {
  cerrarModal();
  let form = $(`.formEdit`);
  $.ajax({
    type: "POST",
    url: `./api/editPlan`,
    data: form.serialize(),
    xhrFields: {
      withCredentials: true,
    },

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow("ERROR", retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          "Información",
          "Editado correctamente.",
          getPlan,
          null,
          "Aceptar"
        );
      }
    },
  });
}

function editarseguimiento() {
  let form = $(`.formEdit`);
  let sistema = $(`.sysName`).text();
  if (form[0].checkValidity()) {
    cerrarModal();
    $.ajax({
      type: "POST",
      url: `./api/editPacSeguimiento?sysName=${sistema}`,
      xhrFields: {
        withCredentials: true,
      },
      data: form.serialize(),

      success: function (retorno, textStatus, request) {
        if (retorno.error) {
          showModalWindow(
            "ERROR",
            retorno.message,
            null,
            "Aceptar",
            null,
            null
          );
        } else {
          showModalWindow(
            "Información",
            "Editado correctamente.",
            getPacServicio,
            null,
            "Aceptar"
          );
        }
      },
    });
  }
}

function borrarseguimiento() {
  cerrarModal();
  let form = $(`.formDel`);
  $.ajax({
    type: "POST",
    url: `./api/deletePacSeguimiento`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, null);
      } else {
        getPacServicio();
        showModalWindow(
          INFORMACION,
          retorno.message,
          null,
          "Aceptar",
          null,
          null
        );
      }
    },
  });
}

function editardashboard() {
  cerrarModal();
}

function checkborrado() {
  let id = $(".deleteSistema").val();
  let idp = $(".breadcrumb-item :last").attr("id");
  let form = `<form class="formDel"><div>Si quiere borrar el activo debe escribir "delete me".<div id="delobject" class='deleteActivo'></div><input class="form-control check-deleteme" type='text' required/><input class='deleteSistema mshide' name='id' type='text' value='${id}' required/><input class='mshide' name='idp' type='text' value='${idp}' required/></div></form>`;
  showModalWindow(
    "¿Seguro que desea borrar el activo?",
    form,
    null,
    "Cancelar",
    null,
    null
  );
  $(".check-deleteme").keyup(function () {
    if ($(".check-deleteme").val() == "delete me") {
      $(".check-deleteme").off("keyup");
      cerrarModal();
      borrado();
    }
  });
}

function borrado() {
  let form = $(`.formDel`);
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/deleteActivo`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow("ERROR", retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          "Información",
          "Borrado correctamente.",
          breadcrumblast,
          null,
          "Aceptar"
        );
      }
    },
  });
}

function breadcrumblast() {
  $(".breadcrumb-item :last")[0].click();
}
function enviarEvalServicio() {
  mostrarLoading();
  let form = $(`.evalservicio`);
  let idSistema = $(`.selectSistema option:selected`).val();
  let idServicio = $(".servicioId").attr("id");
  $.ajax({
    type: "POST",
    url: `./api/newEval/${idSistema}`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error === false) {
        window.location.href = `./historialservicio?id=${idServicio}`;
      } else {
        showModalWindow("Error", retorno.message, null, "Aceptar", null, null);
      }
    },
  });
}

function saveEvalServicio() {
  let form = $(`.evalservicio`);
  let idSistema = $(`.${SELECT_SISTEMA}`).val();
  $.ajax({
    type: "POST",
    url: `./api/saveEval/${idSistema}`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error !== false) {
        showModalWindow("Error", retorno.message, null, "Aceptar", null, null);
      } else {
        showModalWindow(
          "Guardado",
          retorno.message,
          null,
          "Aceptar",
          null,
          null
        );
      }
    },
  });
}

function saveBIA() {
  let form = $(`.evalBIA`);
  $.ajax({
    type: "POST",
    url: `./api/savebia/`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),

    success: function (retorno, textStatus, request) {
      if (retorno.error === false) {
        showModalWindow(
          "Guardado",
          retorno.message,
          null,
          "Aceptar",
          null,
          null
        );
        getBia();
      } else {
        showModalWindow("Error", retorno.message, null, "Aceptar", null, null);
      }
    },
  });
}

function calcularPromedioRiestoActual(datos) {
  let devolver;
  for (let item of datos) {
    item.value = parseInt(item.value);
    if (isNaN(item.value)) {
      item.value = 0;
    }
  }
  let muybajo =
    datos[2].value +
    datos[3].value +
    datos[4].value +
    datos[8].value +
    datos[9].value +
    datos[14].value;
  let bajo =
    datos[1].value + datos[7].value + datos[13].value + datos[19].value;
  let medio =
    datos[0].value +
    datos[6].value +
    datos[12].value +
    datos[18].value +
    datos[24].value;
  let alto =
    datos[5].value + datos[11].value + datos[17].value + datos[23].value;
  let muyalto =
    datos[10].value +
    datos[15].value +
    datos[16].value +
    datos[20].value +
    datos[21].value +
    datos[22].value;
  let total = muybajo + bajo + medio + alto + muyalto;
  let media = total / 5;
  let mayor;
  let subir = false;

  for (let item of datos) {
    item.value = parseInt(item.value);
    if (item.value == 0) {
      item.value = "";
    }
  }
  let masalto = Math.max(muybajo, bajo, medio, alto, muyalto);
  if (masalto == muyalto) {
    mayor = "Crítico";
  } else if (masalto == alto) {
    mayor = "Alto";
  } else if (masalto == medio) {
    mayor = "Moderado";
  } else if (masalto == bajo) {
    mayor = "Medio";
  } else {
    mayor = "Leve";
  }

  if (mayor == "Leve" || mayor == "Medio") {
    if (alto > 0 || muyalto > 0) {
      subir = true;
    }
  }

  devolver = {
    leve: muybajo,
    medio: bajo,
    moderado: medio,
    alto: alto,
    critico: muyalto,
    total: total,
    promedio: media,
    mayor: mayor,
    subir: subir,
  };
  return devolver;
}

function buscarActivos(text) {
  if (text === "") {
    $(".activoParent").empty();
  } else {
    $.ajax({
      type: "GET",
      url: `./api/getActivosByNombre?search=${text}`,
      xhrFields: {
        withCredentials: true,
      },

      success: function (retorno, textStatus, request) {
        if (retorno.error === false) {
          if ($(".activoParent").length < 1) {
            $("#relation").parent().append("<div class='activoParent'><div/>");
          } else {
            $(".activoParent").empty();
          }
          if (retorno.activos.length !== 0) {
            for (let activo of retorno.activos) {
              $(".activoParent").append(
                `<div id='${activo.id}' class='relAc'>${activo.nombre}</div>`
              );
            }
          } else {
            $('name="tipo"').removeClass("mshide");
          }
        } else {
          showModalWindow(
            "Error",
            "Se ha producido un error desconocido.",
            null,
            "Aceptar",
            null,
            null
          );
        }
        $(".relAc").click(function (item) {
          $(".relation").val(item.currentTarget.innerHTML);
          $(".relation").attr("value", item.currentTarget.id);
          $(".activoParent").empty();
        });
      },
    });
  }
}

function getMetricasKpms(areas) {
  mostrarLoading();
  $.ajax({
    method: "POST",
    url: `./api/getMetricasKpms`,
    xhrFields: {
      withCredentials: true,
    },
    data: { areas },
    success: function (retorno, textStatus, request) {
      if (!retorno.error) {
        cerrarModal();
        return retorno;
      } else {
        showModalWindow(ERROR, retorno.message, null, "Aceptar", null, goHome);
      }
    },
  });
}

function configuracion_boton() {
  let obtenerMetricasBtn = document.querySelector(".obtainMetricas");
  obtenerMetricasBtn.addEventListener("click", function () {
    let checkboxes = document.querySelectorAll(
      '#Areas tbody input[type="checkbox"]'
    );
    let areasSeleccionadas = [];
    for (const checkbox of checkboxes) {
      if (checkbox.checked) {
        let idCampo = checkbox.parentNode.nextElementSibling.innerText;
        areasSeleccionadas.push(idCampo);
      }
    }
    getMetricasKpms(areasSeleccionadas);
  });
}

function seleccionar_checkbox() {
  let selectAllCheckbox = document.getElementById("seleccionar-checkbox");
  selectAllCheckbox.addEventListener("change", function () {
    let checkboxes = document.querySelectorAll(
      '#Areas tbody input[type="checkbox"]'
    );
    for (const checkbox of checkboxes) {
      checkbox.checked = selectAllCheckbox.checked;
    }
  });
}

function getAreas() {
  mostrarLoading();
  return new Promise((resolve) => {
    $.ajax({
      url: `./api/getAreas`,
      xhrFields: {
        withCredentials: true,
      },
      method: "GET",
      success: function (retorno, textStatus, request) {
        if (!retorno.error) {
          $("#areas-grid-data").html("");
          let table = document.getElementById("areas-grid-data");
          for (let area of retorno.Areas) {
            table.insertRow().innerHTML =
              "<td>" +
              '<input type="checkbox"></input>' +
              "</td>" +
              "<td>" +
              area.id +
              "</td>" +
              "<td>" +
              area.nombre +
              "</td>";
          }
          seleccionar_checkbox();
          configuracion_boton();
          let data;
          resolve(data);
          cerrarModal();
        } else {
          showModalWindow(
            ERROR,
            retorno.message,
            null,
            "Aceptar",
            null,
            goHome
          );
        }
      },
    });
  });
}

function getServiciobySistemaId(id, nombreactivo) {
  mostrarLoading();
  $.ajax({
    url: `./api/getServiciobySistemaId?id=${id}`,
    xhrFields: {
      withCredentials: true,
    },
    method: "GET",
    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(
          ERROR,
          "Ha ocurrido un error obteniendo el servicio.",
          null,
          "Aceptar",
          null,
          null
        );
      }
      let first = true;
      let servicios = [];
      let content = `${nombreactivo} pertenece a: `;
      for (let servicio of retorno.servicios) {
        if (servicio.tipo == 42 && !servicios.includes(servicio.nombre)) {
          if (!first) {
            content += ", ";
          }
          servicios.push(servicio.nombre);
          first = false;
          content += servicio.nombre;
        }
      }

      showModalWindow(INFORMACION, content, null, "Aceptar", null, null);
    },
  });
}

function obtenerRelacionActivo(id, simple = false) {
  return new Promise(function (resolve, reject) {
    $.ajax({
      type: "GET",
      url: `./api/obtainFathersActivo?id=${id}&simple=${simple}`,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        resolve(retorno);
      },
    });
  });
}

function añadirOrganizaciones(input) {
  $.ajax({
    type: "GET",
    url: `./api/getOrganizaciones`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      for (let organizacion of retorno.Organizaciones) {
        let option = `<option value="${organizacion["id"]}">${organizacion["nombre"]}</option>`;
        $(input).append(option);
      }
    },
  });
}

function setTipoActivos(input) {
  $.ajax({
    type: "GET",
    url: `./api/obtainAllTypeActivos`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      let activos = retorno[0];
      for (let activo of activos) {
        let option = `<option value="${activo["nombre"]}">${activo["nombre"]}</option>`;
        $(input).append(option);
      }
    },
  });
}

function addOptions(input, activos) {
  for (let activo of activos) {
    let option = `<option value="${activo["id"]}">${activo["nombre"]}</option>`;
    $(input).append(option);
  }
}

function setBotonCambioRelacion(hijo, padreID) {
  $(".saveCambioRelacion").click(function () {
    let valores = $(".activoRelacion")
      .map(function () {
        return $(this).val();
      })
      .get();
    let i = valores.length - 1;
    while (i > 0) {
      if (valores[i] != "Ninguno") break;
      i--;
    }
    let relacion = {
      hijo: hijo,
      oldPadre: padreID,
      padre: valores[i],
    };
    $.ajax({
      type: "POST",
      url: `./api/changeRelacion`,
      data: relacion,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        sendPadre(hijo, valores[i]);
      },
    });
  });
}

function getHijosTipo(nombre, idPadre, tipo) {
  let url = "./api/getHijosTipo?";
  if (idPadre && tipo) {
    url += `idPadre=${encodeURIComponent(idPadre)}&tipo=${encodeURIComponent(
      tipo
    )}`;
  } else if (tipo && nombre) {
    url += `tipo=${encodeURIComponent(tipo)}&nombre=${encodeURIComponent(
      nombre
    )}`;
  }
  return fetch(url).then((response) => response.json());
}

function funcionalidadBotonAñadir() {
  $("#AddActivo").click(async function () {
    let lastActivo = $(".lastActivo").val();
    let tipo = $("#tipoActivo").val();
    if (tipo != "Ninguno") {
      const retorno = await getHijosTipo(lastActivo, null, tipo);
      if (retorno["Hijos"].length > 0) {
        $(".lastActivo").removeClass("lastActivo");
        let number = $(".activoRelacion").length;
        $(".activosARelacionar").append("<br>↓<br>");
        $(".activosARelacionar").append("<b>" + tipo + "</b>");
        $(".activosARelacionar").append(`
          <select class="form-control mt-1 lastActivo activoRelacion" id="activo${number}">
            <option value="Ninguno">Ninguno</option>
          </select>`);
        addOptions($(`#activo${number}`), retorno["Hijos"]);
      }
    }
  });
}

function delRelation(hijoID, padreID) {
  mostrarLoading();
  let parentesco = {
    hijo: hijoID,
    padre: padreID,
  };
  fetch("./api/eliminarRelacionActivo", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(parentesco),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        showModalWindow(
          "ERROR",
          "Error al eliminar la relacion:" + data.message,
          null,
          "Aceptar",
          null,
          null
        );
        acceptButton.disabled = false; // Reactivar el botón si hay un error
      } else {
        showModalWindow(
          "Relación eliminada",
          "La relación ha sido eliminada correctamente.",
          null,
          "Aceptar",
          null,
          null
        );
      }
    })
    .catch((error) => {
      showModalWindow(
        "ERROR",
        "Error al eliminar la relacion:" + data.message,
        null,
        "Aceptar",
        null,
        null
      );
      acceptButton.disabled = false; // Reactivar el botón si hay un error
    });
}

function sendPadre(hijoID, padreID) {
  mostrarLoading();
  obtenerRelacionActivo(hijoID).then(function (padres) {
    padres = padres[0];
    let relacionActual = "";
    let len = padres.length - 1;
    for (let i = len; i >= 0; i--) {
      relacionActual += "<b>" + padres[i]["tipo"] + "</b> <br>";
      relacionActual += padres[i]["nombre"] + "<br>";
      if (i != 0) {
        relacionActual += "↓<br>";
      }
    }
    let text = `<div>
                  <h3>${hijoID} - ${padres[0].nombre}</h3>
                  <div class="row mt-4">
                    <div class="col-md-6 text-center selectoresPadre">
                      <h4>Relación actual</h4>
                      <br>
                      <div class="relacionActual mt-1">
                        ${relacionActual}
                      </div>
                    </div>
                    <div class="col-md-6 text-center selectoresPadre">
                      <h4>Nueva relación</h4>
                      <br>
                      <div class="activosARelacionar"> 
                        <b class="mt-3">Organización</b>
                        <select class="form-control mt-1 lastActivo activoRelacion" id="organizacionRelacion" name="organizacion">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                      </div>
                      <button class="btn btn-primary mt-3 saveCambioRelacion">Guardar cambio relacional</button>
                      <hr>
                      <div class="mb-3">
                        Selecciona el tipo de activo a relacionar:
                      </div>
                      <div class="row">
                        <div class="col-md-8">
                          <select class="form-control" id="tipoActivo">
                            <option value="Ninguno">Ninguno</option>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <button class="btn btn btn-secondary" id="AddActivo">Añadir</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>`;
    showModalWindow(
      "Cambiar relación del activo",
      text,
      null,
      "Cerrar",
      null,
      null,
      null,
      "modal-lm"
    );
    añadirOrganizaciones("#organizacionRelacion");
    setTipoActivos("#tipoActivo");
    funcionalidadBotonAñadir();
    setBotonCambioRelacion(hijoID, padreID);
  });
}

function mostrarRelaciónModificable(id, type = "Change") {
  obtenerRelacionActivo(id, true).then(function (padres) {
    padres = padres[0];
    let modal = `<div>
      <div class="Padres"> 
          <b class="mt-3">Padres</b>
          <select class="form-control mt-1 padres" id="padres" name="padres">
            <option value="Ninguno">Nueva relación</option>
          </select>
      </div>`;
    showModalWindow(
      "Selecciona la relación a modificar",
      modal,
      function () {
        if (type == "Change") sendPadre(id, $("#padres").val());
        else if (padres.length > 1) {
          if ($("#padres").val() != "Ninguno")
            delRelation(id, $("#padres").val());
          else
            showModalWindow(
              ERROR,
              "No has seleccionado un activo correcto.",
              null,
              "Cerrar",
              null,
              null
            );
        } else if ($("#padres").val() == "Ninguno") {
          showModalWindow(
            ERROR,
            "No has seleccionado un activo correcto.",
            null,
            "Cerrar",
            null,
            null
          );
        } else {
          showModalWindow(
            ERROR,
            "No puedes eliminar este padre ya que es el único asociado a este activo.",
            null,
            "Cerrar",
            null,
            null
          );
        }
      },
      "Cerrar",
      "Aceptar",
      null,
      null
    );
    for (let padre of padres) {
      let option = `<option value="${padre["id"]}">${padre["nombre"]}</option>`;
      $(".padres").append(option);
    }
  });
}

function mostrarMenuCambioRelacion(id, type = "Change") {
  mostrarLoading();
  mostrarRelaciónModificable(id, type);
}

function handler() {
  $(".activo").click("click", function (event) {
    event.stopPropagation();
    getChild(this.id);
  });

  $(".evaluar").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    window.location.href = `./evaluarservicio?id=${id}`;
  });

  $(".bia").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).closest("div").prev().attr("id");
    window.location.href = `./bia?id=${id}`;
  });

  $(".borrar").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    delActivo(id);
  });

  $(".delPadres").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    mostrarMenuCambioRelacion(id, "Delete");
  });

  $(".ChangePadres").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    mostrarMenuCambioRelacion(id);
  });

  $(".editar").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    editActivo($(this).parent().parent().parent(), id);
  });

  $(".clonar").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    clonarActivo($(this).parent().parent().parent(), id);
  });

  $(".personas").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).parent().parent().parent().prev().attr("id");
    getPersonas($(this).parent().parent().parent(), id);
  });

  $(".historial").click("click", function (event) {
    event.stopPropagation();
    let id = $(this).closest("div").prev().attr("id");
    window.location.href = `./historialservicio?id=${id}`;
  });
}

function checkvalidpassword(password) {
  let strongRegex =
    /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#^?&])[A-Za-z\d@$!%*^#?&]{12,}$/g;
  return strongRegex.test(password.toString());
}

function checkinputempty(selector) {
  let exclude;
  if (selector === "metricas") {
    exclude = [
      "comment_met",
      "comment_sug",
      "KPM50",
      "KPM48",
      "KPM49",
      "KPM51",
      "KPM52",
      "KPM53",
      "KPM56",
      "KPM57",
      "KPM58",
      "KPM59A",
      "KPM59B",
      "KPM60A",
      "KPM60B",
      "KPM62",
      "KPM63",
      "KPM64",
      "KPM70",
    ];
  } else {
    exclude = ["comment_met", "comment_sug"];
  }

  const empty = $(`.form-${selector} input`)
    .filter(function () {
      return this.value === "" && !exclude.includes(this.name);
    })
    .map(function () {
      return this.name;
    })
    .get();
  return empty;
}

function generarcontraseña(e) {
  let array;
  let password = "";
  let input = $(e)[0].currentTarget.nextElementSibling;
  let check;
  do {
    do {
      array = new Uint8Array(1);
      window.crypto.getRandomValues(array);
      for (let numero of array) {
        if (numero > 32 && numero < 127) {
          password += String.fromCharCode(numero);
        }
      }
    } while (password.length < 12);
    check = checkvalidpassword(password);
    if (!check) {
      password = "";
    }
  } while (!check);
  $(input).val(password);
}

function ponerstep() {
  let selector = "";
  if (!$(".form-metricas").hasClass("mshide")) {
    selector = ".metricas";
  } else if (!$(".form-madurez").hasClass("mshide")) {
    selector = ".madurez";
  } else {
    selector = ".csirt";
  }
  let num = $(selector + " > .tab");
  let copy = $(".step");
  $(".block-step").empty();
  for (_ of num) {
    $(selector + " > .block-step").append(copy[0].outerHTML);
  }
  $(".step").first().addClass("active");
}

function refreshToken() {
  if ($("#recaptchaResponse").length) {
    grecaptcha.ready(function () {
      grecaptcha
        .execute("6LcM73oaAAAAAODm1L60_a95HULhaEdRZFSZY7XF", {
          action: "login",
        })
        .then(function (token) {
          let recaptchaResponse = document.getElementById("recaptchaResponse");
          let form = $(".form-login");
          let url = form.attr("action");
          recaptchaResponse.value = token;
          $.ajax({
            type: "POST",
            url: url,
            xhrFields: {
              withCredentials: true,
            },
            data: form.serialize(),
            success: function (retorno, textStatus, request) {
              if (retorno.error === false && retorno.url !== undefined) {
                window.location.href = retorno.url;
              }
              if (retorno.error && retorno.recaptcha_v2) {
                document.getElementById("recaptcha-container").style.display =
                  "block";
                showRecaptchaV2();
              }
              if (retorno.error && !retorno.recaptcha_v2) {
                showModalWindow(ERROR, retorno.message, null, "Aceptar", null);
              }
            },
          });
        });
    });
  }
}

function showRecaptchaV2() {
  grecaptcha.render("recaptcha-container", {
    sitekey: "6LcCdE4qAAAAAGITq9tylzUVkH6mZbr-t1nQyBmX",
    callback: function (response) {
      document.getElementById("recaptchaResponse").value = response;
      document.getElementById("recaptcha_v2_flag").value = "true";
    },
  });
}
