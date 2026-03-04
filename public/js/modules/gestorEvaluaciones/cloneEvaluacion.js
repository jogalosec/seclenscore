import {
  getEvaluacionesSistema,
  clonarEvaluacion,
} from "../../api/evaluacionesApi.js";

import {
  finalLoading,
  displayErrorMessage,
  insertBasicLoadingHtml,
  displaySuccessMessage,
} from "../../utils/utils.js";

import {
  setSelectoresActivos,
  obtenerUltimoSelector,
} from "../../utils/setSelectoresActivos.js";

function observeDOMChanges() {
  const targetNode = document.body;
  const config = { childList: true, subtree: true };

  const callback = function (mutationsList, observer) {
    if (
      document.getElementById("sistemaOrigenDivSistema de InformaciónSelect")
    ) {
      $("#btnMostrarEvaluacionOrigen").removeClass("mshide");
    } else {
      $("#btnMostrarEvaluacionOrigen").addClass("mshide");
    }
    if (
      document.getElementById("sistemaDestinoDivSistema de InformaciónSelect")
    ) {
      $("#btnMostrarEvaluacionDestino").removeClass("mshide");
    } else {
      $("#btnMostrarEvaluacionDestino").addClass("mshide");
    }
  };

  const observer = new MutationObserver(callback);
  observer.observe(targetNode, config);
}

function handleSetSelectoresActivos() {
  let activos = [
    {
      Organización: 94,
      Dirección: 124,
      Área: 123,
      Producto: 67,
      "Servicio de Negocio": 42,
      "Sistema de Información": 33,
    },
  ];
  setSelectoresActivos("sistemaOrigenDiv", activos);
  setSelectoresActivos("sistemaDestinoDiv", activos);
}

function setBotonesEvaluacion(tipo) {
  $(`#btnMostrarEvaluacion${tipo}`).on("click", async function () {
    $(`#preguntas${tipo}`).empty();
    $("#DIVpreguntasDestino").addClass("mshide");
    $("#DIVpreguntasOrigen").addClass("mshide");
    $(".DIVdiferenciasLoading").addClass("mshide");
    $(`#compararEvaluaciones`).addClass("mshide");
    $(`.contenedorSelector${tipo}`).addClass("mshide");
    $(".btn-comparar").addClass("mshide");

    let activo = obtenerUltimoSelector(`sistema${tipo}DivSelector`);
    if (activo != "") {
      try {
        $(`.contenedorSelector${tipo}`).addClass("mshide");
        $(".btn-comparar").addClass("mshide");

        $(`.display_errors${tipo}`).html("");
        finalLoading(`.loadingEvaluaciones${tipo}`, "loading");
        let data = await getEvaluacionesSistema(activo);
        if (data.error) {
          finalLoading(`.loadingEvaluaciones${tipo}`, "error");
          displayErrorMessage(data, `.display_errors${tipo}`);
        } else {
          $(`.loadingEvaluaciones${tipo}`).html("");
          $(`.contenedorSelector${tipo}`).removeClass("mshide");

          $(`#evaluaciones${tipo}Selector`).empty();
          data.Evaluaciones.sort((a, b) => {
            const dateA = new Date(a.fecha);
            const dateB = new Date(b.fecha);
            if (dateA.getTime() !== dateB.getTime()) {
              return dateB - dateA;
            }
            const versionA = a.version ? Number(a.version) : 0;
            const versionB = b.version ? Number(b.version) : 0;
            return versionB - versionA;
          });
          for (let evaluacion of data.Evaluaciones) {
            let option = document.createElement("option");
            if (!evaluacion.version) {
              option.value = evaluacion.id;
            } else {
              option.value = "version-" + evaluacion.id;
            }
            option.textContent = evaluacion.fecha;

            if (evaluacion.nombre) {
              option.textContent += " - " + evaluacion.nombre + " ";
            }

            if (evaluacion.version) {
              option.textContent += evaluacion.version;
            }
            document
              .getElementById(`evaluaciones${tipo}Selector`)
              .appendChild(option);
          }

          if (
            !document
              .querySelector(".contenedorSelectorDestino")
              .classList.contains("mshide") &&
            !document
              .querySelector(".contenedorSelectorOrigen")
              .classList.contains("mshide") &&
            document.getElementById("evaluacionesOrigenSelector").value !==
              "" &&
            document.getElementById("evaluacionesDestinoSelector").value !== ""
          ) {
            $(".btn-comparar").removeClass("mshide");
          }
        }
      } catch (e) {
        finalLoading(`.loadingEvaluaciones${tipo}`, "error");
        displayErrorMessage(e, `.display_errors${tipo}`);
        console.error(e);
      }
    }
  });

  $(`#evaluaciones${tipo}Selector`).on("change", function () {
    $(`#preguntas${tipo}`).empty();
    $("#DIVpreguntasDestino").addClass("mshide");
    $("#DIVpreguntasOrigen").addClass("mshide");
    $(".DIVdiferenciasLoading").addClass("mshide");
    $(`#compararEvaluaciones`).addClass("mshide");
  });

  $(`#sistema${tipo}DivremoveActivoSelector`).on("click", function () {
    $(`#preguntas${tipo}`).empty();
    $("#DIVpreguntasDestino").addClass("mshide");
    $("#DIVpreguntasOrigen").addClass("mshide");
    $(".DIVdiferenciasLoading").addClass("mshide");
    $(`#compararEvaluaciones`).addClass("mshide");
    $(`.contenedorSelector${tipo}`).addClass("mshide");
    $(".btn-comparar").addClass("mshide");
  });
}

