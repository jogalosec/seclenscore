import {
  insertarActivosRevisiones,
  insertarActivosPentests,
  realizarEvaluaciones,
  realizarEvaluacionRevisiones,
  obtenerTodosPentest,
  obtenerTodasRevisiones,
  obtenerActivosPentest,
  obtenerActivosRevision,
  obtenerServiciosPentest,
  obtenerServiciosRevision,
  obtenerActivosHijosTipos,
} from "../../api/evaluacionesApi.js";

function setStats(stats) {
  $(".textSinCerrar").text(stats.abiertas);
  $(".textSinIdentificar").text(stats.identificables);
  $(".textPorEvaluar").text(stats.evaluables);
  $(".textEvaluadas").text(stats.evaluadas);
}

async function insertarActivosRevision(id) {
  cerrarModal();
  let campos = $("#form-InsertActivosRevision :input");
  let data = {};
  campos.each(function () {
    data[$(this).attr("name")] = $(this).val();
  });
  try {
    await insertarActivosRevisiones(id, data);
    location.reload();
  } catch (e) {
    console.error(e);
  }
}

async function insertarActivosPentest(id) {
  cerrarModal();
  let campos = $("#form-InsertActivos :input");
  let data = {};
  campos.each(function () {
    data[$(this).attr("name")] = $(this).val();
  });
  try {
    await insertarActivosPentests(id, data);
    location.reload();
  } catch (e) {
    console.error(e);
  }
}

