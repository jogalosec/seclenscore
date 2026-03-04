import {
  establecerOrganizaciones,
  setCampoAreaProyecto,
  configDesplegablesPentest,
  gestionarResponsable,
} from "../utils/utilsPentest.js";

import {
  obtenerIssue,
  obtenerComentarios,
  mostrarIssues,
  enviarComentario,
  actualizarStatus,
  obtainUsers,
  obtenerCampos,
  delIssue,
  obtenerReporterID,
} from "../api/userApi.js";

import { getHijosTipo } from "../api/serviciosAPI.js";

function crearPentest(tipo) {
  let form = $("#form-newPentest");
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/crearPentest?tipo=${tipo}`,
    xhrFields: {
      withCredentials: true,
    },
    data: form.serialize(),
    success: function (retorno, textStatus, request) {
      $(".tarjetasPentest").empty();
      $("#pyntTabla").bootgrid("clear");
      $("#pentestTabla").bootgrid("clear");
      rellenarTablaYTarjetas();
      cerrarModal();
    },
  });
}

function cambioPentest() {
  $(".nombrePentestInput").change(function () {
    $(".nombrePentest").removeClass("red");
  });
  $(".responsableInput").change(function () {
    $(".responsablePentest").removeClass("red");
  });
  $(".responsableInput").change(function () {
    $(".responsablePentest").removeClass("red");
  });
  $(".descripcionPentestInput").change(function () {
    $(".descripcionPentest").removeClass("red");
  });
  $(".fechaInicioInput").change(function () {
    $(".fechaInicioPentest").removeClass("red");
  });
  $(".fechaFinalInput").change(function () {
    $(".fechaFinalPentest").removeClass("red");
  });
  $(".organizacionInput").change(function () {
    $(".organizacion-label").removeClass("red");
    $(".direccion-label").removeClass("red");
    $(".area-label").removeClass("red");
    $(".producto-label").removeClass("red");
  });
  $(".direccionInput").change(function () {
    $(".direccion-label").removeClass("red");
  });
  $(".areaInput").change(function () {
    $(".area-label").removeClass("red");
  });
  $(".productoInput").change(function () {
    $(".producto-label").removeClass("red");
  });
  $(".areaServicio-input").change(function () {
    $(".areaServicio-label").removeClass("red");
  });
  $(".asignee-input").change(function () {
    $(".responsable-label").removeClass("red");
  });
}

function rellenarTablaYTarjetas() {
  $.ajax({
    type: "GET",
    url: `./api/obtenerPentestSimple`,
    success: function (retorno, textStatus, request) {
      mostrarTarjetasSimple(retorno);
      llenarTablaPentestSimple(retorno);
      llenarTablaPynt(retorno);
    },
  });
}

function editarPentest() {
  let form = $("#editPentest");
  let dates;
  let id = $("#editPentest").attr("class");
  dates = form.serialize() + "&" + `id=${id}`;
  mostrarLoading();
  $.ajax({
    type: "POST",
    url: `./api/editPentest`,
    xhrFields: {
      withCredentials: true,
    },
    data: dates,
    success: function (retorno, textStatus, request) {
      if (retorno["Error"])
        showModalWindow(
          "Modificación de pentest",
          retorno["msg"],
          null,
          "Aceptar",
          null,
          null
        );
      else cerrarModal();
      $("#pentestTabla").bootgrid("clear");
      $(".tarjetasPentest").empty();
      rellenarTablaYTarjetas();
    },
  });
}

function comprobarPentest(tipo) {
  let form = $("#form-newPentest");
  if (!$("#botonAceptar").hasClass("btn-desactivado")) {
    $("#botonAceptar").addClass("btn-desactivado");
    $.ajax({
      type: "POST",
      url: `./api/comprobarPentest`,
      xhrFields: {
        withCredentials: true,
      },
      data: form.serialize(),
      success: function (retorno, textStatus, request) {
        cambioPentest();
        if (!retorno["Error"]) {
          $("#botonAceptar").removeClass("btn-desactivado");
          crearPentest(tipo);
        } else if (retorno["Error"]) {
          if (!retorno["Area"]) $(".area-label").addClass("red");
          if (!retorno["Descripcion"]) $(".descripcionPentest").addClass("red");
          if (!retorno["Organizacion"])
            $(".organizacion-label").addClass("red");
          if (!retorno["Direccion"]) $(".direccion-label").addClass("red");
          if (!retorno["Fecha_final"]) $(".fechaFinalPentest").addClass("red");
          if (!retorno["Fecha_inicio"])
            $(".fechaInicioPentest").addClass("red");
          if (!retorno["Nombre"] || !retorno["Nombre_repe"])
            $(".nombrePentest").addClass("red");
          if (!retorno["Responsable"]) $(".responsablePentest").addClass("red");
          if (!retorno["Producto"]) $(".producto-label").addClass("red");
          if (!retorno["AreaServicio"])
            $(".areaServicio-label").addClass("red");
          if (!retorno["ResponsableProy"])
            $(".responsable-label").addClass("red");
          $("#botonAceptar").removeClass("btn-desactivado");
        }
      },
    });
  }
}

function camposPentest(id, type = 1) {
  return new Promise(function (resolve, reject) {
    $.ajax({
      type: "GET",
      url: `./api/obtenerPentestSimple`,
      success: function (retorno, textStatus, request) {
        for (let pentest of retorno) {
          if (type == 1) {
            if (
              pentest["status"] == 1 ||
              (pentest["tipo"] == "Pynt" && pentest["status"] != 8)
            ) {
              $(id).append(
                `<option value="${pentest["nombre"]}">${pentest["nombre"]}</option>`
              );
            }
          } else {
            $(id).append(
              `<option value="${pentest["nombre"]}">${pentest["nombre"]}</option>`
            );
          }
        }
        resolve(retorno);
      },
    });
  });
}

$(document).ready(function () {
  $(".nav-link").click(function (e) {
    let target = $(this).attr("id");
    $(".tab-content").addClass("mshide");
    $(target).removeClass("mshide");
    $(".nav-link").removeClass("active");
    $(this).addClass("active");

    if (target === "#planificacion-tab") {
      setTimeout(function () {
        $("#calendar").fullCalendar("render");
      }, 100);
    }
  });
  rellenarTablaYTarjetas();

  $(".btn-ImpPentest").click(function (e) {
    $(".excelPent").click();
  });

  let inputPent = $(".excelPent");

  inputPent.change(function () {
    inputCambiado(inputPent, "./api/importPentest");
  });

  function gestionarCreacionPentest(tipo) {
    let form = `<form class="issueForm" id="form-newPentest">
                    <div class="form-group mb-4 row">
                        <label for="text" class="col-4 col-form-label nombrePentest">Nombre</label> 
                        <div class="col-8">
                            <input id="text" name="Nombre" type="text" class="form-control nombrePentestInput" required="required">
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="text4" class="col-4 col-form-label responsablePentest">Pentester</label> 
                        <div class="col-8">
                            <input type="text" name="Responsable" id="inputField" placeholder="Empieza a escribir el usuario" class="form-control responsableInput" autocomplete="off">
                            <ul id="suggestionsInformer" class="list-group"></ul>
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                      <label for="select7" class="col-4 col-form-label areaServicio-label">Proyecto Jira de área/servicio*</label>
                      <div class="col-8">
                        <select id="areaServ" name="AreaServ" class="form-select-custom areaServicio-input" required="required">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                        <small class="form-text">
                            Si no aparece el proyecto hay que solicitarlo a EPG.
                        </small>
                      </div>
                    </div>
                    <div class="form-group mb-4 row">
                      <label for="text4" class="col-4 col-form-label responsable-label">Responsable proyecto clonado*</label>
                        <div class="col-8">
                          <input type="text" name="ResponsableProy" id="asigneeInput" class="form-control asignee-input" autocomplete="off">
                          <ul id="suggestionsResponsable" class="list-group"></ul>
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="textarea" class="col-4 col-form-label descripcionPentest">Descripción</label> 
                        <div class="col-8">
                            <textarea id="textarea" name="Descripcion" maxlength="250" cols="40" rows="5" class="form-control descripcionPentestInput" aria-describedby="textareaHelpBlock"></textarea> 
                            <span id="textareaHelpBlock" class="form-text text-muted textDescription">Descripción pentest (max 250 chars)</span>
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <div class="form-group">
                            <label for="fecha" class="fechaInicioPentest">Fecha de inicio:</label>
                            <input type="date" class="form-control fechaInicioInput" id="fechaStart" name="Fecha_inicio">
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <div class="form-group">
                            <label for="fecha" class="fechaFinalPentest">Fecha de finalización:</label>
                            <input type="date" class="form-control fechaFinalInput" id="fechaFin" name="Fecha_final">
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="select7" class="col-4 col-form-label organizacion-label">Organización</label> 
                        <div class="col-8">
                            <select id="organizacion" name="Organizacion" class="form-select-custom organizacionInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row direccionBloque mshide">
                        <label for="select7" class="col-4 col-form-label direccion-label">Dirección</label> 
                        <div class="col-8">
                            <select id="direccion" name="Direccion" class="form-select-custom direccionInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row areaBloque mshide">
                        <label for="select7" class="col-4 col-form-label area-label">Área</label> 
                        <div class="col-8">
                            <select id="area" name="Area" class="form-select-custom areaInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row productoBloque mshide">
                        <label for="select7" class="col-4 col-form-label producto-label">Servicio / Producto</label> 
                        <div class="col-8">
                            <select id="producto" name="Producto" class="form-select-custom productoInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                </form>`;
    setCampoAreaProyecto();
    showModalWindow("Nuevo pentest", form, function () {
      comprobarPentest(tipo);
    });
    establecerOrganizaciones();
    configDesplegablesPentest("Sistema de Información");
    gestionarResponsable();
  }

  $(".btn-newPentest").click(function (e) {
    gestionarCreacionPentest("Pentest");
  });

  $(".btn-newPynt").click(function (e) {
    gestionarCreacionPentest("Pynt");
  });
});

function llenarTablaPynt(retorno) {
  $("#pyntTabla").bootgrid("destroy");
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
          "<button type='button' class='btn btn-primary btn-xs btn-default command-edit'" +
          " data-row-id='" +
          row.Id +
          "'>Acciones</button>"
        );
      },
    },
  };
  let grid = $("#pyntTabla")
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".command-edit").on("click", function () {
        let id = $(this).data("row-id");
        for (let pentest of retorno) {
          if (pentest.id == id) {
            accionesPentest(pentest, "Pynt");
          }
        }
      });
    });
  for (let pentest of retorno) {
    if (pentest.tipo == "Pynt") {
      let estado;
      if (pentest.status == 1)
        estado = "<label class='rounded-pill estado abierto'>Abierto</label>";
      else
        estado = "<label class='rounded-pill estado cerrado'>Cerrado</label>";
      let issuesArray = [
        {
          Id: pentest.id,
          Nombre: pentest.nombre,
          Proyecto: pentest.proyecto,
          FechaInicio: pentest.fecha_inicio,
          FechaFinal: pentest.fecha_final,
          Estado: estado,
        },
      ];
      $("#pyntTabla").bootgrid("append", issuesArray);
    }
  }
}

function llenarTablaPentestSimple(retorno) {
  $("#pentestTabla").bootgrid("destroy");
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
          "<button type='button' class='btn btn-primary btn-xs btn-default command-edit' " +
          "data-row-id='" +
          row.Id +
          "'>Acciones</button>"
        );
      },
    },
  };
  let grid = $("#pentestTabla")
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".command-edit").on("click", function () {
        let id = $(this).data("row-id");
        for (let pentest of retorno) {
          if (pentest.id == id) {
            accionesPentest(pentest, "Pentest");
          }
        }
      });
    });
  for (let pentest of retorno) {
    if (pentest.tipo == "Pentest") {
      let estado = "";
      if (pentest.status == 1) {
        estado = "<label class='rounded-pill estado abierto'>Abierto</label>";
      } else {
        estado = "<label class='rounded-pill estado cerrado'>Cerrado</label>";
      }
      let issuesArray = [
        {
          Id: pentest.id,
          Nombre: pentest.nombre,
          Proyecto: pentest.proyecto,
          FechaInicio: pentest.fecha_inicio,
          FechaFinal: pentest.fecha_final,
          Estado: estado,
        },
      ];
      $("#pentestTabla").bootgrid("append", issuesArray);
    }
  }
}

