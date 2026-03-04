import { getArbolServicios } from "../../api/serviciosAPI.js";

import {
  insertBasicLoadingHtml,
  displayErrorMessage,
  displaySuccessMessage,
} from "../../utils/utils.js";

function generateDocument(data) {
  const myJson = data.paginas;
  const workbook = XLSX.utils.book_new();
  const worksheet = XLSX.utils.json_to_sheet(myJson.todos);
  const worksheet2 = XLSX.utils.json_to_sheet(myJson.archivados);
  const worksheet3 = XLSX.utils.json_to_sheet(myJson.no_archivados);
  XLSX.utils.book_append_sheet(workbook, worksheet3, "Sin archivar");
  XLSX.utils.book_append_sheet(workbook, worksheet2, "Archivados");
  XLSX.utils.book_append_sheet(workbook, worksheet, "Todos los activos");
  XLSX.writeFile(workbook, "Arbol_activos" + ".xlsx");
  if (document.getElementById("download-treeModal")) {
    document.getElementById("display-loading").innerHTML = "";
    document.getElementById("tituloModalDownload").innerHTML = "";
    displaySuccessMessage("Documento generado con exito", ".display-check");
  }
}

function setDownloadTreeButton() {
  $(".btn-arbol").click(async function () {
    let modal = `
        <div id="download-treeModal">
            <div id="display-loading"></div>
            <div class="display-check"></div>
            <div id="tituloModalDownload">
                <h3>Este excel tardará unos segundos en generarse</h3>
            </div>
            <div class="display-errors"></div>
        </div>
        `;
    showModalWindow(
      "Descargando árbol de servicios",
      modal,
      null,
      "Aceptar",
      null,
      null
    );
    insertBasicLoadingHtml(document.querySelector("#display-loading"));
    try {
      const data = await getArbolServicios();
      if (!data.error) {
        generateDocument(data);
      } else if (document.getElementById("download-treeModal")) {
        document.getElementById("display-loading").innerHTML = "";
        displayErrorMessage(data, ".display-errors");
      }
    } catch (e) {
      if (document.getElementById("download-treeModal")) {
        document.getElementById("display-loading").innerHTML = "";
        displayErrorMessage(e, ".display-errors");
      }
    }
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  setDownloadTreeButton();
});
