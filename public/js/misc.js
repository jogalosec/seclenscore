/* ----------------------------------------------------
* FUNCIONES MISCELANEA
-------------------------------------------------------*/
function getValueChecked(clase) {
  let normativa = "";
  let items = $(`.${clase}:checked`);

  for (let i = 0; i < items.length; i++) {
    if (i === 0) {
      normativa += items[i].defaultValue;
    } else {
      normativa += `;${items[i].defaultValue}`;
    }
  }
  return normativa;
}

function cambiarFecha(fechaISO8601) {
  const fecha = new Date(fechaISO8601);

  // Obtener el día, mes y año de la fecha
  const dia = fecha.getDate();
  const mes = fecha.getMonth() + 1; // Los meses en JavaScript se indexan desde 0
  const anio = fecha.getFullYear();

  // Crear una cadena con el formato "DD/MM/AAAA"
  const fechaFormateada = `${anio}-${mes}-${dia}`;

  return fechaFormateada;
}

function extraerKey(inputString) {
  let i = 0;
  let start = 0;
  let end = 0;
  while (i < inputString.length) {
    if (inputString[i] == ">") {
      start = i + 1;
      while (i < inputString.length) {
        if (inputString[i] == "<") {
          end = i;
          return inputString.substring(start, end);
        }
        i++;
      }
    }
    i++;
  }
}

function selectorPlanCambio() {
  let vista = $("#vista-plan").val();
  if (vista == "pentest") {
    $("input:checked").trigger("click");
    $("input[name=servicio]").trigger("click");
    $("input[name=estado]").trigger("click");
    $("input[name=revisiones]").trigger("click");
    $("input[name=q1]").trigger("click");
    $("input[name=q2]").trigger("click");
    $("input[name=q3]").trigger("click");
    $("input[name=q4]").trigger("click");
  }

  if (vista == "arquitectura") {
    $("input:checked").trigger("click");
    $("input[name=servicio]").trigger("click");
    $("input[name=estado]").trigger("click");
    $("input[name=entorno]").trigger("click");
    $("input[name=tenable]").trigger("click");
    $("input[name=dome9]").trigger("click");
    $("input[name=usuarioacceso]").trigger("click");
  }

  if (vista == "seguimiento") {
    $("input:checked").trigger("click");
    $("input[name=servicio]").trigger("click");
    $("input[name=estado]").trigger("click");
    $("input[name=elevencert]").trigger("click");
    $("input[name=eprivacy]").trigger("click");
    $("input[name=jefeproyecto]").trigger("click");
  }
}

function toggleNav() {
  let sidenav = document.getElementById("Sidenav");
  let arrowImage = document.querySelector(".toggleNav .icono");
  let iconImages = document.querySelectorAll(".icono-img");
  let menuTexts = document.querySelectorAll(".menu-text");

  if (sidenav.style.width === "260px") {
    sidenav.style.width = "50px";
    arrowImage.classList.remove("rotar-180i");

    // Mostrar solo iconos y ocultar texto
    iconImages.forEach(function (img) {
      img.style.display = "inline-block";
    });
    menuTexts.forEach(function (text) {
      text.style.display = "none";
    });
  } else {
    sidenav.style.width = "260px";
    arrowImage.classList.remove("rotar-90d");
    arrowImage.classList.add("rotar-180i");

    // Mostrar iconos y texto
    iconImages.forEach(function (img) {
      img.style.display = "inline-block";
    });
    menuTexts.forEach(function (text) {
      text.style.display = "inline-block";
    });
  }
}

function clearBreadcrumb(call = true) {
  clearResponse();
  hideItem(".visual", true);
  while ($(`${BREADCRUMB}`).length > 1) {
    $(`${BREADCRUMB}`).last().remove();
  }
  let tipo;
  let upd;
  if ($(".tipo-filtrado").text() == "Filtrando por: Servicios") tipo = "42";
  if ($(".tipo-filtrado").text() == "Filtrando por: Servicios archivados")
    tipo = "42a";
  if ($(".tipo-filtrado").text() == "Filtrando por: Organización") {
    tipo = "94";
  }

  if ($(".tipo-servicio").text() == "Mostrando: Todos") upd = "Todos";
  if ($(".tipo-servicio").text() == "Mostrando: BIA no actualizado")
    upd = "NoAct";
  if ($(".tipo-servicio").text() == "Mostrando: ECR incompleto") upd = "NoECR";
  if (call) {
    getActivos(tipo, upd);
  }
}