function accionesPentest(pentest, tipo) {
  mostrarLoading();
  $.ajax({
    type: "GET",
    url: `./api/getInfoPentestByID?id=${pentest.id}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      cerrarModal();
      const documentacion = sanitizeServiceName(retorno.documentacion);
      let botonStatus;
      if (pentest.status == 0 || pentest.status == 2 || pentest.status == 3) {
        botonStatus = `<a href="#" class="btn btn-primary btnOpen${pentest.id} w-100">Abrir</a>`;
      } else {
        botonStatus = `<a href="#" class="btn btn-primary btnFin${pentest.id} w-100">Cerrar</a>`;
      }

      let activos = "";
      for (let activo of retorno.activos) {
        if (activos.length > 0) {
          activos += `<br>`;
        }
        activos += `${activo}`;
      }
      if (activos.length == 0) {
        activos = "Sin producto asignado";
      }
      let vulns = "";
      for (let vuln of retorno.vulns)
        vulns += `<a href="https://jira.tid.es/browse/${vuln}">${vuln}</a>\n`;

      const solicitante = retorno.user_email
        ? retorno.user_email
        : "Sin solicitante";
      const pentesterAsignado = pentest.resp_pentest
        ? pentest.resp_pentest
        : "Sin asignar";
      const pentesterMailSoporte = pentest.mail_soporte
        ? pentest.mail_soporte
        : "Sin correo de soporte";

      pentest.observaciones = retorno.observaciones;


      let form = `
        <div class="row">
          <div class="col-md-12 mb-2">
            <h4 class="text-start">Información del pentest:</h4>
          </div>
          <div class="col-md-12">
            <table class="table">
              <tbody>
                <tr>
                  <td class="text-start col-md-4"><strong>ID:</strong></td>
                  <td class="col-md-6 text-start">${pentest.id}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Nombre:</strong></td>
                  <td class="col-md-6 text-start">${pentest.nombre}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Solicitante:</strong></td>
                  <td class="col-md-6 text-start">${solicitante}</td>
                <tr>
                  <td class="text-start col-md-4"><strong>Pentester asignado:</strong></td>
                  <td class="col-md-6 text-start">${pentesterAsignado}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Contacto de soporte:</strong></td>
                  <td class="col-md-6 text-start">${pentesterMailSoporte}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Proyecto:</strong></td>
                  <td class="col-md-6 text-start">${pentest.proyecto}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Responsable del proyecto:</strong></td>
                  <td class="col-md-6 text-start">${pentest.resp_proyecto}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Fecha de inicio:</strong></td>
                  <td class="col-md-6 text-start">${pentest.fecha_inicio}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Fecha de finalización:</strong></td>
                  <td class="col-md-6 text-start">${pentest.fecha_final}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Producto:</strong></td>
                  <td class="col-md-6 text-start">${activos}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Issues:</strong></td>
                  <td class="col-md-6 text-start">${vulns}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Descripción:</strong></td>
                  <td class="col-md-6 text-start">${pentest.descripcion}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4"><strong>Documentación:</strong></td>
                  <td class="col-md-6 text-start">${documentacion}</td>
                </tr>
              </tbody>
            </table>
            <div class="row mb-2">
              <div class="col-md-8 text-start"><strong>Asignarme Pentest</strong></div>
              <div class="col-md-4"><a href="#" class="btn btn-primary btnAsignar${pentest.id} w-100">Asignar</a></div>
            </div>
            <div class="row mb-2">
              <div class="col-md-8 text-start"><strong>Editar Pentest</strong></div>
              <div class="col-md-4"><a href="#" class="btn btn-primary editPen${pentest.id} w-100">Editar</a></div>
            </div>
            <div class="row mb-2">
              <div class="col-md-8 text-start"><strong>Cerrar Pentest</strong></div>
              <div class="col-md-4">${botonStatus}</div>
            </div>
            <div class="row mb-2">
              <div class="col-md-8 text-start"><strong>Rechazar Pentest</strong></div>
              <div class="col-md-4"><a href="#" class="btn btn-primary btnReject${pentest.id} w-100">Rechazar</a></div>
            </div>
            <div class="row mb-2">
              <div class="col-md-8 text-start"><strong>Generar Informe</strong></div>
              <div class="col-md-4"><a href="#" class="btn btn-primary btnGenerar${pentest.id} w-100">Generar</a></div>
          </div>
        </div>`;

      showModalWindow("", form, null, "Cerrar", null, null, null, "modal-lm");

      if (pentest.status == 1) {
        $(`.btnGenerar${pentest.id}`).addClass("disabled");
      } else if (pentest.status == 2 || pentest.status == 0) {
        $(`.btnFin${pentest.id}`).addClass("enabled");
      }

      if (tipo == "Pentest") {
        setBotonesPentest(pentest);
      } else if (tipo == "Pynt") {
        setBotonesPynt(pentest);
      }

      $(`.btnReject${pentest.id}`).click(function (e) {
        handlePentestReject(pentest);
      });

      $(`.btnAsignar${pentest.id}`).click(function (e) {
        asignarPentester(pentest);
      });

      $(`.btnGenerar${pentest.id}`).click(function (e) {
        generarDocumento(pentest);
      });
    },
  });
}

function setBotonesPynt(pentest) {
  $(`.btnFin${pentest.id}`).click(function (e) {
    mostrarLoading();
    $.ajax({
      type: "POST",
      url: `./api/cambiarStatusPentest?id=${pentest.id}&status=8`,
      success: function (retorno, textStatus, request) {
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
        cerrarModal();
      },
    });
  });
  $(`.btnOpen${pentest.id}`).click(function (e) {
    mostrarLoading();
    $.ajax({
      type: "POST",
      url: `./api/cambiarStatusPentest?id=${pentest.id}&status=5`,
      success: function (retorno, textStatus, request) {
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
        cerrarModal();
      },
    });
  });
  $(`.editPen${pentest.id}`).click(function (e) {
    let form = `<form class="${pentest.id}" id="editPentest">
                  <div class="col-md-12">
                    <div class="form-group mb-4 row">
                      <div class="form-group">
                          <label for="fecha" class="fechaInicioPentest">Fecha de inicio:</label>
                          <input type="date" class="form-control fechaInicioInput" id="fechaStart" name="Fecha_inicio">
                      </div>
                    </div>
                    <div class="form-group mb-4 row">
                      <div class="form-group">
                          <label for="fecha" class="fechaFinalPentest">Fecha de final:</label>
                          <input type="date" class="form-control fechaFinalInput" id="fechaEnd" name="Fecha_final">
                      </div>
                    </div>
                  </div>
                </form>`;
    showModalWindow("Editar pentest", form, editarPentest);
  });
  $(`.btnDel${pentest.id}`).click(function (e) {
    $(`.btnDel${pentest.id}2`).off("click");
    let form = `<h5>
                <b>Estas a punto de eliminar para siempre: </b>
            </h5>
            <br>
            <h4>
                ${pentest.nombre}
            </h4>
            <h4>
                ${pentest.descripcion}
            </h4>
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
                    <button class="btn btn-primary btnDel${pentest.id}2 btnDEL">ELIMINAR</button>
                </div>
            </div>`;
    showModalWindow("¿Eliminar pentest?", form, null, null, null);
    $(`.btnDel${pentest.id}2`).click(function (e) {
      mostrarLoading();
      $.ajax({
        type: "GET",
        url: `./api/eliminarPentest?id=${pentest.id}`,
        success: function (retorno, textStatus, request) {
          if (retorno.error)
            showModalWindow(
              INFORMACION,
              retorno.message,
              null,
              "Aceptar",
              null,
              null
            );
          else {
            $("#pentestTabla").bootgrid("clear");
            $(".tarjetasPentest").empty();
            rellenarTablaYTarjetas();
            cerrarModal();
          }
        },
      });
    });
    $(`.btnCancel`).click(function (e) {
      cerrarModal();
    });
  });
}

function setBotonesPentest(pentest) {
  $(`.btnFin${pentest.id}`).click(function (e) {
    mostrarLoading();
    $.ajax({
      type: "GET",
      url: `./api/cerrarPentest?id=${pentest.id}`,
      success: function (retorno, textStatus, request) {
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
        cerrarModal();
      },
    });
  });
  $(`.btnOpen${pentest.id}`).click(function (e) {
    mostrarLoading();
    $.ajax({
      type: "GET",
      url: `./api/reabrirPentest?id=${pentest.id}`,
      success: function (retorno, textStatus, request) {
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
        cerrarModal();
      },
    });
  });
  $(`.editPen${pentest.id}`).click(function (e) {
    let form = `<form class="${pentest.id}" id="editPentest">
                  <div class="col-md-12">
                    <div class="form-group mb-4 row">
                        <label for="text" class="col-4 col-form-label nombrePentest">Nombre</label> 
                        <div class="col-8">
                            <input id="text" name="Nombre" type="text" class="form-control nombrePentestInput" required="required">
                        </div>
                    </div>
                    <hr>
                    <div class="form-group mb-4 row">
                      <div class="form-group">
                          <label for="fecha" class="fechaInicioPentest">Fecha de inicio:</label>
                          <input type="date" class="form-control fechaInicioInput" id="fechaStart" name="Fecha_inicio">
                      </div>
                    </div>
                    <div class="form-group mb-4 row">
                      <div class="form-group">
                          <label for="fecha" class="fechaFinalPentest">Fecha de final:</label>
                          <input type="date" class="form-control fechaFinalInput" id="fechaEnd" name="Fecha_final">
                      </div>
                    </div>
                    <hr>
                    <div class="form-group mb-4 row">
                        <label for="text4" class="col-4 col-form-label responsablePentest">Pentester</label> 
                        <div class="col-8">
                            <input type="text" name="resp_pentest" id="inputField" placeholder="Empieza a escribir el usuario" class="form-control responsableInput" autocomplete="off">
                            <ul id="suggestionsInformer" class="list-group"></ul>
                        </div>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="select7" class="col-4 col-form-label organizacion-label">Organización</label> 
                        <div class="col-8">
                            <select id="organizacion" name="Organizacion" class="form-select-custom organizacionInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row direccionBloque mshide">
                        <label for="select7" class="col-4 col-form-label direccion-label">Dirección</label> 
                        <div class="col-8">
                            <select id="direccion" name="Direccion" class="form-select-custom direccionInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row areaBloque mshide">
                        <label for="select7" class="col-4 col-form-label area-label">Área</label> 
                        <div class="col-8">
                            <select id="area" name="Area" class="form-select-custom areaInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-4 row productoBloque mshide">
                        <label for="select7" class="col-4 col-form-label producto-label">Servicio / Producto</label> 
                        <div class="col-8">
                            <select id="producto" name="Producto" class="form-select-custom productoInput" required="required">
                                <option value="Ninguno">Ninguno</option>
                            </select>
                        </div>
                    </div>
                  </div>
                </form>`;
    showModalWindow("Editar pentest", form, editarPentest);
    establecerOrganizaciones();
    configDesplegablesPentest("Sistema de Información");
    gestionarResponsable();
  });
  $(`.btnDel${pentest.id}`).click(function (e) {
    $(`.btnDel${pentest.id}2`).off("click");
    let form = `<h5>
                <b>Estas a punto de eliminar para siempre: </b>
            </h5>
            <br>
            <h4>
                ${pentest.nombre}
            </h4>
            <h4>
                ${pentest.descripcion}
            </h4>
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
                    <button class="btn btn-primary btnDel${pentest.id}2 btnDEL">ELIMINAR</button>
                </div>
            </div>`;
    showModalWindow("¿Eliminar pentest?", form, null, null, null);
    $(`.btnDel${pentest.id}2`).click(function (e) {
      mostrarLoading();
      $.ajax({
        type: "GET",
        url: `./api/eliminarPentest?id=${pentest.id}`,
        success: function (retorno, textStatus, request) {
          if (retorno.error)
            showModalWindow(
              INFORMACION,
              retorno.message,
              null,
              "Aceptar",
              null,
              null
            );
          else {
            $("#pentestTabla").bootgrid("clear");
            $(".tarjetasPentest").empty();
            rellenarTablaYTarjetas();
            cerrarModal();
          }
        },
      });
    });
    $(`.btnCancel`).click(function (e) {
      cerrarModal();
    });
  });
}

function fechaNormal(fecha_inicio) {
  let date = new Date(fecha_inicio);
  let day = String(date.getDate()).padStart(2, "0");
  let month = String(date.getMonth() + 1).padStart(2, "0");
  let year = date.getFullYear();
  return `${day}-${month}-${year}`;
}

function mostrarTarjetasSimple(retorno) {
  const today = new Date();
  const eightDaysFromToday = new Date();
  eightDaysFromToday.setDate(today.getDate() + 8);

  for (let pentest of retorno) {
    const startDate = new Date(pentest.fecha_inicio);

    if (startDate <= eightDaysFromToday) {
      let card = `<div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2 d-flex align-items-center">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <img src="./img/evs.svg" class="img-prueba d-flex align-items-center" alt="Imagen">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="card-title">${
                                      pentest.nombre
                                    } - ${fechaNormal(
        pentest.fecha_inicio
      )}</h5>
                                    <p class="card-text">${
                                      pentest.descripcion
                                    }</p>
                                </div>
                                <div class="col-md-2 d-flex justify-content-end align-items-center">
                                    <button type="button" class="btn btn-primary btn_eval d-flex align-items-center btn-pen${
                                      pentest.id
                                    }">Acciones</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
      if (pentest.status == 1) $(".tarjetasPentest").append(card);
      else if (pentest.status == 4) $(".tarjetasPynt").append(card);
      $(`.btn-pen${pentest.id}`).click(function () {
        accionesPentest(pentest, "Pentest");
      });
    }
  }
}

function fetchPentestData() {
  fetch("./api/getDatosFormulario", {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        console.error("Error en la API:", data.message);
      } else {
        const pentestData = data.data;
        if (pentestData.length > 0) {
          renderPentestData(pentestData);
        } else {
          const container = document.getElementById("pentestContainer");
          container.innerHTML =
            '<div class="alert alert-info" role="alert">De momento no hay más solicitudes...</div>';
        }
      }
    })
    .catch((error) => {
      console.error(
        "Error al obtener los datos del formulario de pentest:",
        error
      );
    });
}

function renderPentestData(pentestData) {
  const container = document.getElementById("pentestContainer");

  pentestData.forEach((item) => {
    const card = document.createElement("div");
    card.className = "card mb-4";

    const cardBody = document.createElement("div");
    cardBody.className =
      "card-body d-flex justify-content-between align-items-center";

    const cardContent = document.createElement("div");

    const cardTitle = document.createElement("h5");
    cardTitle.className = "card-title";
    cardTitle.textContent = `Nueva solicitud de ${item.usuario}`;

    const cardText = document.createElement("p");
    cardText.className = "card-text";

    const nombreServicio = sanitizeServiceName(item.nombre_servicio);

    cardText.innerHTML = `
          <strong>Nombre del servicio:</strong> ${nombreServicio}<br>
          <strong>Requiere informe:</strong> ${
            item.req_informe ? "Sí" : "No"
          }<br>
          <strong>Fecha de inicio:</strong> ${item.fecha_inicio}<br>
          <strong>Fecha de fin:</strong> ${item.fecha_fin}
      `;

    const detailsButton = document.createElement("button");
    detailsButton.className = "btn btn-primary mt-2";
    detailsButton.textContent = "Ver detalles";
    detailsButton.setAttribute("data-bs-toggle", "#modal");
    detailsButton.setAttribute("data-bs-target", "#detalleModal");
    detailsButton.addEventListener("click", () => showDetails(item));

    const buttonContainer = document.createElement("div");
    buttonContainer.className = "d-flex justify-content-end";

    const acceptButton = document.createElement("button");
    acceptButton.className = "btn btn-success ml-2";
    acceptButton.textContent = "Aceptar";
    acceptButton.style.marginRight = "10px";
    acceptButton.addEventListener("click", () =>
      handleAccept(acceptButton, item)
    );

    const rejectButton = document.createElement("button");
    rejectButton.className = "btn btn-danger";
    rejectButton.textContent = "Rechazar";
    rejectButton.addEventListener("click", () => handleReject(item, card));

    cardContent.appendChild(cardTitle);
    cardContent.appendChild(cardText);
    cardContent.appendChild(detailsButton);

    buttonContainer.appendChild(acceptButton);
    buttonContainer.appendChild(rejectButton);

    cardBody.appendChild(cardContent);
    cardBody.appendChild(buttonContainer);
    card.appendChild(cardBody);
    container.appendChild(card);
  });

  checkEmptyContainer();
}

