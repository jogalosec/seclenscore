import { getPacsContinuidad, getTablaBIA } from "../api/continuidadApi.js";
import { finalLoading, addDownloadButtonToTable } from "../utils/utils.js";
import constants from "../modules/constants.js";

const options = constants.OPTIONS_TABLE;

function upgradeBootgrid(id, newValue) {
  let rows = $("#tablaPacsContinuidad").bootgrid("getCurrentRows");
  let row = rows.find((row) => row.id === id);
  if (row) {
    row.estado = newValue;
    $("#tablaPacsContinuidad").bootgrid("reload");
  } else {
    console.error(`No se encontró la fila con ID ${id}`);
  }
}

function editarPacContinuidad(idFila) {
  let form = $(`.formEdit`);
  let sistema = $(`.sysName`).text();
  let estado = form.find('select[name="estado"]').val();
  if (form[0].checkValidity()) {
    cerrarModal();
    $.ajax({
      type: "POST",
      url: `./api/ModEstadoPacSeguimiento?sysName=${sistema}`,
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
            cerrarModal,
            null,
            "Aceptar"
          );
          upgradeBootgrid(idFila, estado);
        }
      },
    });
  }
}
function modalVerResponsables(idActivo) {
  $.ajax({
    type: "GET",
    url: `./api/getPersonasActivo?id=${idActivo}`,
    xhrFields: {
      withCredentials: true,
    },
    success: function (retorno, textStatus, request) {
      if (retorno.error) {
        showModalWindow(ERROR, retorno.message, null, "Cerrar", null, goLogin);
      } else {
        const responsables = retorno["responsables"];
        responsables["product_owner"] =
          responsables["product_owner"] || "No asignado";
        responsables["r_desarrollo"] =
          responsables["r_desarrollo"] || "No asignado";
        responsables["r_kpms"] = responsables["r_kpms"] || "No asignado";
        responsables["r_operaciones"] =
          responsables["r_operaciones"] || "No asignado";
        responsables["r_seguridad"] =
          responsables["r_seguridad"] || "No asignado";

        let productowner = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label class="col-form-label">Product Owner</label></div><div class="col-6"><span>${responsables["product_owner"]}</span></div></div>`;
        let r_desarrollo = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label class="col-form-label">R.Desarrollo</label></div><div class="col-6"><span>${responsables["r_desarrollo"]}</span></div></div>`;
        let r_kmps = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label class="col-form-label">R.KPMS</label></div><div class="col-6"><span>${responsables["r_kpms"]}</span></div></div>`;
        let r_operaciones = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label class="col-form-label">R.Operaciones</label></div><div class="col-6"><span>${responsables["r_operaciones"]}</span></div></div>`;
        let r_seguridad = `<div class='row g-3 align-items-center'><div class='col-6 text-start'><label class="col-form-label">R.Seguridad</label></div><div class="col-6"><span>${responsables["r_seguridad"]}</span></div></div>`;
        let content = `<div>${productowner}${r_desarrollo}${r_kmps}${r_operaciones}${r_seguridad}</div>`;

        showModalWindow("Responsables", content, null, "Cerrar", null);
      }
    },
    error: function (xhr, status, error) {
      console.error("Error en la solicitud:", error);
    },
  });
}

function modalEditarContinuidad(id) {
  let rows = $("#tablaPacsContinuidad").bootgrid("getCurrentRows");
  let pos = rows
    .map(function (ie) {
      return ie.id;
    })
    .indexOf(parseInt(id));
  let options = `<option value="No iniciado">No iniciado</option>
    <option value="Iniciado">Iniciado</option>
    <option value="En Progreso">En Progreso</option>
    <option value="Finalizado">Finalizado</option>
    <option value="Descartado">Descartado</option>`;
  let optionsselect = options.replace(
    `"${rows[pos].estado}"`,
    `"${rows[pos].estado}" selected`
  );
  let form =
    `<label class="mshide sysName">${rows[pos].sistema}</label>` +
    `<form class="formEdit needs-validation"><div class="text-start row">` +
    `<input class="mshide" name="id" value="${rows[pos].id}">` +
    `<div class="col-sm-12"><label>Estado</label>` +
    `<select name="estado" id="estado" class="form-select">` +
    `${optionsselect}` +
    `</select></div>` +
    `</form><div class="validation-text"></div>`;
  showModalWindow(
    `Editar PAC de Continuidad del sistema ${rows[pos].sistema}`,
    form,
    function () {
      editarPacContinuidad(rows[pos].id);
    },
    "Cancelar",
    "Guardar"
  );
}

