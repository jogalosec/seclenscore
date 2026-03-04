import {
    getPreguntasEvaluacion,
} from "../../api/evaluacionesApi.js";

import { 
    finalLoading,
} from "../../utils/utils.js";

function createPregunta(tipo, pregunta, ghost = false) {
    let style = "";
    if (ghost) {
        style = "ghost";
    }

    let preguntaHTML = `
        <div class="pregunta-container ${style}" id="pregunta${tipo}${pregunta.id}">
            <p class="pregunta-texto">${pregunta.id}. ${pregunta.duda}</p>
            <p class="respuesta-texto respuesta${pregunta.respuesta}">${pregunta.respuesta}</p>
        </div>
    `;
    return preguntaHTML;
}

function drawPreguntasEvaluacion(soloEnOrigen, soloEnDestino, respuestasDiferentes) {
    $("#preguntasOrigen").empty();
    $("#preguntasDestino").empty();
    $("#DIVpreguntasOrigen").removeClass("mshide");
    $("#DIVpreguntasDestino").removeClass("mshide");

    for (let pregunta of respuestasDiferentes) {
        let formatedPregunta = {
            id: pregunta.id,
            duda: pregunta.duda,
            respuesta: pregunta.respuestaOrigen,
        }
        let preguntaOrigenHTML = createPregunta("Origen", formatedPregunta);

        $("#preguntasOrigen").append(preguntaOrigenHTML);
        formatedPregunta.respuesta = pregunta.respuestaDestino;
        
        let preguntaDestinoHTML = createPregunta("Destino", formatedPregunta);
        $("#preguntasDestino").append(preguntaDestinoHTML);
    }
    
    for (let pregunta of soloEnOrigen) {
        let preguntaOrigenHTML = createPregunta("Origen", pregunta);
        $("#preguntasOrigen").append(preguntaOrigenHTML);
        let preguntaDestinoHTML = createPregunta("Destino", pregunta, true);
        $("#preguntasDestino").append(preguntaDestinoHTML);
    }
    for (let pregunta of soloEnDestino) {
        let preguntaOrigenHTML = createPregunta("Origen", pregunta, true);
        $("#preguntasOrigen").append(preguntaOrigenHTML);
        let preguntaDestinoHTML = createPregunta("Destino", pregunta);
        $("#preguntasDestino").append(preguntaDestinoHTML);
    }
}

async function getPreguntas(evaluacionOrigen, evaluacionDestino) {
    finalLoading(".diferenciasLoading", "loading");
    $(".DIVdiferenciasLoading").removeClass("mshide");
    try {
      let dataOrigen = await getPreguntasEvaluacion(evaluacionOrigen);
      let dataDestino = await getPreguntasEvaluacion(evaluacionDestino);
      if (dataOrigen.error || dataDestino.error) {
        finalLoading(".diferenciasLoading", "error");
      } else {
        finalLoading(".diferenciasLoading", "check");
        evaluacionOrigen = dataOrigen.Evaluacion.meta_value;
        evaluacionDestino = dataDestino.Evaluacion.meta_value;

        const soloEnOrigen = evaluacionOrigen.filter(origen =>
            !evaluacionDestino.some(destino => origen.id === destino.id)
        );

        const soloEnDestino = evaluacionDestino.filter(destino =>
            !evaluacionOrigen.some(origen => origen.id === destino.id)
        );

        const respuestasDiferentes = evaluacionOrigen
                .filter(origen =>
                    evaluacionDestino.some(destino =>
                        origen.id === destino.id && origen.respuesta !== destino.respuesta
                    )
                )
                .map(origen => {
                    const destino = evaluacionDestino.find(d => d.id === origen.id);
                    return {
                        id: origen.id,
                        duda: origen.duda,
                        respuestaOrigen: origen.respuesta,
                        respuestaDestino: destino.respuesta,
                    };
                });
    
        drawPreguntasEvaluacion(soloEnOrigen, soloEnDestino, respuestasDiferentes);
      }
    } catch (e) {
        finalLoading(".diferenciasLoading", "error");
        console.error(e);
    }
}


function handleBotonCompararEvaluacionClick() {
    $(".btn-comparar").click(function () {
        if (
            !document.querySelector(".contenedorSelectorDestino").classList.contains("mshide") &&
            !document.querySelector(".contenedorSelectorOrigen").classList.contains("mshide") &&
            document.getElementById("evaluacionesOrigenSelector").value !== "" &&
            document.getElementById("evaluacionesDestinoSelector").value !== ""
        ) {
            let evaluacionOrigen = document.getElementById("evaluacionesOrigenSelector").value;
            let evaluacionDestino = document.getElementById("evaluacionesDestinoSelector").value;
            getPreguntas(evaluacionOrigen, evaluacionDestino);

        }
    });
}

$(document).ready(function () {
    handleBotonCompararEvaluacionClick();
});
  