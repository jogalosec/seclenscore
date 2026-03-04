// Esta función comprueba si un elemento tiene una clase determinada.
function hasClass(selector, className) {
  let elements;

  if (selector.startsWith("#")) {
    const element = document.querySelector(selector);
    if (!element) {
      return false;
    }
    elements = [element];
  } else if (selector.startsWith(".")) {
    elements = document.querySelectorAll(selector);
    if (elements.length === 0) {
      return false;
    }
  }
  for (let element of elements) {
    if (!element.classList.contains(className)) {
      return false;
    }
  }
  return true;
}

// Esta función añade una clase a un elemento si no la tiene.
function addClass(selector, className) {
  if (selector.startsWith("#")) {
    const element = document.querySelector(selector);
    if (element && !element.classList.contains(className)) {
      element.classList.add(className);
    } else if (!element) {
      console.warn(`El elemento con el selector '${selector}' no existe.`);
      return null;
    }
  } else if (selector.startsWith(".")) {
    const elements = document.querySelectorAll(selector);
    elements.forEach((element) => {
      if (!element.classList.contains(className)) {
        element.classList.add(className);
      }
    });
  }
}

// Esta función elimina una clase de un elemento si la tiene.
function removeClass(selector, className) {
  if (selector.startsWith("#")) {
    const element = document.querySelector(selector);
    if (element?.classList.contains(className)) {
      element.classList.remove(className);
    } else if (!element) {
      console.warn(`El elemento con el selector '${selector}' no existe.`);
      return null;
    }
  } else if (selector.startsWith(".")) {
    const elements = document.querySelectorAll(selector);
    elements.forEach((element) => {
      if (element.classList.contains(className)) {
        element.classList.remove(className);
      }
    });
  }
}

// Esta función elimina por completo lo que tiene dentro un elemento.
function empty(selector) {
  let elements;
  if (selector.startsWith("#")) {
    const element = document.querySelector(selector);
    if (element) {
      while (element.firstChild) {
        element.removeChild(element.firstChild);
      }
    } else {
      console.warn(`El elemento con el selector '${selector}' no existe.`);
      return null;
    }
  } else {
    elements = document.querySelectorAll(selector);
    elements.forEach((element) => {
      while (element.firstChild) {
        element.removeChild(element.firstChild);
      }
    });
  }
}

// Esta función obtiene el valor de un atributo específico de un elemento.
function getAttributeValue(selector, attribute = "value") {
  let element = document.querySelector(selector);

  if (element) {
    return element.getAttribute(attribute);
  } else {
    console.warn(`El elemento con el selector '${selector}' no existe.`);
    return null;
  }
}

// Esta función inserta un string dentro de un elemento dado.
function append(selector, string) {
  let elements = document.querySelectorAll(selector);

  if (elements.length > 0) {
    elements.forEach((element) => {
      element.innerHTML += string;
    });
  } else {
    console.warn(`No se encontraron elementos con el selector '${selector}'.`);
  }
}

// Esta función inserta un string al inicio de un elemento dado.
function appendTop(selector, string) {
  let elements = document.querySelectorAll(selector);

  if (elements.length > 0) {
    elements.forEach((element) => {
      element.innerHTML = string + element.innerHTML;
    });
  } else {
    console.warn(`No se encontraron elementos con el selector '${selector}'.`);
  }
}
