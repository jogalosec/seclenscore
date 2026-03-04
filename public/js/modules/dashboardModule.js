import {
  getDashboardAssets,
  getDashboardBia,
  getSistemas3PS,
  getSistemasTratamientoDatos,
  getServiciosExternos,
  getDashboardEcrAssets,
  getProductosAnalisis,
  getDashboardErs,
  getDashboardPac,
  getDashboardKpms,
  getProductosContinuidad,
  getDashboardGBU,
  deleteRelation,
  editRelation,
  getDashboardCriticidadProductos,
} from "../api/dashboardApi.js";
import {
  displayErrorMessage,
  agregarLoadingHtml,
  addDownloadButtonToTable,
  generateTableGBU,
} from "../utils/utils.js";
import constants from "./constants.js";

import { getHijosTipo } from "../api/serviciosAPI.js";

const options = constants.OPTIONS_TABLE;

document.addEventListener("DOMContentLoaded", () => {
  if (
    document.querySelector("#tiposactivos-grid-data").innerHTML.trim() == ""
  ) {
    agregarLoadingHtml(document.querySelector(".tablaActivoslist"));
    initializeTipoActivos();
  }

  if (document.querySelector("#biaactivos-grid-data").innerHTML.trim() == "") {
    agregarLoadingHtml(document.querySelector(".tablaBialist"));
    initializeBia();
  }

  if (
    document.querySelector("#criticidadProductos-grid-data").innerHTML.trim() ==
    ""
  ) {
    agregarLoadingHtml(document.querySelector(".tablaCriticidadProductos"));
    initializeCriticidadProductos();
  }

  if (document.querySelector(".PS3")) {
    document.querySelector(".PS3").addEventListener("click", function () {
      if (
        document.querySelector("#tiposactivos-grid-data") != null &&
        document.querySelector("#tiposactivos-grid-data").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".tablaUsers"));
        initialize3PS();
      }
    });
  }

  if (document.querySelector(".ecr")) {
    document.querySelector(".ecr").addEventListener("click", function () {
      if (
        document.querySelector("#ecrassets-grid-data") != null &&
        document.querySelector("#ecrassets-grid-data").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".tablaEcrAssets"));
        agregarLoadingHtml(document.querySelector(".tablaEcrProductos"));
        initializeEcrAssets();
        initializeEcrProductos();
      }
    });
  }

  if (document.querySelector(".gbu")) {
    document.querySelector(".gbu").addEventListener("click", function () {
      if (
        document.querySelector(".grafica-gbu") != null &&
        document.querySelector(".grafica-gbu").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".grafica-gbu"));
        initializeGbu();
      }
    });
  }

  if (document.querySelector(".ers")) {
    document.querySelector(".ers").addEventListener("click", function () {
      if (
        document.querySelector("#ersactivos-grid-data") != null &&
        document.querySelector("#ersactivos-grid-data").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".tablaErslist"));
        initializeErs();
      }
    });
  }

  if (document.querySelector(".pac")) {
    document.querySelector(".pac").addEventListener("click", function () {
      if (
        document.querySelector("#tablapac-grid-data") != null &&
        document.querySelector("#tablapac-grid-data").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".tablaPaclist"));
        agregarLoadingHtml(document.querySelector(".graficaPAC"));
        agregarLoadingHtml(document.querySelector(".graficaPACnombre"));
        initializePac();
      }
    });
  }

  if (document.querySelector(".ers")) {
    document.querySelector(".ers").addEventListener("click", function () {
      if (
        document.querySelector("#ersactivos-grid-data") != null &&
        document.querySelector("#ersactivos-grid-data").innerHTML.trim() == ""
      ) {
        agregarLoadingHtml(document.querySelector(".tablaErslist"));
        initializeErs();
      }
    });
  }

  if (document.querySelector(".ps3")) {
    document.querySelector(".ps3").addEventListener("click", function () {
      if (
        document.querySelector("#PS3Total") != null &&
        document.querySelector("#PS3Total").innerHTML.trim() == ""
      ) {
        handleGetSistemas3PS();
        handleGetServiciosExternos();
      }
    });
  }

  let privacidadClicked = false;
  if (document.querySelector(".privacidad")) {
    document
      .querySelector(".privacidad")
      .addEventListener("click", function () {
        if (privacidadClicked) return;
        privacidadClicked = true;
        handleGetSistemasTratamientoDatos();
      });
  }

  let continuidadClicked = false;
  if (document.querySelector(".continuidad")) {
    document
      .querySelector(".continuidad")
      .addEventListener("click", function () {
        if (continuidadClicked) return;
        continuidadClicked = true;
        handleGetProductosContinuidad();
      });
  }

  let easClicked = false;

  $('a[data-bs-toggle="tab"]').on("shown.bs.tab", function (e) {
    const target = $(e.target).attr("href");
    if (target === "#eas" && !easClicked) {
      easClicked = true;
      getRelations();
    }
  });
});

