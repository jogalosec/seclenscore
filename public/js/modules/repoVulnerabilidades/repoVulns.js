import { getAssetsWithVulnerabilities } from "../../api/vulnerabilidadesApi.js";

import { obtainVulnsDocument } from "../../api/repoVulnsApi.js";

import {
  finalLoading,
  insertBasicLoadingHtml,
  displayErrorMessage,
  displaySuccessMessage,
} from "../../utils/utils.js";

import constants from "../constants.js";

function getVulnerabilidades(vulns) {
  let arrayVulns = {
    total: 0,
    low: 0,
    medium: 0,
    major: 0,
    critical: 0,
  };
  for (const vuln of vulns) {
    if (vuln?.fields?.status?.name == "Abierta") {
      arrayVulns.total++;
      if (vuln?.fields?.customfield_25603?.value == "Low") {
        arrayVulns.low++;
      }
      if (vuln?.fields?.customfield_25603?.value == "Medium") {
        arrayVulns.medium++;
      }
      if (vuln?.fields?.customfield_25603?.value == "Major") {
        arrayVulns.major++;
      }
      if (vuln?.fields?.customfield_25603?.value == "Critical") {
        arrayVulns.critical++;
      }
    }
  }
  return arrayVulns;
}

function createAccordionItem(activoVulnerable) {
  let nombreActivo = activoVulnerable.nombre;
  let idActivo = activoVulnerable.id_activo;
  let totalVulns = getVulnerabilidades(activoVulnerable.vulns);
  let contadores = {
    contadorLow: `<label class="pill totalVulns contadorEmpty type-low">0 Low</label>`,
    contadorMedium: `<label class="pill totalVulns contadorEmpty type-medium">0 Medium</label>`,
    contadorMajor: `<label class="pill totalVulns contadorEmpty type-major">0 Major</label>`,
    contadorCritical: `<label class="pill totalVulns contadorEmpty type-critical">0 Criticals</label>`,
  };
  if (totalVulns.low > 0) {
    contadores.contadorLow = `<label class="pill totalVulns contadorLow${idActivo} type-low">${totalVulns.low} Low</label>`;
  }
  if (totalVulns.medium > 0) {
    contadores.contadorMedium = `<label class="pill totalVulns contadorMedium${idActivo} type-medium">${totalVulns.medium} Medium</label>`;
  }
  if (totalVulns.major > 0) {
    contadores.contadorMajor = `<label class="pill totalVulns contadorMajor${idActivo} type-major">${totalVulns.major} Major</label>`;
  }
  if (totalVulns.critical > 0) {
    contadores.contadorCritical = `<label class="pill totalVulns contadorCritical${idActivo} type-critical">${totalVulns.critical} Criticals</label>`;
  }
  let accordion = `
    <div class="accordion" id="accordion${idActivo}">
        <div class='accordion-item' id='accordion${idActivo}-item'>
            <h2 class="accordion-header" id="heading${idActivo}">
                <button class="accordion-button collapsed d-flex justify-content-between align-items-center w-100"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#collapse${idActivo}"
                  aria-expanded="false"
                  aria-controls="collapse${idActivo}">

                  <div class="d-flex justify-content-between w-100">
                    <div class="d-flex align-items-center">
                      <span class="tituloAccordion" id="titulo${idActivo}">
                        <b class="tituloActivo">${nombreActivo}</b>
                      </span>
                    </div>

                    <div class="d-flex align-items-center">
                      <span class="tituloAccordion" id="contadores${idActivo}">
                        ${contadores.contadorCritical}
                        ${contadores.contadorMajor}
                        ${contadores.contadorMedium}
                        ${contadores.contadorLow}
                      </span>
                    </div>
                  </div>
                </button>
            </h2>
            <div id="collapse${idActivo}"
                class="accordion-collapse collapse"
                aria-labelledby="heading${idActivo}"
                data-bs-parent="#accordion${idActivo}-item">
                <div class="accordion-body" id="accordion-body${idActivo}">
                <table class="table table-condensed table-hover table-striped table-11cert table-11certWhite" id='tablaVuln${idActivo}'>
                      <caption>Vulnerabilidades de ${nombreActivo}</caption>
                      <thead>
                          <tr>
                              <th data-column-id="ID" data-header-css-class="idCol" data-visible="false">ID</th>
                              <th data-column-id="CodVuln" data-header-css-class="CodVulnCol">Código</th>
                              <th data-column-id="Nombre" data-header-css-class="nombreCol">Nombre</th>
                              <th data-column-id="Criticidad" data-header-css-class="criticidadCol">Criticidad</th>
                              <th data-column-id="Status" data-header-css-class="statusCol">Status</th>
                              <th data-column-id="TipoPrueba" data-header-css-class="tipoPruebaCol">Tipo de Prueba</th>
                          </tr>
                      </thead>
                      <tbody id="bodyVuln${idActivo}">
                      </tbody>
                  </table>
                </div>
            </div>
        </div>
    </div>`;
  document.getElementById("accordionVulnerabilidades").innerHTML += accordion;
}