function clearResponse() {
  $(".completeNe").html("");
  $(".evalservicio > .eval").html("");
  $(".activos > .response").html("");
}

function hideItem(clase, ocultar) {
  if (ocultar !== undefined) {
    if (ocultar) {
      $(clase).addClass("mshide");
    } else {
      $(clase).removeClass("mshide");
    }
  } else if ($(clase).hasClass("mshide")) {
    $(clase).removeClass("mshide");
  } else {
    $(clase).addClass("mshide");
  }
}

function agregarBotonNuevo(
  contenedorPadreId,
  claseBoton,
  textoBoton = "Nuevo reporte"
) {
  let nuevoDiv = `<button class="btn btn-primary ${claseBoton} nuevo" type="button">${textoBoton}</button>`;
  let contenedorPadre = $(`#${contenedorPadreId}`);
  let contenedor = contenedorPadre.find(".actionBar");

  // Comprobar si ya existe un botón con la misma clase y texto
  let botonExiste =
    contenedor.find(`button.${claseBoton}`).filter(function () {
      return $(this).text() === textoBoton;
    }).length > 0;

  if (!botonExiste) {
    contenedor.children(".search").before(nuevoDiv);
  }
}

function agregarSelectFecha(contenedorPadreId, claseSelect) {
  let nuevoDiv = `Año: <select class="form-select width-auto ${claseSelect} nuevo select-year"><option value="2022">2022</option><option value="2023">2023</option><option value="2024" selected>2024</option><option value="2025" selected>2025</option></select>`;
  let contenedorPadre = $(`#${contenedorPadreId}`);
  let contenedor = contenedorPadre.find(".actionBar");
  contenedor.children(".search").after(nuevoDiv);
  let tabla = $(`#${contenedorPadre[0].nextSibling.id}`);
  tabla.bootgrid("search", "2025");
  $(".search-field").val("");
  $(`.${claseSelect}`).change(function () {
    let year = $(this).val();
    $(`#${contenedorPadre[0].nextSibling.id}`).bootgrid("search", year);
    $(".search-field").val("");
  });
}

function agregarSelector(
  contenedorPadreId,
  claseSelect,
  opciones,
  tipoSelector = "select",
  placeholder = ""
) {
  // Generar el HTML del selector basado en las opciones proporcionadas
  let opcionesHTML = opciones
    .map(
      (opcion) =>
        `<option value="${opcion.value}" ${opcion.selected ? "selected" : ""}>${
          opcion.text
        }</option>`
    )
    .join("");
  let nuevoDiv = `${placeholder}: <${tipoSelector} class="form-select width-auto ${claseSelect} nuevo">${opcionesHTML}</${tipoSelector}>`;

  // Obtener el contenedor padre y añadir el nuevo selector
  let contenedorPadre = $(`#${contenedorPadreId}`);
  let contenedor = contenedorPadre.find(".actionBar");
  contenedor.children(".search").after(nuevoDiv);
}

function dosDecimales(n) {
  return parseFloat(n).toFixed(2);
}