async function handleClonarEvaluacion(IdDestino, IdEvaluacion) {
  $("#botonAceptar").prop("disabled", true);
  try {
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    const data = await clonarEvaluacion(IdDestino, IdEvaluacion);
    $("#botonAceptar").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    if (data.error) {
      finalLoading(".display-errors", "error");
      displayErrorMessage(data, ".display-errors");
    } else {
      finalLoading(".display-errors", "check");
      displaySuccessMessage(
        "Evaluación clonada correctamente",
        ".display-errors"
      );
    }
  } catch (e) {
    $("#botonAceptar").prop("disabled", false);
    document.getElementById("display-loading").innerHTML = "";
    finalLoading(".display-errors", "error");
    displayErrorMessage(e, ".display-errors");
  }
}

function setBotonCloneEvaluacion() {
  $(".btn-clonar").click(function () {
    const origenSelector = document.getElementById(
      "sistemaOrigenDivSistema de InformaciónSelect"
    );
    const destinoSelector = document.getElementById(
      "sistemaDestinoDivSistema de InformaciónSelect"
    );
    const OrigenEvaluacionSelector = document.getElementById(
      "evaluacionesOrigenSelector"
    );
    if (origenSelector && destinoSelector && OrigenEvaluacionSelector) {
      const IDdestinoValue = destinoSelector.value;
      const IDevalValue = OrigenEvaluacionSelector.value;

      const origenText = origenSelector.selectedOptions[0].textContent;
      const destinoText = destinoSelector.selectedOptions[0].textContent;
      const evaluacionText =
        OrigenEvaluacionSelector.selectedOptions[0].textContent;

      const info = `
            <div id="display-loading"></div>
                <h5>
                    <b>Estás a punto de clonar la siguiente evalación: </b>
                </h5>
                <br>
                <div class="row">
                    <div class="col-12 d-flex text-center flex-column align-items-center">
                        <h3>
                            <b>Activo origen: </b> ${origenText}
                        </h3>
                    </div>
                    <div class="col-12 d-flex text-center flex-column align-items-center">
                        <h3>
                            <b>Activo destino: </b> ${destinoText}
                        </h3>
                    </div>
                    <div class="col-12 d-flex text-center flex-column align-items-center">
                        <h3>
                            <b>Evaluacion: </b> ${evaluacionText}
                        </h3>
                    </div>
                </div>
            <div class="display-errors"></div>`;
      showModalWindow(
        "Clonar evaluación",
        info,
        function () {
          handleClonarEvaluacion(IDdestinoValue, IDevalValue);
        },
        "Cerrar",
        "Clonar Evaluación",
        null
      );
    }
  });
}

$(document).ready(function () {
  handleSetSelectoresActivos();
  observeDOMChanges();
  setBotonCloneEvaluacion();
  setBotonesEvaluacion("Origen");
  setBotonesEvaluacion("Destino");
});
