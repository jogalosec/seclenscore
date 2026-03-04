/*!
 * Custom Modal v1.0 (github)
 * Copyright 2011-2019 The Modal Custom Authors (Raúl Llamas Y. Mihai Emanuel Fira)
 * Licensed (github/license)
 */
const MODAL_FOOTER = ".modal-footer";
const BTN_ACEPTAR = "#botonAceptar";
const BTN_CERRAR = "#botonCerrar";
const VENTANA_MODAL = "#ventanaModal";

jQuery(document).ready(function ($) {
  let showAfterLoad = $("#showAfterLoad");
  let body = $("body");
  if (!showAfterLoad.length) {
    let html = '<div id="showAfterLoad" class="display-block">';
    html +=
      '<div class="modal" id="ventanaModal" data-bs-backdrop="static" data-bs-keyboard="false"';
    html +=
      'tabindex="-1" role="dialog" aria-labelledby="ventanaCentradaVerticalmente" aria-hidden="true">';
    html += '<div class="modal-dialog modal-dialog-centered" role="document">';
    html += '<div class="modal-content">';
    html += '<div id="cuerpoModal" class="modal-body text-center text-break">';
    html += "textoVentanaModal";
    html += "</div>";
    html += '<div class="modal-footer">';
    html +=
      '<button id="botonCerrar" type="button" class="btn btn-secondary" data-bs-dismiss="modal">textoBtnCerrar</button>';
    html +=
      '<button id="botonAceptar" type="button" class="btn btn-primary">textBtnAceptar</button>';
    html += "</div>";
    html += "</div>";
    html += "</div>";
    html += "</div>";
    html += "</div>";
    body.append(html);
  }
});

function showModalWindow(
  tituloVentanaModal,
  textoVentanaModal,
  callbackBtnAceptar,
  textoBtnCerrar = "Cerrar",
  textoBtnAceptar = "Aceptar",
  callbackBtnCancelar = null,
  animacion = true,
  tamaño = "modal-sm"
) {
  jQuery(".modal-header").remove();
  if (!isNull(tituloVentanaModal)) {
    jQuery(".modal-content").prepend(
      `<div class="modal-header"><h5 class="modal-title" id="tituloModal">${tituloVentanaModal}</h5></div>`
    );
  }

  $(".modal-dialog").removeClass("modal-sm");
  $(".modal-dialog").removeClass("modal-lm");

  $(".modal-dialog").addClass(tamaño);

  jQuery("#cuerpoModal").html(textoVentanaModal);

  let botonCerrar =
    '<button id="botonCerrar" type="button" class="btn btn-secondary" data-bs-dismiss="modal">textoBtnCerrar</button>';
  let botonAceptar =
    '<button id="botonAceptar" type="button" class="btn btn-primary">textBtnAceptar</button>';

  // COMPROBACIONES PARA UNA VEZ BORRADOS LOS BOTONES GENERARLOS
  if (isNull(document.getElementsByClassName("modal-footer").length)) {
    jQuery(".modal-content").append('<div class="modal-footer"></div>');
  }

  if (isNull(document.getElementById("botonCerrar"))) {
    jQuery(MODAL_FOOTER).append(botonCerrar);
  }
  if (isNull(document.getElementById("botonAceptar"))) {
    jQuery(MODAL_FOOTER).append(botonAceptar);
  }

  if (isNull(textoBtnCerrar) && isNull(textoBtnAceptar)) {
    jQuery(MODAL_FOOTER).remove();
  }

  if (!isNull(textoBtnCerrar)) {
    jQuery(BTN_CERRAR).html(textoBtnCerrar);
  } else {
    jQuery(BTN_CERRAR).remove();
  }
  if (!isNull(textoBtnAceptar)) {
    jQuery(BTN_ACEPTAR).html(textoBtnAceptar);
    jQuery(BTN_ACEPTAR).unbind("click");
    jQuery(BTN_ACEPTAR).click(function () {
      callbackBtnAceptar();
    });
  } else {
    jQuery(BTN_ACEPTAR).remove();
  }
  jQuery(VENTANA_MODAL).removeClass("fade");
  if (animacion) {
    jQuery(VENTANA_MODAL).addClass("fade");
  }
  if (!isNull(callbackBtnCancelar)) {
    jQuery(BTN_CERRAR).unbind("click");
    jQuery(BTN_CERRAR).click(function () {
      callbackBtnCancelar();
    });
  }
  jQuery(VENTANA_MODAL).modal("show");
}

function isNull(item) {
  return item === null || item === 0;
}