function normalizeRecords(records) {
  return records.map((record) => {
    return Object.fromEntries(
      Object.entries(record).map(([key, value]) => [
        key,
        value == null ? "" : String(value),
      ])
    );
  });
}

async function initializeCriticidadProductos() {
  try {
    const dataCriticidad = await getDashboardCriticidadProductos();
    dataCriticidad.criticidadProductos = normalizeRecords(
      dataCriticidad.criticidadProductos
    );
    updateDashboardCriticidadProductos(dataCriticidad);
  } catch (error) {
    console.error("Error al inicializar la criticidad de productos:", error);
  }
}

async function updateDashboardCriticidadProductos(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaCriticidadProductos");
    return;
  }
  if (
    document.querySelector(".tablaCriticidadProductos > .spinner-animation")
  ) {
    document
      .querySelector(".tablaCriticidadProductos > .spinner-animation")
      .remove();
  }

  const table = $("#criticidadProductos").bootgrid(options);

  await pintarGrafica(
    $(".graficaCriticidadProductos"),
    "corechart",
    pintarGraficaCriticidad,
    data.criticidadGrafico
  );

  data.criticidadProductos.forEach((producto) => {
    producto.criticidad = aplicarColorCriticidad(producto.criticidad);
    producto.confidencialidad = aplicarColorCriticidad(
      producto.confidencialidad
    );
    producto.integridad = aplicarColorCriticidad(producto.integridad);
    producto.disponibilidad = aplicarColorCriticidad(producto.disponibilidad);
  });

  table.bootgrid("append", data.criticidadProductos);

  addDownloadButtonToTable(".tablaCriticidadProductos");
}

function aplicarColorCriticidad(nivel) {
  switch (nivel) {
    case "Leve":
      return '<span class="badge" data-criticidad="Leve">Leve</span>';
    case "Bajo":
      return '<span class="badge" data-criticidad="Bajo">Bajo</span>';
    case "Moderado":
      return '<span class="badge" data-criticidad="Moderado">Moderado</span>';
    case "Alto":
      return '<span class="badge" data-criticidad="Alto">Alto</span>';
    case "Crítico":
      return '<span class="badge" data-criticidad="Crítico">Crítico</span>';
    default:
      return '<span class="badge" data-criticidad="No_evaluado">No evaluado</span>';
  }
}

function pintarGraficaCriticidad(item, data) {
  let dataChart = google.visualization.arrayToDataTable(data);
  let options = {
    is3D: false,
    pieSliceTextStyle: {
      color: "white",
      fontSize: 12,
    },
    legend: { position: "top", maxLines: 3 },
    height: 400,
    colors: ["#548237", "#8ac833", "#ffb32f", "#ec673b", "#911927", "#6c757d"],
  };
  let chart = new google.visualization.PieChart(item[0]);
  chart.draw(dataChart, options);
}

async function initializeTipoActivos() {
  try {
    const dataAssets = await getDashboardAssets();
    dataAssets.activoslist = normalizeRecords(dataAssets.activoslist);
    updateDashboardAssets(dataAssets);
  } catch (error) {
    console.error("Error al inicializar el dashboard:", error);
  }
}

async function initializeBia() {
  try {
    const dataBia = await getDashboardBia();
    dataBia.bialist = normalizeRecords(dataBia.bialist);
    updateDashboardBia(dataBia);
  } catch (error) {
    console.error("Error al inicializar el dashboard:", error);
  }
}

