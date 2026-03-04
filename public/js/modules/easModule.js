import {
  setCampoAreaProyecto,
  configDesplegablesPentest,
  gestionarResponsable,
} from "../utils/utilsPentest.js";

import {
  getPrismaCloud,
  getPrismaCloudFromTenant,
  getOsaByType,
  saveEvalOsa,
  getOsaEvalByRevision,
  unassignPrismaAlertToReview,
  getAlertasRevisionByID,
  dismissPrismaAlert,
  getPrismaAlertByCloud,
  crearRelacionSuscripcion,
  obtainAlertsByReview,
  getPrismaSusInfo,
  getReviews,
  getReviewById,
  reportJira,
  getIssuesEAS,
  getMailsEAS,
  crearRevision,
  crearRevisionSinActivos,
  getRelacionSuscripcion,
  cerrarRevisionEAS,
  checkAlertsStatus,
} from "../api/easApi.js";

import {
  obtenerIssue,
  obtenerComentarios,
  enviarComentario,
  actualizarStatus,
  delIssue,
} from "../api/userApi.js";

import {
  agregarLoadingHtml,
  serializeForm,
  displayErrorMessage,
  insertBasicLoadingHtml,
  displaySuccessMessage,
  finalLoading,
} from "../utils/utils.js";
import constants from "./constants.js";
import { getHijosTipo } from "../api/serviciosAPI.js";

const options = constants.OPTIONS_TABLE;

let revision;
let issuesTablaIniciada = false;

function asignarPestañas() {
  insertarCarga($(".tablaSuscripciones"), "cargarSuscripciones");

  const newRevisionButtons = `
    <div class="d-flex justify-content-end align-items-center mb-3">
      <button class="btn btn-primary btn-createNewRevisionSinActivos ms-2">
        <i class="fas fa-plus me-1"></i>Create Revision Without Activos
      </button>
    </div>
  `;

  $(".tarjetasRevision").before(newRevisionButtons);

  $(".btn-createNewRevision").on("click", function () {
    formularioCreacionReview(null);
  });

  $(".btn-createNewRevisionSinActivos").on("click", function () {
    formularioCreacionReviewSinActivos();
  });
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelector("#osa-type").addEventListener("change", function () {
    setFormOsa();
  });

  document.querySelector("#send-osa").addEventListener("click", function (e) {
    e.preventDefault();
    e.target.disabled = true;
    buttonSendEvalOsa();
  });

  $(".btn-relSus").on("click", function (e) {
    e.preventDefault();
    let selectedRows = $("#tablaClouds").bootgrid("getSelectedRows");

    if (!selectedRows || selectedRows.length === 0) {
      showModalWindow(
        "Error",
        "Por favor seleccione al menos una suscripción.",
        null,
        "Aceptar",
        null,
        null,
        null,
        "modal-sm"
      );
      return;
    }

    let currentRows = $("#tablaClouds").bootgrid("getCurrentRows");
    let subscriptions = [];

    selectedRows.forEach(function (id) {
      let rowData = currentRows.find((r) => r.id == id);
      if (rowData) {
        subscriptions.push(rowData);
      }
    });

    setCrearRelacion(subscriptions);
  });
});

function setVolverAccounts() {
  $(".volver").click(function (e) {
    const selectAllCheckbox = document.querySelector(
      ".select-all-policy-alerts"
    );
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = false;
    }
    $(".tableAlerts").addClass("mshide");
    $(".alertasReview").addClass("mshide");
    $(".Reporte").addClass("mshide");
    $(".Suscripciones").removeClass("mshide");
    $(".InfoSuscripcion").addClass("mshide");
    $("#moduleContent").removeClass("mshide");
    document.querySelector(".form-osa").classList.add("mshide");
    $(".Revisiones").click();
  });
}

function obtainAlerts(id) {
  const retorno = obtainAlertsByReview(id);
  return retorno;
}

async function alertasAñadidas(id) {
  const response = await getAlertasRevisionByID(id);
  return {
    items: response.alerts.items,
    totalRows: response.alerts.totalRows,
    suscription: response.suscription,
  };
}

async function setAlertasCloud(id) {
  const bootgridOptions = {
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
          "<button type='button' class='btn btn-primary btn-xs btn-default info-alerts' " +
          "data-row-id='" +
          row.id +
          "'>Info</button>" +
          "</div>"
        );
      },
    },
  };

  $("#accordionInfoOpenAlerts").empty();
  $("#accordionClosedAlerts").empty();

  insertarCarga($(".tablaAlertas"), "cargarAlertas");

  const data = await getPrismaAlertByCloud(id);
  $(".cargarAlertas").remove();

  const openPolicies = data.openAlerts;
  const dismissedPolicies = data.dismissedAlerts;

  const accordionOpenHtml = buildAccordionSuscriptionHtml(openPolicies, "Open");
  $("#accordionInfoOpenAlerts").html(accordionOpenHtml);

  const accordionClosedHtml = buildAccordionSuscriptionHtml(
    dismissedPolicies,
    "Closed"
  );
  $("#accordionClosedAlerts").html(accordionClosedHtml);

  $(".accordion-collapse").on("shown.bs.collapse", function () {
    const policyId = $(this).data("policy-id");
    let status = "open";
    if ($(this).closest("#accordionClosedAlerts").length > 0) {
      status = "dismissed";
    }
    if (!$(this).data("loaded")) {
      loadAlertsForPolicy(policyId, id, status);
      $(this).data("loaded", true);
    }
  });

  $(".searcher").off("keyup").on("keyup", searchInAccordions);

  $(".bootgridable")
    .bootgrid(bootgridOptions)
    .on("loaded.rs.jquery.bootgrid", function () {
      $(this)
        .find(".info-alerts")
        .off("click")
        .on("click", function () {
          const policyId = $(this).data("row-id");
          obtainInfoAlerta(policyId);
        });
    });

  $("#abiertasAlert").addClass("show active");
  $("#dismissedAlert").removeClass("show active");
}

function buildAccordionSuscriptionHtml(policies, accordionSuffix) {
  let index = 0;
  let html = "";

  policies.sort((a, b) => {
    const severityA = a.policy.severity;
    const severityB = b.policy.severity;
    return getSeverityPriority(severityA) - getSeverityPriority(severityB);
  });

  policies.forEach((policyObj) => {
    index++;
    const { policy, alertCount } = policyObj;
    const severityLabel = getSeverity(policy.severity);

    html += `
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading${accordionSuffix}${index}">
          <button class="accordion-button collapsed" type="button" 
                  data-bs-toggle="collapse"
                  data-bs-target="#collapse${accordionSuffix}${index}"
                  aria-expanded="false"
                  aria-controls="collapse${accordionSuffix}${index}">
            ${severityLabel} ${policy.name} (${alertCount} alerts)
          </button>
        </h2>
        <div id="collapse${accordionSuffix}${index}" 
             class="accordion-collapse collapse" 
             aria-labelledby="heading${accordionSuffix}${index}"
             data-bs-parent="#accordion${accordionSuffix}Alerts"
             data-policy-id="${policy.policyId}"
             data-policy-name="${policy.name}">
          <div class="accordion-body">
            <div class="table-container" id="table-${policy.policyId}">
              Cargando alertas...
            </div>
          </div>
        </div>
      </div>
    `;
  });

  return html;
}

async function loadAlertsForPolicy(policyId, cloudId, status) {
  try {
    const container = document.getElementById("table-" + policyId);
    container.innerHTML = "Cargando alertas...";
    const response = await fetch(
      `/api/getPrismaAlertsByPolicy?policyId=${encodeURIComponent(
        policyId
      )}&cloudId=${encodeURIComponent(cloudId)}&status=${encodeURIComponent(
        status
      )}`
    );
    const alerts = await response.json();
    container.innerHTML = buildTableHtml(alerts, policyId, policyId);

    // Inicializamos Bootgrid para la tabla recién cargada
    const bootgridOptions = {
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
            "<button type='button' class='btn btn-primary btn-xs btn-default info-alerts' " +
            "data-row-id='" +
            row.id +
            "'>Info</button>" +
            "</div>"
          );
        },
      },
    };

    const tableSelector = `#table-${policyId}-${policyId}`;
    $(tableSelector)
      .bootgrid(bootgridOptions)
      .on("loaded.rs.jquery.bootgrid", function () {
        $(this)
          .find(".info-alerts")
          .off("click")
          .on("click", function () {
            const policyId = $(this).data("row-id");
            obtainInfoAlerta(policyId);
          });
      });
  } catch (error) {
    console.error("Error cargando alertas para policy", policyId, error);
    document.getElementById("table-" + policyId).innerHTML =
      "Error cargando alertas";
  }
}

function buildTableHtml(alerts, policyId, uniqueKey) {
  const tableId = `table-${uniqueKey}-${policyId}`;

  let html = `
    <table id="${tableId}" 
           class="table table-striped table-hover table-11cert bootgridable">
      <thead>
        <tr>
          <th data-column-id="id" data-header-css-class="col-id" data-identifier="true">
            Alert ID
          </th>
          <th data-column-id="Resource" data-header-css-class="col-resources">
            Resource
          </th>
          <th data-column-id="Nombre" data-header-css-class="col-name" data-visible="false">
            Name
          </th>
          <th data-column-id="Severity" data-header-css-class="col-severity" data-order="asc" data-visible="false">
            Severity
          </th>
          <th data-column-id="SawAsociado" data-header-css-class="col-sawAsociado">
            SAW Asociado
          </th>
          <th data-column-id="Region" data-header-css-class="col-region" data-visible="false">
            Region
          </th>
          <th data-column-id="Fecha" data-header-css-class="col-days">
            Days
          </th>
          <th data-column-id="Estado" data-header-css-class="col-estado">
            Status
          </th>
          <th data-column-id="commands"
              data-formatter="commands"
              data-sortable="false"
              data-searchable="false"
              data-header-css-class="col-commands">
            Actions
          </th>
        </tr>
      </thead>
      <tbody>
  `;

  for (let alert of alerts) {
    const now = Date.now();
    const differenceInMillis = now - alert.alertTime;
    const daysPassed = Math.floor(differenceInMillis / (24 * 60 * 60 * 1000));
    const region = alert.resource.region || "Without region";

    let sawAsociado = "🔴 No Saw associated";

    if (
      alert.policy.complianceMetadata &&
      Array.isArray(alert.policy.complianceMetadata)
    ) {
      for (let comp of alert.policy.complianceMetadata) {
        if (
          comp.policyId === alert.policy.policyId &&
          comp.standardName === "11CERT_KPI_Nist80053_rev4"
        ) {
          sawAsociado = "🟢 " + comp.requirementId;
          break;
        }
      }
    }

    html += `
      <tr data-row-id="${alert.id}">
        <td>${alert.id}</td>
        <td>${alert.resource.id}</td>
        <td>${alert.policy.name}</td>
        <td>${getSeverity(alert.policy.severity)}</td>
        <td>${sawAsociado}</td>
        <td>${region}</td>
        <td>${daysPassed} days</td>
        <td>${getStatus(alert.status)}</td>
        <td></td>
      </tr>
    `;
  }

  html += `
      </tbody>
    </table>
  `;
  return html;
}

function searchInAccordions() {
  const query = $(this).val().toLowerCase();
  let $container = $(this).closest(".tab-content");
  if ($container.length === 0) {
    $container = $("#containerAlertsReport");
  }

  const severityMatch = query.match(
    /^severity\s*:\s*(critical|high|medium|low|informational)$/i
  );
  if (severityMatch) {
    const severityFilter = severityMatch[1].toLowerCase();
    $container.find(".accordion-item").each(function () {
      const itemSeverity = ($(this).attr("data-severity") || "").toLowerCase();
      if (itemSeverity === severityFilter) {
        $(this).show();
        $(this)
          .find(".bootgridable")
          .each(function () {
            $(this).bootgrid("search", "");
          });
      } else {
        $(this).hide();
      }
    });
    return;
  }

  if (query === "") {
    $container.find(".accordion-item").show();
    $container.find(".bootgridable").each(function () {
      $(this).bootgrid("search", "");
    });
    return;
  }

  $container.find(".accordion-item").each(function () {
    const $accordion = $(this);
    const headerText = $accordion
      .find(".accordion-button")
      .text()
      .toLowerCase();
    let headerMatches = headerText.indexOf(query) !== -1;
    let anyRowVisible = false;

    $accordion.find(".bootgridable").each(function () {
      $(this).bootgrid("search", headerMatches ? "" : query);
      if ($(this).find("tbody tr:visible").length > 0) {
        anyRowVisible = true;
      }
    });

    if (headerMatches || anyRowVisible) {
      $accordion.show();
    } else {
      $accordion.hide();
    }
  });
}

function getStatus(status) {
  switch (status.toLowerCase()) {
    case "open":
      return "🔴Open";
    case "dismissed":
      return "🟠Dismissed";
    case "resolved":
      return "🟢Resolved ";
    case "añadida":
      return "🟣Added";
    default:
      return "⚪";
  }
}