function comprobarActivosPentest(id, tipo) {
  let campos = $("#form-InsertActivos :input");
  let error = false;
  campos.each(function () {
    if ($(this).val() == "Ninguno") {
      if ($(this).attr("name") == "Direccion") {
        error = true;
        $(".direccion-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Area") {
        error = true;
        $(".area-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Servicio") {
        error = true;
        $(".servicio-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Sistema") {
        error = true;
        $(".sistema-label").addClass("text-danger");
      }
    }
  });
  if (!error) {
    if (tipo == "Pentest") insertarActivosPentest(id);
    else insertarActivosRevision(id);
  }
}

function comprobarActivosRevision(id, tipo) {
  let campos = $("#form-InsertActivosRevision :input");
  let error = false;
  campos.each(function () {
    if ($(this).val() == "Ninguno") {
      if ($(this).attr("name") == "Direccion") {
        error = true;
        $(".direccion-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Area") {
        error = true;
        $(".area-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Servicio") {
        error = true;
        $(".servicio-label").addClass("text-danger");
      } else if ($(this).attr("name") == "Sistema") {
        error = true;
        $(".sistema-label").addClass("text-danger");
      }
    }
  });
  if (!error) {
    insertarActivosRevision(id);
  }
}

function insertarOpciones(padre, destino, tipo) {
  $(destino).empty();
  let option = `<option value="Ninguno">Cargando opciones...</option>`;
  $(destino).append(option);
  obtenerActivosHijosTipo($(padre).val(), tipo).then((data) => {
    $(destino).empty();
    if (tipo != 33 || $(".sistema-label").length > 1)
      option = `<option value="Ninguno">Ninguno</option>`;
    else option = `<option value="Todos">Todos</option>`;
    $(destino).append(option);
    for (let hijo of data["Hijos"]) {
      option = `<option value="${hijo["id"]}">${hijo["nombre"]}</option>`;
      $(destino).append(option);
    }
  });
}

function obtenerActivosHijosTipo(nombre, tipo) {
  return obtenerActivosHijosTipos(nombre, tipo);
}

async function establecerServicios(tipo, id) {
  const servicios = await obtenerServiciosPentest(tipo, id);
  for (let Servicio of servicios["Hijos"]) {
    let option = `<option value="${Servicio["id"]}">${Servicio["nombre"]}</option>`;
    $(".servicioInput").append(option);
  }
}

async function establecerServiciosRevision(tipo, id) {
  const servicios = await obtenerServiciosRevision(tipo, id);
  for (let Servicio of servicios["Hijos"]) {
    let option = `<option value="${Servicio["id"]}">${Servicio["nombre"]}</option>`;
    $(".servicioInput").append(option);
  }
}

function configDesplegables(tipo, id) {
  establecerServicios(tipo, id);
  $(".servicioInput").on("change", function () {
    $(".sistemaBloqueGeneral").addClass("mshide");
    $(".sistemaInput").val("Ninguno");
    if ($(".servicioInput").val() != "Ninguno") {
      $(".sistemaBloqueGeneral").removeClass("mshide");
      insertarOpciones(
        ".servicioInput",
        ".sistemaInput0",
        "Sistema de Información"
      );
    }
  });
}

function configDesplegablesRevision(tipo, id) {
  establecerServiciosRevision(tipo, id);
  $(".servicioInput").on("change", function () {
    $(".sistemaBloqueGeneral").addClass("mshide");
    $(".sistemaInput").val("Ninguno");
    if ($(".servicioInput").val() != "Ninguno") {
      $(".sistemaBloqueGeneral").removeClass("mshide");
      insertarOpciones(
        ".servicioInput",
        ".sistemaInput0",
        "Sistema de Información"
      );
    }
  });
}

function configSistemas(tipo) {
  $(".añadirBtn").click(function (e) {
    let cantidad = $(".sistema-label").length;
    let sistema = ` <div class="form-group mb-4 row sistemaBloqueGeneral sistemaBloque${cantidad}">
                          <label for="select7" class="col-4 col-form-label sistema-label">Sistema${
                            cantidad + 1
                          }</label> 
                          <div class="col-8">
                              <select id="sistema" name="Sistema${cantidad}" class="form-select-custom sistemaInput sistemaInput${cantidad}" required="required">
                                  <option value="Ninguno">Ninguno</option>
                              </select>
                          </div>
                      </div>`;
    $(".issueForm").append(sistema);
    insertarOpciones(".servicioInput", `.sistemaInput${cantidad}`, tipo);
  });
  $(".quitarBtn").click(function (e) {
    let cantidad = $(".sistema-label").length;
    $(`.sistemaBloque${cantidad - 1}`).remove();
  });
}

function setLabelStyle() {
  $(".direccionInput").on("change", function () {
    $(".direccion-label").removeClass("text-danger");
  });
  $(".areaInput").on("change", function () {
    $(".area-label").removeClass("text-danger");
  });
  $(".servicioInput").on("change", function () {
    $(".servicio-label").removeClass("text-danger");
  });
  $(".sistemaInput").on("change", function () {
    $(".sistema-label").removeClass("text-danger");
  });
}

function asignarActivos(pentest, tipo = "Pentest") {
  let id = pentest.id;
  let form = `<form class="issueForm" id="form-InsertActivos">
                <div class="form-group mb-4 row servicioBloque">
                    <label for="select7" class="col-6 col-form-label servicio-label">Servicio de negocio</label> 
                    <div class="col-6">
                        <select id="servicio" name="Servicio" class="form-select-custom servicioInput" required="required">
                            <option value="Ninguno">Ninguno</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row sistemaBloqueGeneral justify-content-end mshide">
                    <button type="button" class="btn btn-transparent añadirBtn">
                        <img src="./img/añadir.svg" alt="Imagen" class="img-fluid" />
                    </button>
                    <button type="button" class="btn btn-transparent quitarBtn">
                        <img src="./img/restar.svg" alt="Imagen" class="img-fluid" />
                    </button>
                </div>
                <div class="form-group mb-4 row sistemaBloqueGeneral sistemaBloque mshide">
                    <label for="select7" class="col-4 col-form-label sistema-label">Sistema</label> 
                    <div class="col-8">
                        <select id="sistema" name="Sistema" class="form-select-custom sistemaInput sistemaInput0" required="required">
                            <option value="Ninguno">Ninguno</option>
                        </select>
                    </div>
                </div>
            </form>`;
  showModalWindow("Asignar activos", form, function () {
    comprobarActivosPentest(id, tipo);
  });
  configDesplegables("Servicio de Negocio", pentest.id);
  configSistemas("Sistema de Información");
  setLabelStyle();
}

function asignarActivosRevision(revision, tipo = "Revision") {
  let id = revision.id;
  let form = `<form class="issueForm" id="form-InsertActivosRevision">
                <div class="form-group mb-4 row servicioBloque">
                    <label for="select7" class="col-6 col-form-label servicio-label">Servicio de negocio</label> 
                    <div class="col-6">
                        <select id="servicio" name="Servicio" class="form-select-custom servicioInput" required="required">
                            <option value="Ninguno">Ninguno</option>
                        </select>
                    </div>
                </div>
                <div class="form-group row sistemaBloqueGeneral justify-content-end mshide">
                    <button type="button" class="btn btn-transparent añadirBtn">
                        <img src="./img/añadir.svg" alt="Imagen" class="img-fluid" />
                    </button>
                    <button type="button" class="btn btn-transparent quitarBtn">
                        <img src="./img/restar.svg" alt="Imagen" class="img-fluid" />
                    </button>
                </div>
                <div class="form-group mb-4 row sistemaBloqueGeneral sistemaBloque mshide">
                    <label for="select7" class="col-4 col-form-label sistema-label">Sistema</label> 
                    <div class="col-8">
                        <select id="sistema" name="Sistema" class="form-select-custom sistemaInput sistemaInput0" required="required">
                            <option value="Ninguno">Ninguno</option>
                        </select>
                    </div>
                </div>
            </form>`;
  showModalWindow("Asignar activos", form, function () {
    comprobarActivosRevision(id, tipo);
  });
  configDesplegablesRevision("Servicio de Negocio", revision.id);
  configSistemas("Sistema de Información");
  setLabelStyle();
}

async function realizarEvaluacion(pentest) {
  cerrarModal();
  mostrarLoading();
  try {
    const retorno = await realizarEvaluaciones(pentest.id);
    if (!retorno["error"]) {
      console.log("Se ha realizado la evaluación");
      location.reload();
    } else {
      console.log("Error al realizar la evaluación.");
    }
  } catch (e) {
    console.error(e);
  }
}

async function realizarEvaluacionRevision(revision) {
  cerrarModal();
  mostrarLoading();
  try {
    const retorno = await realizarEvaluacionRevisiones(revision.id);
    if (!retorno["error"]) {
      console.log("Se ha realizado la evaluación");
      location.reload();
    } else {
      console.log("Error al realizar la evaluación.");
    }
  } catch (e) {
    console.error(e);
  }
}

async function accionesPentestIdentificado(pentest) {
  mostrarLoading();
  try {
    const retorno = await obtenerActivosPentest(pentest.id);
    cerrarModal();
    let activos = "";
    for (let activo of retorno) {
      activos += `<p>${activo.nombre}</p>\n`;
    }
    let form = `<div class="row">
                  <div class="col-md-12 mb-2">
                      <h4 class="text-start">Vas a evaluar el siguiente pentest:</h4>
                  </div>
                  <div class="col-md-12">
                      <table class="table">
                          <tbody>
                          <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">ID:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.id}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Nombre:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.nombre}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Responsable:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.resp_pentest}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Proyecto:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.proyecto}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Responsable del proyecto:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.resp_proyecto}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Fecha de inicio:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.fecha_inicio}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Fecha de finalización:</strong>
                          </td>
                          <td class="col-md-6 text-start">${pentest.fecha_final}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Activos:</strong>
                          </td>
                          <td class="col-md-6 text-start">${activos}</td>
                      </tr>
                          </tbody>
                      </table>
                  </div>
              </div>`;
    showModalWindow("", form, function () {
      realizarEvaluacion(pentest);
    });
  } catch (e) {
    console.error(e);
  }
}

async function accionesRevisionIdentificado(revision) {
  mostrarLoading();
  try {
    const retorno = await obtenerActivosRevision(revision.id);
    cerrarModal();
    let activos = "";
    for (let activo of retorno) {
      activos += `<p>${activo.nombre}</p>\n`;
    }
    let form = `<div class="row">
                  <div class="col-md-12 mb-2">
                      <h4 class="text-start">Vas a evaluar la siguiente revision:</h4>
                  </div>
                  <div class="col-md-12">
                      <table class="table">
                          <tbody>
                          <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">ID:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.id}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Nombre:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.nombre}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Responsable:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.resp_revision}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Proyecto:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.proyecto}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Responsable del proyecto:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.resp_proyecto}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Fecha de inicio:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.fecha_inicio}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Fecha de finalización:</strong>
                          </td>
                          <td class="col-md-6 text-start">${revision.fecha_final}</td>
                      </tr>
                      <tr>
                          <td class="text-start col-md-4">
                              <strong class="text-start">Activos:</strong>
                          </td>
                          <td class="col-md-6 text-start">${activos}</td>
                      </tr>
                          </tbody>
                      </table>
                  </div>
              </div>`;
    showModalWindow("", form, function () {
      realizarEvaluacionRevision(revision);
    });
  } catch (e) {
    console.error(e);
  }
}

function accionesPentestSinIdentificar(pentest, tipo = "Pentest") {
  let titulo = "";
  if (tipo == "Pentest") {
    titulo = "Vas a identificar el siguiente pentest:";
  } else {
    titulo = "Vas a identificar la siguiente revisión:";
  }
  let form = `<div class="row">
                <div class="col-md-12 mb-2">
                    <h4 class="${titulo}"</h4>
                </div>
                <div class="col-md-12">
                    <table class="table">
                        <tbody>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">ID:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.id}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Nombre:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.nombre}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Responsable:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.resp_pentest}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Proyecto:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.proyecto}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Responsable del proyecto:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.resp_proyecto}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Fecha de inicio:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.fecha_inicio}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Fecha de finalización:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.fecha_final}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Descripción:</strong>
                                </td>
                                <td class="col-md-6 text-start">${pentest.descripcion}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
  showModalWindow("Pentest sin identificar", form, function () {
    asignarActivos(pentest, tipo);
  });
}

function accionesRevisionSinIdentificar(revision, tipo = "Revision") {
  let titulo = "";
  if (tipo == "Revision") {
    titulo = "Vas a identificar la siguiente revisión:";
  }
  let form = `<div class="row">
                <div class="col-md-12 mb-2">
                    <h4 class="${titulo}"</h4>
                </div>
                <div class="col-md-12">
                    <table class="table">
                        <tbody>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">ID:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.id}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Nombre:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.nombre}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Responsable:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.resp_pentest}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Proyecto:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.proyecto}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Responsable del proyecto:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.resp_proyecto}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Fecha de inicio:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.fecha_inicio}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Fecha de finalización:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.fecha_final}</td>
                            </tr>
                            <tr>
                                <td class="text-start col-md-4">
                                    <strong class="text-start">Descripción:</strong>
                                </td>
                                <td class="col-md-6 text-start">${revision.descripcion}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>`;
  showModalWindow("Revision sin identificar", form, function () {
    asignarActivosRevision(revision, tipo);
  });
}

function fechaNormal(fecha_inicio) {
  let date = new Date(fecha_inicio);
  let day = String(date.getDate()).padStart(2, "0");
  let month = String(date.getMonth() + 1).padStart(2, "0");
  let year = date.getFullYear();
  return `${day}-${month}-${year}`;
}

function insertarTarjetaIdent(pentest, tipo = "Pentest") {
  $(".evalCompletas").addClass("mshide");
  let tipoBorder = "border-dashed";
  let img = "";
  if (tipo == "Pentest")
    img = `<img src="./img/evsgris.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  else
    img = `<img src="./img/eas.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  let card = `<div class="col-md-6">
                <div class="card ${tipoBorder} mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    ${img}
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title">${
                                  pentest.nombre
                                } - ${fechaNormal(pentest.fecha_inicio)}</h5>
                                <p class="card-text">${pentest.descripcion}</p>
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
  $(".tarjetas").append(card);
  $(`.btn-pen${pentest.id}`).click(function () {
    accionesPentestSinIdentificar(pentest, tipo);
  });
}

function insertarTarjetaIdentRevision(revision, tipo = "Revision") {
  $(".evalCompletas").addClass("mshide");
  let tipoBorder = "border-dashed";
  let img = "";
  if (tipo == "Revision")
    img = `<img src="./img/eas.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  let card = `<div class="col-md-6">
                <div class="card ${tipoBorder} mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    ${img}
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title">${
                                  revision.nombre
                                } - ${fechaNormal(revision.fecha_inicio)}</h5>
                                <p class="card-text">${revision.descripcion}</p>
                            </div>
                            <div class="col-md-2 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-primary btn_eval d-flex align-items-center btn-rev${
                                  revision.id
                                }">Acciones</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
  $(".tarjetas").append(card);
  $(`.btn-rev${revision.id}`).click(function () {
    accionesRevisionSinIdentificar(revision, tipo);
  });
}

function insertarTarjeta(pentest, tipo = "Pentest") {
  $(".evalCompletas").addClass("mshide");
  let tipoBorder = "border-solid";
  let img = "";
  if (tipo == "Pentest")
    img = `<img src="./img/evsgris.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  else
    img = `<img src="./img/eas.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  let card = `<div class="col-md-6">
                <div class="card ${tipoBorder} mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    ${img}
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title">${pentest.nombre}</h5>
                                <p class="card-text">${pentest.descripcion}</p>
                            </div>
                            <div class="col-md-2 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-primary btn_eval d-flex align-items-center btn-pen${pentest.id}">Acciones</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
  $(".tarjetas").append(card);
  if (tipo == "Pentest") {
    $(`.btn-pen${pentest.id}`).click(function () {
      accionesPentestIdentificado(pentest);
    });
  }
}

function insertarTarjetaRevision(revision, tipo = "Revision") {
  $(".evalCompletas").addClass("mshide");
  let tipoBorder = "border-solid";
  let img = "";
  if (tipo == "Revision")
    img = `<img src="./img/eas.svg" class="img-prueba d-flex align-items-center" alt="Aura"></img>`;
  let card = `<div class="col-md-6">
                <div class="card ${tipoBorder} mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 d-flex align-items-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    ${img}
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title">${revision.nombre}</h5>
                                <p class="card-text">${revision.descripcion}</p>
                            </div>
                            <div class="col-md-2 d-flex justify-content-end align-items-center">
                                <button type="button" class="btn btn-primary btn_eval d-flex align-items-center btn-rev${revision.id}">Acciones</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
  $(".tarjetas").append(card);
  if (tipo == "Revision") {
    $(`.btn-rev${revision.id}`).click(function () {
      accionesRevisionIdentificado(revision);
    });
  }
}

function insertarTablaRealizadas(pentest) {
  let issuesArray = [
    {
      Nombre: pentest.nombre,
      tipoPrueba: pentest.tipo,
      Proyecto: pentest.proyecto,
      Responsable: pentest.resp_pentest,
      ResponsableProy: pentest.resp_pentest,
      FechaInicio: pentest.fecha_inicio,
      FechaFinal: pentest.fecha_final,
    },
  ];
  $("#evaluacionesRealizadas").bootgrid("append", issuesArray);
}

function insertarTablaRealizadasRevision(revision) {
  let issuesArray = [
    {
      Nombre: revision.nombre,
      tipoPrueba: revision.tipo,
      Proyecto: revision.proyecto,
      Responsable: revision.resp_revision,
      ResponsableProy: revision.resp_revision,
      FechaInicio: revision.fecha_inicio,
      FechaFinal: revision.fecha_final,
    },
  ];
  $("#evaluacionesRealizadas").bootgrid("append", issuesArray);
}

function insertarTablaEnProceso(pentest) {
  let issuesArray = [
    {
      Nombre: pentest.nombre,
      tipoPrueba: pentest.tipo,
      Proyecto: pentest.proyecto,
      Responsable: pentest.resp_pentest,
      ResponsableProy: pentest.resp_pentest,
      FechaInicio: pentest.fecha_inicio,
      FechaFinal: pentest.fecha_final,
    },
  ];
  $("#pruebasEnProceso").bootgrid("append", issuesArray);
}

function insertarTablaEnProcesoRevision(revision) {
  let issuesArray = [
    {
      Nombre: revision.nombre,
      tipoPrueba: revision.tipo,
      Proyecto: revision.proyecto,
      Responsable: revision.resp_revision,
      ResponsableProy: revision.resp_revision,
      FechaInicio: revision.fecha_inicio,
      FechaFinal: revision.fecha_final,
      //   commands: estado,
    },
  ];
  $("#pruebasEnProceso").bootgrid("append", issuesArray);
}

function insertarTablaPorEvaluar(pentest) {
  let issuesArray = [
    {
      Nombre: pentest.nombre,
      tipoPrueba: pentest.tipo,
      Proyecto: pentest.proyecto,
      Responsable: pentest.resp_pentest,
      ResponsableProy: pentest.resp_pentest,
      FechaInicio: pentest.fecha_inicio,
      FechaFinal: pentest.fecha_final,
      //   commands: estado,
    },
  ];
  $("#evaluacionesPorRealizar").bootgrid("append", issuesArray);
}

function insertarTablaPorEvaluarRevision(revision) {
  let issuesArray = [
    {
      Nombre: revision.nombre,
      tipoPrueba: revision.tipo,
      Proyecto: revision.proyecto,
      Responsable: revision.resp_revision,
      ResponsableProy: revision.resp_revision,
      FechaInicio: revision.fecha_inicio,
      FechaFinal: revision.fecha_final,
      //   commands: estado,
    },
  ];
  $("#evaluacionesPorRealizar").bootgrid("append", issuesArray);
}

function setStatsSumados(
  statsPentest,
  statsRevision,
  evaluablesPentest,
  evaluablesRevision
) {
  if (statsPentest && statsRevision) {
    setStats({
      abiertas: (statsPentest.abiertas || 0) + (statsRevision.abiertas || 0),
      identificables:
        (statsPentest.identificables || 0) +
        (statsRevision.identificables || 0),
      evaluables: (evaluablesPentest || 0) + (evaluablesRevision || 0),
      evaluadas: (statsPentest.evaluadas || 0) + (statsRevision.evaluadas || 0),
    });
  }
}

async function setinfoPentest() {
  try {
    const retorno = await obtenerTodosPentest();
    const statsPentest = retorno[0];
    let evaluablesPentest = 0;
    for (let pentest of retorno[1]) {
      if (pentest.status == 2 || pentest.status == 5)
        insertarTarjetaIdent(pentest);
      else if (
        pentest.status == 3 ||
        pentest.status == 6 ||
        pentest.status == 4
      ) {
        insertarTarjeta(pentest);
        evaluablesPentest++;
      }
      if (pentest.status == 0 || pentest.status == 7 || pentest.status == 8)
        insertarTablaRealizadas(pentest);
      else if (
        pentest.status == 1 ||
        pentest.status == 4 ||
        pentest.status == 9
      )
        insertarTablaEnProceso(pentest);
      else insertarTablaPorEvaluar(pentest);
    }
    cerrarModal();
    $(".table-pruebas").removeClass("mshide");
    return { statsPentest, evaluablesPentest };
  } catch (e) {
    console.error(e);
  }
}

async function setinfoArquitectura() {
  try {
    const retorno = await obtenerTodasRevisiones();
    const statsRevision = retorno[0];
    let evaluablesRevision = 0;
    for (let revision of retorno[1]) {
      if (revision.status == 2 || revision.status == 5)
        insertarTarjetaIdentRevision(revision, "Revision");
      else if (
        revision.status == 3 ||
        revision.status == 4 ||
        revision.status == 6
      ) {
        insertarTarjetaRevision(revision, "Revision");
        evaluablesRevision++;
      }
      if (revision.status == 0 || revision.status == 7 || revision.status == 8)
        insertarTablaRealizadasRevision(revision);
      else if (
        revision.status == 1 ||
        revision.status == 4 ||
        revision.status == 9
      )
        insertarTablaEnProcesoRevision(revision);
      else insertarTablaPorEvaluarRevision(revision);
    }
    cerrarModal();
    $(".table-pruebas").removeClass("mshide");
    return { statsRevision, evaluablesRevision };
  } catch (e) {
    console.error(e);
  }
}

$(document).ready(async function () {
  mostrarLoading();
  const [
    { statsPentest, evaluablesPentest },
    { statsRevision, evaluablesRevision },
  ] = await Promise.all([setinfoPentest(), setinfoArquitectura()]);
  setStatsSumados(
    statsPentest,
    statsRevision,
    evaluablesPentest,
    evaluablesRevision
  );
});