async function initializeEcrProductos() {
  try {
    const dataEcrProductos = await getProductosAnalisis();
    dataEcrProductos.productos = normalizeRecords(dataEcrProductos.productos);
    updateDashboardEcrProductos(dataEcrProductos);
  } catch (error) {
    displayErrorMessage(error, ".tablaEcrProductos");
  }
}

async function initializeGbu() {
  try {
    const dataGbu = await getDashboardGBU();
    updateDashboardGbu(dataGbu);
  } catch (error) {
    displayErrorMessage(error, ".grafica-gbu");
  }
}

async function initializeErs() {
  try {
    const dataErs = await getDashboardErs();
    updateDashboardErs(dataErs);
  } catch (error) {
    displayErrorMessage(error, ".tablaErslist");
  }
}

async function initializeEcrAssets() {
  try {
    const dataEcr = await getDashboardEcrAssets();
    updateDashboardEcrAssets(dataEcr);
  } catch (error) {
    displayErrorMessage(error, ".tablaEcrAssets");
  }
}

async function initializePac() {
  try {
    const dataPac = await getDashboardPac();
    updateDashboardPac(dataPac);
  } catch (error) {
    displayErrorMessage(error, ".tablaPaclist");
  }
}

async function initializeKpms() {
  try {
    const dataKpms = await getDashboardKpms();
    console.debug(dataKpms);
  } catch (error) {
    displayErrorMessage(error, ".tablaAreas");
  }
}

async function initialize3PS() {
  try {
    const data3PS = await getSistemas3PS();
    updateDashboard3PS(data3PS);
  } catch (error) {
    displayErrorMessage(error, ".tabla3ps");
  }
}

async function updateDashboardEcrProductos(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaEcrProductos");
    return;
  }
  if (document.querySelector(".tablaEcrProductos > .spinner-animation")) {
    document.querySelector(".tablaEcrProductos > .spinner-animation").remove();
  }
  const table = document.getElementById("pruebasProductos");

  $(table).bootgrid(options);
  $(table).bootgrid("clear");
  let numEvalProductos = [
    ["Con", data.count.Productos_eval_num],
    ["Sin", data.count.Productos_no_eval_num],
  ];

  pintarGrafica(
    $(".graficaECRProductos"),
    "corechart",
    pintarTartaSiyNo,
    numEvalProductos
  );

  $(table).bootgrid("append", data.productos);
  addDownloadButtonToTable(".tablaEcrProductos");
}

async function updateDashboardAssets(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaActivoslist");
    return;
  }
  if (document.querySelector(".tablaActivoslist > .spinner-animation")) {
    document.querySelector(".tablaActivoslist > .spinner-animation").remove();
  }
  const table = $("#tiposactivos").bootgrid(options);
  await pintarGrafica(
    $(".graficaActivos"),
    "corechart",
    pintarTartaActivos,
    data.activostipo
  );
  table.bootgrid("clear");
  table.bootgrid("append", data.activoslist);
  addDownloadButtonToTable(".tablaActivoslist");
}

async function updateDashboardBia(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaBialist");
    return;
  }
  if (document.querySelector(".tablaBialist > .spinner-animation")) {
    document.querySelector(".tablaBialist > .spinner-animation").remove();
  }
  const table = $("#biaactivos").bootgrid(options);
  await pintarGrafica($(".graficabia"), "corechart", pintarTartaBIA, data.bia);
  table.bootgrid("append", data.bialist);
  addDownloadButtonToTable(".tablaBialist");
}

async function updateDashboardEcrAssets(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaBialist");
    return;
  }

  if (document.querySelector(".tablaEcrAssets > .spinner-animation")) {
    document.querySelector(".tablaEcrAssets > .spinner-animation").remove();
  }

  const table = document.getElementById("ecractivos");
  $(table).bootgrid(options);
  $(table).bootgrid("clear");
  $(table).bootgrid("append", data.ecrlist);
  addDownloadButtonToTable(".tablaEcrAssets");
}

async function updateDashboardGbu(data) {
  if (data.error) {
    displayErrorMessage(data, ".grafica-gbu");
    return;
  }

  if (document.querySelector(".grafica-gbu > .spinner-animation")) {
    document.querySelector(".grafica-gbu > .spinner-animation").remove();
  }

  let table = generateTableGBU(data);
  let gbuDiv = document.querySelector(".grafica-gbu");
  gbuDiv.appendChild(table);
}

