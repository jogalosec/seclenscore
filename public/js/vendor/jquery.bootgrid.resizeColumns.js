$.fn.bootgrid.Constructor.prototype.enableColumnResize = function () {
  let that = this;

  // Limpiar handlers existentes
  this.element.find(".bootgrid-resize-handle").remove();

  let setupResize = function () {
    that.element.find("thead th:visible").each(function (index) {
      let $th = $(this);

      // Aquellas columnas con las clases que queramos que no se puedan redimensionar
      if ($th.hasClass("select-cell") || $th.hasClass("disable-resize")) {
        return;
      }

      let $handle = $("<div>").addClass("bootgrid-resize-handle").css({
        position: "absolute",
        right: "-2px",
        top: "0",
        bottom: "0",
        width: "5px",
        cursor: "col-resize",
        userSelect: "none",
        zIndex: 100,
        backgroundColor: "transparent",
      });

      if ($th.css("position") === "static") {
        $th.css("position", "relative");
      }

      $th.append($handle);

      $handle.on("mousedown", function (e) {
        e.preventDefault();
        e.stopPropagation();

        let startX = e.pageX;
        let startWidth = $th.outerWidth();
        let colIndex = $th.index();
        let minColWidth = 50; // Ancho mínimo para columnas redimensionables

        let $allHeaders = that.element.find("thead th:visible");
        let initialWidths = [];
        let isResizable = [];
        let fixedColumnsWidth = 0;

        $allHeaders.each(function (idx) {
          let width = $(this).outerWidth();
          let canResize =
            !$(this).hasClass("select-cell") &&
            !$(this).hasClass("disable-resize");

          initialWidths.push(width);
          isResizable.push(canResize);

          if (!canResize) {
            fixedColumnsWidth += width;
          }
        });

        $("body").addClass("bootgrid-resizing");

        $(document)
          .on("mousemove.bootgridResize", function (e) {
            let deltaX = e.pageX - startX;
            let newWidth = startWidth + deltaX;

            if (newWidth < minColWidth) {
              newWidth = minColWidth;
              deltaX = newWidth - startWidth;
            }

            let newWidths = initialWidths.slice();
            newWidths[colIndex] = newWidth;

            if (deltaX > 0) {
              let remainingDelta = deltaX;

              for (
                let i = colIndex + 1;
                i < newWidths.length && remainingDelta > 0;
                i++
              ) {
                if (!isResizable[i]) continue;

                let availableReduction = Math.max(
                  0,
                  initialWidths[i] - minColWidth
                );
                let reduction = Math.min(availableReduction, remainingDelta);

                if (reduction > 0) {
                  newWidths[i] = initialWidths[i] - reduction;
                  remainingDelta -= reduction;
                }
              }

              for (let i = colIndex - 1; i >= 0 && remainingDelta > 0; i--) {
                if (!isResizable[i]) continue;

                let availableReduction = Math.max(
                  0,
                  initialWidths[i] - minColWidth
                );
                let reduction = Math.min(availableReduction, remainingDelta);

                if (reduction > 0) {
                  newWidths[i] = initialWidths[i] - reduction;
                  remainingDelta -= reduction;
                }
              }

              if (remainingDelta > 0) {
                newWidths[colIndex] = startWidth + (deltaX - remainingDelta);
              }
            } else if (deltaX < 0) {
              let spaceToDivide = Math.abs(deltaX);
              let resizableCount = 0;

              for (let i = 0; i < isResizable.length; i++) {
                if (i !== colIndex && isResizable[i]) {
                  resizableCount++;
                }
              }

              if (resizableCount > 0) {
                let spacePerColumn = spaceToDivide / resizableCount;

                for (let i = 0; i < newWidths.length; i++) {
                  if (i !== colIndex && isResizable[i]) {
                    newWidths[i] = initialWidths[i] + spacePerColumn;
                  } else if (!isResizable[i]) {
                    newWidths[i] = initialWidths[i];
                  }
                }
              }
            }

            for (let i = 0; i < newWidths.length; i++) {
              if (!isResizable[i] && i !== colIndex) {
                newWidths[i] = initialWidths[i];
              }
            }

            $allHeaders.each(function (idx) {
              if (idx < newWidths.length) {
                $(this).css("width", newWidths[idx] + "px");
              }
            });

            that.element.find("tbody tr").each(function () {
              $(this)
                .children(":visible")
                .each(function (idx) {
                  if (idx < newWidths.length) {
                    $(this).css("width", newWidths[idx] + "px");
                  }
                });
            });
          })
          .on("mouseup.bootgridResize", function () {
            $(document).off(".bootgridResize");
            $("body").removeClass("bootgrid-resizing");
          });
      });
    });
  };

  if (this.element.find("thead th:visible").length > 0) {
    setupResize();
  } else {
    requestAnimationFrame(setupResize);
  }

  return this;
};

$(document).ready(function () {
  function applyResizeToGrid($grid) {
    let gridInstance =
      $grid.data(".rs.jquery.bootgrid") || $grid.data("bootgrid");
    if (gridInstance && typeof gridInstance.enableColumnResize === "function") {
      gridInstance.enableColumnResize();
    }
  }

  $(document).on("initialized.rs.jquery.bootgrid", function (e) {
    applyResizeToGrid($(e.target));
  });

  $(document).on("loaded.rs.jquery.bootgrid", function (e) {
    applyResizeToGrid($(e.target));
  });

  function setupColumnWatcher($table) {
    if ($table.data("column-watcher-attached")) return;
    $table.data("column-watcher-attached", true);

    let debounceTimer = null;
    let observer = new MutationObserver(function (mutations) {
      let shouldReapply = false;

      mutations.forEach(function (mutation) {
        if (
          mutation.type === "attributes" &&
          (mutation.attributeName === "style" ||
            mutation.attributeName === "class")
        ) {
          let $target = $(mutation.target);
          if ($target.is("th") || $target.is("td")) {
            shouldReapply = true;
          }
        }
        if (mutation.type === "childList") {
          shouldReapply = true;
        }
      });

      if (shouldReapply) {
        if (debounceTimer) {
          cancelAnimationFrame(debounceTimer);
        }
        debounceTimer = requestAnimationFrame(function () {
          applyResizeToGrid($table);
        });
      }
    });

    let thead = $table.find("thead")[0];
    if (thead) {
      observer.observe(thead, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["style", "class"],
      });
    }
  }

  function initializeExistingTables() {
    $('[data-toggle="bootgrid"]').each(function () {
      let $this = $(this);
      if ($this.find("thead th").length > 0) {
        applyResizeToGrid($this);
        setupColumnWatcher($this);
      }
    });
  }

  initializeExistingTables();

  let documentObserver = new MutationObserver(function (mutations) {
    let hasNewBootgridTables = false;

    mutations.forEach(function (mutation) {
      if (mutation.type === "childList") {
        $(mutation.addedNodes)
          .find('[data-toggle="bootgrid"]')
          .addBack('[data-toggle="bootgrid"]')
          .each(function () {
            hasNewBootgridTables = true;
          });
      }
    });

    if (hasNewBootgridTables) {
      requestAnimationFrame(initializeExistingTables);
    }
  });

  documentObserver.observe(document.body, {
    childList: true,
    subtree: true,
  });
});