function getSeverity(severity) {
  switch (severity) {
    case "informational":
      return "<label class='5 rounded-pill pill Leve' style='margin-right: 15px;'>informational </label>";
    case "low":
      return "<label class='4 rounded-pill pill Bajo' style='margin-right: 15px;'>low </label>";
    case "medium":
      return "<label class='3 rounded-pill pill Moderado' style='margin-right: 15px;'>medium </label>";
    case "high":
      return "<label class='2 rounded-pill pill Alto' style='margin-right: 15px;'>high </label>";
    case "critical":
      return "<label class='1 rounded-pill pill Critico' style='margin-right: 15px;'>critical </label>";
    default:
      return "<label class='rounded-pill pill Error' style='margin-right: 15px;'>Without Severity</label>";
  }
}

function getSeverityPriority(severity) {
  const sev = severity.toLowerCase();
  switch (sev) {
    case "critical":
      return 1;
    case "high":
      return 2;
    case "medium":
      return 3;
    case "low":
      return 4;
    case "informational":
      return 5;
    default:
      return 999;
  }
}

function groupByPolicyId(arr) {
  return arr.reduce((acc, alert) => {
    const pName = alert.policy.name;
    const pSeverity = alert.policy.severity;
    const pType = alert.policy.policyType;

    if (!acc[pName]) {
      acc[pName] = {
        policyName: pName,
        policySeverity: pSeverity,
        policyType: pType,
        alerts: [],
      };
    }
    acc[pName].alerts.push(alert);
    return acc;
  }, {});
}

function removeAddedAlertsFromGroup(grouped, alertsAdded) {
  for (const policyId in grouped) {
    const group = grouped[policyId];
    group.alerts = group.alerts.filter(
      (alert) => !alertsAdded.some((ad) => ad.id === alert.id)
    );
  }
  for (const policyId in grouped) {
    if (grouped[policyId].alerts.length === 0) {
      delete grouped[policyId];
    }
  }
}

function handleSelectAllPolicyAlerts(accordionItem, checked) {
  const table = accordionItem.find("table.bootgridable");
  if (table.length) {
    const visibleRowIds = table
      .find("tbody tr:visible")
      .map(function () {
        return $(this).data("row-id");
      })
      .get()
      .filter(Boolean);
    if (checked) {
      if (visibleRowIds.length > 0) {
        table.bootgrid("select", visibleRowIds);
      } else {
        table.bootgrid("select");
      }
    } else {
      table.bootgrid("deselect");
    }
  }
}

function setAlertas(idReview) {
  const bootgridOptions = {
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
          "<button type='button' class='btn btn-primary btn-xs btn-default info-alerts' " +
          "data-row-id='" +
          row.id +
          "'>Info</button>" +
          "</div>"
        );
      },
    },
  };

  $("#containerAlertsOpen").empty();
  $("#containerAlertsClose").empty();
  $("#containerAlertsAdded").empty();

  insertarCarga($(".tablaAlertas"), "cargarAlertas");

  obtainAlerts(idReview).then((data) => {
    alertasAñadidas(idReview).then((añadidas) => {
      const sus = añadidas.suscription;
      if (sus?.id && sus?.name) {
        $("#SuscriptionName").text(sus.name);
        $("#SuscriptionIdText").text(sus.id);
        $(".suscripcion-container").removeClass("mshide");
      } else {
        $(".suscripcion-container").addClass("mshide");
      }
      $(".cargarAlertas").remove();

      const rawOpenAlerts = data.alerts.open;
      const addedAlerts = añadidas;
      const addedIds = new Set(addedAlerts.items.map((alert) => alert.id));
      const openAlerts = rawOpenAlerts.items.filter(
        (alert) => !addedIds.has(alert.id)
      );

      const openByPolicy = groupByPolicyId(openAlerts);
      const addedByPolicy = groupByPolicyId(addedAlerts.items);
      const closedAlerts = data.alerts.close;
      const closeByPolicy = groupByPolicyId(closedAlerts.items);

      const totalPoliciesAdded = Object.keys(addedByPolicy).length;
      const totalAlertsAdded = addedAlerts.totalRows;
      $(".sum-policy-alerts-added").html(
        `There are ${totalPoliciesAdded} policies and a total of ${totalAlertsAdded} alerts.`
      );

      const totalPoliciesOpen = Object.keys(openByPolicy).length;
      const totalAlertsOpen = rawOpenAlerts.totalRows - totalAlertsAdded;
      $(".sum-policy-alerts-open").html(
        `There are ${totalPoliciesOpen} policies and a total of ${totalAlertsOpen} alerts.`
      );

      const totalPoliciesClosed = Object.keys(closeByPolicy).length;
      const totalAlertsClosed = closedAlerts.totalRows;
      $(".sum-policy-alerts-closed").html(
        `There are ${totalPoliciesClosed} policies and a total of ${totalAlertsClosed} alerts.`
      );

      const accordionOpenHtml = buildAccordionReviewHtml(openByPolicy, "Open");
      const accordionCloseHtml = buildAccordionReviewHtml(
        closeByPolicy,
        "Close"
      );
      const accordionAddedHtml = buildAccordionReviewHtml(
        addedByPolicy,
        "Added"
      );

      $("#containerAlertsOpen").html(accordionOpenHtml);
      $("#containerAlertsClose").html(accordionCloseHtml);
      $("#containerAlertsAdded").html(accordionAddedHtml);
      $(".searcher").val("");

      if (
        document.querySelector("#tab-abiertas").hasAttribute("class", "active")
      ) {
        $("#containerAlertsReview").removeClass("mshide");
      }

      if (
        document.querySelector("#tab-added").hasAttribute("class", "active")
      ) {
        $("#añadidas").removeClass("mshide");
      }

      if (
        document.querySelector("#tab-dismissed").hasAttribute("class", "active")
      ) {
        $("#cerradas").removeClass("mshide");
      }

      $(".searcher").on("keyup", searchInAccordions);

      $(".bootgridable")
        .bootgrid(bootgridOptions)
        .on("loaded.rs.jquery.bootgrid", function () {
          $(this)
            .find(".info-alerts")
            .off("click")
            .on("click", function () {
              const id = $(this).data("row-id");
              obtainInfoAlerta(id);
            });
        });

      $(document)
        .off("click", ".select-all-policy-alerts")
        .on("click", ".select-all-policy-alerts", function () {
          const checked = this.checked;
          const header = $(this).closest(".accordion-header");
          const accordionItem = header.closest(".accordion-item");
          const collapse = accordionItem.find(".accordion-collapse");
          const table = accordionItem.find("table.bootgridable");
          const allRowsIds = table.bootgrid().data(".rs.jquery.bootgrid").rows;

          if (!collapse.hasClass("show")) {
            collapse.collapse("show");
          }
          if (checked) {
            table.bootgrid(
              "setSelectedRows",
              allRowsIds.map((row) => row.id)
            );
          } else {
            table.bootgrid("setSelectedRows", []);
          }
        });
    });
  });
}

function buildAccordionReviewHtml(alertsByPolicy, suffix) {
  const entries = Object.entries(alertsByPolicy);

  entries.sort((a, b) => {
    const sevA = a[1].policySeverity;
    const sevB = b[1].policySeverity;
    return getSeverityPriority(sevA) - getSeverityPriority(sevB);
  });

  let index = 0;
  let html = `<div class="accordion" id="accordion${suffix}Alerts">`;

  for (const [policyId, group] of entries) {
    index++;
    const severityTag = getSeverity(group.policySeverity);
    const policyType = group.policyType;
    const policyTypeBadge = `<label class='rounded-pill pill policy-type' style='margin-right: 15px;'>${policyType}</label>`;
    const totalAlerts = group.alerts.length;
    const partialIcon =
      group.addedCount > 0 && group.alerts.length > 0
        ? ' <span title="Aún quedan o han aparecido alertas nuevas por reportar">❗</span>'
        : "";

    const selectAllId = `select-all-${suffix.toLowerCase()}-${index}-${policyId}`;

    html += `
      <div class="accordion-item" data-severity="${group.policySeverity.toLowerCase()}">
        <h2 class="accordion-header d-flex align-items-center" id="heading${suffix}${index}">
          <input type="checkbox" class="select-all-policy-alerts ms-3" id="${selectAllId}" data-table="#subTable-${
      suffix + index
    }-${policyId}" title="Select all alerts in this policy">
          <button class="accordion-button collapsed flex-grow-1"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapse${suffix}${index}"
                  aria-expanded="false"
                  aria-controls="collapse${suffix}${index}">
            ${severityTag} ${policyTypeBadge} ${
      group.policyName
    } (${totalAlerts} alerts)${partialIcon}
          </button>
        </h2>
        <div id="collapse${suffix}${index}" 
             class="accordion-collapse collapse"
             aria-labelledby="heading${suffix}${index}"
             data-bs-parent="#accordion${suffix}Alerts">
          <div class="accordion-body">
            ${buildSubTableHtml(group.alerts, policyId, suffix + index)}
          </div>
        </div>
      </div>
    `;
  }

  html += `</div>`;
  return html;
}

function buildSubTableHtml(alerts, policyId, uniqueSuffix) {
  const tableId = `subTable-${uniqueSuffix}-${policyId}`;

  let html = `
    <table id="${tableId}" 
           class="table table-striped table-hover table-11cert bootgridable">
      <thead>
        <tr>
          <th data-column-id="id" data-header-css-class="col-id" data-identifier="true">
            Alert ID
          </th>
          <th data-column-id="Resource" data-header-css-class="col-resources">
            Resource
          </th>
          <th data-column-id="Nombre" data-header-css-class="col-name" data-visible="false">
            Name
          </th>
          <th data-column-id="Severity" data-header-css-class="col-severity" data-order="asc" data-visible="false">
            Severity
          </th>
          <th data-column-id="SawAsociado" data-header-css-class="col-sawAsociado">
            SAW Asociado
          </th>
          <th data-column-id="Region" data-header-css-class="col-region" data-visible="false">
            Region
          </th>
          <th data-column-id="Fecha" data-header-css-class="col-days">
            Days
          </th>
          <th data-column-id="Estado" data-header-css-class="col-estado">
            Status
          </th>
          <th data-column-id="commands"
              data-formatter="commands"
              data-sortable="false"
              data-searchable="false"
              data-header-css-class="col-commands">
            Actions
          </th>
        </tr>
      </thead>
      <tbody>
  `;

  for (const alert of alerts) {
    const now = Date.now();
    const differenceInMillis = now - alert.alertTime;
    const daysPassed = Math.floor(differenceInMillis / (24 * 60 * 60 * 1000));

    const region = alert.resource?.region || "Without region";

    let sawAsociado = "🔴 No Saw associated";

    if (alert.policy.complianceMetadata !== undefined) {
      for (let comp of alert.policy.complianceMetadata) {
        if (
          comp.policyId === alert.policy.policyId &&
          comp.standardName === "11CERT_KPI_Nist80053_rev4"
        ) {
          sawAsociado = "🟢 " + comp.requirementId;
          break;
        }
      }
    }
    html += `
      <tr data-row-id="${alert.id}">
        <td>${alert.id}</td>
        <td>${alert.resource.id}</td>
        <td>${alert.policy.name}</td>
        <td>${getSeverity(alert.policy.severity)}</td>
        <td>${sawAsociado}</td>
        <td>${region}</td>
        <td>${daysPassed} days</td>
        <td>${getStatus(alert.status)}</td>
        <td></td>
      </tr>
    `;
  }

  html += `
      </tbody>
    </table>
  `;
  return html;
}

function getSelectedRowsFromContainer(containerSelector) {
  let selectedRows = [];
  $(containerSelector)
    .find(".bootgridable")
    .each(function () {
      const rows = $(this).bootgrid("getSelectedRows");
      selectedRows = selectedRows.concat(rows);
    });
  return selectedRows;
}

function obtainInfoAlerta(id) {
  mostrarLoading();
  fetch(`./api/getPrismaAlertInfo?alertId=${id}`, {
    method: "GET",
    credentials: "include",
  })
    .then((response) => response.json())
    .then((retorno) => {
      cerrarModal();
      let alert = retorno.alert;
      const now = Date.now();
      const differenceInMillis = now - alert.alertTime;
      const millisecondsPerDay = 24 * 60 * 60 * 1000;
      const daysPassed = differenceInMillis / millisecondsPerDay;

      let days = Math.floor(daysPassed) + " days";
      let table = `<div class="row">
                            <div class="col-md-12 mb-2">
                              <h4 class="text-start">Alert Information:</h4>
                            </div>
                            <div class="col-md-12">
                              <table class="table">
                                  <tbody>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Alert Name:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.policy.name}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Alert ID:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.id}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Resource Name:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.resource.id}</td>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Type:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.policy.policyType}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Severity:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.policy.severity}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Recomendation:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.policy.recommendation}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Days:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${days}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Status:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${alert.status}</td>
                                    </tr>
                                  </tbody>
                              </table>
                          </div>
                      </div>`;

      showModalWindow("", table, null, "Cerrar", null, null, null, "modal-lm");
    });
}