async function updateDashboardErs(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaErslist");
    return;
  }

  if (document.querySelector(".tablaErslist > .spinner-animation")) {
    document.querySelector(".tablaErslist > .spinner-animation").remove();
  }

  const table = document.getElementById("ersactivos");
  $(table).bootgrid(options);
  $(table).bootgrid("clear");
  $(table).bootgrid("append", data.erslist);
  addDownloadButtonToTable(".tablaErslist");
}

async function updateDashboardPac(data) {
  if (data.error) {
    displayErrorMessage(data, ".tablaPaclist");
    return;
  }

  if (document.querySelector(".tablaPaclist > .spinner-animation")) {
    document.querySelector(".tablaPaclist > .spinner-animation").remove();
  }

  if (document.querySelector(".graficaPAC > .spinner-animation")) {
    document.querySelector(".graficaPAC > .spinner-animation").remove();
  }

  if (document.querySelector(".graficaPACnombre > .spinner-animation")) {
    document.querySelector(".graficaPACnombre > .spinner-animation").remove();
  }

  const table = document.getElementById("tablapac");
  $(table).bootgrid(options);
  $(table).bootgrid("clear");
  await pintarGrafica(
    $(".graficaPAC"),
    "corechart",
    pintarTartaPAC,
    data.estados
  );
  await pintarGrafica(
    $(".graficaPACnombre"),
    "corechart",
    pintarTartaPAC,
    data.proyectoslist
  );

  const seguimientosFiltrados = data.seguimientos
    .filter((seguimiento) => seguimiento.archivado == 0)
    .map((seguimiento) => {
      seguimiento.servicio =
        seguimiento.servicio.length > 1
          ? seguimiento.servicio.map((el) => el.nombre).join(", ")
          : seguimiento.servicio[0].nombre;
      return seguimiento;
    });

  const seguimientosNormalizados = normalizeRecords(seguimientosFiltrados);

  $(table).bootgrid("append", seguimientosNormalizados);
  addDownloadButtonToTable(".tablaPaclist");
}

async function handleGetSistemas3PS() {
  try {
    const data = await getSistemas3PS();
    if (data.error) {
      finalLoading("#loading3PS", "error");
      console.error("Error en la API:", data.message);
    } else {
      insertOptions("#PS3Tabla");
      insertData3PS(data);
      finalLoading("#loading3PS", "check");
      removeClass("#PS3Tabla", "mshide");
    }
  } catch (error) {
    finalLoading("#loading3PS", "error");
    console.error("Error al ejecutar la llamada de API:", error);
  }
}

async function handleGetProductosContinuidad() {
  try {
    const data = await getProductosContinuidad();
    if (data.error) {
      finalLoading("#continuidadLoading", "error");
      console.error("Error en la API:", data.message);
    } else {
      insertOptions("#productosTotalesContinuidad");
      insertDataContinuidad(data);
      finalLoading("#continuidadLoading", "check");
    }
  } catch (error) {
    console.log(error);
  }
}

let datosContinuidad = [
  ["Categoria", "Valor"],
  ["Con plan de continuidad", 0],
  ["Sin plan de continuidad", 0],
];
function insertDataContinuidad(data) {
  removeClass("#productosTotalesContinuidad", "mshide");
  for (let producto of data.productos) {
    let estado;
    if (producto.continuidad) {
      estado =
        '<label class="rounded-pill numberEstado cerrado">🟢 Si </label>';
      datosContinuidad[1][1] += 1;
    } else {
      estado =
        '<label class="rounded-pill numberEstado cerrado">🔴 No </label>';
      datosContinuidad[2][1] += 1;
    }

    let productoArray = [
      {
        Producto: producto["nombre"],
        PlanContinuidad: estado,
      },
    ];
    $("#productosTotalesContinuidad").bootgrid("append", productoArray);
  }
  pintarGrafica(
    $(".graficaContinuidad"),
    "corechart",
    pinterGraficaContinuidad,
    datosContinuidad
  );
}