function sanitizeServiceName(text) {
  if (!text || text === "null") {
    return "";
  }

  const dangerousEntities = ["&lt;", "&gt;", "&quot;", "&amp;", "&#39;"];

  const match = text.match(/^\[&quot;(.*?)&quot;\]$/);
  const processedText = match ? match[1] : text;

  const textArea = document.createElement("textarea");
  textArea.innerHTML = processedText;
  let sanitizedText = textArea.value.replace(
    /&(?:[a-zA-Z]+|#\d+|#x[\da-fA-F]+);/g,
    (entity) => {
      return dangerousEntities.includes(entity)
        ? entity
        : ((document.createElement("textarea").innerHTML = entity),
          document.createElement("textarea").value);
    }
  );

  sanitizedText = sanitizedText.replace(/","/g, " / ").replace(/"/g, "");

  return sanitizedText;
}

function showDetails(item) {
  const nombreServicio = sanitizeServiceName(item.nombre_servicio);
  const versionServicio = sanitizeServiceName(item.version_servicio);
  const documentacion = sanitizeServiceName(item.documentacion);

  let modalBody = `
      <table class="table">
          <tr>
              <th class="text-start">Nombre del producto:</th>
              <td class="text-start">${item.nombre_producto}</td>
          </tr>
          <tr>
              <th class="text-start">Nombre del servicio:</th>
              <td class="text-start">${nombreServicio}</td>
          </tr>
          <tr>
              <th class="text-start">Versión del servicio:</th>
              <td class="text-start">${versionServicio}</td>
          </tr>
          <tr>
              <th class="text-start">Tipo de pentest:</th>
              <td class="text-start">${item.tipo_pentest}</td>
          </tr>
          <tr>
              <th class="text-start">Tipo de entorno:</th>
              <td class="text-start">${item.tipo_entorno}</td>
          </tr>
          <tr>
              <th class="text-start">Fecha de solicitud:</th>
              <td class="text-start">${item.fecha_solicitud}</td>
          </tr>
          <tr>
              <th class="text-start">Fecha de inicio:</th>
              <td class="text-start">${item.fecha_inicio}</td>
          </tr>
          <tr>
              <th class="text-start">Fecha de fin:</th>
              <td class="text-start">${item.fecha_fin}</td>
          </tr>
          <tr>
              <th class="text-start">Requiere informe:</th>
              <td class="text-start">${item.req_informe ? "Sí" : "No"}</td>
          </tr>
          <tr>
              <th class="text-start">Franja horaria:</th>
              <td class="text-start">${item.franja_horaria}</td>
          </tr>
          <tr>
              <th class="text-start">Horas de pentest:</th>
              <td class="text-start">${item.horas_pentest}</td>
          </tr>
          <tr>
              <th class="text-start">Proyecto Jira:</th>
              <td class="text-start">${item.proyecto_jira}</td>
          </tr>
          <tr>
              <th class="text-start">Responsable para Issues:</th>
              <td class="text-start">${item.resp_pentest}</td>
          </tr>
          <tr>
              <th class="text-start">Correo de soporte:</th>
              <td class="text-start">${item.persona_soporte}</td>
          </tr>
          <tr>
              <th class="text-start">Documentación:</th>
              <td class="text-start">${documentacion}</td>
          </tr>
          <tr>
              <th class="text-start">Servicio a pentestear:</th>
              <td class="text-start">${nombreServicio}</td>
          </tr>
      </table>
  `;

  showModalWindow(
    "Información de la solicitud",
    modalBody,
    null,
    "Cerrar",
    null,
    null,
    null,
    "modal-lm"
  );
}

function handleAccept(acceptButton, item) {
  const allActionButtons = document.querySelectorAll(
    ".btn-success, .btn-danger"
  );
  allActionButtons.forEach((button) => {
    button.disabled = true;
    button.classList.add("disabled");
  });

  acceptButton.innerHTML = `<span class="loading-spinner"></span> Procesando...`;

  const nombreServicio = sanitizeServiceName(item.nombre_servicio);
  const activoID = item.id_activo;

  const pentestParams = {
    solicitud_id: item.id,
    Nombre: `Pentest de ${nombreServicio}`,
    Responsable: item.resp_pentest,
    AreaServ: item.proyecto_jira,
    mail_soporte: item.persona_soporte,
    Descripcion: `Pentest a ${nombreServicio}`,
    Fecha_inicio: item.fecha_inicio,
    Fecha_final: item.fecha_fin,
    Producto: activoID,
  };

  fetch("./api/aceptarSolicitudPentest", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: item.id }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        console.error("Error al aceptar la solicitud:", data.message);
      } else {
        fetch("./api/crearPentest", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(pentestParams),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.error) {
              console.error("Error al crear el pentest activo:", data.message);
            } else {
              const startDate = new Date(item.fecha_inicio);
              const today = new Date();
              const eightDaysBefore = new Date(today);
              eightDaysBefore.setDate(today.getDate() + 8);

              const newEvent = {
                id: item.id,
                title: `Pentest de ${nombreServicio}`,
                start: item.fecha_inicio,
                end: item.fecha_fin,
              };

              if (startDate <= eightDaysBefore) {
                $("#calendar").fullCalendar("renderEvent", newEvent);
              } else {
                const existingEvents = Array.from(
                  document.querySelectorAll(
                    "#upcomingEventsList .list-group-item"
                  )
                )
                  .map((li) => ({
                    id: li.dataset.id,
                    title: li.textContent.split(" - ")[0],
                    startDate: li.textContent.split(" - ")[1],
                  }))
                  .filter((event) => event.title !== "No hay eventos próximos");

                updateUpcomingEventsList([
                  ...existingEvents,
                  {
                    id: item.id,
                    title: `Pentest de ${nombreServicio}`,
                    startDate: item.fecha_inicio,
                  },
                ]);
              }

              const successMessage = document.createElement("div");
              successMessage.className = "alert alert-success";
              successMessage.role = "alert";
              successMessage.innerHTML = "Solicitud aceptada";
              document
                .getElementById("pentestContainer")
                .appendChild(successMessage);

              setTimeout(() => {
                successMessage.remove();
              }, 3000);

              $("#pentestTabla").bootgrid("clear");
              $(".tarjetasPentest").empty();
              rellenarTablaYTarjetas();

              const cardElement = acceptButton.closest(".card");
              if (cardElement) {
                cardElement.remove();
                checkEmptyContainer();
              }
            }
          })
          .catch((error) => {
            console.error("Error al crear el pentest activo:", error);
          })
          .finally(() => {
            allActionButtons.forEach((button) => {
              button.disabled = false;
              button.classList.remove("disabled");
            });
          });
      }
    })
    .catch((error) => {
      console.error("Error al guardar la solicitud:", error);
    });
}

function handleReject(item, cardElement) {
  fetch("./api/rechazarSolicitudPentest", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: item.id }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        console.error("Error al rechazar la solicitud:", data.message);
      } else {
        cardElement.remove();
        checkEmptyContainer();
      }
    })
    .catch((error) => {
      console.error("Error en la petición para eliminar la solicitud:", error);
    })
    .finally(() => {
      const allActionButtons = document.querySelectorAll(
        ".btn-success, .btn-danger"
      );
      allActionButtons.forEach((button) => {
        button.disabled = false;
        button.classList.remove("disabled");
      });
    });
}

function checkEmptyContainer() {
  const container = document.getElementById("pentestContainer");
  if (container && !container.querySelector(".card")) {
    container.innerHTML =
      '<div class="alert alert-info" role="alert">De momento no hay más solicitudes...</div>';
  }
}

function updateUpcomingEventsList(events) {
  const eventListContainer = document.getElementById("upcomingEventsList");

  eventListContainer.innerHTML = "";

  if (events.length === 0) {
    const noEventsItem = document.createElement("li");
    noEventsItem.className = "list-group-item";
    noEventsItem.textContent = "No hay eventos próximos.";
    eventListContainer.appendChild(noEventsItem);
    return;
  }

  const maxVisible = 8;

  events.slice(0, maxVisible).forEach((event) => {
    if (event?.title && event?.startDate) {
      const eventItem = document.createElement("li");
      eventItem.className = "list-group-item";
      eventItem.dataset.id = event.id;
      eventItem.textContent = `${event.title} - ${event.startDate}`;
      eventListContainer.appendChild(eventItem);
    }
  });

  if (events.length > maxVisible) {
    const viewMoreButton = document.createElement("button");
    viewMoreButton.className = "btn btn-link";
    viewMoreButton.textContent = "Ver más";
    viewMoreButton.style.display = "block";
    viewMoreButton.style.marginTop = "10px";

    viewMoreButton.addEventListener("click", () => {
      events.slice(maxVisible).forEach((event) => {
        if (event?.title && event?.startDate) {
          const eventItem = document.createElement("li");
          eventItem.className = "list-group-item";
          eventItem.dataset.id = event.id;
          eventItem.textContent = `${event.title} - ${event.startDate}`;
          eventListContainer.appendChild(eventItem);
        }
      });

      viewMoreButton.style.display = "none";
    });

    eventListContainer.appendChild(viewMoreButton);
  }
}

function initializeCalendar() {
  let calendarEl = $("#calendar");
  if (calendarEl.length) {
    loadInitialEvents(function (events) {
      const calendarEvents = events.calendarEvents;
      const upcomingEvents = events.upcomingEvents;

      $("#calendar").fullCalendar({
        header: {
          left: "prev,next today",
          center: "title",
          right: "month,agendaWeek,agendaDay",
        },
        locale: "es",
        firstDay: 1,
        defaultView: "month",
        editable: true,
        events: calendarEvents,
      });

      updateUpcomingEventsList(upcomingEvents);
    });
  } else {
    console.error("Contenedor de calendario no encontrado");
  }
}

function loadInitialEvents(callback) {
  fetch("./api/getEvents")
    .then((response) => response.json())
    .then((events) => {
      const today = new Date();
      const eightDaysBefore = new Date(today);
      eightDaysBefore.setDate(today.getDate() + 8);

      const eventsInCalendar = [];
      const upcomingEvents = [];

      events.forEach((event) => {
        const startDate = new Date(event.fecha_inicio);
        const endDate = new Date(event.fecha_final);
        endDate.setDate(endDate.getDate() + 1);

        const formattedEvent = {
          id: event.id,
          title: sanitizeServiceName(event.nombre),
          start: event.fecha_inicio,
          end: endDate.toISOString().split("T")[0],
        };

        if (startDate <= eightDaysBefore) {
          eventsInCalendar.push(formattedEvent);
        } else {
          upcomingEvents.push({
            id: event.id,
            title: sanitizeServiceName(event.nombre),
            startDate: event.fecha_inicio,
          });
        }
      });

      callback({
        calendarEvents: eventsInCalendar,
        upcomingEvents: upcomingEvents,
      });
    })
    .catch((error) => {
      console.error("Error al cargar los eventos:", error);
    });
}

$(document).ready(initializeCalendar);

function handlePentestReject(item) {
  showModalWindow(
    "INFO",
    "¿Seguro que quiere rechazar el pentest?",
    () => {
      const aceptarButton = document.querySelector(
        ".modal-footer .btn-primary"
      );
      if (aceptarButton) aceptarButton.disabled = true;

      rechazarPentest(item);
    },
    null,
    "Aceptar"
  );
}

function rechazarPentest(item) {
  fetch("./api/rechazarSolicitudPentest", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ id: item.solicitud_id }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        showModalWindow(
          "Error",
          `Error al rechazar la solicitud: ${data.message}`,
          null,
          "Cerrar",
          null
        );
      } else {
        cerrarModal();
        eliminarPentest(item);
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
        initializeCalendar();
      }
    })
    .catch((error) => {
      showModalWindow(
        "Error",
        "Error en la petición para rechazar la solicitud. Inténtelo de nuevo.",
        null,
        "Cerrar",
        null
      );
    });
}

function eliminarPentest(item) {
  fetch(`/api/eliminarPentest?id=${item.id}`, {
    method: "GET",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        showModalWindow(
          "Error",
          `Error al eliminar el pentest: ${data.message}`,
          null,
          "Cerrar",
          null
        );
      } else {
        $("#calendar").fullCalendar("removeEvents", item.solicitud_id);
      }
    })
    .catch((error) => {
      showModalWindow(
        "Error",
        "Error en la petición para eliminar el pentest. Inténtelo nuevamente.",
        null,
        "Cerrar",
        null
      );
    });
}

function asignarPentester(item) {
  fetch(`./api/asignPentester`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      id: item.id,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (!data.status) {
        showModalWindow(
          "Error al Asignar Pentester",
          data.message,
          null,
          "Cerrar",
          null
        );
      } else {
        showModalWindow(
          "Pentester Asignado Correctamente",
          "El pentest ha sido asignado exitosamente.",
          null,
          "Cerrar",
          null
        );
        $("#pentestTabla").bootgrid("clear");
        $(".tarjetasPentest").empty();
        rellenarTablaYTarjetas();
      }
    })
    .catch((error) => {
      showModalWindow(
        "Error en la Asignación",
        "Ocurrió un error inesperado al intentar asignar el pentester. Inténtelo nuevamente.",
        null,
        "Cerrar",
        null
      );
    });
}

function fetchAndPopulateEmails() {
  fetch("./api/getEmails?endpointName=/evs")
    .then((response) => {
      if (!response.ok) {
        throw new Error(
          `Error al obtener los correos. Status: ${response.status}`
        );
      }
      return response.json();
    })
    .then((emails) => {
      const mailSelect = document.getElementById("mailList");
      if (!mailSelect) {
        console.warn("No se encontró el select #mailList en el DOM.");
        return;
      }

      emails.forEach((email) => {
        const option = document.createElement("option");
        option.value = email;
        option.textContent = email;
        mailSelect.appendChild(option);
      });

      mailSelect.addEventListener("change", () => {
        const selectedEmail = mailSelect.value;
        if (selectedEmail && selectedEmail !== "Ninguno") {
          addEmailField(selectedEmail);
          mailSelect.value = "Ninguno";
        }
      });
    })
    .catch((error) => {
      console.error("Error al cargar los correos:", error);
    });
}

function addEmailField(email) {
  const emailListContainer = document.getElementById("emailListContainer");
  const warningMessage = document.getElementById("emailWarning");

  const existingEmails = Array.from(
    emailListContainer.querySelectorAll("input[type='text']")
  ).map((input) => input.value);

  if (existingEmails.includes(email)) {
    if (warningMessage) {
      warningMessage.textContent = `El correo "${email}" ya ha sido seleccionado.`;
      warningMessage.style.display = "block";
    }
    return;
  }

  if (warningMessage) {
    warningMessage.style.display = "none";
  }

  const emailField = document.createElement("div");
  emailField.classList.add("email-field", "row", "mb-2");

  const col11Div = document.createElement("div");
  col11Div.classList.add("col-11", "d-flex", "align-items-center");
  const input = document.createElement("input");
  input.type = "text";
  input.classList.add("form-control");
  input.value = email;
  input.readOnly = true;
  col11Div.appendChild(input);

  const col1Div = document.createElement("div");
  col1Div.classList.add(
    "col-1",
    "d-flex",
    "justify-content-center",
    "align-items-center"
  );
  const removeBtn = document.createElement("button");
  removeBtn.type = "button";
  removeBtn.classList.add("remove-email-btn");
  removeBtn.title = "Eliminar correo";
  removeBtn.addEventListener("click", () => {
    emailListContainer.removeChild(emailField);
  });
  col1Div.appendChild(removeBtn);

  emailField.appendChild(col11Div);
  emailField.appendChild(col1Div);
  emailListContainer.appendChild(emailField);
}

function generarDocumento(item) {
  if (!item.id) {
    showModalWindow(
      "Error",
      "No se ha podido generar el documento. Inténtelo nuevamente.",
      null,
      "Cerrar",
      null
    );
    return;
  }

  let observacionesValor = "";

  if (
    item.observaciones &&
    Array.isArray(item.observaciones) &&
    item.observaciones.length > 0 &&
    typeof item.observaciones[0] === "object" &&
    "comentarios" in item.observaciones[0]
  ) {
    observacionesValor = item.observaciones[0].comentarios;
  } else if (
    item.observaciones &&
    typeof item.observaciones === "object" &&
    "comentarios" in item.observaciones
  ) {
    observacionesValor = item.observaciones.comentarios;
  }

  let modalContent = `
    <div class="form-group mb-4 row">
      <label for="observaciones" class="col-4 col-form-label">Observaciones adicionales</label>
      <div class="col-8">
        <textarea
          id="observaciones"
          name="Observaciones"
          cols="40"
          rows="5"
          class="form-control"
          aria-describedby="textareaHelpBlock"
        >${observacionesValor || ""}</textarea>
      </div>
    </div>
  `;

  if (item.informe_enviado !== 1 && item.solicitud_id) {
    modalContent += `
      <div class="form-group mb-4 row">
        <label for="mailList" class="col-4 col-form-label" id="mailListLabel" style="cursor: pointer;">
          Correos a poner en copia
        </label>
        <div class="col-8 position-relative">
          <select id="mailList" name="emails" class="form-select-custom">
            <option value="Ninguno" selected>(Selecciona un correo)</option>
          </select>
          <span id="tooltip" style="display: none; position: absolute; background: #333; color: #fff; padding: 5px; border-radius: 4px; font-size: 12px; top: 45px; left: 0; z-index: 1000;">
            A los correos seleccionados se les enviará un mail compartiendo el informe.
          </span>
        </div>
      </div>
      <div id="emailListContainer" class="mb-4"></div>
      <div id="emailWarning" class="text-danger" style="display: none; font-size: 0.9rem;">
      </div>
    `;
  }

  showModalWindow(
    "Observaciones",
    modalContent,
    function () {
      const observaciones = document.getElementById("observaciones").value;
      let selectedEmails = [];

      if (item.informe_enviado !== 1) {
        const emailFields = document.querySelectorAll(
          "#emailListContainer .email-field input"
        );
        selectedEmails = Array.from(emailFields).map((input) => input.value);
      }

      mostrarLoading();

      const obsParam = observaciones
        ? `&observaciones=${encodeURIComponent(observaciones)}`
        : "";

      const emailsParam =
        selectedEmails.length > 0
          ? `&emails=${encodeURIComponent(selectedEmails.join(","))}`
          : "";

      const urlDocumento = `/api/generarDocumentoPentest?id=${item.id}${obsParam}${emailsParam}`;

      fetch(urlDocumento, {
        method: "GET",
        credentials: "include",
      })
        .then((response) => {
          if (!response.ok) {
            return response.json().then((err) => {
              throw new Error(
                err.message ||
                  `Error al generar el documento. Status: ${response.status}`
              );
            });
          }

          const contentDisposition = response.headers.get(
            "Content-Disposition"
          );
          if (!contentDisposition) {
            throw new Error(
              "No se encontró el encabezado Content-Disposition."
            );
          }

          const fileName = contentDisposition.substring(
            contentDisposition.indexOf("filename=") + 9
          );

          return response.blob().then((blob) => ({ blob, fileName }));
        })
        .then(({ blob, fileName }) => {
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", fileName);
          document.body.appendChild(link);
          link.click();
          link.parentNode.removeChild(link);

          cerrarModal();
        })
        .catch((error) => {
          showModalWindow("ERROR", error.message, null, "Cerrar", null);
        });
    },
    "Cerrar",
    "Aceptar",
    null
  );

  if (item.informe_enviado !== 1) {
    fetchAndPopulateEmails();

    const mailListLabel = document.getElementById("mailListLabel");
    const tooltip = document.getElementById("tooltip");

    if (mailListLabel && tooltip) {
      mailListLabel.addEventListener("mouseover", () => {
        tooltip.style.display = "block";
      });

      mailListLabel.addEventListener("mouseout", () => {
        tooltip.style.display = "none";
      });
    }
  }
}