function incluirBotonesTabla() {
  $("#cloudTable").bootgrid("destroy");
  let options = {
    caseSensitive: false,
    rowSelect: true,
    keepSelection: true,
    selection: true,
    multiSelect: true,
    rowCount: [10, 25, 50, -1],
    labels: {
      noResults: "No results found",
      infos: "Showing {{ctx.start}}-{{ctx.end}} of {{ctx.total}} rows",
      search: "Search",
    },
    formatters: {
      commands: function (column, row) {
        return (
          "<div class='d-flex justify-content-center align-items-center'>" +
          "<button type='button' class='btn btn-primary btn-xs btn-default ver-alerts' " +
          "data-row-id='" +
          row.id +
          "' id='" +
          row.Cloud +
          "'>...</button>" +
          "</div>"
        );
      },
    },
  };
  let grid = $("#tablaClouds")
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".ver-alerts").on("click", function () {
        let id = $(this).data("row-id");
        let cloud = $(this).attr("id");
        mostralInfoSuscripcion(id, cloud);
      });
    });
}

function setInfoAzureGCP(retorno, id) {
  let cloud = retorno.cloud;
  let activos = retorno.activos;
  let nombre_activo;
  let form = "";
  if (!activos || activos.length === 0 || activos === null) {
    nombre_activo = "No Active Associated";
  } else if (Array.isArray(activos)) {
    nombre_activo = activos.map((a) => a.nombre).join(", ");
  } else if (activos?.nombre) {
    nombre_activo = activos.nombre;
  } else {
    nombre_activo = "No Active Associated";
  }
  if (cloud.cloudAccount) {
    $(".ColID").text(cloud.cloudAccount.accountId);
    $(".ColNombre").text(cloud.cloudAccount.name);
    $(".ColOwner").text(cloud.cloudAccount.cloudAccountOwner);

    $(".ColActive").text(nombre_activo);
    form = `<div class="row">
                        <div class="col-md-12 mb-2">
                            <h4>Suscription Information:</h4>
                        </div>
                        <div class="col-md-12">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Suscription ID:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${cloud.cloudAccount.accountId}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Name:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${cloud.cloudAccount.name}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Owner:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${cloud.cloudAccount.cloudAccountOwner}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Cloud:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${cloud.environmentType}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-start col-md-4">
                                            <strong class="text-start">Linked asset:</strong>
                                        </td>
                                        <td class="col-md-6 text-start">${nombre_activo}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <a href="#" class="btn btn-primary crearRevision${id} mx-2">Create review</a>
                            <a href="#" class="btn btn-primary verAlertas${id} mx-2">Alerts</a>
                        </div>
                    </div>`;
  } else {
    $(".ColID").text(cloud.accountId);
    $(".ColNombre").text(cloud.name);
    $(".ColOwner").text("Undefined");
    $(".ColActive").text(nombre_activo);
    form = `<div class="row">
                                    <div class="col-md-12 mb-2">
                                        <h4>Suscription information:</h4>
                                    </div>
                                    <div class="col-md-12">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <td class="text-start col-md-4">
                                                        <strong class="text-start">Suscription ID:</strong>
                                                    </td>
                                                    <td class="col-md-6 text-start">${cloud.accountId}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-start col-md-4">
                                                        <strong class="text-start">Name:</strong>
                                                    </td>
                                                    <td class="col-md-6 text-start">${cloud.name}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-start col-md-4">
                                                        <strong class="text-start">Owner:</strong>
                                                    </td>
                                                    <td class="col-md-6 text-start">Undefined</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-start col-md-4">
                                                        <strong class="text-start">Cloud:</strong>
                                                    </td>
                                                    <td class="col-md-6 text-start">${cloud.cloudType}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-start col-md-4">
                                                        <strong class="text-start">11Cert Active:</strong>
                                                    </td>
                                                    <td class="col-md-6 text-start">${nombre_activo}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <a href="#" class="btn btn-primary crearRevision${id} mx-2">Create review</a>
                                        <a href="#" class="btn btn-primary verAlertas${id} mx-2">Alerts</a>
                                    </div>
                                </div>`;
  }
  return form;
}

function mostralInfoSuscripcion(id, cloud) {
  mostrarLoading();
  getPrismaSusInfo(id, cloud)
    .then((retorno) => {
      cerrarModal();
      let form = setInfoAzureGCP(retorno, id);
      showModalWindow("", form, null, "Cerrar", null, null);
      setCrearRevisionButton(id);
      setVerAlertasCloud(id);
    })
    .catch((error) => {
      console.error("Error en la petición: ", error);
    });
}

function setCrearRevisionButton(id) {
  $(`.crearRevision${id}`).click(async function () {
    mostrarLoading(false);
    try {
      let data = await getRelacionSuscripcion(id);
      if (data.relation) {
        formularioCreacionReview(id);
      } else {
        let currentRows = $("#tablaClouds").bootgrid("getCurrentRows");
        let subscriptions = [];

        let rowData = currentRows.find((r) => r.id == id);
        if (rowData) {
          subscriptions.push(rowData);
        }

        setCrearRelacion(subscriptions, id);
      }
    } catch (error) {
      console.log("Error en la petición: ", error);
    }
  });
}

function formularioCreacionReview(id) {
  let activo = $(".ColActive").text();
  let form = `<form class="issueForm" id="form-newRevision">
                        <div class="form-group mb-4 row">
                            <label for="select7" class="col-4 col-form-label organizacion-label">Activo de 11Cert</label>
                            <div class="col-8 d-flex align-items-center justify-content-center">
                                <label><b>${activo}</b></label>
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
                            <label for="textarea" class="col-4 col-form-label descripcionRevision">Descripción</label>
                            <div class="col-8">
                                <textarea id="textarea" name="Descripcion" maxlength="250" cols="40" rows="5" class="form-control descripcionRevisionInput" aria-describedby="textareaHelpBlock"></textarea>
                                <span id="textareaHelpBlock" class="form-text text-muted textDescription">Descripción revisión (max 250 chars)</span>
                            </div>
                        </div>
                    </form><div id="display-loading"></div>
    <div class="display-check"></div><div class="display-errors"></div>`;
  setCampoAreaProyecto();
  showModalWindow("Nueva revisión", form, function () {
    if (validarRevision()) handleCrearRevision(id);
  });
  $(".descripcionRevisionInput").val("Review_" + activo);
  configDesplegablesPentest("Sistema de Información");
  gestionarResponsable(false);
}

function formularioCreacionReviewSinActivos() {
  let form = `<form class="issueForm" id="form-newRevision">
                <div class="form-group mb-4 row">
                    <label for="revisionName" class="col-4 col-form-label">Nombre de la revisión*</label>
                    <div class="col-8">
                        <input type="text" name="RevisionName" id="revisionName" class="form-control" required>
                    </div>
                </div>
                <div class="form-group mb-4 row">
                    <label for="select7" class="col-4 col-form-label areaServicio-label">Proyecto Jira de área/servicio*</label>
                    <div class="col-8">
                        <select id="areaServ" name="AreaServ" class="form-select areaServicio-input" required="required">
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
                    <label for="textarea" class="col-4 col-form-label descripcionRevision">Descripción</label>
                    <div class="col-8">
                        <textarea id="textarea" name="Descripcion" maxlength="250" cols="40" rows="5" class="form-control descripcionRevisionInput" aria-describedby="textareaHelpBlock"></textarea>
                        <span id="textareaHelpBlock" class="form-text text-muted textDescription">Descripción revisión (max 250 chars)</span>
                    </div>
                </div>
            </form><div id="display-loading"></div>
<div class="display-check"></div><div class="display-errors"></div>`;

  setCampoAreaProyecto();
  showModalWindow("Nueva revisión sin activos", form, function () {
    handleCrearRevisionSinActivos();
  });

  configDesplegablesPentest("Sistema de Información");
  gestionarResponsable(false);
}

function setVerAlertasRevision(revision) {
  cerrarModal();
  const element = document.getElementById("SuscriptionId");
  if (element) {
    element.className = revision.id;
  }

  const nombreRevision = document.querySelector("#SuscriptionId");
  if (nombreRevision) {
    nombreRevision.textContent = `Report Alerts - ${revision.nombre}`;
  }

  $(".Suscripciones").addClass("mshide");
  $(".alertasReview").removeClass("mshide");
  cerrarModal();
  setAlertas(revision.id);
  setGestionAlertasSuscription();
}

async function handleCrearRevision(id) {
  const form = document.getElementById("form-newRevision");

  try {
    $("#botonAceptar").prop("disabled", true);
    document.querySelector(".display-errors").innerHTML = "";
    document.querySelector(".display-check").innerHTML = "";
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    const formData = new FormData(form);

    const formValues = {};
    formData.forEach((value, key) => {
      formValues[key] = value;
    });
    const response = await crearRevision(id, formValues);
    $("#botonAceptar").prop("disabled", false);
    if (response.error) {
      displayErrorMessage(response, ".display-errors");
      document.getElementById("display-loading").innerHTML = "";
    } else {
      document.getElementById("display-loading").innerHTML = "";
      displaySuccessMessage("Review successfully created", ".display-check");
      $("#tablaRevisiones").bootgrid("clear");
      $(".tarjetasRevision").empty();
      await rellenarTablaYTarjetas();
      document.getElementById("RevisionesTab").click();
      setVerAlertasRevision(response.revision);
    }
  } catch (error) {
    $("#botonAceptar").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    displayErrorMessage(error, ".display-errors");
    console.error("Error:", error);
  }
}

function validarRevisionSinActivos() {
  let valid = true;

  const nombreRevision = $("#revisionName");
  if (nombreRevision.val().trim() === "") {
    nombreRevision.addClass("is-invalid");
    valid = false;
  } else {
    nombreRevision.removeClass("is-invalid");
  }

  const areaServ = $("#areaServ");
  if (!areaServ.val() || areaServ.val() === "Ninguno") {
    areaServ.addClass("is-invalid");
    valid = false;
  } else {
    areaServ.removeClass("is-invalid");
  }

  const responsable = $("#asigneeInput");
  if (!responsable.val() || responsable.val().trim() === "") {
    responsable.addClass("is-invalid");
    valid = false;
  } else {
    responsable.removeClass("is-invalid");
  }

  return valid;
}

async function handleCrearRevisionSinActivos() {
  const form = document.getElementById("form-newRevision");

  try {
    if (!validarRevisionSinActivos()) {
      return;
    }

    $("#botonAceptar").prop("disabled", true);
    document.querySelector(".display-errors").innerHTML = "";
    document.querySelector(".display-check").innerHTML = "";
    insertBasicLoadingHtml(document.querySelector("#display-loading"));

    const formData = {
      Nombre: form.querySelector("#revisionName").value,
      ResponsableProy: form.querySelector("#asigneeInput").value,
      Descripcion: form.querySelector("#textarea").value,
      AreaServ: form.querySelector("#areaServ").value,
    };

    const response = await crearRevisionSinActivos(formData);
    $("#botonAceptar").prop("disabled", false);

    if (response.error) {
      displayErrorMessage(response, ".display-errors");
      document.getElementById("display-loading").innerHTML = "";
    } else {
      document.getElementById("display-loading").innerHTML = "";
      cerrarModal();

      $("#tablaRevisiones").bootgrid("clear");
      $(".tarjetasRevision").empty();
      await rellenarTablaYTarjetas(false);
      document.getElementById("RevisionesTab").click();
    }
  } catch (error) {
    $("#botonAceptar").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    displayErrorMessage(error, ".display-errors");
    console.error("Error:", error);
  }
}

function setGestionAlertasRevision() {
  $(".dismissToRevision").click(function () {
    const openRows = getSelectedRowsFromContainer("#containerAlertsOpen");

    if (openRows.length > 0) {
      formDismissAlerts(openRows);
    }
  });

  $(".addToRevision").click(function () {
    const openRows = getSelectedRowsFromContainer("#containerAlertsOpen");

    if (openRows.length > 0) {
      let revision = document.getElementById("SuscriptionId");
      revision = Array.from(revision.classList)[0];

      addAlerts(openRows, revision);
      $("#tab-added").tab("show");
    }
  });

  $(".unassignAlerts").click(function () {
    const addedRows = getSelectedRowsFromContainer("#containerAlertsAdded");

    if (addedRows.length > 0) {
      let revision = document.getElementById("SuscriptionId");
      revision = Array.from(revision.classList)[0];
      unassignAlerts(addedRows, revision);
    }
  });

  $(".btnReportJira").click(async function () {
    let revisionId = document.getElementById("SuscriptionId");
    revisionId = Array.from(revisionId.classList)[0];
    let data = await getReviewById(revisionId);
    console.log(data);

    if (data.revision.status == "Abierta") {
      console.log("Hay que crear la revision");
      avisoCerrarRevision(revisionId);
    } else {
      handleReportAlertas(revisionId);
    }
  });
}

function avisoCerrarRevision(revisionId) {
  let aviso = `<div class="row">
                <div class="col-md-12 mb-2">
                    <h4>¿Cerrar revisión?</h4>
                </div>
                <div class="col-md-12">
                    <p>Para poder reportar las alertas a Jira primero tienes que cerrar la revisión. ¿Cerrar revisión?</p>
                </div>
            </div><div id="display-loading"></div>
    <div class="display-check"></div><div class="display-errors"></div>`;
  showModalWindow(
    "Aviso",
    aviso,
    async function () {
      $("#botonAceptar").prop("disabled", true);
      insertBasicLoadingHtml(document.querySelector("#display-loading"));
      let data = await cerrarRevision(revisionId, true);
      if (!data.error) handleReportAlertas(revisionId);
    },
    "Cerrar",
    "Aceptar",
    null
  );
}