function pinterGraficaContinuidad(item, datosContinuidad) {
  let data = google.visualization.arrayToDataTable(datosContinuidad);
  let options = {
    is3D: false,
    pieSliceTextStyle: {
      color: "white",
      fontSize: 12,
    },
    legend: { position: "top", maxLines: 3 },
    height: 400,
  };
  let chart = new google.visualization.PieChart(item[0]);
  chart.draw(data, options);
}

async function handleGetSistemasTratamientoDatos(start = 0, total = 20) {
  try {
    const data = await getSistemasTratamientoDatos(start);
    if (data.error) {
      finalLoading("#loadingConf", "error");
      finalLoading("#loadingConfProductos", "error");

      console.error("Error en la API:", data.message);
    } else {
      llenarTablasActivosCaracter(data, start, total);
    }
  } catch (error) {
    finalLoading("#loadingConf", "error");
    finalLoading("#loadingConfProductos", "error");
    console.error("Error al ejecutar la llamada de API:", error);
  }
}

async function handleGetServiciosExternos(start = 0, total = 50) {
  try {
    const data = await getServiciosExternos(start, total);
    if (data.error) {
      console.error("Error en la API:", data.message);
    } else {
      llenarTablasIngresos(data, start, total);
    }
  } catch (error) {
    finalLoading("#loadingSisIngresos", "error");
    console.error("Error al ejecutar la llamada de API:", error);
  }
}

function insertData3PS(data) {
  for (let sistema of data) {
    let sistemaArray = [
      {
        Producto: sistema["servicio"],
        Sistema: sistema["sistema"],
        FechaEvaluacion: sistema["fecha"],
      },
    ];
    $("#PS3Tabla").bootgrid("append", sistemaArray);
  }
}

function insertOptions(element) {
  let options = {
    caseSensitive: false,
    rowSelect: true,
    labels: {
      noResults: "No results found",
      infos: "Showing {{ctx.start}}-{{ctx.end}} of {{ctx.total}} rows",
      search: "Search",
    },
  };
  $(element).bootgrid(options);
}

let datosConfig = [
  ["Categoria", "Valor"],
  ["Con activo TDP", 0],
  ["Bia con TDP.", 0],
  ["Con ambos.", 0],
];
function pintarGraficaPrivacidad(item, data) {
  for (let producto of data.productos) {
    if (producto.activoConfig && producto.biaConfig) {
      datosConfig[3][1] += 1;
    } else if (producto.activoConfig) {
      datosConfig[1][1] += 1;
    } else if (producto.biaConfig) {
      datosConfig[2][1] += 1;
    }
  }

  let dataDraw = google.visualization.arrayToDataTable(datosConfig);
  let options = {
    is3D: false,
    pieSliceTextStyle: {
      color: "white",
      fontSize: 12,
    },
    legend: { position: "top", maxLines: 3 },
    height: 400,
    slices: {
      0: { color: "#fd7e14" },
      1: { color: "#6f42c1" },
      2: { color: "#26C02C" },
    },
  };
  let chart = new google.visualization.PieChart(item[0]);
  chart.draw(dataDraw, options);
}

function mostrarDatosPrivacidad(data) {
  data.productos.forEach((producto) => {
    renderProductoRow(producto);
    if (producto.sistemas && producto.sistemas.length > 0) {
      renderSistemasRows(producto);
    }
  });
  insertDataGrafica(data);
}

function renderProductoRow(producto) {
  const biaConfig = producto.biaConfig ? "🟢 Si " : "🔴 No ";
  const activoConfig = producto.activoConfig ? "🟢 Si " : "🔴 No ";
  const productoArray = [
    {
      Producto: producto["nombre"],
      TieneBia: biaConfig,
      TieneAct: activoConfig,
    },
  ];
  $("#tablaConfProductos").bootgrid("append", productoArray);
}

function renderSistemasRows(producto) {
  const biaConfig = producto.biaConfig ? "🟢 Si " : "🔴 No ";
  producto.sistemas.forEach((sistema) => {
    if (!sistema.length) return;
    const activoConfig = sistema[0].activoConfig ? "🟢 Si " : "🔴 No ";
    let TipoConfig;
    if (biaConfig === "🟢 Si " && activoConfig === "🟢 Si ") {
      TipoConfig = "Ambos ";
    } else if (biaConfig === "🟢 Si ") {
      TipoConfig = "Bia ";
    } else if (activoConfig === "🟢 Si ") {
      TipoConfig = "Activo ";
    } else {
      TipoConfig = "Ninguno ";
    }
    const sistemaArray = [
      {
        Producto: producto["nombre"],
        Sistema: sistema[0]["nombre"],
        TieneBia: biaConfig,
        TieneAct: activoConfig,
        TipoConfig: TipoConfig,
      },
    ];
    $("#tablaConf").bootgrid("append", sistemaArray);
  });
}

