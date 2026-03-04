function Tags(element) {
  if (!element) {
    throw new Error(
      "DOM Element is undifined! Please choose HTML target element."
    );
  }

  let DOMParent = element;
  let DOMList;
  let DOMInput;
  let dataAttribute;
  let arrayOfList;

  function DOMCreate() {
    let ul = document.createElement("ul");
    let input = document.createElement("input");

    DOMParent.appendChild(ul);
    DOMParent.appendChild(input);

    // first child is <ul>
    DOMList = DOMParent.firstElementChild;
    // last child is <input>
    DOMInput = DOMParent.lastElementChild;
  }

  function DOMRender() {
    // clear the entire <li> inside <ul>
    DOMList.innerHTML = "";

    // render each <li> to <ul>
    arrayOfList.forEach((currentValue, index) => {
      let li = document.createElement("li");
      li.innerHTML = `${currentValue} <a>&times;</a>`;
      li.querySelector("a").addEventListener("click", function () {
        onDelete(index);
      });

      DOMList.appendChild(li);
    });

    setAttribute();
  }

  function onKeyUp() {
    DOMInput.addEventListener("keyup", function (event) {
      let text = this.value.trim();

      if (text.includes(",") || event.keyCode === 13) {
        let newTag = text.replace(",", "");
        if (newTag !== "") {
          const exists = arrayOfList.some((tag) => tag === newTag);
          if (!exists) {
            arrayOfList.push(newTag);
          }
        }
        this.value = "";
      }

      DOMRender();
    });
  }

  function onDelete(id) {
    arrayOfList = arrayOfList.filter(function (currentValue, index) {
      if (index === id) {
        return false;
      }
      return currentValue;
    });

    DOMRender();
  }

  function getAttribute() {
    dataAttribute = DOMParent.getAttribute("data-simple-tags");
    if (!dataAttribute?.trim()) {
      arrayOfList = [];
      return;
    }
    dataAttribute = dataAttribute.split(",");
    const seen = new Set();
    arrayOfList = dataAttribute
      .map((currentValue) => currentValue.trim())
      .filter((currentValue) => {
        if (currentValue.length > 0 && !seen.has(currentValue)) {
          seen.add(currentValue);
          return true;
        }
        return false;
      });
  }

  function setAttribute() {
    DOMParent.setAttribute("data-simple-tags", arrayOfList.toString());
  }

  getAttribute();
  DOMCreate();
  DOMRender();
  onKeyUp();
}