function handleReportAlertas(revisionId) {
  const addedRows = getSelectedRowsFromContainer("#containerAlertsAdded");
  if (addedRows.length > 0) {
    reportarAlertas(revisionId, addedRows);
  } else {
    showModalWindow(
      "Aviso",
      "No has seleccionado ninguna alerta para reportar",
      null,
      "Aceptar",
      null,
      null
    );
  }
}

function setGestionAlertasSuscription() {
  $(".dismissSelectedAlerts").click(function () {
    const selectedRows = getSelectedRowsFromContainer(
      "#accordionInfoOpenAlerts"
    );

    const cloudId = document.getElementById("susId").textContent;

    if (selectedRows.length === 0) {
      showModalWindow("Warning", "No alerts selected.", null, "OK", null, null);
      return;
    }

    formDismissAlertsCloud(selectedRows, cloudId);
  });
}

function formDismissAlertsCloud(alertsArray, cloudId) {
  if (alertsArray.length === 0) {
    showModalWindow("Warning", "No alerts selected.", null, "OK", null, null);
    return; // Salir si no hay alertas seleccionadas
  }
  $("#textarea").val("");
  let form = `<form class="issueForm" id="form-DismissAlert">
                    <div class="mb-4 row alerts text-left">
                        <h3>You are going to dismiss the following alerts:</h3>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="textarea" class="col-4 col-form-label descripcionDismiss">Dismiss description</label>
                        <div class="col-8">
                            <textarea id="textarea" name="Descripcion" maxlength="250" cols="40" rows="5" class="form-control DismissDescriptionInput" aria-describedby="textareaHelpBlock"></textarea>
                            <span id="textareaHelpBlock" class="form-text text-muted textDescription">Dismiss description</span>
                        </div>
                    </div>
                </form>`;

  showModalWindow("Dismiss Alerts", form, function () {
    let comment = $("#textarea").val();
    dismissAlertsCloud(alertsArray, comment, cloudId);
  });

  if (alertsArray.length == 0) {
    $(".alerts").append("<p>No alerts selected.</p>");
  } else {
    alertsArray.forEach((alert) => {
      $(".alerts").append(`<p>Alert ID: ${alert}</p>`);
    });
  }
}

function formDismissAlerts(alertsArray) {
  if (alertsArray.length === 0) {
    showModalWindow("Warning", "No alerts selected.", null, "OK", null, null);
    return; // Salir si no hay alertas seleccionadas
  }
  $("#textarea").val("");
  let form = `<form class="issueForm" id="form-DismissAlert">
                    <div class="mb-4 row alerts text-left">
                        <h3>You are going to dismiss the following alerts:</h3>
                    </div>
                    <div class="form-group mb-4 row">
                        <label for="textarea" class="col-4 col-form-label descripcionDismiss">Dismiss description</label>
                        <div class="col-8">
                            <textarea id="textarea" name="Descripcion" maxlength="250" cols="40" rows="5" class="form-control DismissDescriptionInput" aria-describedby="textareaHelpBlock"></textarea>
                            <span id="textareaHelpBlock" class="form-text text-muted textDescription">Dismiss description</span>
                        </div>
                    </div>
                </form>`;

  showModalWindow("Dismiss Alerts", form, function () {
    let comment = $("#textarea").val();
    dismissAlerts(alertsArray, comment);
  });

  if (alertsArray.length == 0) {
    $(".alerts").append("<p>No alerts selected.</p>");
  } else {
    alertsArray.forEach((alert) => {
      $(".alerts").append(`<p>Alert ID: ${alert}</p>`);
    });
  }
}

function setVerAlertasCloud(id) {
  $(`.verAlertas${id}`).click(function () {
    $(".Suscripciones").addClass("mshide");
    $(".InfoSuscripcion").removeClass("mshide");
    cerrarModal();
    setAlertasCloud(id);
  });
}

function setCrearRelacion(subscriptions, revisionId = null) {
  let tableHtml = `<table class="tabla-suscripciones">
    <tbody>`;

  subscriptions.forEach(function (sub) {
    tableHtml += `<tr>
      <td>${sub.Nombre}</td>
      <td>${sub.Cloud}</td>
      <td>${sub.id}</td>
    </tr>`;
  });

  tableHtml += `</tbody>
  </table>`;

  let subIds = subscriptions.map((s) => s.id);
  let subNames = subscriptions.map((s) => s.Nombre);

  subNames = subNames.map((name) => name.replace("🔴 ", "").replace("🟢 ", ""));

  // Crear los inputs ocultos
  let hiddenInputIds = `<input type="hidden" id="selectedSubscriptions" value="${subIds.join(
    ","
  )}">`;
  let hiddenInputNames = `<input type="hidden" id="selectedSubscriptionsNames" value="${subNames.join(
    ","
  )}">`;

  let form = `
    <form class="issueForm" id="form-relation">
      <div class="mb-3">
        <label class="form-label">Suscripciones seleccionadas:</label>
        ${tableHtml}
        ${hiddenInputIds}
        ${hiddenInputNames}
      </div>
      <div class="form-group mb-4 row">
        <label for="organizacion" class="col-4 col-form-label organizacion-label">Organización</label>
        <div class="col-8">
          <select id="organizacion" name="Organizacion"
                  class="form-select-custom organizacionInput" required>
            <option value="Ninguno">Ninguno</option>
          </select>
        </div>
      </div>

      <div class="form-group mb-4 row direccionBloque mshide">
        <label for="direccion" class="col-4 col-form-label direccion-label">Dirección</label>
        <div class="col-8">
          <select id="direccion" name="Direccion"
                  class="form-select-custom direccionInput">
            <option value="Ninguno">Ninguno</option>
          </select>
          <div id="display-loading-direccion" class="display-loading"></div>
          <small id="direccionError" class="form-text text-danger"></small>
        </div>
      </div>

      <div class="form-group mb-4 row areaBloque mshide">
        <label for="area" class="col-4 col-form-label area-label">Área</label>
        <div class="col-8">
          <select id="area" name="Area"
                  class="form-select-custom areaInput">
            <option value="Ninguno">Ninguno</option>
          </select>
          <div id="display-loading-area" class="display-loading"></div>
          <small id="areaError" class="form-text text-danger"></small>
        </div>
      </div>

      <div class="form-group mb-4 row productoBloque mshide">
        <label for="producto" class="col-4 col-form-label producto-label">Producto o Servicio</label>
        <div class="col-8">
          <select id="producto" name="Producto"
                  class="form-select-custom productoInput">
            <option value="Ninguno">Ninguno</option>
          </select>
          <div id="display-loading-producto" class="display-loading"></div>
          <small id="productoError" class="form-text text-danger"></small>
        </div>
      </div>

      <div class="form-group mb-4 row nombreServicioBloque mshide">
        <label for="nombreServicio" class="col-4 col-form-label nombreServicio-label">Nombre del Servicio</label>
        <div class="col-8">
          <select id="nombreServicio" name="nombreServicio"
                  class="form-select-custom nombreServicioInput">
            <option value="">Seleccione un servicio</option>
          </select>
          <div id="display-loading-nombreServicio" class="display-loading"></div>
        </div>
      </div>

      <div id="display-loading-organizacion" class="display-loading"></div>
      <small id="organizacionError" class="form-text text-danger"></small>

      <div class="form-group row">
        <div class="col-12 text-right">
          <button type="submit" class="btn btn-primary" id="guardarRelacion">Guardar</button>
        </div>
      </div>
    </form>
  `;

  showModalWindow(
    "Crear relación",
    form,
    null,
    "Cerrar",
    null,
    null,
    null,
    "modal-lm"
  );

  $("#form-relation").on("submit", function (e) {
    e.preventDefault();
    createRelation(revisionId);
  });

  initializeRelations();
}

function actualizarFilaVisible(idFila, nuevaData) {
  // Obtener las filas visibles
  let currentRows = $("#tablaClouds").bootgrid("getCurrentRows");

  // Buscar la fila por ID
  let fila = currentRows.find((row) => row.id === idFila);

  if (fila) {
    // Actualizar datos visibles en el array
    Object.assign(fila, nuevaData);

    // Actualizar visualmente el contenido del grid usando jQuery (DOM)
    let $row = $("#tablaClouds").find('tr[data-row-id="' + idFila + '"]');

    if ($row.length > 0) {
      if (nuevaData.Nombre !== undefined) {
        $row.find("td").eq(3).text(nuevaData.Nombre);
      }
    }
  } else {
    console.log("Fila con ID " + idFila + " no visible actualmente.");
  }
}

async function createRelation(revisionId) {
  const esFormularioValido = validarCamposObligatorios();
  if (!esFormularioValido) {
    return;
  }

  let id_activo = $("#nombreServicio").val();
  if (!id_activo || id_activo === "") {
    id_activo = $("#producto").val();
  }

  let suscriptionsIdsStr = $("#selectedSubscriptions").val();
  let suscriptionsNamesStr = $("#selectedSubscriptionsNames").val();

  let subscriptionsIds = suscriptionsIdsStr.split(",");
  let subscriptionsNames = suscriptionsNamesStr.split(",");

  const dataBody = {
    id_activo: id_activo,
    subscriptions: subscriptionsIds,
    subscriptionNames: subscriptionsNames,
  };

  mostrarLoading();

  try {
    const data = await crearRelacionSuscripcion(dataBody);
    if (data.error) {
      throw new Error(data.message);
    }
    const dataForm = data[0];

    if (dataForm.error) {
      showModalWindow("Error", dataForm.message, null, "Cerrar", null, null);
    } else {
      for (let i = 0; i < dataBody.subscriptions.length; i++) {
        const cloud = dataBody.subscriptions[i];
        const name = dataBody.subscriptionNames[i];
        actualizarFilaVisible(cloud, { Nombre: "🟢 " + name });
      }
      if (revisionId) {
        const relation = await getRelacionSuscripcion(
          dataBody.subscriptions[0]
        );
        const name = relation["activo"]["nombre"];
        $(".ColActive").text(name);
        formularioCreacionReview(revisionId);
      } else {
        showModalWindow(
          "Success",
          dataForm.message,
          null,
          "Cerrar",
          null,
          null
        );
      }
    }
  } catch (error) {
    showModalWindow(
      "Error",
      "Error al crear la relación. " + error.message,
      null,
      "Vale",
      null,
      null
    );
  }
}

function setTenantAccounts(cloud, accountgroup) {
  let issuesArray = [];
  getPrismaCloudFromTenant(cloud.accountId)
    .then((retorno) => {
      for (let childCloud of retorno.cloud) {
        let fecha = new Date(childCloud.lastModifiedTs);
        fecha = fecha.toISOString().split("T")[0];

        let iconoAsociacion = childCloud.asociacion ? "🟢Si " : "🔴No ";
        let iconoStatus;
        if (childCloud.status === "error") {
          iconoStatus = "🔴 ";
        } else if (childCloud.status === "ok") {
          iconoStatus = "🟢 ";
        } else {
          iconoStatus = "🟠 ";
        }
        let iconoRevision = childCloud.hasReview ? "🟢Si " : "🔴No ";

        if (childCloud.accountType === "account") {
          let tenantName = childCloud.tenant
            ? childCloud.tenant
            : "No tiene Tenant";
          issuesArray.push({
            id: childCloud.accountId,
            Linked: iconoAsociacion,
            Nombre: childCloud.name,
            Cloud: childCloud.cloudType,
            Fecha: fecha,
            estado: iconoStatus + childCloud.status,
            Revision: iconoRevision,
            AccountGroup: accountgroup,
            nombreTenant: tenantName,
          });
        }
      }
      $("#tablaClouds").bootgrid("append", issuesArray);
      let realizadasLlamadas = parseInt($("#realizadasLlamadas").text()) + 1;
      let totalLlamadas = parseInt($("#totalLlamadas").text());
      $("#realizadasLlamadas").text(realizadasLlamadas);
      if (realizadasLlamadas === totalLlamadas) {
        finalLoading("#loadingClouds", "check");
      }
    })
    .catch((error) => {
      finalLoading("#loadingClouds", "error");
      console.error("Error en la petición: ", error);
    });
}

function configurarTablaClouds() {
  let flag = false;
  getPrismaCloud()
    .then((retorno) => {
      $(".cargarSuscripciones").remove();
      $(".contadores").removeClass("mshide");
      $("#tablaClouds").removeClass("mshide");
      incluirBotonesTabla();

      for (let cloud of retorno.cloud) {
        let fecha = new Date(cloud.lastModifiedTs);
        fecha = fecha.toISOString().split("T")[0];

        let acGroup = cloud.groups?.[0]?.name
          ? cloud.groups[0].name
          : "Sin account group";

        let iconoAsociacion = cloud.asociacion ? "🟢Si " : "🔴No ";
        let iconoStatus;
        if (cloud.status === "error") {
          iconoStatus = "🔴 ";
        } else if (cloud.status === "ok") {
          iconoStatus = "🟢 ";
        } else {
          iconoStatus = "🟠 ";
        }
        let iconoRevision = cloud.hasReview ? "🟢Si " : "🔴No ";
        if (cloud.accountType === "account") {
          let issuesArray = [
            {
              id: cloud.accountId,
              Linked: iconoAsociacion,
              Nombre: cloud.name,
              Cloud: cloud.cloudType,
              Fecha: fecha,
              AccountGroup: acGroup,
              estado: iconoStatus + cloud.status,
              Revision: iconoRevision,
              nombreTenant: "Tenant desconocido",
            },
          ];
          $("#tablaClouds").bootgrid("append", issuesArray);
        } else {
          let totalLlamadas = $("#totalLlamadas").text();
          $("#totalLlamadas").text(parseInt(totalLlamadas) + 1);
          if (!flag) {
            sleep(100).then(() => {
              setTenantAccounts(cloud, acGroup);
              flag = true;
            });
          } else {
            setTenantAccounts(cloud, acGroup);
          }
        }
      }
    })
    .catch((error) => {
      console.error("Error en la petición: ", error);
    });
}

