import { postDataDocument } from "./api.js";

export async function obtainVulnsDocument(infoDownload) {
  const url = "./api/obtainVulnsDocument";
  const params = new URLSearchParams();
  const fileName = "Vulnerabilidades.xlsx";

  params.append('format', infoDownload.format);
  if (infoDownload.analysisTypes && Array.isArray(infoDownload.analysisTypes)) {
    infoDownload.analysisTypes.forEach(type => params.append('analysisTypes[]', type));
  }
  if (infoDownload.criticidadType && Array.isArray(infoDownload.criticidadType)) {
    infoDownload.criticidadType.forEach(type => params.append('criticidadType[]', type));
  }
  if (infoDownload.estadoType && Array.isArray(infoDownload.estadoType)) {
    infoDownload.estadoType.forEach(type => params.append('estadoType[]', type));
  }
  params.append('detailedInfo', infoDownload.detailedInfo);


  return postDataDocument(url, params, fileName);
}
