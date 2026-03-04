$.fn.bootgrid.Constructor.prototype.setSelectedRows = function (ids) {
  // 1) Reemplazo interno
  this.selectedRows = ids.slice();

  // 2) Desmarco todo en la UI
  let box = this.options.css.selectBox;
  this.element
    .find("tbody > tr")
    .removeClass(this.options.css.selected)
    .find("input." + box)
    .prop("checked", false);

  // 3) Marco las filas que quiero
  for (let i = 0; i < ids.length; i++) {
    let id = ids[i],
      $tr = this.element.find("tbody tr[data-row-id='" + id + "']");
    $tr
      .addClass(this.options.css.selected)
      .find("input." + box)
      .prop("checked", true);
  }

  return this;
};