async function dismissAlertsCloud(alertsArray, comment, cloudId) {
  mostrarLoading();
  const response = await dismissPrismaAlert(alertsArray, comment);

  if (!response.error) {
    showModalWindow(
      "Success",
      "Alerts dismissed successfully.",
      null,
      "OK",
      null,
      function () {
        setAlertasCloud(cloudId);
        $(".searcher").val("");
      }
    );
  } else {
    showModalWindow(
      "Error",
      "Failed to dismiss alerts.",
      null,
      "OK",
      null,
      null
    );
  }
}

async function dismissAlerts(alertsArray, comment) {
  mostrarLoading();
  const response = await dismissPrismaAlert(alertsArray, comment);

  if (!response.error) {
    showModalWindow(
      "Success",
      "Alerts dismissed successfully.",
      null,
      "OK",
      null,
      function () {
        const revisionID =
          document.getElementById("SuscriptionId").classList[0];

        $("#containerAlertsOpen").empty();
        $("#containerAlertsClose").empty();
        $("#containerAlertsAdded").empty();
        $(".searcher").val("");

        setAlertas(revisionID);
      }
    );
  } else {
    showModalWindow(
      "Error",
      "Failed to dismiss alerts.",
      null,
      "OK",
      null,
      null
    );
  }
}

async function addAlerts(alertsArray, idRevision) {
  mostrarLoading();

  const body = JSON.stringify({
    alerts: alertsArray,
    idRevision: idRevision,
  });

  try {
    const rawResponse = await fetch(`./api/assignPrismaAlertToReview`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: body,
    });

    if (!rawResponse.ok) {
      const dataError = await rawResponse.json();
      throw new Error(
        dataError.message ||
          "Ocurrió un error inesperado al asignar las alertas."
      );
    }

    const response = await rawResponse.json();

    if (response.error) {
      showModalWindow("Error", response.message, null, "Aceptar", null, null);
    } else {
      const añadidas = await alertasAñadidas(idRevision);

      const addedByPolicy = groupByPolicyId(añadidas.items);

      const accordionAddedHtml = buildAccordionReviewHtml(
        addedByPolicy,
        "Added"
      );

      $("#containerAlertsAdded").html(accordionAddedHtml);
      $(".searcher").val("");

      $(".bootgridable")
        .bootgrid({
          selection: true,
          multiSelect: true,
          rowSelect: true,
          keepSelection: true,
          caseSensitive: false,
          rowCount: [10, 25, 50, -1],
          formatters: {
            commands: function (column, row) {
              return `
                <button type="button"
                        class="btn btn-primary btn-xs info-alerts"
                        data-row-id="${row.id}">
                  Info
                </button>
              `;
            },
          },
        })
        .on("loaded.rs.jquery.bootgrid", function () {
          $(".info-alerts").on("click", function () {
            const id = $(this).data("row-id");
            obtainInfoAlerta(id);
          });
        });

      setAlertas(idRevision);

      showModalWindow(
        "Información",
        response.message,
        null,
        "Aceptar",
        null,
        null
      );
    }
  } catch (error) {
    const errorMessage =
      error.message || "Ocurrió un error inesperado al asignar las alertas.";
    showModalWindow("Error", errorMessage, null, "Aceptar", null, null);
  }
}

async function unassignAlerts(alertsArray, idRevision) {
  mostrarLoading();
  const response = await unassignPrismaAlertToReview(alertsArray, idRevision);

  if (!response.error) {
    const añadidas = await alertasAñadidas(idRevision);

    const addedByPolicy = groupByPolicyId(añadidas.items);

    const accordionAddedHtml = buildAccordionReviewHtml(addedByPolicy, "Added");

    $("#containerAlertsAdded").html(accordionAddedHtml);
    $(".searcher").val("");

    $(".bootgridable")
      .bootgrid({
        selection: true,
        multiSelect: true,
        rowSelect: true,
        keepSelection: true,
        caseSensitive: false,
        rowCount: [10, 25, 50, -1],
        formatters: {
          commands: function (column, row) {
            return `
            <button type="button" class="btn btn-primary btn-xs info-alerts"
                    data-row-id="${row.id}">
              Info
            </button>
          `;
          },
        },
      })
      .on("loaded.rs.jquery.bootgrid", function () {
        $(".info-alerts").on("click", function () {
          const id = $(this).data("row-id");
          obtainInfoAlerta(id);
        });
      });

    setAlertas(idRevision);

    $("#added").removeClass("mshide");
    showModalWindow(
      "Información",
      response.message,
      null,
      "Aceptar",
      null,
      null
    );
  } else {
    showModalWindow("Error", response.message, null, "Aceptar", null, null);
  }
}

function validarRevision() {
  let campos = $("#form-newRevision :input");
  let valid = true;

  for (const element of Array.from(campos)) {
    let campo = $(element);

    // Excluir la validación para la fecha de fin (si es opcional)
    if (campo.attr("name") === "Fecha_final") {
      continue; // Saltar la validación de este campo
    }

    // Verificar si el campo tiene la clase "asignee-input" para excluirlo de la validación
    if (!campo.hasClass("asignee-input")) {
      // Verificar si el campo está vacío o tiene un valor inválido
      if (campo.val() === "" || campo.val() === "Ninguno") {
        valid = false;
        campo.addClass("is-invalid");
      } else {
        campo.removeClass("is-invalid");
      }
    }
  }
  return valid;
}

async function getRevisiones() {
  try {
    const revisiones = await getReviews();
    return revisiones;
  } catch (error) {
    console.error("Error al obtener las revisiones: ", error);
    return null;
  }
}