function newTarjeta(insertarEn, nombreTarjeta, funcTarjeta, func) {
  let botonEval = "";
  let botonDelPadres = "";
  let botonCambiarPadres = "";
  let botonBIA = "";
  let botonDel = "";
  let botonEdit = "";
  let botonHistory = "";
  let botonClonar = "";
  let botonPersonas = "";
  let data = "";
  let idactivo;

  if (!isNullUndefined(func)) {
    if (!isNullUndefined(func["eval"])) {
      botonEval = `<div class="row evaluar"><img class="icono" alt="icono Evaluar servicio" title="Evaluar Servicio" src="./img/eval.svg"> Evaluación</div>`;
    }

    if (!isNullUndefined(func["del"])) {
      botonDel = `<div class="separator"></div><div class="row text-red borrar"><img class="icono delete-red aviso-rojo" alt="icono Borrar" title="Borrar activo" src="./img/delete.svg"> Eliminar</div>`;
    }

    if (!isNullUndefined(func["del_relation"])) {
      botonDelPadres = `<div class="row delPadres"><img class="icono" alt="icono cambiar relación" title="Eliminar relación" src="./img/Cortar.svg">Eliminar relación</div>`;
    }

    if (!isNullUndefined(func["Change_parents"])) {
      botonCambiarPadres = `<div class="row ChangePadres"><img class="icono" alt="icono cambiar relación" title="Cambiar relación" src="./img/Cambiar.svg">Cambiar relación</div>`;
    }

    if (!isNullUndefined(func["edit"])) {
      botonEdit = `<div class="row editar"><img class="icono" alt="icono Editar" title="Editar activo" src="./img/edit.svg"> Editar</div>`;
    }

    if (!isNullUndefined(func["history"])) {
      botonHistory = `<img class="icono historial" alt="icono Historial" title="Historial del activo" src="./img/history.svg"> `;
    }

    if (!isNullUndefined(func["bia"])) {
      botonBIA = `<img class="icono bia" alt="icono Bia" title="BIA del activo" src="./img/bia.svg"> `;
    }

    if (!isNullUndefined(func["clone"])) {
      botonClonar = `<div class="row clonar"><img class="icono" alt="icono Clonar" title="Clonar activo" src="./img/clone.svg"> Clonar</div>`;
    }

    if (!isNullUndefined(func["personas"])) {
      botonPersonas = `<div class="row personas"><img class="icono" alt="icono Personas" title="Información personas" src="./img/responsables.svg"> Responsables</div>`;
    }
  }

  if (!isNullUndefined(funcTarjeta)) {
    idactivo = funcTarjeta;
    funcTarjeta = `id="${funcTarjeta}"`;
  }
  data += '<div class="row tarjeta">';

  if (
    botonEval === "" &&
    botonEdit === "" &&
    botonDel === "" &&
    botonHistory === ""
  ) {
    data += `<div class="col-12" ${funcTarjeta}>${nombreTarjeta}</div>`;
  } else {
    let botonMas = `<img class="icono mas" data-bs-toggle="collapse" data-bs-target="#more-${idactivo}" aria-expanded="false" aria-controls="more-${idactivo}" alt="icono más opciones" title="Mas opciones" src="./img/more.svg"><div class="collapse" id="more-${idactivo}"><div class="card card-body card-custom">${botonDelPadres}${botonCambiarPadres}${botonEdit}${botonEval}${botonClonar}${botonPersonas}${botonDel}</div></div>`;
    data += `<div class="col-sm activo" ${funcTarjeta}> ${nombreTarjeta}</div>`;
    data += `<div class="col-auto text-end">${botonBIA}${botonHistory}${botonMas}</div>`;
  }

  data += "</div>";
  let tarjeta = $(insertarEn).append(data);
  return tarjeta[0].lastChild;
}

function isNullUndefined(item) {
  return item === null || item === undefined || item === false;
}

function collapse(item) {
  let padre = item.parentElement;
  for (let i = 0; i < padre.childNodes.length; i++) {
    if (i > 1) {
      if ($(padre.childNodes[i]).hasClass("mshide")) {
        $(padre.childNodes[i]).removeClass("mshide");
        $(item).addClass("rotar-90d");
      } else {
        if (!$(padre.childNodes[i]).attr("src")) {
          $(padre.childNodes[i]).addClass("mshide");
        }
        $(item).removeClass("rotar-90d");
      }
    }
  }
}

