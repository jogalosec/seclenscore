import Constants from "../modules/constants.js";

export function setTabsModulo() {
  $(".nav-link").click(function (e) {
    let target = $(this).attr("id");
    $(".tab-content").addClass("mshide");
    $(target).removeClass("mshide");
    $(".nav-link").removeClass("active");
    $(this).addClass("active");
  });
}

export function displayErrorMessage(error, element) {
  const alert = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
      <strong>Error:</strong> ${error.message}</div>`;
  document.querySelector(`${element}`).innerHTML = alert;
}

export function displaySuccessMessage(message, element) {
  const alert = `
  <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
    <symbol id="check-circle-fill" fill="currentColor" viewBox="0 0 16 16">
      <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
    </symbol>
  </svg>
  <div class="alert alert-success d-flex align-items-center" role="alert">
    <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Success:"><use xlink:href="#check-circle-fill"/></svg>
    <div>
      <strong>Éxito:</strong> ${message}
    </div>
  </div>`;
  document.querySelector(`${element}`).innerHTML = alert;
}

export function agregarLoadingHtml(contenedor) {
  if (!contenedor.querySelector(".loadingHTML")) {
    let tempDiv = document.createElement("div");
    tempDiv.innerHTML = Constants.getLoadingHtml(124, true);
    let loadingNode = tempDiv.firstChild;
    loadingNode.classList.add("loadingHTML");
    contenedor.appendChild(loadingNode);
  }
}

export function insertBasicLoadingHtml(contenedor) {
  contenedor.innerHTML = `
  <svg class='spinner-a mb-2' height='60' role='img' viewBox='0 0 66 66' width='60'>
                  <circle class='spinner-circle' cx='33' cy='33' fill='none' r='30' role='presentation' stroke-width='3' stroke='#0d6efd'></circle>
              </svg>`;
}

export function finalLoading(element, type) {
  let img;
  if (type == "check")
    img = `<img src="./img/check.svg" width="60" height="60" alt="checked" class="checked">`;
  else if (type == "error")
    img = `<img src="./img/wrong.svg" width="60" height="60" alt="checked" class="checked">`;
  else
    img = `<svg class="spinner-a" height="60" role="img" viewBox="0 0 66 66" width="60">
                    <circle class="spinner-circle" cx="33" cy="33" fill="none" r="30" role="presentation" stroke-width="3" stroke="#0d6efd"></circle>
            </svg>`;
  empty(element);
  append(element, img);
}

export function serializeForm(form) {
  const formData = new FormData(form);
  const serializedObject = {};

  for (const [key, value] of formData.entries()) {
    serializedObject[key] = value;
  }
  return serializedObject;
}

export function extractHexColor(htmlString) {
  const regex = /background-color:\s*(#[0-9a-fA-F]{6})/;
  const match = htmlString.match(regex);

  if (match) {
    return match[1];
  }

  return null;
}

export function addDownloadButtonToTable(containerSelector) {
  const container = document.querySelector(containerSelector);
  if (container) {
    const actionsDiv = container.querySelector(".actions");
    if (actionsDiv) {
      const existingDownloadButton =
        actionsDiv.querySelector(".download-group");
      if (!existingDownloadButton) {
        const downloadButton = document.createElement("div");
        downloadButton.className = "btn btn-default download-group";
        downloadButton.innerHTML =
          '<img class="icono downloadTabla" src="./img/download.svg" alt="descargar" title="Descargar tabla">';

        downloadButton.addEventListener("click", () => {
          const table = container.querySelector("table");
          if (table) {
            const tableId = table.id;
            if (tableId) {
              exportTable(tableId, containerSelector);
            } else {
              console.error("La tabla no tiene un ID.");
            }
          } else {
            console.error("No se encontró una tabla dentro del contenedor.");
          }
        });

        actionsDiv.prepend(downloadButton);
      }
    } else {
      console.error(
        `No se encontró un div con la clase 'actions' dentro de ${containerSelector}`
      );
    }
  } else {
    console.error(
      `No se encontró un contenedor con el selector ${containerSelector}`
    );
  }
}

function buildHierarchy(data) {
  const map = new Map();
  data.forEach((item) => map.set(item.id, { ...item, hijos: [] }));

  const root = [];
  data.forEach((item) => {
    if (item.padre === null) {
      root.push(map.get(item.id));
    } else {
      map.get(item.padre).hijos.push(map.get(item.id));
    }
  });
  return root;
}

function calculateRowspan(node) {
  if (!node.hijos.length) return 1;
  return node.hijos.reduce((sum, child) => sum + calculateRowspan(child), 0);
}

export function generateTableGBU(data) {
  const hierarchy = buildHierarchy(data);
  const table = document.createElement("table");
  table.className =
    "table table-condensed table-hover table-striped bootgrid-table";

  const thead = document.createElement("thead");
  const headerRow = document.createElement("tr");
  ["Dirección", "Área", "Unidad", "Servicio de Negocio"].forEach((text) => {
    const th = document.createElement("th");
    th.textContent = text;
    headerRow.appendChild(th);
  });
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement("tbody");

  function addRows(node, level = 0, parentRow = null) {
    const isNewRow = !parentRow;
    const row = isNewRow ? document.createElement("tr") : parentRow;

    if (
      row.childNodes.length <= level &&
      (node.tipo_id === 124 || node.tipo_id === 123 || node.tipo_id === 122)
    ) {
      const cell = document.createElement("td");
      cell.textContent = node.nombre;
      cell.rowSpan = calculateRowspan(node);
      row.appendChild(cell);
    } else if (
      row.childNodes.length <= level &&
      node.tipo_id === 42 &&
      node.archivado === 0
    ) {
      let loopCount = 0;
      while (row.childNodes.length < 3) {
        row.appendChild(document.createElement("td"));
        loopCount++;
        if (loopCount > 10) break;
      }
      const cell = document.createElement("td");
      cell.textContent = node.nombre;
      cell.rowSpan = calculateRowspan(node);
      row.appendChild(cell);
    }
    let loopCount = 0;
    while (row.childNodes.length > 4) {
      row.removeChild(row.lastChild);
      loopCount++;
      if (loopCount > 10) break;
    }

    if (!node.hijos.length) {
      for (let i = row.childNodes.length; i < 4; i++) {
        const emptyCell = document.createElement("td");
        row.appendChild(emptyCell);
      }
    }

    if (isNewRow) tbody.appendChild(row);

    node.hijos.forEach((child, index) => {
      if (index === 0) {
        addRows(child, level + 1, row);
      } else {
        addRows(child, level + 1);
      }
    });
  }

  hierarchy.forEach((node) => addRows(node));
  table.appendChild(tbody);
  return table;
}

function exportTable(tableId, fileName) {
  const table = document.getElementById(tableId);
  if (!table) {
    console.error(`No se encontró una tabla con el ID ${tableId}`);
    return;
  }

  // Convertir la tabla a un array de arrays
  const rows = table.querySelectorAll("tr");
  const data = Array.from(rows).map((row) => {
    const cols = row.querySelectorAll("td, th");
    return Array.from(cols).map((col) => col.innerText);
  });

  // Crear un libro de trabajo y una hoja de trabajo
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet(data);

  // Añadir la hoja de trabajo al libro de trabajo
  XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

  // Generar el archivo XLS
  const xls = XLSX.write(wb, { bookType: "xlsx", type: "array" });

  // Crear un blob y descargar el archivo
  const blob = new Blob([xls], {
    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.setAttribute("href", url);
  a.setAttribute("download", `${fileName}.xlsx`);
  a.click();
  URL.revokeObjectURL(url);
}