document.addEventListener("DOMContentLoaded", function () {
  moduloSDLC();
  configBotonesSDLC();
  fetchPentestData();
});

let currentApp = "Kiuwan";

function configBotonesSDLC() {
  $(".contKiuwan").click(function (e) {
    $(".contKiuwan").addClass("sombraRegistroKiuwan");
    $(".contSonarqube").removeClass("sombraRegistroSonar");
  });
  $(".contSonarqube").click(function (e) {
    $(".contKiuwan").removeClass("sombraRegistroKiuwan");
    $(".contSonarqube").addClass("sombraRegistroSonar");
  });
}

function modificarApp() {
  let SDLCform = document.getElementById("form-editAPP");
  let botonAceptar = document.getElementById("botonAceptar");

  if (!botonAceptar.classList.contains("btn-desactivado")) {
    let id = document.querySelector(".appID").textContent;
    botonAceptar.classList.add("btn-desactivado");
    mostrarLoading();

    const formData = new URLSearchParams(new FormData(SDLCform));

    fetch(`./api/modificarAppSDLC?id=${id}`, {
      method: "POST",
      credentials: "include",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        location.reload();
      })
      .catch((error) => {
        console.error("Error al modificar la aplicación:", error);
        botonAceptar.classList.remove("btn-desactivado");
      });
  }
}

function crearAppSDLC() {
  let SDLCform = document.getElementById("form-newAPP");
  let botonAceptar = document.getElementById("botonAceptar");

  const direccionElement = document.getElementById("direccionInput");
  const areaElement = document.getElementById("areaInput");
  const productoElement = document.getElementById("productoInput");
  const appElement = document.getElementById("appSelect");

  const kiuwanSlotsElement = document.getElementById("kiuwanSlotsInput");
  const fechaAnalisisKiuwanElement = document.getElementById("fecha");

  const sonarqubeSlotElement = document.getElementById("sonarqubeSlot");
  const fechaAnalisisSonarElement = document.getElementById("fechaSonar");
  const urlSonarElement = document.getElementById("urlSonar");

  if (!botonAceptar.classList.contains("btn-desactivado")) {
    botonAceptar.classList.add("btn-desactivado");

    const formData = new URLSearchParams(new FormData(SDLCform));

    formData.append("direccion_id", direccionElement.value);
    formData.append("area_id", areaElement.value);
    formData.append("producto_id", productoElement.value);
    formData.append("app", appElement.value);

    if (appElement.value === "Kiuwan") {
      formData.append("kiuwan_id", kiuwanSlotsElement.value);
      formData.append(
        "fecha_analisis_kiuwan",
        fechaAnalisisKiuwanElement.value
      );
    } else if (appElement.value === "Sonarqube") {
      formData.append("sonarqube_slot", sonarqubeSlotElement.value);
      formData.append("fecha_analisis_sonar", fechaAnalisisSonarElement.value);
      formData.append("url_sonar", urlSonarElement.value);
    }

    fetch("./api/crearAppSDLC", {
      method: "POST",
      credentials: "include",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Error en la red");
        }
        return response.json();
      })
      .then((retorno) => {
        if (retorno.Error == "No") {
          mostrarLoading();
          if (retorno.Created == "Yes") {
            let issuesArray = [
              {
                id: retorno.id,
                Direccion: retorno.Direccion,
                Area: retorno.Area,
                Producto: retorno.Producto,
                kiuwan_id: retorno.kiuwan_id,
                slot_sonarqube: retorno.slot_sonarqube,
                CMM: retorno.CMM,
                Exposicion: retorno.Exposicion,
                Analisis: retorno.Analisis,
                Comentarios: retorno.Comentarios,
                url_sonar: retorno.url_sonar || "",
                fechaAnalisis:
                  retorno.fecha_analisis_kiuwan || retorno.fecha_analisis_sonar,
              },
            ];
            appendIssuesArray(issuesArray);
            cerrarModal();

            if (retorno.app === "Kiuwan") {
              let totalKiuwan = parseInt($(".textKiuwanTotal").text()) + 1;
              $(".textKiuwanTotal").text(totalKiuwan);
            } else if (retorno.app === "Sonarqube") {
              let totalSonarqube =
                parseInt($(".textSonarqubeTotal").text()) + 1;
              $(".textSonarqubeTotal").text(totalSonarqube);
            }

            location.reload();
          } else {
            location.reload();
          }
        } else {
          botonAceptar.classList.remove("btn-desactivado");
        }
      })
      .catch((error) => {
        console.error("Error en la solicitud:", error);
        botonAceptar.classList.remove("btn-desactivado");
      });
  }
}

function obtenerOrganizaciones() {
  fetch("./api/getOrganizaciones", {
    method: "GET",
    credentials: "include",
  })
    .then((response) => response.json())
    .then((retorno) => {
      let organizacionId = "27384";
      handleOrganizacionChange(organizacionId);
    })
    .catch((error) => {
      console.error("Error al cargar las organizaciones:", error);
    });
}

async function insertarOpciones(padre, destino, tipo) {
  try {
    const data = await getHijosTipo(padre.value, null, tipo);
    destino.innerHTML = '<option value="Ninguno">Ninguno</option>';
    data["Hijos"].forEach((hijo) => {
      let option = document.createElement("option");
      option.value = hijo.id;
      option.textContent = hijo.nombre;
      destino.appendChild(option);
    });
  } catch (error) {
    destino.innerHTML = '<option value="Ninguno">Ninguno</option>';
    console.error("Error al cargar las opciones:", error);
  }
}

function configDesplegablesSDLC() {
  document.querySelectorAll(".direccionInput").forEach((input) => {
    input.addEventListener("change", function () {
      document
        .querySelectorAll(".areaBloque, .productoBloque")
        .forEach((bloque) => bloque.classList.add("mshide"));
      document
        .querySelectorAll(".areaInput, .productoInput")
        .forEach((input) => (input.value = "Ninguno"));
      if (this.value != "Ninguno") {
        document
          .querySelectorAll(".areaBloque")
          .forEach((bloque) => bloque.classList.remove("mshide"));
        document
          .querySelectorAll(".areaInput")
          .forEach((areaInput) => insertarOpciones(this, areaInput, "Área"));
      }
    });
  });

  document.querySelectorAll(".areaInput").forEach((input) => {
    input.addEventListener("change", function () {
      document
        .querySelectorAll(".productoBloque")
        .forEach((bloque) => bloque.classList.add("mshide"));
      document
        .querySelectorAll(".productoInput")
        .forEach((input) => (input.value = "Ninguno"));
      if (this.value != "Ninguno") {
        document
          .querySelectorAll(".productoBloque")
          .forEach((bloque) => bloque.classList.remove("mshide"));
        document
          .querySelectorAll(".productoInput")
          .forEach((productoInput) =>
            insertarOpciones(this, productoInput, "Producto")
          );
      }
    });
  });
}

function handleOrganizacionChange(organizacionId) {
  $("#direccionInput")
    .empty()
    .append('<option value="Ninguno">Ninguno</option>');
  $("#areaInput").empty().append('<option value="Ninguno">Ninguno</option>');
  $("#productoInput")
    .empty()
    .append('<option value="Ninguno">Ninguno</option>');
  $("#sistemaInput").empty().append('<option value="Ninguno">Ninguno</option>');

  if (organizacionId != "Ninguno") {
    fetch(`./api/getDirecciones?organizacionId=${organizacionId}`, {
      method: "GET",
      credentials: "include",
    })
      .then((response) => response.json())
      .then((retorno) => {
        if (retorno.Direcciones && retorno.Direcciones.length > 0) {
          let direccionSelect = $("#direccionInput");
          direccionSelect
            .empty()
            .append('<option value="Ninguno">Ninguno</option>');
          retorno.Direcciones.forEach((direccion) => {
            let option = `<option value="${direccion.id}" data-nombre="${direccion.nombre}">${direccion.nombre}</option>`;
            direccionSelect.append(option);
          });
          $("#direccion").removeClass("mshide");
        }
      })
      .catch((error) => {
        console.error("Error al cargar las direcciones:", error);
      });
  }
}

function cargarTablaSDLC() {
  let kiuwanClick = false;
  let sonarqubeClick = false;
  if (
    (!kiuwanClick && currentApp == "Kiuwan") ||
    (!sonarqubeClick && currentApp == "Sonarqube")
  ) {
    const apiUrl = `./api/obtenerSDLC?app=${currentApp}`;
    fetch(apiUrl, {
      method: "GET",
      credentials: "include",
    })
      .then((response) => response.json())
      .then((retorno) => {
        let totalKiuwan = 0;
        let totalSonarqube = 0;

        let tableSelector =
          currentApp === "Kiuwan" ? "#sdlcTablaKiuwan" : "#sdlcTablaSonarqube";

        $(tableSelector).bootgrid({
          formatters: {
            commands: function (column, row) {
              return `
              <button type='button' class='btn btn-xs btn-default command-edit' data-row-id='${row.id}'>
                <img class='icono' src='./img/edit.svg' />
              </button>
              <button type='button' class='btn btn-xs btn-default command-delete' data-row-id='${row.id}'>
                <img class='icono' src='./img/delete.svg' />
              </button>`;
            },
          },
        });

        $(tableSelector).bootgrid("clear");

        retorno.forEach((aplicacion) => {
          if (aplicacion.analisis == 0) aplicacion.analisis = "No";
          else aplicacion.analisis = "Si";

          if (aplicacion.criticidad == 0) aplicacion.criticidad = "No";
          else aplicacion.criticidad = "Si";

          if (aplicacion.exposicion == 0) aplicacion.exposicion = "No";
          else aplicacion.exposicion = "Si";

          verifyKPM(
            aplicacion.kiuwan_slot,
            aplicacion.fecha_analisis_kiuwan,
            aplicacion.cumple_kpm
          );
          verifySonarKpm(
            aplicacion.slot_sonarqube,
            aplicacion.fecha_analisis_sonar,
            aplicacion.cumple_kpm_sonar
          );

          if (aplicacion.app && aplicacion.app.toLowerCase() === "kiuwan") {
            totalKiuwan++;
          } else if (
            aplicacion.app &&
            aplicacion.app.toLowerCase() === "sonarqube"
          ) {
            totalSonarqube++;
          }

          let cumpleLabel = "";
          if (aplicacion.app === "Kiuwan") {
            if (aplicacion.cumple_kpm === 1 || aplicacion.cumple_kpm === "1") {
              cumpleLabel =
                "<label class='rounded-pill estado cerrado'>Cumple</label>";
            } else {
              cumpleLabel =
                "<label class='rounded-pill estado abierto'>No Cumple</label>";
            }
          } else if (aplicacion.app === "Sonarqube") {
            if (
              aplicacion.cumple_kpm_sonar === 1 ||
              aplicacion.cumple_kpm_sonar === "1"
            ) {
              cumpleLabel =
                "<label class='rounded-pill estado cerrado'>Cumple</label>";
            } else {
              cumpleLabel =
                "<label class='rounded-pill estado abierto'>No Cumple</label>";
            }
          }

          let cmmColor;
          if (aplicacion.CMM >= 3) {
            cmmColor = "rounded-pill numberEstado cerrado";
          } else {
            cmmColor = "rounded-pill numberEstado abierto";
          }

          let comentariosSanitizados = sanitizeServiceName(
            aplicacion.comentarios
          );

          let issuesArray = [
            {
              id: aplicacion.id.toString(),
              Direccion: aplicacion.direccion,
              Area: aplicacion.area,
              Producto: aplicacion.producto,
              slot_kiuwan: aplicacion.kiuwan_slot,
              slot_sonarqube: aplicacion.slot_sonarqube,
              Criticidad: aplicacion.criticidad,
              Exposicion: aplicacion.exposicion,
              Analisis: aplicacion.analisis,
              CMM: `<label class='${cmmColor}'>${aplicacion.CMM}</label>`,
              Comentarios: comentariosSanitizados,
              url_sonar: aplicacion.url_sonar
                ? `<a href="${aplicacion.url_sonar}" target="_blank">${aplicacion.url_sonar}</a>`
                : "",
              fechaAnalisis: aplicacion.fecha_analisis_kiuwan,
              fechaAnalisisSonar: aplicacion.fecha_analisis_sonar,
              cumpleKPM07: cumpleLabel,
            },
          ];
          appendIssuesArray(issuesArray);
        });

        if (currentApp === "Kiuwan") {
          $(".textKiuwanTotal").text(totalKiuwan);
        } else {
          $(".textSonarqube").text(totalSonarqube);
        }

        $(tableSelector).on("loaded.rs.jquery.bootgrid", function () {
          $(tableSelector)
            .find(".command-edit")
            .on("click", function () {
              let rowId = $(this).data("row-id");
              fetch(`./api/obtenerSDLC?id=${rowId}`, {
                method: "GET",
                credentials: "include",
              })
                .then((response) => response.json())
                .then((retorno) => {
                  let isKiuwan = retorno.app === "Kiuwan";
                  let isSonarqube = retorno.app === "Sonarqube";

                  let formAPP = `
                <form class="issueForm" id="form-editAPP">
                  <div class="form-group row mshide">
                    <label for="select6" class="col-4 col-form-label label appID">${
                      retorno.id
                    }</label>
                  </div>
                  <div class="form-group row">
                    <label for="select6" class="col-4 col-form-label label">Producto:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.producto
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="select6" class="col-4 col-form-label label">Dirección:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.direccion
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="select6" class="col-4 col-form-label label">Área:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.area
                      }</label>
                    </div>
                  </div>
                  
                  <div class="form-group row ${
                    isKiuwan ? "" : "mshide"
                  }" id="kiuwanSlotBloque">
                    <label for="appKiuwan" class="col-4 col-form-label label">Slot en Kiuwan:</label>
                    <div class="col-8">
                      <label for="appKiuwan" class="col-8 col-form-label label">${
                        retorno.kiuwan_slot || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isSonarqube ? "" : "mshide"
                  }" id="sonarSlotBloque">
                    <label for="appSonarQube" class="col-4 col-form-label label">Slot en Sonarqube:</label>
                    <div class="col-8">
                      <label for="appSonarQube" class="col-8 col-form-label label">${
                        retorno.slot_sonarqube || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isKiuwan ? "" : "mshide"
                  }" id="kiuwanFechaBloque">
                    <label for="fechaAnalisis" class="col-4 col-form-label label">Fecha de Análisis (Kiuwan):</label>
                    <div class="col-8">
                      <label for="fechaAnalisis" class="col-8 col-form-label label">${
                        retorno.fecha_analisis_kiuwan || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isSonarqube ? "" : "mshide"
                  }" id="sonarFechaBloque">
                    <label for="fechaSonar" class="col-4 col-form-label label">Fecha de Análisis en Sonarqube:</label>
                    <div class="col-8">
                      <label for="fechaSonar" class="col-8 col-form-label label">${
                        retorno.fecha_analisis_sonar || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group mb-4 row">
                    <label class="col-4">Análisis de código</label>
                    <div class="col-8">
                      <label class="col-form-label">${
                        retorno.analisis === 1 ? "Sí" : "No"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group mb-4 row">
                    <label class="col-4">Nueva Madurez</label>
                    <div class="col-8">
                      <select id="cmmSelect" name="CMM" class="form-select-custom" required="required">
                        <option value="5" ${
                          retorno.CMM == 5 ? "selected" : ""
                        }>5</option>
                        <option value="4" ${
                          retorno.CMM == 4 ? "selected" : ""
                        }>4</option>
                        <option value="3" ${
                          retorno.CMM == 3 ? "selected" : ""
                        }>3</option>
                        <option value="2" ${
                          retorno.CMM == 2 ? "selected" : ""
                        }>2</option>
                        <option value="1" ${
                          retorno.CMM == 1 ? "selected" : ""
                        }>1</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group mb-2 row">
                    <label for="textarea" class="col-4 col-form-label">Actualizar comentarios</label>
                    <div class="col-8">
                      <textarea id="textarea" name="Comentarios" cols="40" rows="5" class="form-control">${
                        retorno.comentarios
                      }</textarea>
                    </div>
                  </div>
                  <div class="form-group mb-2 row ${
                    isSonarqube ? "" : "mshide"
                  }" id="urlSonarBloque">
                    <label for="urlSonar" class="col-4 col-form-label">Nueva URL de SonarQube</label>
                    <div class="col-8">
                      <input type="url" id="urlSonar" name="url_sonar" class="form-control" value="${
                        retorno.url_sonar || ""
                      }" placeholder="https://sonarqube.example.com/project" />
                    </div>
                  </div>
                </form>
              `;

                  showModalWindow(
                    "Editar aplicación",
                    formAPP,
                    modificarApp,
                    "Cerrar",
                    "Aceptar",
                    null,
                    null,
                    "modal-lm"
                  );
                });
            });

          $(".command-delete").on("click", function () {
            let rowId = $(this).data("row-id");
            fetch(`./api/obtenerSDLC?id=${rowId}`, {
              method: "GET",
              credentials: "include",
            })
              .then((response) => response.json())
              .then((retorno) => {
                let isKiuwan = retorno.app === "Kiuwan";
                let isSonarqube = retorno.app === "Sonarqube";

                let form = `
                <h5><b>Estás a punto de eliminar para siempre: </b></h5>
                <form class="issueForm" id="form-delAPP">
                  <div class="form-group row">
                    <label for="select6" class="col-4 col-form-label label">Área:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.area
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row">
                    <label for="select6" class="col-4 col-form-label label">Producto:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.producto
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isKiuwan ? "" : "mshide"
                  }" id="kiuwanSlotBloque">
                    <label for="select6" class="col-4 col-form-label label">Slot en Kiuwan:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.kiuwan_slot || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isSonarqube ? "" : "mshide"
                  }" id="sonarSlotBloque">
                    <label for="select6" class="col-4 col-form-label label">Slot en Sonarqube:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.slot_sonarqube || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isKiuwan ? "" : "mshide"
                  }" id="kiuwanFechaBloque">
                    <label for="select6" class="col-4 col-form-label label">Fecha de Análisis (Kiuwan):</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.fecha_analisis_kiuwan || "N/A"
                      }</label>
                    </div>
                  </div>
                  <div class="form-group row ${
                    isSonarqube ? "" : "mshide"
                  }" id="sonarFechaBloque">
                    <label for="select6" class="col-4 col-form-label label">Fecha de Análisis en Sonarqube:</label>
                    <div class="col-8">
                      <label for="select6" class="col-8 col-form-label label">${
                        retorno.fecha_analisis_sonar || "N/A"
                      }</label>
                    </div>
                  </div>
                </form>
                <hr>
                <h5><b>¿Estás seguro?</b></h5>
                <br>
                <div class="row">
                  <div class="col-md-6">
                    <button class="btn btn-primary btnCancel">CANCELAR</button>
                  </div>
                  <div class="col-md-6">
                    <button class="btn btn-primary btnDel">ELIMINAR</button>
                  </div>
                </div>`;

                showModalWindow(
                  "¿Eliminar aplicación?",
                  form,
                  null,
                  null,
                  null
                );

                document
                  .querySelector(".btnCancel")
                  .addEventListener("click", function () {
                    cerrarModal();
                  });

                document
                  .querySelector(".btnDel")
                  .addEventListener("click", function () {
                    mostrarLoading();

                    let isKiuwan = retorno.app === "Kiuwan";
                    let apiUrl = `./api/eliminarAppSDLC?id=${rowId}&app=${retorno.app}`;

                    if (isKiuwan && retorno.kiuwan_id) {
                      apiUrl += `&kiuwan_id=${retorno.kiuwan_id}`;
                    }

                    fetch(apiUrl, {
                      method: "POST",
                      credentials: "include",
                    })
                      .then((response) => response.json())
                      .then((data) => {
                        location.reload();
                      })
                      .catch((error) => {
                        console.error(
                          "Error al eliminar la aplicación:",
                          error
                        );
                      });
                  });
              });
          });
        });
      });
  }
}