function incluirBotonesContinuidad() {
  $("#tablaPacsContinuidad").bootgrid("destroy");
  let grid = $("#tablaPacsContinuidad")
    .bootgrid({
      ...options,
      formatters: {
        commands: function (column, row) {
          return (
            "<div class='d-flex justify-content-center align-items-center gap-2'>" +
            "<button type='button' class='btn btn-secondary btn-xs btn-default actionPac' title='Gestionar PAC'" +
            "data-row-id='" +
            row.id +
            "' id='" +
            row.id +
            "'><img class='icono ver-pac' alt='Gestionar PAC' src='./img/analitica_blue.svg'></button>" +
            "<button type='button' class='btn btn-secondary btn-xs btn-default responsables' title='Ver responsables' " +
            "data-row-servicio-id='" +
            row.servicioId +
            "' id='" +
            row.id +
            "'><img class='icono' alt='icono Personas' src='./img/responsables_continuidad.svg'></button>"
          );
        },
      },
    })
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".actionPac").on("click", function () {
        let id = $(this).data("row-id");
        modalEditarContinuidad(id);
      });
      grid.find(".responsables").on("click", function () {
        let id = $(this).data("row-servicio-id");
        modalVerResponsables(id);
      });
    });
  addDownloadButtonToTable(".container-tablaPacsContinuidad");
}

function incluirBotonesTablaBIA(data) {
  $("#tablaDatosBIA").bootgrid("append", data);
  addDownloadButtonToTable(".container-tablaBIA");
}

async function handleGetTablaBIA() {
  try {
    cerrarModal();
    $("#tablaDatosBIA").bootgrid("destroy");
    $("#tablaDatosBIA").bootgrid({
      ...options,
      formatters: {
        commands: function (column, row) {
          return (
            "<div class='d-flex justify-content-center align-items-center'>" +
            `<a href='./bia?id=${row.activobia}' class='btn btn-secondary btn-xs btn-default' target="_blank" title="Editar BIA"><img class="icono bia" alt="icono Bia" src="./img/bia_blue.svg"></a>` +
            "</div>"
          );
        },
      },
    });
    finalLoading("#loadingTablaBIA", "loading");

    $("#tablaDatosBIA").addClass("mshide");
    $(".graficaPacs").empty();

    const data = await getTablaBIA();

    if (data.tabla && Array.isArray(data.tabla)) {
      removeClass("#tablaDatosBIA", "mshide");

      const rows = data.tabla.map((fila) => ({
        id_bia: fila.id_bia,
        nombreActivo: fila.nombreActivo,
        activobia: fila.activobia,
        preguntaOcho: fila.respuesta8 || "N/A",
        preguntaNueve: fila.respuesta9 || "N/A",
        preguntaDiez: fila.respuesta10 || "N/A",
      }));

      incluirBotonesTablaBIA(rows);
    } else {
      console.log(data.msg);
    }
    finalLoading("#loadingTablaBIA", "check");
  } catch (error) {
    finalLoading("#loadingTablaBIA", "error");
    console.error("Error al obtener los datos del módulo BIA: ", error);
  }
}

function addAlertCard() {
  let card = `<div id="alertaEval" class="card floating-card" style="width: 18rem;">
                    <div class="card-body">
                        <h5 class="card-title" id="alert-title">Nuevas Evaluaciones</h5>
                        <p class="card-text" id="alert-text">Se han creado <b>nuevas evaluaciones</b> de algunos PACs de continuidad desde la última vez que entraste, <b>checkea las fechas verdes</b>.</p>
                        <div class="text-end">
                            <a class="btn btn-secondary" id="cerrar-alerta">Cerrar</a>
                        </div>
                    </div>
                </div>`;
  $(".container").append(card);
  document
    .getElementById("cerrar-alerta")
    .addEventListener("click", function () {
      $("#alertaEval").remove();
    });
}

