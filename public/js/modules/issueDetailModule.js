import {
  obtenerIssue,
  obtenerComentarios,
  enviarComentario,
  actualizarStatus,
  delIssue,
} from "../api/userApi.js";
import { checkAlertsStatus } from "../api/easApi.js";
import { finalLoading } from "../utils/utils.js";

function getIssueIdFromUrl() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function formatearDescripcion(desc) {
  if (!desc) return "Sin descripción";

  let resultado = desc;

  resultado = resultado.replace(/Resolution:/g, "<br><br>Resolution:");
  resultado = resultado.replace(/Saw ID:/g, "<br><br>Saw ID:");

  resultado = resultado.replace(/ - /g, "<br>- ");

  resultado = resultado.replace(/(\d{1,5}\.\s)/g, "<br>$1");

  return resultado;
}

function formatDate(ms) {
  if (!ms) return "N/A";
  const d = new Date(ms);
  const pad = (num) => num.toString().padStart(2, "0");
  return (
    `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(
      d.getUTCDate()
    )} ` +
    `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(
      d.getUTCSeconds()
    )}`
  );
}
function toCapitalCase(text) {
  return text.toLowerCase().replace(/(?:^|\s)\w/g, function (match) {
    return match.toUpperCase();
  });
}
async function verDetalleIssue(row) {
  try {
    $(".tablaIssues").addClass("mshide");
    mostrarLoading();

    let key = row ? row.id : getIssueIdFromUrl();

    if (!key) {
      console.error("No issue ID provided and none found in URL");
      finalLoading("#issuesLoading", "error");
      return;
    }

    const retorno = await obtenerIssue(key);

    if (!retorno.issues || retorno.issues.length === 0) {
      console.log("No se encontró la issue en la respuesta:", retorno);
      $(".container.pag-IssueDetail").html(`
        <div class="issue-error-container">
          <div class="issue-error">
            <span class="issue-error-label">ERROR</span>
          </div>
          <h2 class="issue-error-subtitle">No se pudo encontrar información sobre la issue solicitada</h2>
          <p class="issue-error-desc">No existe una incidencia con la clave '${key}'</p>
          <a href="/eas?showIssuesTab=1" class="btn btn-primary issue-error-btn">Volver a la lista de issues</a>
        </div>
      `);
      return;
    }

    let issue = retorno.issues[0];
    document.title = `Detalle Issue - ${issue.key}`;
    $(".btn-volver")
      .off("click")
      .on("click", function () {
        window.location.href = "/eas?showIssuesTab=1";
      });

    cerrarModal();
    $(".moduloInfo").removeClass("mshide");
    $(".infoIssue").removeClass("mshide");

    $(".enlaceIssue").attr("href", "https://jira.tid.es/browse/" + issue.key);
    $(".enlaceIssue").text(issue.key);
    $(".infoSummary").text(issue.fields.summary);
    $(".infoEstado").text(issue.fields.status.name);
    $(".infoNivel").text(issue.fields.security.name);

    let clonadaKey = "Sin clonar";
    if (issue.fields.issuelinks?.[0]?.inwardIssue?.key) {
      clonadaKey = issue.fields.issuelinks[0].inwardIssue.key;
      $(".enlaceClonada").attr(
        "href",
        "https://jira.tid.es/browse/" + clonadaKey
      );
      $(".enlaceClonada").text(clonadaKey);
    } else {
      $(".enlaceClonada").text("Sin clonar");
    }

    let resolution = issue.fields.resolution
      ? issue.fields.resolution.name
      : "Sin resolver";
    $(".infoRes").text(resolution);

    if (issue.fields.labels && issue.fields.labels.length > 0) {
      const tagsHtml = issue.fields.labels
        .map((tag) => `<span class="badge text-bg-primary">${tag}</span>`)
        .join(" ");
      $(".infoTag").html(tagsHtml);
    } else {
      $(".infoTag").text("Sin etiquetas");
    }
    const rawDesc = issue.fields.description;
    const descFormateada = formatearDescripcion(rawDesc);
    $(".infoDesc").html(descFormateada);

    if (issue.fields.customfield_25603?.value) {
      $(".infoPrio").text(issue.fields.customfield_25603.value);
    } else {
      $(".infoPrio").text("No se ha podido obtener la prioridad");
    }

    if (issue.fields.customfield_24501?.value) {
      $(".infoProy").text(issue.fields.customfield_24501.value);
    } else {
      $(".infoProy").text("No se ha podido obtener el proyecto");
    }

    let definicion = "Sin definicion";
    if (issue.fields.customfield_25704?.child?.value) {
      definicion = issue.fields.customfield_25704.child.value;
    }
    if (issue.fields.customfield_25704?.value) {
      $(".infoMeto").text(
        issue.fields.customfield_25704.value + " - " + definicion
      );
    } else {
      $(".infoMeto").text(
        "No se ha podido obtener la metodología - " + definicion
      );
    }

    $(".infoCVSS").text(issue.fields.customfield_25703 || "");
    $(".infoVulne").text(issue.fields.customfield_25702 || "");

    if (issue.fields.customfield_12611?.value)
      $(".infoAnalysis").text(issue.fields.customfield_12611.value);
    else
      $(".infoAnalysis").text(
        "No se ha podido obtener información del análisis"
      );

    if (issue.fields.customfield_12610?.value)
      $(".infoProb").text(issue.fields.customfield_12610.value);
    else
      $(".infoProb").text(
        "No se ha podido obtener la información de la probabilidad"
      );

    if (issue.fields.customfield_25603?.value)
      $(".infoImpact").text(issue.fields.customfield_25603.value);
    else
      $(".infoImpact").text(
        "No se ha podido obtener la información del impacto"
      );

    if (issue.fields.customfield_12700?.value)
      $(".infoStatus").text(issue.fields.customfield_12700.value);
    else
      $(".infoStatus").text(
        "No se ha podido obtener la información del estado"
      );
    if (issue.fields.status.name == "Cerrada") {
      $(".btn-openIssue").removeClass("mshide");
      $(".btn-closeIssue").addClass("mshide");
    } else {
      $(".btn-closeIssue").removeClass("mshide");
      $(".btn-openIssue").addClass("mshide");
    }
    $(".infoReference").text(issue.fields.customfield_29100 || "");

    try {
      const infoAlertas = await checkAlertsStatus(issue.key);
      const accordionHtml = buildAlertsAccordionHtml(infoAlertas);
      $(".infoAlertas").html(accordionHtml);

      $("#collapseAlerts").on("shown.bs.collapse", function () {
        $(this).find("table.bootgridable").bootgrid(options);
      });
    } catch (e) {
      console.error("Error al obtener el estado de alertas:", e);
      $(".infoAlertas").text("Error al cargar estado de alertas");
    }

    if (clonadaKey !== "Sin clonar") {
      const retComentarios = await obtenerComentarios(clonadaKey);
      $(".chat").empty();
      if (retComentarios.comments?.length > 0) {
        for (let comentario of retComentarios.comments) {
          $(".chat").append(`
            <div class="card mb-2">
              <div class="card-header writter">
                <h6 class="card-title mb-0 escritor">
                  ${toCapitalCase(comentario.author.displayName)}
                </h6>
              </div>
              <div class="card-body">
                <p class="card-text comentario">${comentario.body}</p>
              </div>
            </div>
          `);
        }
      } else {
        $(".chat").append(`
          <div class="card mb-2">
            <div class="card-header writter">
              <h6 class="card-title mb-0 escritor">ElevenCert</h6>
            </div>
            <div class="card-body">
              <p class="card-text comentario">Esta vulnerabilidad no tiene comentarios.</p>
            </div>
          </div>
        `);
      }
    } else {
      $(".chat").empty();
      $(".chat").append(`
        <div class="card mb-2">
          <div class="card-header writter">
            <h6 class="card-title mb-0 escritor">ElevenCert</h6>
          </div>
          <div class="card-body">
            <p class="card-text comentario">Esta vulnerabilidad no está clonada.</p>
          </div>
        </div>
      `);
    }

    $(".btnEnviarArchivos")
      .off("click")
      .on("click", function () {
        let form = $(".ArchivosForm");
        let formData = new FormData(form[0]);
        $.ajax({
          type: "POST",
          url: `./api/subirArchivos?key=${issue.key}&clonada=${clonadaKey}`,
          xhrFields: {
            withCredentials: true,
          },
          data: formData,
          processData: false,
          contentType: false,
          success: function (retorno, textStatus, request) {
            showModalWindow(
              "Información",
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

    $(".btnEnviarComentario")
      .off("click")
      .on("click", async function () {
        const comentario = $(".commentWritter").val();
        if (comentario.trim() !== "") {
          try {
            const retorno = await enviarComentario(clonadaKey, comentario);
            $(".chat").append(`
            <div class="card mb-2">
              <div class="card-header writter">
                <h6 class="card-title mb-0 escritor">Bot-11cert</h6>
              </div>
              <div class="card-body">
                <p class="card-text comentario">${retorno}</p>
              </div>
            </div>
          `);
          } catch (error) {
            console.error(error);
          }
        }
      });

    cerrarModal();

    $(".btn-status").click(function () {
      let accion;
      if ($(this).hasClass("btn-closeIssue")) accion = "cerrar";
      else accion = "abrir";

      async function cambiarEstado() {
        try {
          const response = await actualizarStatus(accion, issue.key);
          if (response.error && response.message) {
            showModalWindow(
              "Aviso",
              response.message,
              null,
              "Cerrar",
              null,
              null
            );
            return;
          }
          $(".textAbiertas").text("0");
          $(".textCerradas").text("0");
          $(".textRegistradas").text("0");
          $(".textClonadas").text("0");
          obtenerIssues(0, 20, 1);
          if (row) {
            if (accion == "abrir")
              row.Estado =
                "<label class='rounded-pill estado abierto'>Abierta</label>";
            else
              row.Estado =
                "<label class='rounded-pill estado cerrado'>Cerrada</label>";
          }
        } catch (error) {
          console.log(error);
        }
        verDetalleIssue({ id: key });
        cerrarModal();
      }

      let form = `<h5>
                <b>Estas a punto de ${accion} la issue: </b>
              </h5>
              <br>
              <h4>
                ${issue.key}
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

    $(".btn-delIssue").click(function () {
      let form = `<h5>
                <b>Estas a punto de eliminar para siempre: </b>
              </h5>
              <br>
              <h4>
                ${issue.key}
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
        try {
          await delIssue(issue.key);
          location.reload();
        } catch (error) {
          console.log(error);
        }
      });
    });
  } catch (error) {
    console.error("Error al cargar los detalles de la issue:", error);
    finalLoading("#issuesLoading", "error");
  }
}

function buildAlertsAccordionHtml(infoAlertas) {
  const totalRows = infoAlertas.totalRows;
  const items = infoAlertas.items;
  const statusCounts = items.reduce((acc, item) => {
    acc[item.status] = (acc[item.status] || 0) + 1;
    return acc;
  }, {});
  const resolvedCount = statusCounts["resolved"] || statusCounts["closed"] || 0;
  let headerText;
  if (totalRows > 0 && resolvedCount === totalRows) {
    headerText =
      "Todas las alertas se han resuelto, es recomendable cerrar la issue";
  } else {
    headerText = Object.keys(statusCounts)
      .map((status) => `${statusCounts[status]}/${totalRows} alertas ${status}`)
      .join(", ");
  }

  return `
  <div class="accordion" id="accordionAlerts">
    <div class="accordion-item">
      <h2 class="accordion-header" id="headingAlerts">
        <button class="accordion-button collapsed" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapseAlerts"
                aria-expanded="false"
                aria-controls="collapseAlerts">
          ${headerText}
        </button>
      </h2>
      <div id="collapseAlerts" class="accordion-collapse collapse"
           aria-labelledby="headingAlerts"
           data-bs-parent="#accordionAlerts">
        <div class="accordion-body" id="alertsTableContainer">
          ${buildAlertsTableHtml(items)}
        </div>
      </div>
    </div>
  </div>`;
}

function buildAlertsTableHtml(alerts) {
  let html = `
    <table class="table table-striped table-hover table-11cert bootgridable">
      <thead>
        <tr>
          <th data-column-id="alert-id" data-header-css-class="col-alert-id" data-identifier="true">Alert ID</th>
          <th data-column-id="resource-id" data-header-css-class="col-resource-id">Resource ID</th>
          <th data-column-id="resource-name" data-header-css-class="col-resource-name" data-visible="false">Resource Name</th>
          <th data-column-id="status" data-header-css-class="col-status">Status</th>
          <th data-column-id="reason" data-header-css-class="col-reason" data-visible="false">Reason</th>
          <th data-column-id="firstSeen" data-header-css-class="col-firstSeen" data-visible="false">FirstSeen</th>
          <th data-column-id="lastSeen" data-header-css-class="col-lastSeen" data-visible="false">LastSeen</th>
          <th data-column-id="alertTime" data-header-css-class="col-alertTime" data-visible="false">AlertTime</th>
          <th data-column-id="lastUpdated" data-header-css-class="col-lastUpdated">LastUpdated</th>
        </tr>
      </thead>
      <tbody>
  `;

  for (const item of alerts) {
    const fs = formatDate(item.firstSeen);
    const ls = formatDate(item.lastSeen);
    const at = formatDate(item.alertTime);
    const lu = formatDate(item.lastUpdated);

    html += `
      <tr data-row-id="${item.id}">
        <td>${item.id}</td>
        <td>${item.resource.id}</td>
        <td>${item.resource.name}</td>
        <td>${item.status}</td>
        <td>${item.reason}</td>
        <td>${fs}</td>
        <td>${ls}</td>
        <td>${at}</td>
        <td>${lu}</td>
      </tr>
    `;
  }

  html += `
      </tbody>
    </table>
  `;
  return html;
}

document.addEventListener("DOMContentLoaded", function () {
  const issueId = getIssueIdFromUrl();
  if (issueId) {
    verDetalleIssue({ id: issueId });
  }

  $(".btnVerIssue").on("click", function () {
    const id = $(this).data("row-id");
    verDetalleIssue({ id: id });
  });
});

function cerrarModal() {
  $(".modal").modal("hide");
  $("body").removeClass("modal-open");
  $(".modal-backdrop").remove();
}