function pintarEsquema(id) {
  let $esquemavisual = $("#esquemavisual");
  $esquemavisual.html("");
  $esquemavisual.html(
    "<div class='text-center'><div class='spinner-border text-primary loading' role='status'></div>"
  );
  google.charts.load("current", { packages: ["orgchart"], language: "es" });

  google.charts.setOnLoadCallback(function () {
    const $orgChart = $("#esquemavisual");
    const orgChartTable = ".google-visualization-orgchart-table";

    $.ajax({
      type: "GET",
      url: `./api/getTree/${id}`,
      xhrFields: {
        withCredentials: true,
      },
      success: function (retorno) {
        let data = new google.visualization.DataTable(retorno.tree);
        data.addColumn("string", "Name");
        data.addColumn("string", "Manager");
        data.addColumn("string", "id");

        for (let value of retorno.tree) {
          let Pos_Name = value.nombre;
          let Emp_Name = value.tipo;
          let Emp_ID = value.id.toString();
          let Mngr_ID = value.padre != null ? value.padre.toString() : null;

          data.addRow([
            {
              v: Emp_ID,
              f: Pos_Name,
            },
            Mngr_ID,
            Emp_Name,
          ]);
        }

        data.setRowProperty(0, "style", "background: #009999 !important");

        let chart = new google.visualization.OrgChart(
          document.getElementById("esquemavisual")
        );
        chart.draw(data, {
          allowHtml: true,
          allowCollapse: true,
          size: "small",
        });

        $orgChart.append(
          "<button class='btn btn-default visual-mas'>+</button>"
        );
        $orgChart.append(
          "<button class='btn btn-default visual-menos'>-</button>"
        );

        $(".visual-mas, .visual-menos").click(function () {
          let element = $(orgChartTable);
          let scaleX = element.outerWidth() / element.width();
          let newScale =
            parseFloat(scaleX.toFixed(2)) +
            parseFloat($(this).hasClass("visual-menos") ? -0.02 : 0.02);

          if (newScale >= 0.58 && newScale <= 1.06) {
            element
              .removeClass()
              .addClass(
                `google-visualization-orgchart-table scale-${newScale * 100}`
              );
          }
        });

        $(".google-visualization-orgchart-node").dblclick(function () {});
      },
    });
  });
}

function pintarBarrasAnidadas(item, datos) {
  if (datos[1].length !== 0) {
    let dominios = [];
    let cumplimiento = [];
    let data = new google.visualization.DataTable();
    let count = 0;
    data.addColumn("string", "Estados");
    data.addColumn("number", "CT");
    data.addColumn("number", "CP");
    data.addColumn("number", "NC");
    data.addColumn("number", "NE");
    for (let value of datos[1]) {
      if (!dominios.includes(value.dominio)) {
        dominios.push(value.dominio);
        cumplimiento.push({ ct: 0, cp: 0, nc: 0, ne: 0 });
      }
      let index = dominios.indexOf(value.dominio);
      if (value.mne >= 60) {
        cumplimiento[index].ne++;
      } else if (value.mct < 50) {
        cumplimiento[index].nc++;
      } else if (value.mct < 85) {
        cumplimiento[index].cp++;
      } else {
        cumplimiento[index].ct++;
      }
    }
    for (let dominio of dominios) {
      let textoCorto = dominio;
      if (textoCorto === "Organización de la Seguridad de la Información")
        textoCorto = "Org. de la Seguridad de la Información";
      data.addRow([
        textoCorto,
        cumplimiento[count].ct,
        cumplimiento[count].cp,
        cumplimiento[count].nc,
        cumplimiento[count].ne,
      ]);
      count++;
    }

    let options = {
      chart: {
        fontFamily: "TelefonicaHeadline-Light",
      },
      annotations: {
        highContrast: true,
        textStyle: {
          auraColor: "none",
          fontSize: 4,
          color: "black",
        },
      },
      width: 500,
      height: 550,
      legend: { position: "top", maxLines: 3 },
      bar: { groupWidth: "40%" },
      isStacked: "percent",
      series: [
        { color: "#006600" }, //verde
        { color: "#b3b300" }, //amarillo
        { color: "#cc0000" }, //rojo
        { color: "#a6a6a6" }, //gris,
      ],
      vAxis: {
        textStyle: {
          fontSize: 8,
        },
      },

      hAxis: {
        minValue: 0,
        ticks: [0.1, 0.3, 0.5, 0.7, 0.9],
      },
    };

    let chart = new google.visualization.BarChart(item.childNodes[1]);
    chart.draw(data, options);
  }
}