function pintarGraficaPacs(item, data) {
  let datosPacs = [
    ["Categoria", "Valor"],
    ["No Iniciado", 0],
    ["Iniciado", 0],
    ["En Progreso", 0],
    ["Finalizado", 0],
    ["Descartado", 0],
  ];
  for (let seguimiento of data) {
    if (seguimiento.Archivado) continue;
    if (seguimiento.estado == "No iniciado") datosPacs[1][1] += 1;
    else if (seguimiento.estado == "Iniciado") datosPacs[2][1] += 1;
    else if (seguimiento.estado == "En Progreso") datosPacs[3][1] += 1;
    else if (seguimiento.estado == "Finalizado") datosPacs[4][1] += 1;
    else if (seguimiento.estado == "Descartado") datosPacs[5][1] += 1;
  }
  let dataGrafica = google.visualization.arrayToDataTable(datosPacs);
  let options = {
    is3D: false,
    pieSliceTextStyle: {
      color: "white",
      fontSize: 12,
    },
    legend: { position: "top", maxLines: 3 },
    height: 400,
    slices: {
      0: { color: "#e03f15" },
      1: { color: "#109619" },
      2: { color: "#97029c" },
      3: { color: "#3266cc" },
      4: { color: "#ffa005" },
    },
  };
  let chart = new google.visualization.PieChart(item[0]);
  chart.draw(dataGrafica, options);
}

function insertDataGrafica(data) {
  pintarGrafica($(".graficaPacs"), "corechart", pintarGraficaPacs, data);
}

async function handleGetPacsContinuidad() {
  try {
    cerrarModal();

    finalLoading("#loadingPacsContinuidad", "loading");
    $("#tablaPacsContinuidad").bootgrid("destroy");
    $("#tablaPacsContinuidad").addClass("mshide");
    $(".graficaPacs").empty();

    const data = await getPacsContinuidad();

    removeClass("#tablaPacsContinuidad", "mshide");
    incluirBotonesContinuidad();
    let alertaCard = false;
    for (let seguimiento of data) {
      if (seguimiento.Archivado) continue;
      let disponibilidad = getDisponibilidadLabel(seguimiento.BIA);
      let fechaLabel = setFechaLabel(seguimiento);
      if (!alertaCard && seguimiento.Alert) {
        alertaCard = true;
        addAlertCard();
      }
      let seguimientoArray = [
        {
          id: seguimiento.id,
          sistemaId: seguimiento.activo_id,
          servicioId: seguimiento.servicio_id,
          servicio: seguimiento.servicio,
          sistema: seguimiento.activo_nombre,
          estado: seguimiento.estado,
          biaDisponibilidad: disponibilidad,
          FechaEval: fechaLabel,
        },
      ];
      $("#tablaPacsContinuidad").bootgrid("append", seguimientoArray);
    }
    insertDataGrafica(data);
    finalLoading("#loadingPacsContinuidad", "check");
  } catch (error) {
    finalLoading("#loadingPacsContinuidad", "error");
    console.error("Se ha producido un error: ", error);
  }
}

function setFechaLabel(seguimiento) {
  let fecha = new Date(seguimiento.fecha_evaluacion);
  let year = fecha.getFullYear();
  let month = (fecha.getMonth() + 1).toString().padStart(2, "0");
  let day = fecha.getDate().toString().padStart(2, "0");
  fecha = `${year}-${month}-${day}`;
  if (seguimiento.Alert) {
    return `<label class='rounded-pill pill fechaNew'>${fecha}</label>`;
  } else return `<label class='rounded-pill pill fechaOld'>${fecha}</label>`;
}

function getDisponibilidadLabel(estado) {
  switch (estado) {
    case "Leve":
      return "<label class='5 rounded-pill pill Leve'>Leve</label>";
    case "Bajo":
      return "<label class='4 rounded-pill pill Bajo'>Bajo</label>";
    case "Moderado":
      return "<label class='3 rounded-pill pill Moderado'>Moderado</label>";
    case "Alto":
      return "<label class='2 rounded-pill pill Alto'>Alto</label>";
    case "Critico":
      return "<label class='1 rounded-pill pill Critico'>Critico</label>";
    default:
      return "<label class='rounded-pill pill Error'>Bia sin calcular</label>";
  }
}

$(document).ready(function () {
  handleGetPacsContinuidad();
  handleGetTablaBIA();
});