function mostrarTarjetasSimple(retorno) {
  for (let revision of retorno) {
    let card = `<div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-2 d-flex align-items-center">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <img src="./img/eas.svg" class="img-prueba d-flex align-items-center" alt="Imagen">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="card-title">${revision.nombre}</h5>
                                    <p class="card-text">${revision.descripcion}</p>
                                </div>
                                <div class="col-12 d-flex justify-content-end align-items-center">
                                    <button type="button" class="btn btn-primary btn_eval d-flex align-items-center btn-pen${revision.id}">Acciones</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
    if (revision.status == 1) $(".tarjetasRevision").append(card);
    else if (revision.status == 4) $(".tarjetasPynt").append(card);
    $(`.btn-pen${revision.id}`).click(function () {
      accionesRevision(revision);
    });
  }
}

function accionesRevision(revision) {
  mostrarLoading();
  getReviewById(revision.id)
    .then((retorno) => {
      cerrarModal();
      let botonStatus;
      if (revision.status == 0 || revision.status == 2 || revision.status == 3)
        botonStatus = `<a href="#" class="btn btn-primary btnOpen${revision.id} mx-2">Open review</a>`;
      else
        botonStatus = `<a href="#" class="btn btn-primary btnFin${revision.id} mx-2">Close review</a>`;

      let activos = "";
      for (let activo of retorno.activos) {
        activos += `<p>${activo}</p><br>`;
      }

      let vulns = "";
      if (retorno.vulns.length > 5) {
        let primerasVulns = retorno.vulns.slice(0, 5);
        let restantesVulns = retorno.vulns.slice(5);

        vulns =
          primerasVulns.map((vuln) => `<span>${vuln}</span>`).join(", ") +
          ` <a href="#" class="mostrar-todas-vulns">Ver más</a>`;

        vulns += `<span class="todas-vulns" style="display: none;">
                  , ${restantesVulns
                    .map((vuln) => `<span>${vuln}</span>`)
                    .join(", ")}
                </span>`;
      } else {
        vulns = retorno.vulns.map((vuln) => `<span>${vuln}</span>`).join(", ");
      }

      let botonDocumentoDisabled = vulns.trim() === "" ? "disabled" : "";
      let userEmail = retorno.usuario
        ? retorno.usuario
        : "Usuario sin identificar";

      let form = `<div class="row">
          <div class="col-md-12 mb-2">
            <h4 class="text-start">Review Information:</h4>
          </div>
          <div class="col-md-12">
            <table class="table">
              <tbody>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Review ID:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.id}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Review From:</strong>
                  </td>
                  <td class="col-md-6 text-start">${userEmail}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Name:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.nombre}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Manager:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.resp_revision}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Jira Project:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.proyecto}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Project Manager:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.resp_proyecto}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Init Date:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.fecha_inicio}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Finish Date:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.fecha_final}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Product:</strong>
                  </td>
                  <td class="col-md-6 text-start">${activos}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Alerts:</strong>
                  </td>
                  <td class="col-md-6 text-start">${vulns}</td>
                </tr>
                <tr>
                  <td class="text-start col-md-4">
                    <strong class="text-start">Description:</strong>
                  </td>
                  <td class="col-md-6 text-start">${revision.descripcion}</td>
                </tr>
              </tbody>
            </table>
            <div class="col-md-12">
              <!-- <a href="#" class="btn btn-secondary btnReport${revision.id} mx-2">Report alerts</a> -->
              <!-- <a href="#" class="btn btn-secondary btnDocumento${revision.id} mx-2 ${botonDocumentoDisabled}">Download full document</a> -->
            </div>
            <div class="col-md-12 mt-3">
              <a href="#" class="btn btn-primary btnAlerts${revision.id} mx-2">Report alerts</a>
              <a href="#" class="btn btn-primary editOSAsPen${revision.id} mx-2">OSAs Form</a>
              <a href="#" class="btn btn-primary editPen${revision.id} mx-2">Edit review</a>
              ${botonStatus}
              <a href="#" class="btn btn-primary btnDel${revision.id} btn-delIssue mx-2">Delete</a>
            </div>
          </div>
        </div>`;

      showModalWindow("", form, null, "Cerrar", null, null, null, "modal-lm");

      document
        .querySelector(".mostrar-todas-vulns")
        ?.addEventListener("click", (e) => {
          e.preventDefault();
          document.querySelector(".todas-vulns").style.display = "inline";
          e.target.style.display = "none";
        });

      setBotonesRevision(revision);
    })
    .catch((error) => {
      console.error("Error fetching revision information: ", error);
    });
}

function obtainDate(timestamp) {
  let date = new Date(timestamp);

  let day = date.getUTCDate();
  let month = date.getUTCMonth() + 1;
  let year = date.getUTCFullYear();

  day = day < 10 ? "0" + day : day;
  month = month < 10 ? "0" + month : month;

  let formattedDate = `${day}/${month}/${year}`;
  return formattedDate;
}

function generarReporteJira(revisionId, alertasAsignadas = [], formData = {}) {
  if (!alertasAsignadas.length) {
    showModalWindow(
      "Aviso",
      "No has seleccionado ninguna alerta para reportar.",
      null,
      "Cerrar",
      null,
      null
    );
    return Promise.reject(new Error("Sin alertas seleccionadas"));
  }

  const payload = {
    alertasAsignadas,
    revisionId,
    tags: formData.tags || "",
    observaciones: formData.observaciones || "",
  };

  return reportJira(payload)
    .then((result) => {
      if (result.error) {
        showModalWindow(
          "Error al crear la issue",
          result.message,
          null,
          "Cerrar",
          null,
          null
        );
        throw new Error(result.message);
      } else {
        return result;
      }
    })
    .catch((error) => {
      showModalWindow(
        "Error al crear la issue",
        error.message || "Ocurrió un error inesperado",
        null,
        "Cerrar",
        null,
        null
      );
      throw error;
    });
}

function generarDocumento(
  revisionId,
  alertasSeleccionadas = [],
  formData = {}
) {
  return new Promise((resolve, reject) => {
    if (!revisionId || isNaN(revisionId)) {
      showModalWindow(
        "Error",
        "El ID de la revisión es inválido. Inténtelo nuevamente.",
        null,
        "Cerrar",
        null
      );
      return reject(new Error("ID inválido"));
    }

    mostrarLoading();

    try {
      let url = `./api/generarDocumentoRevision?revisionId=${encodeURIComponent(
        revisionId
      )}`;
      if (alertasSeleccionadas.length) {
        url += `&alertasAsignadas=${encodeURIComponent(
          alertasSeleccionadas.join(",")
        )}`;
      }
      if (formData.observaciones) {
        url += `&observaciones=${encodeURIComponent(formData.observaciones)}`;
      }
      if (formData.emails?.length) {
        url += `&emails=${encodeURIComponent(formData.emails.join(","))}`;
      }
      if (formData.tags) {
        url += `&tags=${encodeURIComponent(formData.tags)}`;
      }

      fetch(url, {
        method: "GET",
        credentials: "include",
      })
        .then((res) => {
          if (!res.ok) {
            return res.json().then((err) => {
              throw new Error(
                err.message ||
                  `Error al generar el documento. Status: ${res.status}`
              );
            });
          }
          return res;
        })
        .then((res) => {
          const cd = res.headers.get("Content-Disposition");
          if (!cd) throw new Error("No se encontró Content-Disposition.");
          const match = /filename="?([^"]+)"?/.exec(cd);
          const fileName = match ? match[1] : `documento_${Date.now()}.docx`;

          return res.blob().then((blob) => {
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = objectUrl;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);

            cerrarModal();
            showModalWindow(
              "Éxito",
              "El documento se ha generado correctamente.",
              null,
              "Cerrar",
              null
            );
            resolve();
          });
        })
        .catch((e) => {
          cerrarModal();
          showModalWindow("Error", e.message, null, "Cerrar", null);
          reject(e);
        });
    } catch (e) {
      cerrarModal();
      showModalWindow("Error", e.message, null, "Cerrar", null);
      reject(e);
    }
  });
}

async function reportarAlertas(revisionId, alertasAsignadas) {
  mostrarLoading();

  const reporte = await new Promise((resolve) => {
    const modalContent = `
    <div class="form-group mb-4 row">
      <label for="observaciones" class="col-4 col-form-label">Observaciones adicionales</label>
      <div class="col-8">
        <textarea id="observaciones" name="observaciones" cols="40" rows="5" class="form-control"></textarea>
      </div>
    </div>
    <div class="form-group mb-4 row">
      <label for="tags" class="col-4 col-form-label">Etiquetas</label>
      <div class="col-8">
        <div id="tags-container" data-simple-tags=""></div>
      </div>
    </div>
    <div class="form-group mb-4 row">
      <label for="mailList" class="col-4 col-form-label" id="mailListLabel" style="cursor: pointer;">
        Correos adicionales que poner en copia
      </label>
      <div class="col-8 position-relative">
        <select id="mailList" class="form-select-custom">
          <option value="Ninguno" selected>(Selecciona un correo)</option>
        </select>
        <div class="input-group mt-2">
          <input type="text" id="manualMailInput" class="form-control" placeholder="usuario@telefonica.com">
          <button type="button" id="addManualMailBtn" class="btn btn-secondary">Añadir</button>
        </div>
        <span id="tooltip" style="display:none; position:absolute; background:#333; color:#fff; padding:5px; border-radius:4px; font-size:12px; top:45px; left:0; z-index:1000;">
          A los correos seleccionados se les enviará un mail con el informe.
        </span>
      </div>
    </div>
    <div class="mb-2">
      <span>Se les enviará el informe a los siguientes usuarios:</span>
    </div>
    <div id="emailListContainer" class="mb-4"></div>
    <div id="emailWarning" class="text-danger" style="display:none; font-size:0.9rem;"></div>
  `;

    showModalWindow(
      "Detalles del reporte",
      modalContent,
      function () {
        const tagsValue = document
          .getElementById("tags-container")
          .getAttribute("data-simple-tags");
        const observaciones = document
          .getElementById("observaciones")
          .value.trim();

        const emailInputs = document.querySelectorAll(
          "#emailListContainer .email-field input"
        );
        const selectedEmails = Array.from(emailInputs)
          .filter((i) => !i.hasAttribute("data-system-email"))
          .map((i) => i.value.trim())
          .filter((e) => e);

        resolve({
          tags: tagsValue,
          observaciones: observaciones,
          emails: selectedEmails,
        });
        cerrarModal();
      },
      "Cerrar",
      "Aceptar",
      null
    );

    const observer = new MutationObserver((mutations, obs) => {
      const tagsDiv = document.getElementById("tags-container");
      if (tagsDiv) {
        new Tags(tagsDiv);
        obs.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    fetchAndPopulateEmails(revisionId);
    const lbl = document.getElementById("mailListLabel");
    const tip = document.getElementById("tooltip");
    if (lbl && tip) {
      lbl.addEventListener("mouseover", () => (tip.style.display = "block"));
      lbl.addEventListener("mouseout", () => (tip.style.display = "none"));
    }
  });
  let resultado;
  try {
    resultado = await generarReporteJira(revisionId, alertasAsignadas, reporte);
  } catch (jiraError) {
    resultado = {
      Error: true,
      Message: jiraError.message || "Error al reportar a Jira",
      Executions: [],
    };
  }

  const { Error: errFlag, Executions, MissingPolicies, Message } = resultado;

  if (errFlag || !Executions?.length) {
    showModalWindow("Atención", Message, null, "Cerrar", null);
    return;
  }

  try {
    await generarDocumento(revisionId, alertasAsignadas, reporte);
  } catch (docError) {
    showModalWindow(
      "Error",
      "Fallo al generar el documento: " + docError.message,
      null,
      "Cerrar",
      null
    );
    return;
  }

  if (Array.isArray(MissingPolicies) && MissingPolicies.length) {
    showModalWindow("Atención", Message, null, "Cerrar", null);
  } else {
    showModalWindow("Éxito", Message, null, "Cerrar", null);
  }
}

function addEmailField(email, removable = true) {
  const container = document.getElementById("emailListContainer");
  const warning = document.getElementById("emailWarning");

  const existing = Array.from(
    container.querySelectorAll("input[type=text]")
  ).map((i) => i.value);
  if (existing.includes(email)) {
    warning.textContent = `El correo "${email}" ya ha sido seleccionado.`;
    warning.style.display = "block";
    return;
  }
  warning.style.display = "none";

  const wrapper = document.createElement("div");
  wrapper.classList.add("email-field", "row", "mb-2");

  const colInput = document.createElement("div");
  colInput.classList.add("col-11", "d-flex", "align-items-center");
  const input = document.createElement("input");
  input.type = "text";
  input.readOnly = true;
  input.classList.add("form-control");
  input.value = email;
  // Marcar los emails no removibles con un atributo especial
  if (!removable) {
    input.setAttribute("data-system-email", "true");
  }
  colInput.appendChild(input);

  const colBtn = document.createElement("div");
  colBtn.classList.add(
    "col-1",
    "d-flex",
    "justify-content-center",
    "align-items-center"
  );
  const btn = document.createElement("button");
  btn.type = "button";
  btn.classList.add("remove-email-btn");
  btn.title = "Eliminar correo";
  btn.innerHTML = "&times;";
  if (!removable) {
    btn.disabled = true;
    btn.classList.add("disabled");
  } else {
    btn.addEventListener("click", () => {
      wrapper.remove();
    });
  }
  colBtn.appendChild(btn);

  wrapper.appendChild(colInput);
  wrapper.appendChild(colBtn);
  container.appendChild(wrapper);
}

function fetchAndPopulateEmails(revisionId) {
  return getMailsEAS("/eas", revisionId)
    .then((emails) => {
      const mailSelect = document.getElementById("mailList");
      if (!mailSelect) {
        console.warn("No existe #mailList");
        return;
      }

      mailSelect.innerHTML = `<option value="Ninguno" selected>(Selecciona un correo)</option>`;

      emails.emails_eas.forEach((email) => {
        const opt = document.createElement("option");
        opt.value = email;
        opt.textContent = email;
        mailSelect.appendChild(opt);
      });

      // Añadir automáticamente "user_email" y "mail_responsable_proyecto" para dar feedback al usuario :)
      if (emails.user_email) {
        addEmailField(emails.user_email, false);
      }
      if (emails.mail_responsable_proyecto) {
        addEmailField(emails.mail_responsable_proyecto, false);
      }

      mailSelect.addEventListener("change", () => {
        const val = mailSelect.value;
        if (val && val !== "Ninguno") {
          addEmailField(val);
          mailSelect.value = "Ninguno";
        }
      });
      const manualInput = document.getElementById("manualMailInput");
      const manualBtn = document.getElementById("addManualMailBtn");
      if (manualInput && manualBtn) {
        manualBtn.addEventListener("click", () => {
          const val = manualInput.value.trim();
          if (val && /^[a-zA-Z0-9._%+-]+@telefonica\.com$/i.test(val)) {
            addEmailField(val);
            manualInput.value = "";
          } else {
            const warning = document.getElementById("emailWarning");
            if (warning) {
              warning.textContent =
                "Solo se permiten correos @telefonica.com válidos.";
              warning.style.display = "block";
            }
          }
        });
      }
    })
    .catch((err) => {
      console.error("Error cargando correos:", err);
    });
}

async function cerrarRevision(revisionId, fromAlerts = null) {
  try {
    let data = await cerrarRevisionEAS(revisionId);
    let closeModal = true;
    if (fromAlerts) {
      closeModal = false;
      $("#botonAceptar").prop("disabled", false);
      document.getElementById("display-loading").innerHTML = "";
      displaySuccessMessage("Review cerrada correctamente", ".display-check");
    }
    $("#tablaRevisiones").bootgrid("clear");
    $(".tarjetasRevision").empty();
    rellenarTablaYTarjetas(closeModal);
    return data;
  } catch (error) {
    if (fromAlerts) {
      $("#botonAceptar").prop("disabled", false);
      document.getElementById("display-loading").innerHTML = "";
      displayErrorMessage(error, ".display-errors");
      console.error("Error:", error);
    }
  }
}

function setBotonesRevision(revision) {
  $(`.btnFin${revision.id}`).click(function (e) {
    mostrarLoading();
    cerrarRevision(revision.id);
  });
  $(`.btnOpen${revision.id}`).click(function (e) {
    mostrarLoading();
    fetch(`./api/reabrirRevision?id=${revision.id}`, {
      method: "GET",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Error al reabrir la revisión.");
        }
        return response.json();
      })
      .then(() => {
        $("#tablaRevisiones").bootgrid("clear");
        $(".tarjetasRevision").empty();
        rellenarTablaYTarjetas();
        cerrarModal();
      })
      .catch((error) => {
        console.error(error);
      });
  });
  $(`.btnAlerts${revision.id}`).click(function (e) {
    cerrarModal();
    const element = document.getElementById("SuscriptionId");
    if (element) {
      element.className = revision.id;
    }

    const nombreRevision = document.querySelector("#SuscriptionId");
    if (nombreRevision) {
      nombreRevision.textContent = `Report Alerts - ${revision.nombre}`;
    }

    $(".Suscripciones").addClass("mshide");
    $(".alertasReview").removeClass("mshide");
    cerrarModal();
    setAlertas(revision.id);
    setGestionAlertasSuscription();
  });

  $(`.editOSAsPen${revision.id}`).click(function (e) {
    let id = revision.id;
    let name = revision.nombre;
    document.querySelector("#name-revision").textContent = name;
    document.querySelector("#id-revision-osa").value = id;
    document.querySelector(".form-osa").classList.remove("mshide");
    document.querySelector(".Suscripciones").classList.add("mshide");
    cerrarModal();
    setFormOsa();
  });

  $(`.editPen${revision.id}`).click(function (e) {
    let form = `<form class="${revision.id}" id="editRevision">
                    <div class="col-md-12">
                      <div class="form-group mb-4 row">
                        <div class="form-group">
                            <label for="fecha" class="fechaInicioRevision">Fecha de inicio:</label>
                            <input type="date" class="form-control fechaInicioInput" id="fechaStart${revision.id}" name="Fecha_inicio">
                        </div>
                      </div>
                      <div class="form-group mb-4 row">
                        <div class="form-group">
                            <label for="fecha" class="fechaFinalRevision">Fecha de final:</label>
                            <input type="date" class="form-control fechaFinalInput" id="fechaEnd${revision.id}" name="Fecha_final">
                        </div>
                      </div>
                      <div class="form-group mb-4 row">
                        <span id="dateError${revision.id}" class="text-danger" style="display:none;">La fecha de inicio no puede ser posterior a la fecha de fin.</span>
                      </div>
                    </div>
                  </form>`;
    showModalWindow("Editar revision", form, editarRevision);
  });
  $(`.btnDel${revision.id}`).click(function (e) {
    $(`.btnDel${revision.id}2`).off("click");
    let form = `<h5>
                  <b>Estas a punto de eliminar para siempre: </b>
              </h5>
              <br>
              <h4>
                  ${revision.nombre}
              </h4>
              <h4>
                  ${revision.descripcion}
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
                      <button class="btn btn-primary btnDel${revision.id}2 btnDEL">ELIMINAR</button>
                  </div>
              </div>`;
    showModalWindow("¿Eliminar revision?", form, null, null, null);
    $(`.btnDel${revision.id}2`).click(function (e) {
      mostrarLoading();
      fetch(`./api/eliminarRevision?id=${revision.id}`, {
        method: "GET",
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Error al eliminar la revisión.");
          }
          return response.json();
        })
        .then((retorno) => {
          if (retorno.error)
            showModalWindow(
              "Información",
              retorno.message,
              null,
              "Aceptar",
              null,
              null
            );
          else {
            $("#tablaRevisiones").bootgrid("clear");
            $(".tarjetasRevision").empty();
            rellenarTablaYTarjetas();
            cerrarModal();
          }
        })
        .catch((error) => {
          console.error(error);
        });
    });
    $(`.btnCancel`).click(function (e) {
      cerrarModal();
    });
  });
}

function editarRevision() {
  let form = $("#editRevision");
  let id = $("#editRevision").attr("class");
  let startDate = new Date($(`#fechaStart${id}`).val());
  let endDate = new Date($(`#fechaEnd${id}`).val());

  // Contenedor para el mensaje de error
  let errorMessage = $(`#dateError${id}`);

  if (startDate > endDate || startDate.getTime() === endDate.getTime()) {
    errorMessage.text(
      "Error: La fecha de inicio no puede ser posterior o igual a la fecha de fin."
    );
    errorMessage.css("color", "red");
    errorMessage.show();
    return; // Evitar la llamada a la API
  } else {
    errorMessage.hide();
  }

  // Si la validación pasa, continuar con la llamada a la API
  let dates = form.serialize() + "&" + `id=${id}`;
  mostrarLoading();

  fetch(`./api/editRevision`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: dates,
    credentials: "include",
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error("Error al editar la revisión.");
      }
      return response.json();
    })
    .then(() => {
      $("#tablaRevisiones").bootgrid("clear");
      $(".tarjetasRevision").empty();
      rellenarTablaYTarjetas();
      cerrarModal();
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}

function configurarBotonesTablaRevision(retorno) {
  $("#tablaRevisiones").bootgrid("destroy");
  let options = {
    caseSensitive: false,
    rowSelect: true,
    labels: {
      noResults: "No results found",
      infos: "Showing {{ctx.start}}-{{ctx.end}} of {{ctx.total}} rows",
      search: "Search",
    },
    formatters: {
      commands: function (column, row) {
        return (
          "<div>" +
          "<button type='button' class='btn btn-secondary commands-edit' " +
          "data-row-id='" +
          row.Id +
          "'>" +
          "<img src='./img/more.svg' alt='More' class='moreButton'></img>" +
          "</button>" +
          "</div>"
        );
      },
    },
  };
  let grid = $("#tablaRevisiones")
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".commands-edit").on("click", function () {
        let id = $(this).data("row-id");
        for (let revision of retorno) {
          if (revision.id == id) {
            accionesRevision(revision);
          }
        }
      });

      grid.find(".commands-osa").on("click", function () {
        let id = $(this).data("row-id");
        let name = grid
          .bootgrid("getCurrentRows")
          .find((row) => row.Id == id).Nombre;
        document.querySelector("#name-revision").textContent = name;
        document.querySelector("#id-revision-osa").value = id;
        document.querySelector(".form-osa").classList.remove("mshide");
        document.querySelector("#content-eas").classList.add("mshide");
        setFormOsa();
      });
    });
}