function insertDataGrafica(data) {
  pintarGrafica(
    $(".graficaPrivacidadProductos"),
    "corechart",
    pintarGraficaPrivacidad,
    data
  );
}

let datosIngresos = [
  ["Categoria", "Valor"],
  ["Con evaluación de 3PS.", 0],
  ["Sin evaluación de 3PS.", 0],
];
function pintarDataIngresos(item, data) {
  let organizacion = "Telefónica Innovación Digital";
  for (let sistema of data.activos) {
    if (sistema.servicioExpuesto && sistema.organizacion == organizacion) {
      let eval3PS;
      let evalNorm;
      if (sistema.evaluacion3PS) {
        datosIngresos[1][1] += 1;
        eval3PS = "🟢 Si ";
      } else {
        datosIngresos[2][1] += 1;
        eval3PS = "🔴 No ";
      }
      if (sistema.evaluacionNorm) evalNorm = "🟢 Si ";
      else evalNorm = "🔴 No ";
      let data = google.visualization.arrayToDataTable(datosIngresos);
      let options = {
        is3D: false,
        pieSliceTextStyle: {
          color: "white",
          fontSize: 12,
        },
        legend: { position: "top", maxLines: 3 },
        height: 400,
      };
      let chart = new google.visualization.PieChart(item[0]);
      chart.draw(data, options);
      let sistemaArray = [
        {
          Direccion: sistema["direccion"],
          Area: sistema["area"],
          Producto: sistema["producto"],
          Servicio: sistema["servicio"],
          Eval3PS: eval3PS,
          EvalNorm: evalNorm,
        },
      ];
      $("#SisIngresosTabla").bootgrid("append", sistemaArray);
    }
  }
}

function insertDataIngresos(data) {
  pintarGrafica($(".graficaIngresos"), "corechart", pintarDataIngresos, data);
}

function llenarTablasIngresos(data, start, total) {
  if (start == 0) {
    insertOptions("#SisIngresosTabla");
    removeClass("#SisIngresosTabla", "mshide");
  }
  total = total + data.sistemas_analizados;
  if (data.total > total) {
    empty(".porcentajeIngresos");
    let porcentaje = (total / data.total) * 100;
    append(".porcentajeIngresos", `${porcentaje.toFixed(0)}%`);
  } else {
    finalLoading("#loadingSisIngresos", "check");
    empty(".porcentajeIngresos");
  }
  insertDataIngresos(data);
}