function verifyKPM(appName, fechaAnalisis, cumpleActual) {
  if (!appName || !fechaAnalisis) {
    return;
  }

  const hoy = new Date();
  const fechaAnalisisDate = new Date(fechaAnalisis);
  const oneYear = new Date(hoy.setFullYear(hoy.getFullYear() - 1));

  const cumple = fechaAnalisisDate >= oneYear ? 1 : 0;

  if (cumpleActual == null || cumple.toString() !== cumpleActual.toString()) {
    fetch("./api/updateCumpleKpm", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        app_name: appName,
        cumple_kpm: cumple.toString(),
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.error) {
          console.error(
            "Error en la actualización de cumple_kpm:",
            data.message
          );
        } else {
          console.log("Actualización de cumple_kpm:", data.message);
        }
      })
      .catch((error) => {
        console.error("Error al actualizar cumple_kpm:", error);
      });
  }
}

function verifySonarKpm(slot_sonarqube, fechaAnalisisSonar, cumpleActualSonar) {
  const hoy = new Date();
  const fechaAnalisisSonarDate = new Date(fechaAnalisisSonar);
  const oneYear = new Date(hoy.setFullYear(hoy.getFullYear() - 1));

  const cumple = fechaAnalisisSonarDate >= oneYear ? 1 : 0;

  if (
    cumpleActualSonar !== undefined &&
    cumple.toString() !== cumpleActualSonar.toString()
  ) {
    fetch("./api/updateSonarKPM", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        slot_sonarqube: slot_sonarqube,
        cumple_kpm_sonar: cumple.toString(),
      }),
    })
      .then((response) => response.json())
      .then((data) => {})
      .catch((error) => {
        console.error("Error al actualizar cumple_kpm_sonar:", error);
      });
  }
}

function appendIssuesArray(issuesArray) {
  let tableSelector =
    currentApp === "Kiuwan" ? "#sdlcTablaKiuwan" : "#sdlcTablaSonarqube";
  $(tableSelector).bootgrid("append", issuesArray);
}

function moduloSDLC() {
  document.querySelector(".btn-newAPP").addEventListener("click", function () {
    let formAPP = `
          <form class="issueForm" id="form-newAPP">
              <div class="form-group mb-4 row">
                  <label for="appType" class="col-4 col-form-label">Aplicación de análisis</label>
                  <div class="col-8">
                      <select id="appSelect" name="app" class="form-select-custom" required="required">
                          <option value="Kiuwan">Kiuwan</option>
                          <option value="Sonarqube">Sonarqube</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row">
                  <label for="direccionInput" class="col-4 col-form-label direccion-label">Dirección</label>
                  <div class="col-8">
                      <select id="direccionInput" name="Direccion" class="form-select-custom direccionInput" required="required">
                          <option value="Ninguno">Ninguno</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row areaBloque mshide" id="area">
                  <label for="areaInput" class="col-4 col-form-label area-label">Área</label>
                  <div class="col-8">
                      <select id="areaInput" name="Area" class="form-select-custom areaInput" required="required">
                          <option value="Ninguno">Ninguno</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row productoBloque mshide" id="producto">
                  <label for="productoInput" class="col-4 col-form-label producto-label">Producto</label>
                  <div class="col-8">
                      <select id="productoInput" name="Producto" class="form-select-custom productoInput" required="required">
                          <option value="Ninguno">Ninguno</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row kiuwanSlotsBloque mshide" id="kiuwanSlots">
                  <label for="kiuwanSlotsInput" class="col-4 col-form-label">Slots de Kiuwan</label>
                  <div class="col-8">
                      <select id="kiuwanSlotsInput" name="KiuwanSlots" class="form-select-custom" required="required">
                          <option value="Ninguno">Seleccione un slot</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row sonarqubeSlotBloque mshide">
                  <label for="sonarqubeSlot" class="col-4 col-form-label">Nombre del Slot de Sonarqube</label>
                  <div class="col-8">
                      <input type="text" id="sonarqubeSlot" name="sonarqube_slot" class="form-control" placeholder="Nombre del slot en Sonarqube" />
                  </div>
              </div>
              <!-- Campo CMM -->
              <div class="form-group mb-4 row">
                  <label for="cmmSelect" class="col-4 col-form-label">Nivel de madurez</label>
                  <div class="col-8">
                      <select id="cmmSelect" name="CMM" class="form-select-custom" required="required">
                          <option value="5">5</option>
                          <option value="4">4</option>
                          <option value="3">3</option>
                          <option value="2">2</option>
                          <option value="1">1</option>
                      </select>
                  </div>
              </div>
              <div class="form-group mb-4 row mshide">
                  <label class="col-4">Análisis de código</label>
                  <div class="col-8">
                      <div class="custom-control custom-radio d-inline-block">
                          <input name="Analisis" id="analisis_si" type="radio" class="custom-control-input" value="1" checked>
                          <label for="analisis_si" class="custom-control-label">Sí</label>
                      </div>
                      <div class="custom-control custom-radio d-inline-block">
                          <input name="Analisis" id="analisis_no" type="radio" class="custom-control-input" value="0">
                          <label for="analisis_no" class="custom-control-label">No</label>
                      </div>
                  </div>
              </div>
              <div class="form-group mb-4 row">
                  <label for="textarea" class="col-4 col-form-label">Comentarios</label>
                  <div class="col-8">
                      <textarea id="textarea" name="Comentarios" cols="40" rows="5" class="form-control" aria-describedby="textareaHelpBlock"></textarea>
                  </div>
              </div>
              <div class="form-group mb-4 row urlSonarBloque mshide">
                  <label for="urlSonar" class="col-4 col-form-label">URL SonarQube</label>
                  <div class="col-8">
                      <input type="url" id="urlSonar" name="url_sonar" class="form-control" placeholder="https://sonarqube.example.com/project" />
                  </div>
              </div>
              <div class="form-group mb-4 row fechaKiuwanBloque">
                  <label for="fecha" class="col-4 col-form-label">Fecha de Análisis</label>
                  <div class="col-8">
                      <input type="text" id="fecha" name="fecha_analisis_kiuwan" class="form-control" readonly="readonly" />
                  </div>
              </div>
              <div class="form-group mb-4 row sonarqubeFechaBloque mshide">
                  <label for="fechaSonar" class="col-4 col-form-label">Fecha de Análisis en Sonarqube</label>
                  <div class="col-8">
                      <input type="date" id="fechaSonar" name="fecha_analisis_sonarqube" class="form-control" />
                  </div>
              </div>
          </form>
      `;

    showModalWindow(
      "Registrar aplicación",
      formAPP,
      crearAppSDLC,
      "Cerrar",
      "Aceptar",
      null,
      null,
      "modal-lm"
    );

    obtenerOrganizaciones();
    configDesplegablesSDLC();

    document
      .getElementById("appSelect")
      .addEventListener("change", function () {
        const kiuwanSlotsBloque = document.querySelector(".kiuwanSlotsBloque");
        const sonarqubeSlotBloque = document.querySelector(
          ".sonarqubeSlotBloque"
        );
        const urlSonarBloque = document.querySelector(".urlSonarBloque");
        const sonarqubeFechaBloque = document.querySelector(
          ".sonarqubeFechaBloque"
        );
        const fechaAnalisisKiuwanElement =
          document.querySelector(".fechaKiuwanBloque");

        if (this.value === "Sonarqube") {
          kiuwanSlotsBloque.classList.add("mshide");
          urlSonarBloque.classList.remove("mshide");
          sonarqubeSlotBloque.classList.remove("mshide");
          sonarqubeFechaBloque.classList.remove("mshide");
          fechaAnalisisKiuwanElement.classList.add("mshide");
        } else {
          urlSonarBloque.classList.add("mshide");
          sonarqubeSlotBloque.classList.add("mshide");
          sonarqubeFechaBloque.classList.add("mshide");
          fechaAnalisisKiuwanElement.classList.remove("mshide");
          kiuwanSlotsBloque.classList.remove("mshide");
        }
      });

    document
      .getElementById("productoInput")
      .addEventListener("change", function () {
        const kiuwanSlotsBloque = document.querySelector(".kiuwanSlotsBloque");
        const appSelect = document.getElementById("appSelect");
        const sonarqubeSlotBloque = document.querySelector(
          ".sonarqubeSlotBloque"
        );
        const sonarqubeFechaBloque = document.querySelector(
          ".sonarqubeFechaBloque"
        );

        if (this.value !== "Ninguno" && appSelect.value === "Kiuwan") {
          kiuwanSlotsBloque.classList.remove("mshide");
          cargarSlotsKiuwan();
          sonarqubeSlotBloque.classList.add("mshide");
          sonarqubeFechaBloque.classList.add("mshide");
        } else {
          kiuwanSlotsBloque.classList.add("mshide");
        }
      });
  });

  document.querySelector(".contKiuwan").addEventListener("click", function () {
    currentApp = "Kiuwan";
    document.getElementById("kiuwanTable").style.display = "block";
    document.getElementById("sonarqubeTable").style.display = "none";
    cargarTablaSDLC();
  });

  document
    .querySelector(".contSonarqube")
    .addEventListener("click", function () {
      currentApp = "Sonarqube";
      document.getElementById("kiuwanTable").style.display = "none";
      document.getElementById("sonarqubeTable").style.display = "block";
      cargarTablaSDLC();
    });

  cargarTablaSDLC();

  document
    .querySelector(".btn-downloadSDLC")
    .addEventListener("click", function () {
      let numeroFila = 0;
      let filas = (
        currentApp === "Kiuwan"
          ? $("#sdlcTablaKiuwan")
          : $("#sdlcTablaSonarqube")
      )
        .bootgrid()
        .data(".rs.jquery.bootgrid").rows;
      filas.forEach((fila) => {
        delete filas[numeroFila].id;
        numeroFila += 1;
      });
      const workbook = XLSX.utils.book_new();
      const worksheet = XLSX.utils.json_to_sheet(filas);
      XLSX.utils.book_append_sheet(workbook, worksheet, "SDLC");
      XLSX.writeFile(workbook, `Aplicaciones_SDLC_${currentApp}.xlsx`);
    });
}