function llenarTablasVulnerabilidades(activoVulnerable) {
  let idActivo = activoVulnerable.id_activo;

  const options = {
    ...constants.OPTIONS_TABLE,
  };

  $(`#tablaVuln${idActivo}`).bootgrid(options);

  let arrayVulns = [];
  for (const vuln of activoVulnerable.vulns) {
    if (vuln != null) {
      let key = `<a class='ID-nombre' href='https://jira.tid.es/browse/${vuln.key}' target="_blank">${vuln.key}</a>`;
      let status = `<label class='pill estado ${vuln.fields.status.name.toLowerCase()}'>${
        vuln.fields.status.name
      }</label>`;
      let tipoPrueba = `<label class='pill estado ${vuln.pruebaInfo.tipo_prueba.toLowerCase()}'>${
        vuln.pruebaInfo.tipo_prueba
      }</label>`;
      let criticidad = `<label class='pill estado ${vuln.fields.customfield_25603.value.toLowerCase()}-criticidad'>${
        vuln.fields.customfield_25603.value
      }</label>`;

      arrayVulns.push({
        ID: vuln.id,
        CodVuln: key,
        Nombre: vuln.fields.summary,
        Criticidad: criticidad,
        Status: status,
        TipoPrueba: tipoPrueba,
      });
    }
  }

  $(`#tablaVuln${idActivo}`).bootgrid("append", arrayVulns);
}

function addDatosContadores(valoresAbiertas, valoresCerradas) {
  $("#low-contador").text(valoresAbiertas.low.toString());
  $("#medium-contador").text(valoresAbiertas.medium.toString());
  $("#major-contador").text(valoresAbiertas.major.toString());
  $("#critical-contador").text(valoresAbiertas.critical.toString());
  $("#low-contador-closed").text(valoresCerradas.low.toString());
  $("#medium-contador-closed").text(valoresCerradas.medium.toString());
  $("#major-contador-closed").text(valoresCerradas.major.toString());
  $("#critical-contador-closed").text(valoresCerradas.critical.toString());
  $("#chartVulnerabilidades").removeClass("mshide");
}

function insertarDatosGraficas(vulnerabilidades) {
  let valoresAbiertas = {
    low: 0,
    medium: 0,
    major: 0,
    critical: 0,
  };

  let valoresCerradas = {
    low: 0,
    medium: 0,
    major: 0,
    critical: 0,
  };
  for (const activoVulnerable of Object.values(vulnerabilidades)) {
    for (const vuln of activoVulnerable.vulns) {
      if (vuln != null) {
        if (vuln.fields.status.name == "Abierta") {
          valoresAbiertas[vuln.fields.customfield_25603.value.toLowerCase()]++;
        }
        if (vuln.fields.status.name == "Cerrada") {
          valoresCerradas[vuln.fields.customfield_25603.value.toLowerCase()]++;
        }
      }
    }
  }
  addDatosContadores(valoresAbiertas, valoresCerradas);
}

function fillAccordion(vulnerabilidades) {
  document.getElementById("accordionVulnerabilidades").innerHTML = "";
  let contadorAccordion = 0;
  for (const activoVulnerable of Object.values(vulnerabilidades)) {
    createAccordionItem(activoVulnerable);
    contadorAccordion++;
    $("#totalActivosVuln").text(contadorAccordion.toString());
  }
  for (const activoVulnerable of Object.values(vulnerabilidades)) {
    llenarTablasVulnerabilidades(activoVulnerable);
  }
}

async function handleAssetsWithVulnerabilities() {
  try {
    const data = await getAssetsWithVulnerabilities();
    if (!data.error) {
      finalLoading("#loadingVulnerabilidades", "check");
      insertarDatosGraficas(data.vulnerabilidades);
      fillAccordion(data.vulnerabilidades);
    } else {
      throw new Error(data.error);
    }
  } catch (error) {
    finalLoading("#loadingVulnerabilidades", "error");
    console.error("Error fetching assets with vulnerabilities:", error);
    return;
  }
}

function setSearch() {
  const searchInput = document.querySelector(".searcher");
  const container = document.getElementById("accordionVulnerabilidades");

  searchInput.addEventListener("input", function () {
    const query = this.value.toLowerCase();
    const acordeones = container.querySelectorAll(".accordion");
    let contadorAccordion = 0;
    acordeones.forEach((acordeon) => {
      const text = acordeon.textContent.toLowerCase();
      if (text.includes(query)) {
        acordeon.style.display = "";
        contadorAccordion++;
      } else {
        acordeon.style.display = "none";
      }
    });
    $("#totalActivosVuln").text(contadorAccordion.toString());
  });
}