function llenarTablasActivosCaracter(data, start, total) {
  if (start == 0) {
    insertOptions("#tablaConf");
    insertOptions("#tablaConfProductos");
    removeClass("#tablaConf", "mshide");
    removeClass("#tablaConfProductos", "mshide");
  }
  if (data.total > total) {
    empty(".porcentajePrivacidad");
    let porcentaje = (total / data.total) * 100;
    append(".porcentajePrivacidad", `${porcentaje.toFixed(0)}%`);
    total = total + data.productosAnalizados;
    handleGetSistemasTratamientoDatos(start + 20, total);
  } else {
    finalLoading("#loadingConfProductos", "check");
    finalLoading("#loadingConf", "check");
    empty(".porcentajePrivacidad");
  }
  mostrarDatosPrivacidad(data);
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

function getRelations() {
  fetch("./api/getSuscriptionRelations", {
    method: "GET",
    credentials: "include",
  })
    .then(function (response) {
      if (!response.ok) {
        throw new Error("Error en la solicitud: " + response.statusText);
      }
      return response.json();
    })
    .then(function (data) {
      if (data.relations && data.relations.length > 0) {
        llenarTablaEas(data.relations);
      } else {
        $("#easTableBody").html(
          "<tr><td colspan='4'>No se encontraron relaciones</td></tr>"
        );
      }
      finalLoading("#easLoading", "check");
    })
    .catch(function (error) {
      finalLoading("#easLoading", "error");
    });
}

function llenarTablaEas(relations) {
  $("#easTable").bootgrid("destroy");

  let options = {
    rowSelect: true,
    caseSensitive: false,
    labels: {
      noResults: "No se han encontrado resultados.",
      infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
      search: "Buscar",
    },
    formatters: {
      commands: function (column, row) {
        return (
          "<button type='button' class='btn btn-primary btn-xs command-action' " +
          "data-row-id='" +
          row.idSuscripcion +
          "'>" +
          "Acciones</button>"
        );
      },
    },
  };

  let grid = $("#easTable")
    .bootgrid(options)
    .on("loaded.rs.jquery.bootgrid", function () {
      grid.find(".command-action").on("click", function () {
        let idSub = $(this).attr("data-row-id");
        let rel = relations.find((r) => r.suscription_id == idSub);
        if (rel) accionesEas(rel);
      });
    });

  let rows = relations.map(function (relation) {
    return {
      id: relation.id,
      Padre:
        relation.tipo_activo == 67 ? relation.nombre_activo : relation.Padre,
      Activo:
        relation.tipo_activo == 67
          ? "No se especificó servicio ❗"
          : relation.nombre_activo,
      Suscripcion: relation.suscription_name,
      idSuscripcion: relation.suscription_id,
      commands: "",
    };
  });

  if (rows.length > 0) {
    grid.bootgrid("append", rows);
  }
  addDownloadButtonToTable(".tablaEas");
}

function accionesEas(rel) {
  const producto = rel.tipo_activo == 67 ? rel.nombre_activo : rel.Padre;
  const servicio =
    rel.tipo_activo == 67 ? "No se especificó servicio ❗" : rel.nombre_activo;

  const form = `
    <div class="row">
      <div class="col-md-12">
        <table class="table">
          <tbody>
            <tr>
              <td class="text-start col-md-4"><strong>Producto:</strong></td>
              <td class="col-md-6 text-start">${producto}</td>
            </tr>
            <tr>
              <td class="text-start col-md-4"><strong>Servicio:</strong></td>
              <td class="col-md-6 text-start">${servicio}</td>
            </tr>
            <tr>
              <td class="text-start col-md-4"><strong>Nombre Suscripción:</strong></td>
              <td class="col-md-6 text-start">${rel.suscription_name}</td>
            </tr>
            <tr>
              <td class="text-start col-md-4"><strong>ID Suscripción:</strong></td>
              <td class="col-md-6 text-start">${rel.suscription_id}</td>
            </tr>
          </tbody>
        </table>

        <div class="row mb-2">
          <div class="col-md-8 text-start"><strong>Editar Suscripción</strong></div>
          <div class="col-md-4">
            <a href="#" class="btn btn-primary editSus${rel.suscription_id} w-100">Editar</a>
          </div>
        </div>

        <div class="row mb-2">
          <div class="col-md-8 text-start"><strong>Eliminar Suscripción</strong></div>
          <div class="col-md-4">
            <a href="#" class="btn btn-danger deleteSus${rel.suscription_id} w-100">Eliminar</a>
          </div>
        </div>
      </div>
    </div>
  `;

  showModalWindow(
    "Acciones de Suscripción",
    form,
    null,
    "Cerrar",
    null,
    null,
    null,
    "modal-lm"
  );

  $(`.editSus${rel.suscription_id}`).click(function (e) {
    e.preventDefault();
    handleEditarSuscripcion(rel);
  });

  $(`.deleteSus${rel.suscription_id}`).click(function (e) {
    e.preventDefault();
    handleEliminarSuscripcion(rel.suscription_id, rel.suscription_name);
  });
}

function handleEliminarSuscripcion(suscription_id, suscriptionName) {
  showModalWindow(
    "Confirmar eliminación",
    `<p class="text-start">
       ¿Seguro que deseas eliminar la suscripción 
       "<strong>${suscriptionName}</strong>"?
     </p>`,
    function () {
      eliminarRelacion(suscription_id);
    },
    "Cancelar",
    "Aceptar",
    null,
    null
  );
}

function eliminarRelacion(suscription_id) {
  mostrarLoading();
  deleteRelation(suscription_id)
    .then((response) => {
      if (response.error) {
        console.error("Error al eliminar la suscripción:", response.message);
        showModalWindow(
          "Error",
          "No se ha podido eliminar la suscripción.",
          null,
          "Cerrar",
          null,
          null,
          null,
          null
        );
      } else {
        showModalWindow(
          "Éxito",
          "Suscripción eliminada correctamente.",
          null,
          "Cerrar",
          null,
          null,
          null,
          null
        );
        getRelations();
      }
    })
    .catch((error) => {
      console.error("Error en la solicitud de eliminación:", error);
      showModalWindow(
        "Error",
        "Ha ocurrido un error al procesar la petición.",
        null,
        "Cerrar",
        null,
        null,
        null,
        null
      );
    });
}

async function handleEditarSuscripcion(rel) {
  const activoId = rel.id_activo;
  const suscriptionId = rel.suscription_id;
  const nombreActual = rel.suscription_name;
  const servicioActual =
    rel.tipo_activo === 67 ? "No se especificó servicio" : rel.nombre_activo;

  const isProducto = rel.tipo_activo === 67;
  try {
    let items = [];
    if (isProducto) {
      const data = await getHijosTipo(null, activoId, 42);
      items = Array.isArray(data) ? data : data.Hijos || [];
    } else {
      const res = await fetch(`./api/getBrothers?id_activo=${activoId}`, {
        method: "GET",
        credentials: "include",
      });
      if (!res.ok) throw new Error(res.statusText);
      const raw = await res.json();
      items = Array.isArray(raw) ? raw : raw.Hijos || [];
    }
    const opciones = items
      .map((i) => `<option value="${i.id}">${i.nombre}</option>`)
      .join("");

    let tableHtml = `
        <table class="tabla-editar-suscripcion">
          <tbody>
            <tr>
              <td class="label-cell"><strong>Servicio actual:</strong></td>
              <td class="value-cell">${servicioActual}</td>
            </tr>
            <tr>
              <td class="label-cell"><strong>Nuevo Servicio:</strong></td>
              <td class="value-cell">
                <select
                  id="selectServicios"
                  class="form-control select-centre"
                >
                  ${
                    items.length
                      ? `<option value="">Seleccionar</option>${opciones}`
                      : `<option value="">No hay servicios disponibles</option>`
                  }
                </select>
              </td>
            </tr>
            <tr>
              <td class="label-cell"><strong>Nombre Suscripción:</strong></td>
              <td class="value-cell">${nombreActual}</td>
            </tr>
            <tr>
              <td class="label-cell"><strong>ID Suscripción:</strong></td>
              <td class="value-cell">${suscriptionId}</td>
            </tr>
          </tbody>
        </table>
      `;

    const form = `
        <form id="form-edit-suscripcion">
          <div class="mb-3">${tableHtml}</div>
          <div class="text-right">
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      `;
    showModalWindow(
      "Editar suscripción",
      form,
      null,
      "Cerrar",
      null,
      null,
      null,
      "modal-sm"
    );

    $("#form-edit-suscripcion").on("submit", function (e) {
      e.preventDefault();
      const selSrv = $("#selectServicios").val();
      const nuevoActivoId = selSrv || activoId;
      editarSuscripcion(suscriptionId, nuevoActivoId);
    });
  } catch (err) {
    console.error("Error cargando opciones:", err);
    showModalWindow(
      "Error",
      `<p>No se pudieron cargar las opciones de servicio.</p>`,
      null,
      "Cerrar"
    );
  }
}

function editarSuscripcion(suscription_id, id_activo) {
  mostrarLoading();
  editRelation(suscription_id, id_activo)
    .then((response) => {
      if (response.error) {
        showModalWindow(
          "Error",
          "No se pudo actualizar la suscripción.",
          null,
          "Cerrar"
        );
      } else {
        showModalWindow(
          "Éxito",
          "Suscripción actualizada correctamente.",
          null,
          "Cerrar"
        );
        getRelations();
      }
    })
    .catch(() => {
      showModalWindow(
        "Error",
        "Error en la solicitud de edición.",
        null,
        "Cerrar"
      );
    });
}