function cargarSlotsKiuwan() {
  fetch("./api/getKiuwanAplication")
    .then((response) => response.json())
    .then((data) => {
      const kiuwanSlotsInput = document.getElementById("kiuwanSlotsInput");
      kiuwanSlotsInput.innerHTML =
        '<option value="Ninguno">Seleccione un slot</option>';

      const availableSlots = data.filter((slot) => slot.registrada !== 1);

      availableSlots.forEach((slot) => {
        const option = document.createElement("option");
        option.value = slot.id;
        option.textContent = slot.app_name;
        kiuwanSlotsInput.appendChild(option);
      });

      kiuwanSlotsInput.addEventListener("change", function () {
        const selectedSlotId = this.value;

        if (selectedSlotId !== "Ninguno") {
          const selectedSlot = availableSlots.find(
            (slot) => slot.id == selectedSlotId
          );

          if (selectedSlot) {
            const fechaAnalisisElement = document.getElementById("fecha");
            if (selectedSlot.analysis_date) {
              fechaAnalisisElement.value = selectedSlot.analysis_date;
            } else {
              fechaAnalisisElement.value = "";
            }
          }
        } else {
          const fechaAnalisisElement = document.getElementById("fecha");
          fechaAnalisisElement.value = "";
        }
      });
    })
    .catch((error) => console.error("Error cargando slots de Kiuwan:", error));
}

function obtenerPentest() {
  return new Promise(function (resolve, reject) {
    $.ajax({
      type: "GET",
      url: `./api/obtenerIssuesPentest`,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        resolve(retorno);
      },
    });
  });
}