function llenarTablaRevision(retorno) {
  configurarBotonesTablaRevision(retorno);
  let osa_formated = "";
  let estado = "";
  for (let revision of retorno) {
    if (revision.status == 1)
      estado = "<label class='rounded-pill estado abierto'>Opened</label>";
    else estado = "<label class='rounded-pill estado cerrado'>Closed</label>";

    if (revision.osa !== null) {
      osa_formated =
        "<label class='rounded-pill numberEstado cerrado'>SI </label>";
    } else {
      osa_formated =
        "<label class='rounded-pill numberEstado abierto'>NO </label>";
    }

    const cloudName = revision.cloudName || "";

    let revisionArray = [
      {
        Id: revision.id,
        Nombre: revision.nombre,
        Proyecto: revision.proyecto,
        CloudId: revision.cloudId,
        CloudName: cloudName,
        osa: osa_formated,
        FechaInicio: revision.fecha_inicio,
        FechaFinal: revision.fecha_final,
        Estado: estado,
        Responsable: revision.user_email,
      },
    ];
    $("#tablaRevisiones").bootgrid("append", revisionArray);
  }
}

function rellenarTablaYTarjetas(closeModal = true) {
  finalLoading("#reviewsLoading", "loading");
  $("#tablaRevisiones").addClass("mshide");
  return getRevisiones().then((data) => {
    mostrarTarjetasSimple(data);
    llenarTablaRevision(data);
    $("#tablaRevisiones").removeClass("mshide");
    finalLoading("#reviewsLoading", "check");
    if (closeModal) {
      cerrarModal();
    }
  });
}

async function buttonSendEvalOsa() {
  const form = document.querySelector("#form-data-osa");
  const serializedData = serializeForm(form);
  const response = await saveEvalOsa(serializedData);
  if (response.error) {
    showModalWindow(
      "Error",
      response.message,
      null,
      "Cerrar",
      null,
      null,
      null
    );
  } else {
    showModalWindow(
      "Información",
      response.message,
      null,
      "Cerrar",
      null,
      reloadRevision,
      null
    );
  }
  document.querySelector("#send-osa").disabled = false;
}

function reloadRevision() {
  document.querySelector(".form-osa").classList.add("mshide");
  document.querySelector(".Suscripciones").classList.remove("mshide");
  finalLoading("#reviewsLoading", "loading");
  $("#tablaRevisiones").addClass("mshide");
  getRevisiones().then((data) => {
    llenarTablaRevision(data);
    finalLoading("#reviewsLoading", "check");
  });
}

async function setFormOsa() {
  let osaList = document.querySelector(".osa-list");
  osaList.innerHTML = "";
  agregarLoadingHtml(osaList);

  let type = document.querySelector("#osa-type").value;
  let revisionId = document.querySelector("#id-revision-osa").value;

  // Ejecutar las llamadas asincrónicas en paralelo
  const [osas, osaEval] = await Promise.all([
    getOsaByType(type),
    getOsaEvalByRevision(revisionId),
  ]);
  osaList.innerHTML = "";
  if (document.querySelector(".osa-list > .spinner-animation")) {
    document.querySelector(".osa-list > .spinner-animation").remove();
  }

  osas.OSA.forEach((osa) => {
    const label = document.createElement("label");
    label.setAttribute("for", "selector1");
    label.className = "col-sm-4 col-form-label mt-2 mb-2 osa-label";
    label.textContent = `${osa.cod} - ${osa.name}`;
    const infoIcon = document.createElement("img");
    infoIcon.src = "/img/info.svg";
    infoIcon.alt = "Info";
    infoIcon.className = "ms-2 info-icon";
    infoIcon.style.cursor = "pointer";
    infoIcon.setAttribute("data-bs-toggle", "tooltip");
    infoIcon.setAttribute("title", osa.description);

    label.appendChild(infoIcon);

    const div = document.createElement("div");
    div.className = "col-sm-2";

    const select = document.createElement("select");
    select.className = "form-select osa-select";
    select.name = osa.cod;
    select.id = `selector-${osa.cod}`;
    let codigo = osa.cod;
    for (let i = 0; i <= 6; i++) {
      const option = document.createElement("option");
      option.value = i;
      option.textContent = i;
      if (osaEval.OSA[codigo] == i) {
        option.selected = true;
      }
      select.appendChild(option);
    }
    div.appendChild(select);
    osaList.appendChild(label);
    osaList.appendChild(div);
  });
}

async function configurarTablaIssues() {
  // Mostramos la sección de issues
  $("#issues").removeClass("mshide");
  $(".tablaIssues").removeClass("mshide");

  $(".contClonadas")
    .attr("title", "Ver issues clonadas cerradas")
    .tooltip({ placement: "top" });

  // Botones para alternar vistas Clonadas / Registradas
  $(".contClonadas").on("click", function () {
    $(".ClonadasCerradas").removeClass("mshide");
    $(".issuesOriginales").addClass("mshide");
    $(".contClonadas").addClass("sombraClonadas");
    $(".contRegistradas").removeClass("sombraRegistro");
  });

  $(".contRegistradas").on("click", function () {
    $(".issuesOriginales").removeClass("mshide");
    $(".ClonadasCerradas").addClass("mshide");
    $(".contRegistradas").addClass("sombraRegistro");
    $(".contClonadas").removeClass("sombraClonadas");
  });

  $("#issuesTabla")
    .bootgrid({
      caseSensitive: false,
      rowCount: [10, 25, 50, -1],
      formatters: {
        commands: function (column, row) {
          return `
      <button class="btn btn-primary btnVerIssueTab" data-row-id="${row.id}" title="Abrir en nueva pestaña">
        Ver
      </button>
    `;
        },
      },
    })
    .on("loaded.rs.jquery.bootgrid", function () {
      $(".btnVerIssueTab")
        .off("click")
        .on("click", function () {
          const id = $(this).data("row-id");
          window.open(`/issueDetail?id=${encodeURIComponent(id)}`, "_blank");
        });
    });

  $("#cerradasClonadasTabla")
    .bootgrid({
      caseSensitive: false,
      rowCount: [10, 25, 50, -1],
      formatters: {
        commands: function (column, row) {
          return `
            <button class="btn btn-primary btnVerIssue" data-row-id="${row.id}">
              Ver
            </button>
          `;
        },
      },
    })
    .on("loaded.rs.jquery.bootgrid", function () {
      $(".btnVerIssue")
        .off("click")
        .on("click", function () {
          const id = $(this).data("row-id");
          const row = { id: id };
          verDetalleIssue(row);
        });
    });

  try {
    await obtenerIssues(0, 20, 1);
  } catch (err) {
    console.error("Error al configurar tabla issues:", err);
  }

  $(".btn-volver")
    .off("click")
    .on("click", async function () {
      try {
        $(".moduloInfo").addClass("mshide");
        $(".infoIssue").addClass("mshide");
        $(".btn-volver").addClass("mshide");
        $(".tablaIssues").removeClass("mshide");
      } catch (error) {
        console.error("Error al volver a la tabla:", error);
      }
    });
}

async function obtenerIssues(startAt, maxResults, chunkNumber) {
  try {
    finalLoading("#issuesLoading", "loading");
    $("#issuesTabla").bootgrid("clear");
    $("#cerradasClonadasTabla").bootgrid("clear");
    const data = await getIssuesEAS(startAt, maxResults);
    const issues = data.issues || [];
    const rowsParaTabla = issues.map(mapIssueToRowEas);

    $("#issuesTabla").bootgrid("append", rowsParaTabla);

    const nextStartAt = startAt + issues.length;
    const totalIssues = data.total || nextStartAt;

    if (nextStartAt < totalIssues && chunkNumber < 3) {
      await obtenerIssues(nextStartAt, maxResults, chunkNumber + 1);
    } else {
      finalLoading("#issuesLoading", "check");
    }
  } catch (error) {
    console.error("Error en obtenerIssues (chunk #" + chunkNumber + ")", error);
    finalLoading("#issuesLoading", "error");
  }
}

function mapIssueToRowEas(issue) {
  let totalReg = parseInt($(".textRegistradas").text()) || 0;
  $(".textRegistradas").text(totalReg + 1);

  const mainStatus = issue.fields?.status?.name || "";
  let estado = "";
  if (mainStatus === "Cerrada" || mainStatus === "Resuelta") {
    estado = "<label class='rounded-pill estado cerrado'>Cerrada</label>";
    let cerradas = parseInt($(".textCerradas").text()) || 0;
    $(".textCerradas").text(cerradas + 1);
  } else if (mainStatus === "Abierta") {
    estado = "<label class='rounded-pill estado abierto'>Abierta</label>";
    let abiertas = parseInt($(".textAbiertas").text()) || 0;
    $(".textAbiertas").text(abiertas + 1);
  } else {
    estado = `<label class='rounded-pill estado otroEstado'>${mainStatus}</label>`;
  }

  let infoClonada =
    "<label class='rounded-pill estado sinClonar'>Sin clonar</label>";
  if (issue.fields?.issuelinks?.[0]?.inwardIssue) {
    const clonStatus =
      issue.fields.issuelinks[0].inwardIssue.fields?.status?.name;
    if (clonStatus === "Cerrada" || clonStatus === "Resuelta") {
      infoClonada =
        "<label class='rounded-pill estado cerrado'>Cerrada</label>";

      if (mainStatus !== "Cerrada" && mainStatus !== "Resuelta") {
        let clonadas = parseInt($(".textClonadas").text()) || 0;
        $(".textClonadas").text(clonadas + 1);
      }
    } else if (clonStatus === "Abierta") {
      infoClonada =
        "<label class='rounded-pill estado abierto'>Abierta</label>";
    } else if (clonStatus) {
      infoClonada = `<label class='rounded-pill estado otroEstado'>${clonStatus}</label>`;
    }
  }

  let clon;
  if (issue.fields.issuelinks[0]?.inwardIssue?.key) {
    clon = issue.fields.issuelinks[0].inwardIssue.key;
  } else {
    clon = "Sin clonar";
  }
  const keyPlain = issue.key || "SIN-KEY";
  let keyLink = `<a class='ID-nombre' href='https://jira.tid.es/browse/${issue.key}' target="_blank">${issue.key}</a>`;
  clon =
    `<a class='ID-clon' href='https://jira.tid.es/browse/${clon}' target="_blank">` +
    clon +
    `</a>`;
  const resumen = issue.fields?.summary || "";
  const proyecto = issue.fields?.customfield_24501?.value || "";
  const responsable = issue.fields?.reporter?.displayName || "";
  const review = issue.reviewName || "";

  const cloudId = issue.cloudId || "";
  const cloudName = issue.cloudName || "";

  const fecha = issue.fields?.created || "";
  let dias = "";
  if (fecha) {
    const createdDate = new Date(fecha).getTime();
    dias = Math.floor((Date.now() - createdDate) / (1000 * 60 * 60 * 24));
  }

  let prio;
  const custom25603 = issue.fields?.customfield_25603?.value;
  if (!custom25603) {
    prio = "<label>Sin prioridad</label>";
  } else if (custom25603 === "Low") {
    prio =
      "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped low'></div>";
  } else if (custom25603 === "Medium") {
    prio =
      "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped medium'></div>";
  } else if (custom25603 === "Major") {
    prio =
      "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped major'></div>";
  } else {
    prio =
      "<div class='rounded-pill ID-prioridad progress-bar progress-bar-striped critical'></div>";
  }

  let issuesArray = [
    {
      id: keyPlain,
      issueLink: keyLink + "<br>" + clon,
      CloudId: cloudId,
      CloudName: cloudName,
      Estado: estado,
      EstadoClonada: infoClonada,
      Resumen: resumen,
      Proyecto: proyecto,
      Responsable: responsable,
      Review: review,
      Fecha: fecha,
      Dias: dias + " dias",
      Prioridad: prio,
    },
  ];
  if (
    infoClonada ==
      "<label class='rounded-pill estado cerrado'>Cerrada</label>" &&
    estado != "<label class='rounded-pill estado cerrado'>Cerrada</label>"
  ) {
    $("#cerradasClonadasTabla").bootgrid("append", issuesArray);
  }
  return {
    id: keyPlain,
    issueLink: keyLink + "<br>" + clon,
    CloudId: cloudId,
    CloudName: cloudName,
    Estado: estado,
    EstadoClonada: infoClonada,
    Resumen: resumen,
    Proyecto: proyecto,
    Responsable: responsable,
    Review: review,
    Fecha: fecha,
    Dias: dias + " dias",
    Prioridad: prio,
  };
}

