import { 
    getActivosTipo,
    getHijosTipo
} from "../api/serviciosAPI.js";

import {
    insertBasicLoadingHtml,
    displayErrorMessage,
  } from "./utils.js";

export function obtenerUltimoSelector(claseSelector) {
  const selectores = document.querySelectorAll("." + claseSelector);

  if (selectores.length > 0) {
    const valores = Array.from(selectores).map((select) => select.value);
    for (let i = valores.length - 1; i >= 0; i--) {
      if (valores[i] !== "") {
        return valores[i];
      }
    }
    return valores[valores.length - 1];
  } else {
    return `No hay selectores`;
  }
}

export function setSelectoresActivos(idContenedor, activos) {
  let totalSelectores = 0;

  function insertarSelectorNuevo(contenedor, activos) {
    let i = 0;
    Object.entries(activos[0]).forEach(([key, value]) => {
      if (i > totalSelectores) {
        return;
      }
      if (i == totalSelectores) {
        const idUltimoSelector = obtenerUltimoSelector(
          contenedor.id + `Selector`
        );
        fillSelectorHijos(contenedor, idUltimoSelector, key, value);
      }
      i += 1;
    });
  }

  function setBotonesActivos(contenedor, activos) {
    let botones = `
      <div class="form-group row mb-2 d-flex align-items-cente justify-content-end">
          <div class="col-12 text-end">
            <button type="button" class="btn btn-danger" id="${contenedor.id}removeActivoSelector">-</button>
            <button type="button" class="btn btn-primary" id="${contenedor.id}addActivoSelector">+</button>
          </div>
      </div>
      `;
    contenedor.innerHTML += botones;
    $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, true);
    $(`#${contenedor.id}addActivoSelector`).click(function () {
      const length = Object.keys(activos[0]).length;
      if (totalSelectores != length) {
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
        insertarSelectorNuevo(contenedor, activos);
      } else {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, true);
      }
    });
    $(`#${contenedor.id}removeActivoSelector`).click(function () {
      if (totalSelectores != 1 && totalSelectores > 1) {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
        contenedor.removeChild(contenedor.lastChild);
        totalSelectores -= 1;
      } else {
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, true);
      }
    });
  }

  function removeSelectsBelow(contenedor, changedSelectId) {
    let found = false;
    const children = Array.from(contenedor.children);
    for (let child of children) {
      const select = child.querySelector('select');
      if (select) {
        if (found) {
          contenedor.removeChild(child);
          totalSelectores -= 1;
        }
        if (select.id === changedSelectId) {
          found = true;
        }
      }
    }
  }

  function createSelect(contenedor, tipo, idTipo) {
    const formGroup = document.createElement("div");
    formGroup.className = `form-group row mb-5 d-flex align-items-center`;

    const colLabel = document.createElement(`div`);
    colLabel.className = `col-3 text-center`;
    const label = document.createElement(`label`);
    label.setAttribute(`for`, `${contenedor.id}${tipo}Select`);
    label.textContent = tipo;
    colLabel.appendChild(label);

    const colSelect = document.createElement(`div`);
    colSelect.className = `col-9`;
    const select = document.createElement(`select`);
    select.name = tipo;
    select.className = `form-select ${contenedor.id + totalSelectores} ${
      contenedor.id + `Selector`
    }`;
    select.id = `${contenedor.id}${tipo}Select`;
    colSelect.appendChild(select);

    select.addEventListener('change', function() {
      removeSelectsBelow(contenedor, select.id);
    });

    formGroup.appendChild(colLabel);
    formGroup.appendChild(colSelect);
    contenedor.appendChild(formGroup);
  }

  async function fillSelectorHijos(contenedor, idActivo, tipo, idTipo) {
    try {
      insertBasicLoadingHtml(document.querySelector(`#${contenedor.id}display-loading`));
      $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, true);
      $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, true);
      const data = await getHijosTipo(null, idActivo, tipo);
      if (data.error) {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
        document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
        displayErrorMessage(`Error mostrando ${tipo}`, ".display-errors");
      } else {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
        document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
        let activos = data[`Hijos`];
        createSelect(contenedor, tipo, idTipo);
        for (let activo of activos) {
          let option = document.createElement(`option`);
          option.value = activo.id;
          option.textContent = activo.nombre;
          document.getElementById(`${contenedor.id}${tipo}Select`).appendChild(option);
        }
        totalSelectores += 1;
      }
    } catch (e) {
      $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
      $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
      document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
      displayErrorMessage(e, `.display-errors`);
      return e;
    }
  }

  async function fillSelectorStart(contenedor, tipo, idTipo) {
    try {
      insertBasicLoadingHtml(document.querySelector(`#${contenedor.id}display-loading`));
      $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, true);
      $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, true);
      const data = await getActivosTipo(idTipo);
      if (data.error) {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
        document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
        displayErrorMessage(`Error mostrando ${tipo}`, `.display-errors`);
      } else {
        $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
        $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
        document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
        let activos = data[`activos`];
        createSelect(contenedor, tipo, idTipo);
        for (let activo of activos) {
          let option = document.createElement(`option`);
          option.value = activo.id;
          option.textContent = activo.nombre;
          document.getElementById(`${contenedor.id}${tipo}Select`).appendChild(option);
        }
        totalSelectores += 1;
      }
    } catch (e) {
      $(`#${contenedor.id}addActivoSelector`).prop(`disabled`, false);
      $(`#${contenedor.id}removeActivoSelector`).prop(`disabled`, false);
      document.getElementById(`${contenedor.id}display-loading`).innerHTML = ``;
      displayErrorMessage(e, `.display-errors`);
      return e;
    }
  }

  const contenedor = document.getElementById(idContenedor);
  let i = 0;
  setBotonesActivos(contenedor, activos);
  Object.entries(activos[0]).forEach(([key, value]) => {
    if (i > totalSelectores) {
      return;
    } else if (i == 0) {
      fillSelectorStart(contenedor, key, value);
    }
    i += 1;
  });
}