async function downloadVulns() {
  try {
    $("#botonAceptar").prop("disabled", true);
    document.querySelector(".display-errors").innerHTML = "";
    document.querySelector(".display-check").innerHTML = "";
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    const form = document.getElementById("form-downloadVulns");
    const formData = new FormData(form);
    const format = formData.get("format");
    const analysisTypes = [];
    const estadoType = [];
    const criticidadType = [];
    form.querySelectorAll('input[name="estadoType"]:checked').forEach((el) => {
      estadoType.push(el.value);
    });
    form
      .querySelectorAll('input[name="criticidadType"]:checked')
      .forEach((el) => {
        criticidadType.push(el.value);
      });
    form
      .querySelectorAll('input[name="analysisType"]:checked')
      .forEach((el) => {
        analysisTypes.push(el.value);
      });
    let detailedInfo = false;
    if (form.querySelector('input[name="detailedInfo"]:checked')) {
      detailedInfo = true;
    }
    const infoDownload = {
      format: format,
      analysisTypes: analysisTypes,
      detailedInfo: detailedInfo ? "yes" : "no",
      estadoType: estadoType,
      criticidadType: criticidadType,
    };
    await obtainVulnsDocument(infoDownload);
    $("#botonAceptar").prop("disabled", false);
    cerrarModal();
  } catch (error) {
    $("#botonAceptar").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    console.error("Error downloading vulnerabilities:", error);
    displayErrorMessage(error, ".display-errors");
  }
}

function setBotonDownload() {
  const botonDownload = document.getElementById("btnDownloadVulns");
  botonDownload.addEventListener("click", function () {
    const modal = `
      <div class="modal-body">
        <form class="downloadForm" id="form-downloadVulns">
          <div class="form-group mb-4 row">
              <label for="appType" class="col-4 col-form-label">File format</label>
              <div class="col-8">
                  <select id="formatSelect" name="format" class="form-select-custom" required="required">
                      <option value="Excel">Excel</option>
                  </select>
              </div>
          </div>
          <div class="form-group mb-4 row">
            <label class="col-4 col-form-label">Analysis Type</label>
            <div class="col-8">
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="analysisPentest" name="analysisType" value="Pentest" checked>
                    <label class="form-check-label mb-0" for="analysisPentest">Pentest</label>
                </div>
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="analysisArchitecture" name="analysisType" value="Arquitectura" checked>
                    <label class="form-check-label mb-0" for="analysisArchitecture">Architecture</label>
                </div>
            </div>
          </div>
          <div class="form-group mb-4 row">
            <label class="col-4 col-form-label">Estado</label>
            <div class="col-8">
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="EstadoAbierta" name="estadoType" value="Abierta" checked>
                    <label class="form-check-label mb-0" for="EstadoAbierta">Abierta</label>
                </div>
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="EstadoCerrada" name="estadoType" value="Cerrada" checked>
                    <label class="form-check-label mb-0" for="EstadoCerrada">Cerrada</label>
                </div>
            </div>
          </div>
          <div class="form-group mb-4 row">
            <label class="col-4 col-form-label">Criticidad</label>
            <div class="col-8">
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="CriticidadCritical" name="criticidadType" value="Critical" checked>
                    <label class="form-check-label mb-0" for="CriticidadCritical">Critical</label>
                </div>
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="CriticidadMajor" name="criticidadType" value="Major" checked>
                    <label class="form-check-label mb-0" for="CriticidadMajor">Major</label>
                </div>
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="CriticidadMedium" name="criticidadType" value="Medium" checked>
                    <label class="form-check-label mb-0" for="CriticidadMedium">Medium</label>
                </div>
                <div class="form-check d-flex align-items-center mb-1 gap-2">
                    <input class="form-check-input m-0" type="checkbox" id="CriticidadLow" name="criticidadType" value="Low" checked>
                    <label class="form-check-label mb-0" for="CriticidadLow">Low</label>
                </div>
            </div>
          </div>

          <div class="form-group mb-4 row">
            <label class="col-4 col-form-label">Include detailed asset information</label>
            <div class="col-8 d-flex align-items-center ">
              <div class="form-check d-flex align-items-center mb-1 gap-2">
                <input class="form-check-input m-0" type="checkbox" id="detailedInfo" name="detailedInfo" value="True">
                <label class="form-check-label mb-0" for="detailedInfo">Yes</label>
              </div>
              </div>
              <small class="text-muted">
                Please note this could significantly delay data retrieval.
              </small>
          </div>
        </form>
        <div id="display-loading"></div>
        <div class="display-check"></div>
        <div class="display-errors"></div>
      </div>
    `;
    showModalWindow(
      "Download Wizard",
      modal,
      function () {
        downloadVulns();
      },
      "Close",
      "Download"
    );
  });
}

$(document).ready(function () {
  handleAssetsWithVulnerabilities();
  setBotonDownload();
  setSearch();
});