async function verDetalleIssue(row) {
  try {
    $(".tablaIssues").addClass("mshide");
    mostrarLoading();

    let key = row.id;
    const retorno = await obtenerIssue(key);

    if (!retorno.issues || retorno.issues.length === 0) {
      console.log("No se encontró la issue en la respuesta:", retorno);
      return;
    }

    let issue = retorno.issues[0];

    cerrarModal();
    $(".btn-volver").removeClass("mshide");
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

    // Etiquetas
    if (issue.fields.labels && issue.fields.labels.length > 0) {
      $(".infoTag").text(issue.fields.labels.join(", "));
    } else {
      $(".infoTag").text("Sin etiquetas");
    }

    // Descripción: formatear (ej. con reemplazos de salto de línea)
    const rawDesc = issue.fields.description;
    const descFormateada = formatearDescripcion(rawDesc);
    $(".infoDesc").html(descFormateada);

    // Prioridad (customfield_25603)
    if (issue.fields.customfield_25603?.value) {
      $(".infoPrio").text(issue.fields.customfield_25603.value);
    } else {
      $(".infoPrio").text("No se ha podido obtener la prioridad");
    }

    // Proyecto (customfield_24501)
    if (issue.fields.customfield_24501?.value) {
      $(".infoProy").text(issue.fields.customfield_24501.value);
    } else {
      $(".infoProy").text("No se ha podido obtener el proyecto");
    }

    // Metodología (customfield_25704)
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

    // Otros customfields (CVSS, Vulne, etc.)
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

    // Comentarios si está clonada
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
      // No clonada => sin comentarios
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

    // Subir archivos
    $(".btnEnviarArchivos")
      .off("click")
      .on("click", function () {
        mostrarLoading();
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

    // Enviar comentario
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
        mostrarLoading();

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
          if (accion == "abrir")
            row.Estado =
              "<label class='rounded-pill estado abierto'>Abierta</label>";
          else
            row.Estado =
              "<label class='rounded-pill estado cerrado'>Cerrada</label>";
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
        mostrarLoading();
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
  // Contar estados
  const statusCounts = items.reduce((acc, item) => {
    acc[item.status] = (acc[item.status] || 0) + 1;
    return acc;
  }, {});
  // Determinar texto del header
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

async function obtenerOrganizaciones() {
  const loadOrg = document.getElementById("display-loading-organizacion");
  loadOrg.innerHTML = "";
  insertBasicLoadingHtml(loadOrg);

  try {
    const res = await fetch("./api/getOrganizaciones", {
      method: "GET",
      credentials: "include",
    });
    const ret = await res.json();
    const $sel = $(".organizacionInput")
      .empty()
      .append('<option value="Ninguno">Ninguno</option>');
    ret.Organizaciones.forEach((o) => {
      $sel.append(
        `<option value="${o.id}" data-nombre="${o.nombre}">${o.nombre}</option>`
      );
    });
    $sel.val("27384").change();
  } catch (err) {
    console.error("Error al cargar organizaciones:", err);
  } finally {
    loadOrg.innerHTML = "";
  }
}

async function handleOrganizacionChange() {
  const loadOrg = document.getElementById("display-loading-organizacion");
  const loadDir = document.getElementById("display-loading-direccion");
  const loadArea = document.getElementById("display-loading-area");
  const loadProd = document.getElementById("display-loading-producto");
  const loadServ = document.getElementById("display-loading-nombreServicio");

  [loadOrg, loadDir, loadArea, loadProd, loadServ].forEach(
    (c) => (c.innerHTML = "")
  );
  insertBasicLoadingHtml(loadOrg);

  $("#direccion, #area, #producto")
    .empty()
    .append('<option value="Ninguno">Ninguno</option>');
  $("#nombreServicio").empty();
  $(
    ".direccionBloque, .areaBloque, .productoBloque, .nombreServicioBloque"
  ).addClass("mshide");

  const orgId = $("#organizacion").val();
  if (orgId === "Ninguno") {
    loadOrg.innerHTML = "";
    return;
  }

  try {
    insertBasicLoadingHtml(loadDir);
    let ret = await (
      await fetch(`./api/getDirecciones?organizacionId=${orgId}`, {
        method: "GET",
        credentials: "include",
      })
    ).json();
    loadDir.innerHTML = "";

    if (ret.Direcciones?.length) {
      const $sel = $("#direccion")
        .empty()
        .append('<option value="Ninguno">Ninguno</option>');
      ret.Direcciones.forEach((d) => {
        $sel.append(
          `<option value="${d.id}" data-nombre="${d.nombre}">${d.nombre}</option>`
        );
      });
      $(".direccionBloque").removeClass("mshide");
      return;
    }

    insertBasicLoadingHtml(loadArea);
    let ret2 = await getHijosTipo(orgId, null, "Área");
    loadArea.innerHTML = "";

    if (ret2.Hijos?.length) {
      const $selA = $("#area")
        .empty()
        .append('<option value="Ninguno">Ninguno</option>');
      ret2.Hijos.forEach((a) => {
        $selA.append(
          `<option value="${a.id}" data-nombre="${a.nombre}">${a.nombre}</option>`
        );
      });
      $(".areaBloque").removeClass("mshide");
      return;
    }

    insertBasicLoadingHtml(loadProd);
    let ret3 = await getHijosTipo(orgId, null, "Producto");
    loadProd.innerHTML = "";

    if (ret3.Hijos?.length) {
      const $selP = $("#producto")
        .empty()
        .append('<option value="Ninguno">Ninguno</option>');
      ret3.Hijos.forEach((p) => {
        $selP.append(
          `<option value="${p.id}" data-nombre="${p.nombre}">${p.nombre}</option>`
        );
      });
      $(".productoBloque").removeClass("mshide");
      return;
    }

    insertBasicLoadingHtml(loadServ);
    let ret4 = await getHijosTipo(orgId, null, "Servicio de Negocio");
    loadServ.innerHTML = "";

    if (ret4.Hijos?.length) {
      const $selS = $("#nombreServicio")
        .empty()
        .append('<option value="">Seleccione un servicio</option>');
      ret4.Hijos.forEach((s) => {
        $selS.append(`<option value="${s.id}">${s.nombre}</option>`);
      });
      $(".nombreServicioBloque").removeClass("mshide");
    }
  } catch (err) {
    console.error("Error en handleOrganizacionChange:", err);
  } finally {
    loadOrg.innerHTML = "";
  }
}

async function handleDireccionChange() {
  const loadArea = document.getElementById("display-loading-area");
  loadArea.innerHTML = "";
  insertBasicLoadingHtml(loadArea);

  $("#area").empty().append('<option value="Ninguno">Ninguno</option>');
  if ($("#direccion").val() !== "Ninguno") {
    try {
      let ret = await getHijosTipo($("#direccion").val(), null, "Área");
      const $sel = $("#area")
        .empty()
        .append('<option value="Ninguno">Ninguno</option>');
      ret.Hijos?.forEach((a) => {
        $sel.append(
          `<option value="${a.id}" data-nombre="${a.nombre}">${a.nombre}</option>`
        );
      });
      $(".areaBloque").removeClass("mshide");
    } catch (err) {
      console.error("Error al cargar áreas:", err);
    }
  } else {
    $(".areaBloque").addClass("mshide");
  }
  loadArea.innerHTML = "";
}

async function handleAreaChange() {
  const loadProd = document.getElementById("display-loading-producto");
  loadProd.innerHTML = "";
  insertBasicLoadingHtml(loadProd);

  $("#producto").empty().append('<option value="Ninguno">Ninguno</option>');
  if ($("#area").val() !== "Ninguno") {
    try {
      let ret = await getHijosTipo($("#area").val(), null, "Producto");
      const $sel = $("#producto")
        .empty()
        .append('<option value="Ninguno">Ninguno</option>');
      ret.Hijos?.forEach((p) => {
        $sel.append(
          `<option value="${p.id}" data-nombre="${p.nombre}">${p.nombre}</option>`
        );
      });
      $(".productoBloque").removeClass("mshide");
    } catch (err) {
      console.error("Error al cargar productos:", err);
    }
  } else {
    $(".productoBloque").addClass("mshide");
  }
  loadProd.innerHTML = "";
}

async function handleProductoChange() {
  const loadServ = document.getElementById("display-loading-nombreServicio");
  loadServ.innerHTML = "";
  insertBasicLoadingHtml(loadServ);

  const $selServ = $("#nombreServicio").empty();
  if ($("#producto").val() && $("#producto").val() !== "Ninguno") {
    try {
      let ret = await getHijosTipo($("#producto").val(), null, "Servicio de Negocio");
      if (ret.Hijos?.length) {
        $selServ.append('<option value="">Seleccione un servicio</option>');
        ret.Hijos.forEach((s) => {
          $selServ.append(`<option value="${s.id}">${s.nombre}</option>`);
        });
      } else {
        $selServ.append(
          '<option value="">No hay servicios disponibles</option>'
        );
      }
      $(".nombreServicioBloque").removeClass("mshide");
    } catch (err) {
      console.error("Error al cargar servicios:", err);
      $(".nombreServicioBloque").addClass("mshide");
    }
  } else {
    $(".nombreServicioBloque").addClass("mshide");
  }
  loadServ.innerHTML = "";
}

function validarCamposObligatorios() {
  let esValido = true;

  const organizacionVal = $("#organizacion").val();
  if (!organizacionVal || organizacionVal === "Ninguno") {
    $("#organizacionError").text("Debes seleccionar una organización.");
    esValido = false;
  } else {
    $("#organizacionError").text("");
  }

  if ($(".direccionBloque").is(":visible")) {
    const direccionVal = $("#direccion").val();
    if (!direccionVal || direccionVal === "Ninguno") {
      $("#direccionError").text("Debes seleccionar una dirección.");
      esValido = false;
    } else {
      $("#direccionError").text("");
    }
  } else {
    $("#direccionError").text("");
  }

  if ($(".areaBloque").is(":visible")) {
    const areaVal = $("#area").val();
    if (!areaVal || areaVal === "Ninguno") {
      $("#areaError").text("Debes seleccionar un área.");
      esValido = false;
    } else {
      $("#areaError").text("");
    }
  } else {
    $("#areaError").text("");
  }

  if ($(".productoBloque").is(":visible")) {
    const productoVal = $("#producto").val();
    if (!productoVal || productoVal === "Ninguno") {
      $("#productoError").text("Debes seleccionar un producto.");
      esValido = false;
    } else {
      $("#productoError").text("");
    }
  } else {
    $("#productoError").text("");
  }

  return esValido;
}

function initializeRelations() {
  obtenerOrganizaciones();
  $(".organizacionInput").on("change", handleOrganizacionChange);
  $("#direccion").on("change", handleDireccionChange);
  $("#area").on("change", handleAreaChange);
  $("#producto").on("change", handleProductoChange);
}
jQuery(document).ready(function ($) {
  const params = new URLSearchParams(window.location.search);
  if (params.get("showIssuesTab") === "1") {
    $('a[href="#issues"]').tab("show");
  }
  asignarPestañas();
  setVolverAccounts();
  rellenarTablaYTarjetas();
  configurarTablaIssues();
  configurarTablaClouds();
  setGestionAlertasSuscription();
  setGestionAlertasRevision();
});