async function pintarGrafica(datos, tipo, func, data2, norm) {
  google.charts.load("current", { packages: [tipo], language: "es" });
  google.charts.setOnLoadCallback(function () {
    if (data2 !== undefined) {
      func(datos, data2, norm);
    } else {
      func(datos);
    }
  });
}

function pintarTartaActivos(item, datos) {
  let mappedToArray = datos.map((d) => Array.from(Object.values(d)));
  mappedToArray.unshift(["tipo", "num"]);
  let data = google.visualization.arrayToDataTable(mappedToArray);

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

function pintarTartaSiyNo(item, datos) {
  let mappedToArray = datos.map((d) => Array.from(Object.values(d)));
  mappedToArray.unshift(["Si", "No"]);
  let data = google.visualization.arrayToDataTable(mappedToArray);

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

function pintarTartaBIA(item, datos) {
  datos.unshift(["ConBia", "SinBia"]);
  let data = google.visualization.arrayToDataTable(datos);
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

function pintarTartaPAC(item, datos) {
  datos.unshift(["estado", "numero"]);
  let data = google.visualization.arrayToDataTable(datos);
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

function pintarTartaEcr(item, datos) {
  datos.unshift(["ConECR", "SinECR"]);
  let data = google.visualization.arrayToDataTable(datos);

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

function pintarTarta(item, datos) {
  let cumplimiento = contarCumplimiento(datos[1]);
  let data = google.visualization.arrayToDataTable([
    ["Estado", "Controles"],
    ["CT", cumplimiento.ct],
    ["CP", cumplimiento.cp],
    ["NC", cumplimiento.nc],
    ["NE", cumplimiento.ne],
  ]);

  let options = {
    is3D: false,
    pieSliceTextStyle: {
      color: "white",
      fontSize: 12,
    },
    legend: { position: "top", maxLines: 3 },
    height: 550,
    width: 500,
    pieHole: 0.65,
    slices: {
      0: { color: "#006600" },
      1: { color: "#b3b300" },
      2: { color: "#cc0000" },
      3: { color: "#a6a6a6" },
      4: { color: "#a6a6a6" }, //gris,
    },
  };

  let chart = new google.visualization.PieChart(item.childNodes[2]);
  chart.draw(data, options);
  google.visualization.events.addListener(chart, "select", function () {
    let selection = chart.getSelection();
    if (selection[0] != undefined) {
      let row = selection[0].row;
      let id = data.getValue(row, 0);
      if (id == "NE") {
        let selectorIdEval = $(`.fechaEval option:selected`);
        let idEval = selectorIdEval.val();
        let idVersion = null;
        if ("evaluacion" in selectorIdEval[0].dataset) {
          idVersion = selectorIdEval[0].dataset.evaluacion;
        }
        getEvalNoEvaluados(item.firstChild.innerText, idEval, idVersion);
      }
    }
  });
}

function crearFormCuestionario(preguntas, selector) {
  clearResponse();
  let image =
    "<img class='icono colapsar icono-12 rotar-90d' src='./img/collapse.svg'/> ";
  $(".totalPreguntas").html(`Total preguntas: ${preguntas.length}`);
  $(".totalPreguntas").removeClass("mshide");
  let dominio = [];
  for (let pregunta of preguntas) {
    if (!dominio.includes(pregunta.dominio)) {
      dominio.push(pregunta.dominio);
      let cosa = dominio.indexOf(pregunta.dominio);
      newTarjeta(selector, image + pregunta.dominio, `dominio${cosa}`);
    }
    let index = dominio.indexOf(pregunta.dominio);
    newTarjeta(
      `#dominio${index}`,
      `${pregunta.cod} - ${pregunta.cod_ctrls} - ${pregunta.id}: ${pregunta.duda}`,
      `${pregunta.id}`
    );
    $(`#${pregunta.id}`).removeClass("col-12");
    $(`#${pregunta.id}`).addClass("col-10");
    let option = `<div class="col-2 text-end"><input type="text" class="mshide inputComment-${pregunta.id}" name="comment[${pregunta.id}]" /><input type="radio" name="evaluate[${pregunta.id}]" value="1" required /> Si <input type="radio" name="evaluate[${pregunta.id}]" value="0" /> No `;
    option += `<img class="icono icono-20 show-comentario" src="./img/comment.svg" title="Añadir comentario"></div>`;
    $($(`#dominio${index} > div > #${pregunta.id}`).parent()).append(option);
  }
  $(".colapsar").click(function (e) {
    collapse(e.target);
  });

  $(".colapsar").click();
  cerrarModal();
  handler();

  $(".show-comentario").click(function (e) {
    showComentario(e.target.parentElement.parentElement.firstElementChild.id);
  });
}

function downloadEsquema() {
  html2canvas(document.querySelector(".google-visualization-orgchart-table"), {
    allowTaint: true,
    logging: false,
  }).then((canvas) => {
    downloadCanvas(canvas);
  });
}

function downloadTree() {
  let nombre = $(".breadcrumb-item :last").text();
  let idp = $(".breadcrumb-item :last").attr("id");
  let url = `./api/downloadTree?id=${idp}`;
  $.ajax({
    url: url,
    type: "GET",
    xhrFields: {
      withCredentials: true,
      responseType: "blob",
    },
    success: function (data) {
      if (data.error === true) {
        showModalWindow("¡ERROR!", data.message, null, "Aceptar", null);
      } else {
        let a = document.createElement("a");
        let url = window.URL.createObjectURL(data);
        a.href = url;
        a.download = `${nombre}.xlsx`;
        document.body.append(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      }
    },
    error: function (xhr) {
      if (xhr.status !== 403) {
        showModalWindow(
          "¡ERROR!",
          "Ha ocurrido un error desconocido.",
          null,
          "Aceptar",
          null
        );
      } else {
        showModalWindow(
          "¡ERROR!",
          "No tienes permisos para realizar esta acción.",
          null,
          "Aceptar",
          null
        );
      }
    },
  });
  cerrarModal();
}

function downloadCanvas(canvas) {
  let myImage = canvas.toDataURL("image/jpeg", 1);
  let link = document.createElement("a");
  link.setAttribute("href", myImage);
  let servicio = $(".google-visualization-orgchart-node");
  servicio = servicio[0].innerText;
  link.setAttribute("download", `Esquema_${servicio.replace(/ /g, "_")}.jpeg`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showModalWindow(
    INFORMACION,
    "Se ha descargado la imagen del esquema correctamente.",
    null,
    "Aceptar",
    null,
    null
  );
}

function exportacionDoc() {
  let id = $(SERVICIO_ID).attr("id");
  $.get(`./api/getDocumentacionEval?id=${id}`, {}, function (retorno) {
    let Word = "data:word/document.xml,";
    Word += retorno;
    let encodedUri = encodeURI(Word);

    let link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "L2.docx");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });
}

function createNewPac() {
  let pac = $("#proyecto").val();
  let sistema = $("#sistemasPac").val();
  if (pac !== "undefined" || sistema !== "undefined") {
    $.ajax({
      url: `./api/createPac`,
      type: "POST",
      data: {
        pac: pac,
        sistema: sistema,
      },
      xhrFields: {
        withCredentials: true,
      },
      success: function (data, textStatus, jqXHR) {
        if (data.error === true) {
          showModalWindow("¡ERROR!", data.message, null, "Aceptar", null);
        } else {
          showModalWindow(
            INFORMACION,
            data.message,
            getPacServicio,
            null,
            "Aceptar",
            null
          );
        }
      },
      error: function (xhr) {
        let error = xhr.responseJSON;
        showModalWindow(
          "¡ERROR!",
          error.message || "Ha ocurrido un error desconocido.",
          null,
          "Aceptar",
          null
        );
      },
    });
  }
}

function expandir(item) {
  $(".colapsar").click();
  if ($(item).text() === "Expandir") {
    $(item).text("Contraer");
  } else {
    $(item).text("Expandir");
  }
}

function cerrarModal() {
  $("#ventanaModal").modal("hide");
}

function mostrarLoading(texto = null) {
  let textoMensaje;
  if (texto == null) {
    textoMensaje = "Esta operación puede tardar unos segundos.";
  } else {
    textoMensaje = "";
  }
  let mensaje = "";
  mensaje = `<div class="spinner-animation"><svg class="spinner-a" height="124" role="img" viewBox="0 0 66 66" width="124"><title>Cargando</title><circle class="spinner-circle" cx="33" cy="33" fill="none" r="30" role="presentation" stroke-width="3" stroke="#019DF4"></circle></svg></div><div>${textoMensaje}</div>`;
  showModalWindow(null, mensaje, null, null, null, null, false);
}

function insertarCarga(site, id = "") {
  let mensaje = "";
  mensaje = `<div class="spinner-animation text-center load ${id}"> \
      <svg class="spinner-a" height="124" role="img" viewBox="0 0 66 66" width="124">\
        <circle class="spinner-circle" cx="33" cy="33" fill="none" r="30" role="presentation" stroke-width="3" stroke="#019DF4"></circle>\
      </svg>\
      <div>Cargando contenido.</div>\
    </div>`;
  site.append(mensaje);
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function laMedia(value, total) {
  let num = (value * 100) / total;
  return num.toFixed(1);
}

function contarCumplimiento(values) {
  let cumplimiento = { ct: 0, cp: 0, nc: 0, ne: 0 };
  if (values !== null) {
    for (let value of values) {
      if (value.mne >= 60) {
        cumplimiento.ne++;
      } else if (value.mct < 50) {
        cumplimiento.nc++;
      } else if (value.mct < 85) {
        cumplimiento.cp++;
      } else {
        cumplimiento.ct++;
      }
    }
  }
  return cumplimiento;
}

function lockkpms() {
  let div = $(this).parent().parent().parent().parent().parent();
  let tiporeporte;
  let tabla = $(div).closest("div").find("table");
  tabla = $(`#${tabla.attr("id")}`).bootgrid();
  if (tabla.attr("id") == "table-metricas") {
    tiporeporte = "metricas";
  } else if (tabla.attr("id") == "table-madurez") {
    tiporeporte = "madurez";
  } else {
    tiporeporte = "csirt";
  }
  let filasMarcadas = tabla.bootgrid("getSelectedRows");
  let idsMarcados = [];
  if (filasMarcadas.length === 0) {
    showModalWindow(
      INFORMACION,
      "No se ha seleccionado ningún reporte para bloquear.",
      null,
      "Aceptar",
      null,
      null
    );
  } else {
    $.each(filasMarcadas, function (index, fila) {
      idsMarcados.push(fila);
    });
    $.ajax({
      url: `./api/lockKpms`,
      type: "POST",
      data: {
        id: idsMarcados,
        tipo: tiporeporte,
      },
      xhrFields: {
        withCredentials: true,
      },
      success: function (data, textStatus, jqXHR) {
        cerrarModal();
        if (data.error === true) {
          showModalWindow("¡ERROR!", data.message, null, "Aceptar", null);
        } else {
          showModalWindow(
            INFORMACION,
            data.message,
            null,
            "Aceptar",
            null,
            getkpms
          );
        }
      },
    });
  }
}

function unlockkpms() {
  let div = $(this).parent().parent().parent().parent().parent();
  let tiporeporte;
  let tabla = $(div).closest("div").find("table");
  tabla = $(`#${tabla.attr("id")}`).bootgrid();
  if (tabla.attr("id") == "table-metricas") {
    tiporeporte = "metricas";
  } else if (tabla.attr("id") == "table-madurez") {
    tiporeporte = "madurez";
  } else {
    tiporeporte = "csirt";
  }
  let filasMarcadas = tabla.bootgrid("getSelectedRows");
  let idsMarcados = [];
  if (filasMarcadas.length === 0) {
    showModalWindow(
      INFORMACION,
      "No se ha seleccionado ningún reporte para desbloquear.",
      null,
      "Aceptar",
      null,
      null
    );
  } else {
    $.each(filasMarcadas, function (index, fila) {
      idsMarcados.push(fila);
    });
    $.ajax({
      url: `./api/unlockKpms`,
      type: "POST",
      data: {
        id: idsMarcados,
        tipo: tiporeporte,
      },
      xhrFields: {
        withCredentials: true,
      },
      success: function (data, textStatus, jqXHR) {
        cerrarModal();
        if (data.error === true) {
          showModalWindow("¡ERROR!", data.message, null, "Aceptar", null);
        } else {
          showModalWindow(
            INFORMACION,
            data.message,
            null,
            "Aceptar",
            null,
            getkpms
          );
        }
      },
    });
  }
}

function inputCambiado(input, urlPost) {
  let file = input.prop("files")[0];
  let formData = new FormData();
  formData.append("file", file);
  mostrarLoading();
  $.ajax({
    url: urlPost,
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    xhrFields: {
      withCredentials: true,
    },

    success: function (data, textStatus, jqXHR) {
      cerrarModal();
      if (data.error === true) {
        showModalWindow("¡ERROR!", data.message, null, "Aceptar", null);
      } else if (data.redirect !== undefined) {
        showModalWindow(
          INFORMACION,
          data.message,
          redirectHistoryService,
          null,
          "Aceptar"
        );
      } else {
        showModalWindow(INFORMACION, data.message, null, "Aceptar", null);
      }
    },
  });
}

function updateBreadCrumb(nombre, nivel, id) {
  let level = $(BREADCRUMB).length;
  if ($(`${BREADCRUMB} #${id}`).length === 0) {
    $(`.${BREADLIST}`).append(
      `<li class="breadcrumb-item" aria-current="page"><a href="#" id="${id}" class="b-item title" level="${
        level + nivel
      }">${nombre}</a></li>`
    );
  }

  $(`#${id}`).one("click", function (event) {
    event.stopPropagation();
    let niveles = $(BREADCRUMB).length;
    nivel = $(this).attr("level");
    if (level < niveles) {
      for (let i = 0; i < niveles - nivel; i++) {
        $(BREADCRUMB).last().remove();
      }
    }
    getChild(this.id);
  });
}

function showComentario(idPregunta) {
  let texto = "";
  if ($(".inputComment-" + idPregunta) !== "") {
    texto = $(".inputComment-" + idPregunta).val();
  }

  showModalWindow(
    "Nuevo comentario",
    `<textarea id="${idPregunta}" class="textarea-comentario" cols="28" rows="5">`,
    LinkComentario
  );
  $(".textarea-comentario").val(texto);
}

function LinkComentario() {
  let id = $(".textarea-comentario").attr("id");
  let texto = $(".textarea-comentario").val();
  $(".inputComment-" + id).val(texto);
  cerrarModal();
}

function lastItemb() {
  $(`${BREADCRUMB} a`).last().click();
}

function redirectHistoryService() {
  let idservicio = $(".servicioId").attr("id");
  window.location.href = `./historialservicio?id=${idservicio}`;
}

function goLogin() {
  window.location.href = `./login`;
}

function goHome() {
  window.location.href = `./app`;
}

function heatMap(id, datos) {
  let titulo = "";
  if (id === "heatmap-inherit") {
    titulo = "Riesgo Inherente";
  } else if (id === "heatmap-now") {
    titulo = "Riesgo Actual";
  } else {
    titulo = "Riesgo Residual";
  }

  Highcharts.chart(id, {
    chart: {
      type: "heatmap",
      plotBackgroundColor: "none",
    },

    title: {
      text: titulo,
    },

    accessibility: {
      enabled: false,
    },

    xAxis: {
      categories: ["Muy bajo", "Bajo", "Medio", "Alto", "Muy alto"],
      title: {
        text: "Probabilidad",
      },
      min: 0,
      max: 4,
    },

    yAxis: {
      categories: ["Muy alto", "Alto", "Medio", "Bajo", "Muy bajo"],
      title: {
        text: "Impacto",
      },
      reversed: true,
      min: 0,
      max: 4,
    },

    colorAxis: {
      min: 0,
    },

    legend: {
      enabled: false,
    },

    series: [
      {
        name: "amenazas",
        borderWidth: 1,
        data: datos,
        dataLabels: {
          enabled: true,
          color: "#ffffff",
        },
      },
    ],
  });
  $(".highcharts-credits").remove();
}