jQuery(document).ready(function ($) {
  function configBotonesIssues() {
    $(".contClonadas").click(function (e) {
      $(".ClonadasCerradas").removeClass("mshide");
      $(".contClonadas").addClass("sombraClonadas");
      $(".contRegistradas").removeClass("sombraRegistro");
      $(".issues").addClass("mshide");
    });
    $(".contRegistradas").click(function (e) {
      $(".ClonadasCerradas").addClass("mshide");
      $(".contClonadas").removeClass("sombraClonadas");
      $(".contRegistradas").addClass("sombraRegistro");
      $(".issues").removeClass("mshide");
    });
  }

  if ($("div").hasClass("pag-EVS")) {
    let start = 0;
    let maxResult = 50;
    mostrarLoading();
    obtenerPentest().then(function (resultado) {
      let pentest = resultado;
      configBotonesIssues();
      let options = {
        rowSelect: true,
        selection: true,
        labels: {
          noResults: "No se han encontrado resultados.",
          infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
          search: "Buscar",
        },
        formatters: {
          commands: function (column, row) {
            return (
              "<div class='d-flex justify-content-center align-items-center'>" +
              "<button type='button' class='btn btn-secondary btn-xs btn-default commands-masInfo' " +
              "data-row-id='" +
              extraerKey(row.id) +
              "'>Más info</button>" +
              "</div>"
            );
          },
        },
      };
      let grid = $("#issuesTabla")
        .bootgrid(options)
        .on("loaded.rs.jquery.bootgrid", function () {
          grid.find(".commands-masInfo").on("click", function () {
            let id = $(this).data("row-id");
            let row = grid
              .bootgrid("getCurrentRows")
              .find((row) => extraerKey(row.id) == id);
            getInfoIssue(row);
          });
        });
      obtenerIssues(start, maxResult, 1, pentest);
    });
  }

  async function obtenerIssues(start, maxResult, page, pentest) {
    try {
      const retorno = await mostrarIssues(start);
      let total = retorno["total"];
      if (retorno != null) {
        if (page == 1) {
          $(".tablaIssues").removeClass("mshide");
          cerrarModal();
        }

        for (let issue of retorno.issues) {
          if (issue.fields.issuetype.name != "Vulnerability") continue;
          let contador_pentest = 0;
          let nombre_pentest = "Sin pentest";
          for (let pent of pentest) {
            if (issue.key == pent["id_issue"]) {
              nombre_pentest = pent["Pentest"]["nombre"];
              pentest.splice(contador_pentest, 1);
              break;
            }
            contador_pentest += 1;
          }
          let clon;
          let infoClonada;
          if (issue.fields.issuelinks[0]?.inwardIssue?.key) {
            clon = issue.fields.issuelinks[0].inwardIssue.key;
            if (issue.fields.issuelinks[0]?.inwardIssue?.fields?.status?.name) {
              infoClonada =
                issue.fields.issuelinks[0].inwardIssue.fields.status.name;
              let total_contador;
              if (infoClonada == "Cerrada" || infoClonada == "Resuelta") {
                infoClonada =
                  "<label class='rounded-pill estado cerrado'>Cerrada</label>";
                if (
                  issue.fields.status.name != "Cerrada" &&
                  issue.fields.status.name != "Resuelta"
                ) {
                  total_contador = parseInt($(".textClonadas").text()) + 1;
                  $(".textClonadas").text(total_contador);
                }
              } else if (infoClonada == "Abierta") {
                infoClonada =
                  "<label class='rounded-pill estado abierto'>Abierta</label>";
              } else {
                infoClonada =
                  "<label class='rounded-pill estado otroEstado'>" +
                  infoClonada +
                  "</label>";
              }
            } else
              infoClonada =
                "<label class='rounded-pill estado sinClonar'>Sin clonar</label>";
          } else {
            clon = "Sin clonar";
            infoClonada =
              "<label class='rounded-pill estado sinClonar'>Sin clonar</label>";
          }
          let proyecto;
          if (issue.fields.customfield_24501?.value)
            proyecto = issue.fields.customfield_24501.value;
          else proyecto = "Undefined";
          let total_contador;
          if (issue.fields.status.name == "Abierta") {
            issue.fields.status.name =
              "<label class='rounded-pill estado abierto'>" +
              issue.fields.status.name +
              "</label>";
            total_contador = parseInt($(".textAbiertas").text()) + 1;
            $(".textAbiertas").text(total_contador);
          } else {
            issue.fields.status.name =
              "<label class='rounded-pill estado cerrado'>" +
              issue.fields.status.name +
              "</label>";
            total_contador = parseInt($(".textCerradas").text()) + 1;
            $(".textCerradas").text(total_contador);
          }
          let prio;
          if (!issue.fields.customfield_25603?.value)
            prio = "<label>Sin prioridad </label>";
          else if (issue.fields.customfield_25603.value == "Low")
            prio =
              "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped low' role='progressbar'>" +
              "</div>";
          else if (issue.fields.customfield_25603.value == "Medium")
            prio =
              "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped medium' role='progressbar'>" +
              "</div>";
          else if (issue.fields.customfield_25603.value == "Major")
            prio =
              "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped major' role='progressbar'>" +
              "</div>";
          else
            prio =
              "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped critical' role='progressbar'>" +
              "</div>";

          let responsable;
          if (issue.fields.reporter != null)
            responsable = issue.fields.reporter.displayName;
          else responsable = "Sin responsable";

          let id = `<a class='ID-nombre' href='https://jira.tid.es/browse/${issue.key}' target="_blank">${issue.key}</a>`;
          clon = "<label class='ID-clon'>" + clon + "</label>";
          let issueFecha = cambiarFecha(issue.fields.created);
          let issuesArray = [
            {
              id: id + "<br>" + clon,
              Estado: issue.fields.status.name,
              EstadoClonada: infoClonada,
              Resumen: issue.fields.summary,
              Proyecto: proyecto,
              Responsable: toCapitalCase(responsable),
              Pentest: nombre_pentest,
              Fecha: issueFecha,
              Dias: calcularDiasPasados(issueFecha) + " días",
              Prioridad: prio,
            },
          ];
          total_contador = parseInt($(".textRegistradas").text()) + 1;
          $(".textRegistradas").text(total_contador);
          $("#issuesTabla").bootgrid("append", issuesArray);
          $(".ID-nombre").click(function () {});
          if (
            infoClonada ==
              "<label class='rounded-pill estado cerrado'>Cerrada</label>" &&
            issue.fields.status.name !=
              "<label class='rounded-pill estado cerrado'>Cerrada</label>"
          )
            $("#cerradasClonadasTabla").bootgrid("append", issuesArray);
        }
        if (start + maxResult < total) {
          obtenerIssues(start + maxResult, maxResult, page + 1, pentest);
        } else {
          finalLoading("#issuesLoading", "check");
        }
      } else {
        let mensaje =
          "No han podido obtenerse todas las issues. Si la issue que buscas no está aquí recarga la pestaña.";
        showModalWindow(ERROR, mensaje, null, "Aceptar", null, null);
      }
    } catch (error) {
      console.error("Error al obtener issues:", error);
      finalLoading("#issuesLoading", "error");

      // Mostrar un mensaje de error más descriptivo con opción para reintentar
      let mensaje =
        "Ha ocurrido un error al cargar las issues: " +
        (error.message || "Error desconocido") +
        ".<br><br>¿Desea intentar nuevamente?";

      showModalWindow(
        "Error",
        mensaje,
        function () {
          // Función para reintentar la carga
          cerrarModal();
          mostrarLoading();
          obtenerIssues(start, maxResult, page, pentest);
        },
        null,
        "Reintentar",
        null
      );
    }
  }

  function finalLoading(element, type) {
    let img;
    if (type == "check")
      img = `<img src="./img/check.svg" width="60" height="60" alt="checked" class="checked">`;
    else if (type == "error")
      img = `<img src="./img/wrong.svg" width="60" height="60" alt="checked" class="checked">`;
    empty(element);
    append(element, img);
  }

  function toCapitalCase(text) {
    return text.toLowerCase().replace(/(?:^|\s)\w/g, function (match) {
      return match.toUpperCase();
    });
  }

  function calcularDiasPasados(desdeFecha) {
    let fechaProporcionada = new Date(desdeFecha);
    let fechaActual = new Date();
    let diferenciaEnMs = fechaActual - fechaProporcionada;
    let diasPasados = Math.floor(diferenciaEnMs / (1000 * 60 * 60 * 24));
    return diasPasados;
  }

  function getInfoIssue(row) {
    async function showInfo(type) {
      $(".btnEnviarArchivos").off("click");
      $(".btnEnviarComentario").off("click");
      if (typeof row != "undefined") {
        $(".tablaIssues").addClass("mshide");
        mostrarLoading();
        let key = extraerKey(row.id);
        const retorno = await obtenerIssue(key);

        let issue = retorno.issues[0];
        if (type == 1) {
          configurarEdit(issue, key);
        }
        $(".btn-volver").removeClass("mshide");
        $(".btn-newIssue").addClass("mshide");
        $(".moduloInfo").removeClass("mshide");
        $(".infoIssue").removeClass("mshide");
        let clonada;
        let resolution;
        let etiquetas;
        let definicion;

        if (issue.fields.issuelinks[0]?.inwardIssue?.key) {
          key = issue.key + " / " + issue.fields.issuelinks[0].inwardIssue.key;
          clonada = issue.fields.issuelinks[0].inwardIssue.key;
          $(".enlaceClonada").text(issue.fields.issuelinks[0].inwardIssue.key);
          $(".enlaceClonada").attr(
            "href",
            "https://jira.tid.es/browse/" +
              issue.fields.issuelinks[0].inwardIssue.key
          );
        } else {
          clonada = "Sin clonar";
          key = issue.key + " / " + "Sin clonar";
          $(".enlaceClonada").text("Sin clonar");
        }
        if (issue.fields.resolution == null) resolution = "Sin resolver";
        else resolution = issue.fields.resolution;
        if (issue.fields.labels[0]) {
          etiquetas = "";
          for (let etiqueta of issue.fields.labels) {
            etiquetas = etiquetas + etiqueta + ", ";
          }
          etiquetas = etiquetas.substring(0, etiquetas.length - 2);
        } else etiquetas = "Sin etiquetas";
        if (issue.fields.customfield_25704?.child)
          definicion = issue.fields.customfield_25704.child.value;
        else definicion = "Sin definicion";
        $(".enlaceIssue").attr(
          "href",
          "https://jira.tid.es/browse/" + issue.key
        );
        $(".enlaceIssue").text(issue.key);
        $(".infoSummary").text(issue.fields.summary);
        $(".infoKey").text(key);
        $(".infoTipo").text(issue.fields.issuetype.name);
        $(".infoEstado").text(issue.fields.status.name);
        $(".infoRes").text(resolution);
        $(".infoNivel").text(issue.fields.security.name);
        $(".infoTag").text(etiquetas);
        $(".infoDesc").text(issue.fields.description);
        if (issue.fields.customfield_25603?.value)
          $(".infoPrio").text(issue.fields.customfield_25603.value);
        else $(".infoPrio").text("No se ha podido obtener la prioridad");
        if (issue.fields.customfield_24501?.value)
          $(".infoProy").text(issue.fields.customfield_24501.value);
        else
          $(".infoProy").text(
            "No se ha podido obtener la información del proyecto"
          );
        if (issue.fields.customfield_25704?.value)
          $(".infoMeto").text(
            issue.fields.customfield_25704.value + " - " + definicion
          );
        else
          $(".infoMeto").text(
            "No se ha podido obtener la información de la metodología" +
              " - " +
              definicion
          );

        $(".infoCVSS").text(issue.fields.customfield_25703);
        $(".infoVulne").text(issue.fields.customfield_25702);
        if (issue.fields.customfield_12611?.value)
          $(".infoAnalysis").text(issue.fields.customfield_12611.value);
        else
          $(".infoAnalysis").text(
            "No se ha podido obtener información del análisis"
          );
        if (issue.fields.customfield_12609?.value)
          $(".infoImpact").text(issue.fields.customfield_12609.value);
        else
          $(".infoImpact").text(
            "No se ha podido obtener la información del impacto"
          );
        if (issue.fields.customfield_12610?.value)
          $(".infoProb").text(issue.fields.customfield_12610.value);
        else
          $(".infoProb").text(
            "No se ha podido obtener la información de la probabilidad"
          );
        if (issue.fields.customfield_12700?.value)
          $(".infoStatus").text(issue.fields.customfield_12700.value);
        else
          $(".infoStatus").text(
            "No se ha podido obtener la información del estado"
          );
        $(".infoUrl").text(issue.fields.customfield_12800);
        if (issue.fields.status.name == "Cerrada") {
          $(".btn-openIssue").removeClass("mshide");
          $(".btn-closeIssue").addClass("mshide");
        } else {
          $(".btn-closeIssue").removeClass("mshide");
          $(".btn-openIssue").addClass("mshide");
        }
        if (clonada != "Sin clonar") {
          const retorno = await obtenerComentarios(clonada);
          $(".chat").empty();
          if (retorno.comments.length > 0) {
            for (let comentario of retorno.comments) {
              $(".chat").append(` <div class="card mb-2">
                                          <div class="card-header writter">
                                              <h6 class="card-title mb-0 escritor">${toCapitalCase(
                                                comentario.author.displayName
                                              )}</h6>
                                          </div>
                                          <div class="card-body">
                                              <p class="card-text comentario">${
                                                comentario.body
                                              }</p>
                                          </div>
                                      </div>`);
            }
          } else {
            $(".chat").append(` <div class="card mb-2">
                                        <div class="card-header writter">
                                            <h6 class="card-title mb-0 escritor">ElevenCert</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text comentario">Esta vulnerabilidad no tiene comentarios.</p>
                                        </div>
                                    </div>`);
          }
        } else {
          $(".chat").empty();
          $(".chat").append(` <div class="card mb-2">
                                            <div class="card-header writter">
                                                <h6 class="card-title mb-0 escritor">ElevenCert</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text comentario">Esta vulnerablidad no esta clonada.</p>
                                            </div>
                                        </div>`);
        }
        $(".btnEnviarArchivos").click(function () {
          mostrarLoading();
          let form = $(".ArchivosForm");
          let formData = new FormData(form[0]);
          $.ajax({
            type: "POST",
            url: `./api/subirArchivos?key=${issue.key}&clonada=${clonada}`,
            xhrFields: {
              withCredentials: true,
            },
            data: formData,
            processData: false,
            contentType: false,
            success: function (retorno, textStatus, request) {
              showModalWindow(
                INFORMACION,
                "Se han subido los archivos correctamente",
                null,
                "Aceptar",
                null,
                null
              );
            },
          });
          cerrarModal();
        });

        $(".btnEnviarComentario").click(async function () {
          let comentario = $(".commentWritter").val();
          if (comentario != "") {
            try {
              const retorno = await enviarComentario(clonada, comentario);
              $(".chat").append(` <div class="card mb-2">
                <div class="card-header writter">
                    <h6 class="card-title mb-0 escritor">Bot-11cert</h6>
                </div>
                <div class="card-body">
                    <p class="card-text comentario">${retorno}</p>
                </div>
              </div>`);
            } catch (error) {
              console.log(error);
            }
          }
        });
        cerrarModal();
      }
    }

    showInfo(1);
    $(".btn-status").click(function () {
      let accion;
      if ($(this).hasClass("btn-closeIssue")) accion = "cerrar";
      else accion = "abrir";

      async function cambiarEstado() {
        mostrarLoading();
        let key = extraerKey(row.id);

        try {
          await actualizarStatus(accion, key);
          showInfo(0);
          if (accion == "abrir")
            row.Estado =
              "<label class='rounded-pill estado abierto'>Abierta</label>";
          else
            row.Estado =
              "<label class='rounded-pill estado cerrado'>Cerrada</label>";
        } catch (error) {
          console.log(error);
        }
      }

      let separador = $(".infoKey").text().indexOf(" ");
      let key = $(".infoKey").text().substring(0, separador);
      let form = `<h5>
                <b>Estas a punto de ${accion} la issue: </b>
              </h5>
              <br>
              <h4>
                ${key}
              </h4>
              <h4>
                ${$(".infoSummary").text()}
              </h4>
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
                  <button class="btn btn-primary btnChange">${accion.toUpperCase()}</button>
                </div>
              </div>`;
      showModalWindow("¿Cambiar estado?", form, null, null, null);

      $(".btnChange").click(function () {
        cambiarEstado();
      });

      $(".btnCancel").click(function () {
        cerrarModal();
      });
    });
  }

  function configurarEdit(issue, key) {
    $(".btn-editIssue").click(function () {
      let form = `<form class="issueForm" id="form-newIssue">
                    <div class="form-group mb-4 row">
                      <label for="text" class="col-4 col-form-label resumen-label">Resumen*</label>
                      <div class="col-8">
                        <input id="text" name="Resumen" type="text" class="form-control resumen-input" required="required">
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select7" class="col-4 col-form-label areaServicio-label">Proyecto de área/servicio*</label>
                        <div class="col-8">
                          <select id="areaServ" name="AreaServ" class="form-select-custom areaServicio-input" required="required">
                            <option value="Ninguno">Ninguno</option>
                          </select>
                        </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select7" class="col-4 col-form-label pentest-label">Pentest*</label>
                      <div class="col-8">
                        <select id="pentest" name="pentest" class="form-select-custom pentest-input" required="required">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="textarea" class="col-4 col-form-label">Descripción</label>
                      <div class="col-8">
                        <textarea id="textarea" name="Descrip" cols="40" rows="5" class="form-control" aria-describedby="textareaHelpBlock"></textarea>
                        <span id="textareaHelpBlock" class="form-text text-muted">Issue description</span>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select1" class="col-4 col-form-label metodologia-label">Metodología*</label>
                      <div class="col-8">
                        <select id="Metod" name="metodologia" class="form-select-custom metodologia-input" required="required">
                          <option value="Ninguna">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select1" class="col-4 col-form-label definicion-label">Definición de la vulnerabilidad*</label>
                      <div class="col-8">
                        <select id="Def" name="Definicion" class="form-select-custom definicion-input" required="required">
                          <option value="Ninguna">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="text4" class="col-4 col-form-label informador-label">Informador*</label>
                      <div class="col-8">
                        <input type="text" name="Informador" id="inputField" placeholder="Empieza a escribir el usuario" class="form-control informer-input" autocomplete="off">
                        <ul id="suggestionsInformer" class="list-group"></ul>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label class="col-4 analysisType-label">Tipo de análisis*</label>
                      <div class="col-8">
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_0" type="radio" class="analysisType-input custom-control-input" value="Pentesting" required="required">
                          <label for="radio_0" class="custom-control-label">Pentesting</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_1" type="radio" class="analysisType-input custom-control-input" value="Network Scan" required="required">
                          <label for="radio_1" class="custom-control-label">Escaneo de red</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_2" type="radio" class="analysisType-input custom-control-input" value="Security Configuration Review" required="required">
                          <label for="radio_2" class="custom-control-label">Revisión de la configuración de la seguridad</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_3" type="radio" class="analysisType-input custom-control-input" value="Code Review" required="required">
                          <label for="radio_3" class="custom-control-label">Revisión de código</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_4" type="radio" class="analysisType-input custom-control-input" value="Architecture Review" required="required">
                          <label for="radio_4" class="custom-control-label">Revisión de la arquitectura</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_5" type="radio" class="analysisType-input custom-control-input" value="Legal Compliance" required="required">
                          <label for="radio_5" class="custom-control-label">Legal Compliance</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                          <input name="radio" id="radio_6" type="radio" class="analysisType-input custom-control-input" value="Corporate Policy" required="required">
                          <label for="radio_6" class="custom-control-label">Política corporativa</label>
                        </div>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select4" class="col-4 col-form-label impacto-label">Impacto de la vulnerabilidad*</label>
                      <div class="col-8">
                        <select id="Impact" name="VulImpact" class="form-select-custom impacto-input" required="required">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select5" class="col-4 col-form-label probExplotacion-label">Probabilidad de explotación*</label>
                      <div class="col-8">
                        <select id="ExpProb" name="ExpProb" class="form-select-custom probExplotacion-input" required="required">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select" class="col-4 col-form-label prioridad-label">Prioridad CISO*</label>
                      <div class="col-8">
                        <select id="PrioCiso" name="Prioridad" class="form-select-custom prioridad-input" required="required" aria-describedby="selectHelpBlock">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                        <span id="selectHelpBlock" class="form-text text-muted">SOLO MODIFICABLE POR CISO</span>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="select6" class="col-4 col-form-label statusVuln-label">Estado de la vulnerabilidad*</label>
                      <div class="col-8">
                        <select id="Status" name="VulnStatus" class="form-select-custom statusVuln-input" required="required">
                          <option value="Ninguno">Ninguno</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group mb-4 row">
                      <label for="textarea2" class="col-4 col-form-label URL-label">URL*</label>
                      <div class="col-8">
                        <textarea id="textarea2" name="URL" cols="40" rows="5" class="form-control URL-input" required="required"></textarea>
                      </div>
                    </div>
                  </form>`;
      showModalWindow(
        "Editar Issue",
        form,
        comprobarEdit,
        "Cerrar",
        "Aceptar",
        null,
        null,
        "modal-lm"
      );
      reporterSelector("inputField", "suggestionsInformer", "CISOCDCOIN");
      camposPentest(".pentest-input", 1).then(function (resultado) {
        seleccionarPentest(key);
      });
      completarCampos(issue);
      cisoPriority();
      setCampos();
    });
  }

  async function comprobarEdit() {
    let form = $("#form-newIssue");
    let reporterName = form.find('input[name="Informador"]').val();
    try {
      const resultado = await obtenerReporterID(reporterName);
      let newValue = resultado;
      form.find('input[name="Informador"]').val(newValue);
      if (!$("#botonAceptar").hasClass("btn-desactivado")) {
        $("#botonAceptar").addClass("btn-desactivado");
        $.ajax({
          type: "POST",
          url: `./api/comprobarCampos`,
          xhrFields: {
            withCredentials: true,
          },
          data: form.serialize(),
          success: function (retorno, textStatus, request) {
            $(".resumen-label").removeClass("red");
            $(".prioridad-label").removeClass("red");
            $(".areaServicio-label").removeClass("red");
            $(".metodologia-label").removeClass("red");
            $(".definicion-label").removeClass("red");
            $(".analysisType-label").removeClass("red");
            $(".impacto-label").removeClass("red");
            $(".probExplotacion-label").removeClass("red");
            $(".statusVuln-label").removeClass("red");
            $(".URL-label").removeClass("red");
            if (!retorno["Error"]) {
              editarIssue(form);
            } else {
              cambioForm();
              $("#botonAceptar").removeClass("btn-desactivado");
              if (!retorno["Resumen"]) $(".resumen-label").addClass("red");

              if (!retorno["Prioridad"]) $(".prioridad-label").addClass("red");

              if (!retorno["AreaServicio"])
                $(".areaServicio-label").addClass("red");

              if (!retorno["Metodologia"])
                $(".metodologia-label").addClass("red");

              if (!retorno["Definicion"])
                $(".definicion-label").addClass("red");

              if (!retorno["Informer"]) $(".informador-label").addClass("red");

              if (!retorno["AnalysisType"])
                $(".analysisType-label").addClass("red");

              if (!retorno["Impacto"]) $(".impacto-label").addClass("red");

              if (!retorno["ProbExplotacion"])
                $(".probExplotacion-label").addClass("red");

              if (!retorno["StatusVuln"])
                $(".statusVuln-label").addClass("red");

              if (!retorno["URL"]) $(".URL-label").addClass("red");
            }
          },
        });
      }
    } catch (error) {
      console.log(error);
    }
  }

  async function reporterSelector(input, sugg, proyecto) {
    const suggestionsList = document.getElementById(sugg);
    const inputField = document.getElementById(input);
    if (proyecto != null) {
      try {
        const retorno = await obtainUsers(proyecto);
        const suggestions = [];
        if (proyecto != null) {
          $("#" + input).attr("placeholder", "Empieza a escribir el usuario");
          for (let reporter of retorno) {
            suggestions.push(reporter.name);
            suggestions.push(reporter.emailAddress);
            suggestions.push(reporter.displayName);
          }
        }

        function updateSuggestions() {
          let maxSuggestions = 5;
          let numSuggestions = 0;
          const inputValue = inputField.value;

          if (inputValue === "") {
            suggestionsList.style.display = "none";
            return;
          }

          const filteredSuggestions = suggestions.filter(function (suggestion) {
            return suggestion
              .toLowerCase()
              .startsWith(inputValue.toLowerCase());
          });

          suggestionsList.innerHTML = "";

          filteredSuggestions.forEach(function (suggestion) {
            if (numSuggestions == maxSuggestions) return;
            else numSuggestions++;

            const listItem = document.createElement("li");
            listItem.textContent = suggestion;
            listItem.addEventListener("click", function () {
              inputField.value = suggestion;
              suggestionsList.style.display = "none";
            });
            suggestionsList.appendChild(listItem);
          });

          if (filteredSuggestions.length > 0) {
            suggestionsList.style.display = "block";
          } else {
            suggestionsList.style.display = "none";
          }
        }

        inputField.addEventListener("input", updateSuggestions);

        inputField.addEventListener("keyup", function (event) {
          if (event.key === "Backspace") {
            updateSuggestions();
          }
        });
      } catch (error) {
        console.log(error);
      }
    } else {
      inputField.value = "";
      $("#" + input).attr("placeholder", "Ningún proyecto seleccionado");
      suggestionsList.style.display = "none";
    }
  }

  function completarCampos(issue) {
    let valor;
    $(".resumen-input").val(issue.fields.summary);
    valor = issue.fields.customfield_25603.value;
    $(".prioridad-input").append(`<option value="${valor}">${valor}</option>`);
    $(`.prioridad-input option[value=${valor}]`).prop("selected", true);
    valor = issue.fields.customfield_24501.value;
    $(".areaServicio-input").append(
      `<option value="${valor}">${valor}</option>`
    );
    $(`.areaServicio-input option[value=${valor}]`).prop("selected", true);
    $("#textarea").val(issue.fields.description);
    valor = issue.fields.customfield_25704.value;
    $(".metodologia-input").append(
      `<option value="${valor}">${valor}</option>`
    );
    $(`.metodologia-input option[value="${valor}"]`).prop("selected", true);
    valor = issue.fields.customfield_25704.child.value;
    $(".definicion-input").append(`<option value="${valor}">${valor}</option>`);
    $(`.definicion-input option[value="${valor}"]`).prop("selected", true);
    $(".informer-input").val(issue.fields.reporter.displayName);
    valor = issue.fields.customfield_12611.value;
    $(`input[name='radio'][value='${valor}']`).prop("checked", true);
    valor = issue.fields.customfield_12609.value;
    $(".impacto-input").append(`<option value="${valor}">${valor}</option>`);
    $(`.impacto-input option[value="${valor}"]`).prop("selected", true);
    valor = issue.fields.customfield_12610.value;
    $(".probExplotacion-input").append(
      `<option value="${valor}">${valor}</option>`
    );
    $(`.probExplotacion-input option[value="${valor}"]`).prop("selected", true);
    valor = issue.fields.customfield_12700.value;
    $(".statusVuln-input").append(`<option value="${valor}">${valor}</option>`);
    $(`.statusVuln-input option[value="${valor}"]`).prop("selected", true);
    $(".URL-input").val(issue.fields.customfield_12800);
  }

  function gestionarResponsable(informer = true) {
    $(".asignee-input").attr("placeholder", "Ningún proyecto seleccionado");
    if (informer) {
      reporterSelector("inputField", "suggestionsInformer", "CISOCDCOIN");
    }
    $(".areaServicio-input").change(function () {
      reporterSelector("asigneeInput", "suggestionsResponsable", null);
      if ($(".areaServicio-input").val() != "Ninguno") {
        $(".asignee-input").attr("placeholder", "Cargando usuarios...");
        reporterSelector(
          "asigneeInput",
          "suggestionsResponsable",
          $(".areaServicio-input").val()
        );
      } else $(".asignee-input").attr("placeholder", "Ningún proyecto seleccionado");
    });
  }

  function cisoPriority() {
    $(".impacto-input, .probExplotacion-input").change(function () {
      let tablaImpacto = ["Ninguno", "Critical", "High", "Medium", "Low"];
      let tablaProb = ["Ninguno", "Low", "Medium", "High"];
      let tablaPrioridad = [
        ["Major", "Critical", "Critical"],
        ["Medium", "Major", "Critical"],
        ["Low", "Medium", "Major"],
        ["Low", "Low", "Medium"],
      ];
      let impacto = $(".impacto-input").val();
      let probExplotacion = $(".probExplotacion-input").val();
      let i = 0;
      while (tablaImpacto[i] != impacto) i++;
      let j = 0;
      while (tablaProb[j] != probExplotacion) j++;
      if (i == 0 || j == 0) $(".prioridad-input").val("Ninguno");
      else $(".prioridad-input").val(tablaPrioridad[i - 1][j - 1]);
    });
  }

  $(".btn-newIssue").click(function () {
    let form = `<form class="issueForm" id="form-newIssue"">
                  <div class="form-group mb-4 row">
                    <label for="text" class="col-4 col-form-label resumen-label">Resumen*</label>
                    <div class="col-8">
                      <input id="text" name="Resumen" type="text" class="form-control resumen-input" required="required">
                    </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label for="select7" class="col-4 col-form-label pentest-label">Pentest*</label>
                    <div class="col-8">
                      <select id="pentest" name="pentest" class="form-select-custom pentest-input" required="required">
                        <option value="Ninguno">Ninguno</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label for="textarea" class="col-4 col-form-label">Descripción</label>
                    <div class="col-8">
                      <textarea id="textarea" name="Descrip" cols="40" rows="5" class="form-control" aria-describedby="textareaHelpBlock"></textarea>
                      <span id="textareaHelpBlock" class="form-text text-muted">Issue description</span>
                    </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label for="select1" class="col-4 col-form-label metodologia-label">Metodología*</label>
                    <div class="col-8">
                      <select id="Metod" name="metodologia" class="form-select-custom metodologia-input" required="required">
                        <option value="Ninguna">Ninguno</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label for="select1" class="col-4 col-form-label definicion-label">Definición de la vulnerabilidad*</label>
                    <div class="col-8">
                      <select id="Def" name="Definicion" class="form-select-custom definicion-input" required="required">
                        <option value="Ninguna">Ninguno</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label for="text4" class="col-4 col-form-label informador-label">Informador*</label>
                      <div class="col-8">
                        <input type="text" name="Informador" id="inputField" placeholder="Empieza a escribir el usuario" class="form-control informer-input" autocomplete="off">
                        <ul id="suggestionsInformer" class="list-group"></ul>
                      </div>
                  </div>

                  <div class="form-group mb-4 row">
                    <label class="col-4 analysisType-label">Tipo de análisis*</label>
                    <div class="col-8">
                      <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_0" type="radio" class="analysisType-input custom-control-input" value="Pentesting" required="required" checked>
                        <label for="radio_0" class="custom-control-label">Pentesting</label>
                      </div>
                      <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_1" type="radio" class="analysisType-input custom-control-input" value="Network Scan" required="required">
                        <label for="radio_1" class="custom-control-label">Escaneo de red</label>
                      </div>
                      <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_2" type="radio" class="analysisType-input custom-control-input" value="Security Configuration Review" required="required">
                        <label for="radio_2" class="custom-control-label">Revisión de la configuración de la seguridad</label>
                      </div>
                      <div class="custom-control custom-radio custom-control-inline">
                      <input name="radio" id="radio_3" type="radio" class="analysisType-input custom-control-input" value="Code Review" required="required">
                        <label for="radio_3" class="custom-control-label">Revisión de código</label>
                      </div>
                      <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_4" type="radio" class="analysisType-input custom-control-input" value="Architecture Review" required="required">
                        <label for="radio_4" class="custom-control-label">Revisión de la arquitectura</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_5" type="radio" class="analysisType-input custom-control-input" value="Legal Compliance" required="required">
                        <label for="radio_5" class="custom-control-label">Legal Compliance</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                        <input name="radio" id="radio_6" type="radio" class="analysisType-input custom-control-input" value="Corporate Policy" required="required">
                        <label for="radio_6" class="custom-control-label">Política corporativa</label>
                        </div>
                        </div>
                        </div>

                        <div class="form-group mb-4 row">
                        <label for="select4" class="col-4 col-form-label impacto-label">Impacto de la vulnerabilidad*</label>
                        <div class="col-8">
                        <select id="Impact" name="VulImpact" class="form-select-custom impacto-input" required="required">
                        <option value="Ninguno">Ninguno</option>
                        </select>
                        </div>
                        </div>

                        <div class="form-group mb-4 row">
                        <label for="select5" class="col-4 col-form-label probExplotacion-label">Probabilidad de explotación*</label>
                        <div class="col-8">
                        <select id="ExpProb" name="ExpProb" class="form-select-custom probExplotacion-input" required="required">
                        <option value="Ninguno">Ninguno</option>
                      </select>
                    </div>
                    </div>

                    <div class="form-group mb-4 row">
                    <label for="select" class="col-4 col-form-label prioridad-label">Prioridad CISO*</label>
                    <div class="col-8">
                    <select id="PrioCiso" name="Prioridad" class="form-select-custom prioridad-input" required="required" aria-describedby="selectHelpBlock">
                    <option value="Ninguno">Ninguno</option>
                    </select>
                    <span id="selectHelpBlock" class="form-text text-muted">SOLO MODIFICABLE POR CISO</span>
                    </div>
                    </div>

                    <div class="form-group mb-4 row">
                    <label for="select6" class="col-4 col-form-label statusVuln-label">Estado de la vulnerabilidad*</label>
                    <div class="col-8">
                    <select id="Status" name="VulnStatus" class="form-select-custom statusVuln-input" required="required">
                    <option value="Ninguno">Ninguno</option>
                    </select>
                    </div>
                    </div>

                    <div class="form-group mb-4 row">
                    <label for="textarea2" class="col-4 col-form-label URL-label">URL*</label>
                      <div class="col-8">
                        <textarea id="textarea2" name="URL" cols="40" rows="5" class="form-control URL-input" required="required"></textarea>
                      </div>
                    </div>

                      <div class="form-group mb-4 row">
                        <label for="archivos" class="col-4 form-label evidencia-label">Evidencias:</label>
                        <div class="col-8">
                          <input type="file" class="form-control-file" id="archivos" name="file[]" multiple>
                        </div>
                      </div>
                    </form>`;
    setCampos();
    showModalWindow(
      "Crear Issue",
      form,
      comprobarCrear,
      "Cerrar",
      "Aceptar",
      null,
      null,
      "modal-lm"
    );
    gestionarResponsable();
    cisoPriority();
    camposPentest(".pentest-input").then(function (resultado) {});
  });

  async function setCampos() {
    try {
      const retorno = await obtenerCampos();
      let campos = retorno["values"];
      changeSelect(campos[3]["allowedValues"], "#areaServ");
      changeSelect(campos[2]["allowedValues"], "#PrioCiso");
      changeSelect(campos[6]["allowedValues"], "#Metod");
      for (let valor of campos[6]["allowedValues"]) {
        if ($(".metodologia-input").val() == valor.value) {
          changeSelect(valor.children, "#Def");
        }
      }
      $(".metodologia-input").change(function () {
        $("#Def").empty();
        $("#Def").append(`<option value="Ninguna">Ninguna</option>`);
        for (let valor of campos[6]["allowedValues"]) {
          if ($(".metodologia-input").val() == valor.value) {
            changeSelect(valor.children, "#Def");
          }
        }
      });
      changeSelect(campos[11]["allowedValues"], "#Impact");
      changeSelect(campos[12]["allowedValues"], "#ExpProb");
      changeSelect(campos[13]["allowedValues"], "#Status");
    } catch (error) {
      console.log(error);
    }
  }

  function cambioForm() {
    let prob_status = false;
    let imp_status = false;
    $(".resumen-input").change(function () {
      $(".resumen-label").removeClass("red");
    });
    $(".asignee-input").change(function () {
      $(".responsable-label").removeClass("red");
    });
    $(".pentest-input").change(function () {
      $(".pentest-label").removeClass("red");
    });
    $(".pentest-input").change(function () {
      $(".pentest-label").removeClass("red");
    });
    $(".prioridad-input").change(function () {
      $(".prioridad-label").removeClass("red");
    });
    $(".prioridad-input").change(function () {
      $(".prioridad-label").removeClass("red");
    });
    $(".areaServicio-input").change(function () {
      $(".areaServicio-label").removeClass("red");
    });
    $(".metodologia-input").change(function () {
      $(".metodologia-label").removeClass("red");
    });
    $(".definicion-input").change(function () {
      $(".definicion-label").removeClass("red");
    });
    $(".informer-input").change(function () {
      $(".informador-label").removeClass("red");
    });
    $(".analysisType-input").change(function () {
      $(".analysisType-label").removeClass("red");
    });
    $(".impacto-input").change(function () {
      if (!imp_status) imp_status = true;
      if (imp_status && prob_status) $(".prioridad-label").removeClass("red");
      $(".impacto-label").removeClass("red");
    });
    $(".probExplotacion-input").change(function () {
      if (!prob_status) prob_status = true;
      if (imp_status && prob_status) $(".prioridad-label").removeClass("red");
      $(".probExplotacion-label").removeClass("red");
    });
    $(".statusVuln-input").change(function () {
      $(".statusVuln-label").removeClass("red");
    });
    $(".URL-input").change(function () {
      $(".URL-label").removeClass("red");
    });
  }

  async function comprobarCrear() {
    let form = $("#form-newIssue");
    let formData = new FormData(form[0]);
    let reporterName = form.find('input[name="Responsable"]').val();
    let newValue;

    try {
      const resultado = await obtenerReporterID(reporterName);
      newValue = resultado;
      form.find('input[name="Responsable"]').val(newValue);

      reporterName = form.find('input[name="Informador"]').val();
      const resultado2 = await obtenerReporterID(reporterName);
      newValue = resultado2;
      form.find('input[name="Informador"]').val(newValue);
      if (!$("#botonAceptar").hasClass("btn-desactivado")) {
        $("#botonAceptar").addClass("btn-desactivado");
        $.ajax({
          type: "POST",
          url: `./api/comprobarCampos`,
          xhrFields: {
            withCredentials: true,
          },
          data: formData,
          processData: false,
          contentType: false,
          success: function (retorno, textStatus, request) {
            $(".pentest-label").removeClass("red");
            $(".resumen-label").removeClass("red");
            $(".prioridad-label").removeClass("red");
            $(".metodologia-label").removeClass("red");
            $(".definicion-label").removeClass("red");
            $(".analysisType-label").removeClass("red");
            $(".impacto-label").removeClass("red");
            $(".probExplotacion-label").removeClass("red");
            $(".statusVuln-label").removeClass("red");
            $(".URL-label").removeClass("red");
            if (!retorno["Error"]) {
              crearIssue(form);
            } else {
              cambioForm();
              $("#botonAceptar").removeClass("btn-desactivado");
              if (!retorno["Resumen"]) $(".resumen-label").addClass("red");
              if (!retorno["Pentest"]) $(".pentest-label").addClass("red");
              if (!retorno["Pentest"]) $(".pentest-label").addClass("red");
              if (!retorno["Prioridad"]) $(".prioridad-label").addClass("red");
              if (!retorno["Metodologia"])
                $(".metodologia-label").addClass("red");
              if (!retorno["Definicion"])
                $(".definicion-label").addClass("red");
              if (!retorno["Informer"]) $(".informador-label").addClass("red");
              if (!retorno["AnalysisType"])
                $(".analysisType-label").addClass("red");
              if (!retorno["Impacto"]) $(".impacto-label").addClass("red");
              if (!retorno["ProbExplotacion"])
                $(".probExplotacion-label").addClass("red");
              if (!retorno["StatusVuln"])
                $(".statusVuln-label").addClass("red");
              if (!retorno["URL"]) $(".URL-label").addClass("red");
            }
          },
        });
      }
    } catch (error) {
      console.log(error);
    }
  }

  $(".btn-delIssue").click(function () {
    let separador = $(".infoKey").text().indexOf(" ");
    let key = $(".infoKey").text().substring(0, separador);
    let form = `<h5>
              <b>Estas a punto de eliminar para siempre: </b>
            </h5>
            <br>
            <h4>
              ${key}
            </h4>
            <h4>
              ${$(".infoSummary").text()}
            </h4>
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
                <button class="btn btn-primary btnDel">ELIMINAR</button>
              </div>
            </div>`;
    showModalWindow("¿Eliminar issue?", form, null, null, null);

    $(".btnCancel").click(function () {
      cerrarModal();
    });

    $(".btnDel").click(async function () {
      mostrarLoading();
      try {
        await delIssue(key);
        recargarVentana();
      } catch (error) {
        console.log(error);
      }
    });
  });

  function changeSelect(opciones, id) {
    let i = 0;
    let opcion_existente;
    let valor;
    if ($(id).find("option")[1])
      opcion_existente = $(id).find("option")[1].text;
    else opcion_existente = null;
    for (const opcion of opciones) {
      if (!opcion.disabled) {
        valor = opcion["value"];
        if (valor == opcion_existente) {
          let optionToRemove = $(id).find(`option[value="${valor}"]`);
          optionToRemove.remove();
          $(id).append(`<option value="${valor}">${valor}</option>`);
          let optionToSelect = $(id).find(`option[value="${valor}"]`);
          optionToSelect.prop("selected", true);
        } else $(id).append(`<option value="${valor}">${valor}</option>`);
        i++;
      }
    }
  }

  function crearIssue(form) {
    mostrarLoading();
    let formData = new FormData(form[0]);
    $.ajax({
      type: "POST",
      url: `./api/newIssue`,
      xhrFields: {
        withCredentials: true,
      },
      data: formData,
      processData: false,
      contentType: false,
      success: function (retorno, textStatus, request) {
        if (!retorno["Error"]) {
          let key = retorno["Execution"];
          setResponsable(key, formData).then(function (resultado) {
            if (resultado == 204) gestionarPentest(key, form);
            else if (resultado == -1) {
              console.log(
                "Ha fallado la obtención de informacion de la clonada"
              );
            } else console.log("Ha fallado el cambio de nombre");
          });
        } else {
          cerrarModal();
          console.log("No se ha podido crear la issue");
        }
      },
    });
  }

  function setResponsable(key, form) {
    return new Promise(function (resolve, reject) {
      obtenerIssue(key)
        .then((retorno) => {
          let issue = retorno.issues[0];
          if (issue.fields.issuelinks[0]?.inwardIssue?.key) {
            let clonada = issue.fields.issuelinks[0].inwardIssue.key;
            $.ajax({
              type: "POST",
              url: `./api/modificarClonada?clone=${clonada}`,
              xhrFields: {
                withCredentials: true,
              },
              data: form,
              processData: false,
              contentType: false,
              success: function (retorno, textStatus, request) {
                if (retorno != 204)
                  console.log("Ha fallado el cambio de nombre");
                resolve(retorno);
              },
            });
          } else {
            console.log("Fallo al obtener los datos de la clonada");
            resolve(-1);
          }
        })
        .catch((error) => {
          console.log(error);
          reject(error);
        });
    });
  }

  function gestionarPentest(key, form) {
    return new Promise(function (resolve, reject) {
      let pentest = form.find('select[name="pentest"]').val();
      let definicion = form.find('select[name="Definicion"]').val();
      definicion = encodeURIComponent(definicion);
      $.ajax({
        type: "GET",
        url: `./api/gestionarPentest?key=${key}&pentest=${pentest}&vuln=${definicion}`,
        xhrFields: {
          withCredentials: true,
        },
        success: function (retorno, textStatus, request) {
          recargarVentana();
          resolve(retorno);
        },
      });
    });
  }

  function editarIssue(form) {
    mostrarLoading();
    let separador = $(".infoKey").text().indexOf(" ");
    let key = $(".infoKey").text().substring(0, separador);
    $.ajax({
      type: "POST",
      url: `./api/editIssue?key=${key}`,
      xhrFields: {
        withCredentials: true,
      },
      data: form.serialize(),
      success: function (retorno, textStatus, request) {
        gestionarPentest(key, form).then(function (resultado) {
          mostrarLoading();
          recargarVentana();
        });
      },
    });
  }

  $(".btn-volver").click(function () {
    $(".btn-volver").addClass("mshide");
    $(".btn-openIssue").addClass("mshide");
    $(".btn-closeIssue").addClass("mshide");
    $(".btn-newIssue").removeClass("mshide");
    $(".moduloInfo").addClass("mshide");
    $(".infoIssue").addClass("mshide");
    $(".tablaIssues").removeClass("mshide");
    $(".infoKey").empty();
    $(".btn-editIssue").off("click");
  });

  function seleccionarPentest(key) {
    $.ajax({
      type: "GET",
      url: `./api/obtainPentestIssue?key=${key}`,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno, textStatus, request) {
        if (retorno != "Sin pentest") {
          $(".pentest-input").val(retorno);
        }
      },
    });
  }

  function recargarVentana() {
    location.reload();
  }
});

$(document).ready(function () {
  const href = window.location.hash;
  if (href === "#solicitudesPentest") {
    $(".nav-link").removeClass("active");
    $(".tab-content").addClass("mshide");
    $("#solicitudesPentest").removeClass("mshide");
    $("a[id='#solicitudesPentest']").addClass("active");
  }
});
