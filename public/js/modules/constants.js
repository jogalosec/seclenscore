class Constants {
  static getLoadingHtml(size = 124, includeText = false) {
    const textHtml = includeText ? "<p>Cargando contenido...</p>" : "";
    return `<div class="spinner-animation text-center" style="display: flex; justify-content: center; align-items: center;">
        <svg class="spinner-a" height="${size}" role="img" viewBox="0 0 66 66" width="${size}">
          <title>Cargando</title>
          <circle class="spinner-circle" cx="33" cy="33" fill="none" r="30" role="presentation" stroke-width="3" stroke="#019DF4"></circle>
        </svg><div>${textHtml}</div></div>
    `;
  }

  static OPTIONS_TABLE = {
    caseSensitive: false,
    labels: {
      noResults: "No se han encontrado resultados.",
      infos: "Mostrando {{ctx.start}}-{{ctx.end}} de {{ctx.total}} filas",
      search: "Buscar",
    },
  };
}

export default Constants;
