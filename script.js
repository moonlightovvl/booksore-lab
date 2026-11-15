"use strict";

// Один файл подключён ко всем страницам; каждый блок проверяет наличие своих элементов.
const catalogFilter = document.querySelector("#catalog-filter");
if (catalogFilter) {
  const search = document.querySelector("#search");
  const genre = document.querySelector("#genre");
  const cards = document.querySelectorAll(".book-item");
  const status = document.querySelector("#catalog-status");
  const noResults = document.querySelector("#no-results");

  function normalize(text) {
    return text.toLocaleLowerCase("ru").replaceAll("ё", "е").trim();
  }

  function filterBooks() {
    const query = normalize(search.value);
    const selectedGenre = genre.value;
    let count = 0;

    for (const card of cards) {
      const text = normalize(`${card.dataset.title} ${card.dataset.author}`);
      const matchesQuery = text.includes(query);
      const matchesGenre = !selectedGenre || card.dataset.genres.split(" ").includes(selectedGenre);
      card.hidden = !(matchesQuery && matchesGenre);
      if (!card.hidden) {
        count += 1;
      }
    }

    status.textContent = `Найдено книг: ${count}`;
    noResults.hidden = count !== 0;
  }

  catalogFilter.addEventListener("submit", (event) => {
    event.preventDefault();
    filterBooks();
  });
  search.addEventListener("input", filterBooks);
  genre.addEventListener("change", filterBooks);
  filterBooks();
}

const orderForm = document.querySelector("#order-form");
if (orderForm) {
  const bookSelect = document.querySelector("#book-select");
  const quantity = document.querySelector("#quantity");
  const delivery = document.querySelector("#delivery");
  const addressGroup = document.querySelector("#address-group");
  const address = document.querySelector("#address");
  const phone = document.querySelector("#customer-phone");
  const email = document.querySelector("#customer-email");
  const customerName = document.querySelector("#customer-name");
  const total = document.querySelector("#order-total");
  const submit = document.querySelector("#submit-order");
  const modalElement = document.querySelector("#feedback-modal");
  const modal = new bootstrap.Modal(modalElement);
  let returnFocus = submit;

  const requestedBook = new URLSearchParams(window.location.search).get("book");
  if (Array.from(bookSelect.options).some((option) => option.value === requestedBook)) {
    bookSelect.value = requestedBook;
  }

  const rubles = new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  });

  function updateSummary() {
    const book = bookSelect.selectedOptions[0];
    const cover = document.querySelector("#summary-cover");
    document.querySelector("#summary-book-title").textContent = book.dataset.title;
    document.querySelector("#summary-author").textContent = book.dataset.author;
    cover.src = book.dataset.image;
    cover.alt = `Обложка книги «${book.dataset.title}»`;
    const count = quantity.valueAsNumber;
    const validCount = Number.isInteger(count) && count >= 1 && count <= 99;
    total.value = validCount ? rubles.format(Number(book.dataset.price) * count) : "Укажите от 1 до 99 книг";
  }

  function updateDelivery() {
    const needsAddress = delivery.value === "courier";
    addressGroup.hidden = !needsAddress;
    address.disabled = !needsAddress;
    address.required = needsAddress;
  }

  // type="tel" не проверяет телефон сам: считаем цифры и проверяем допустимые символы.
  function validateFields() {
    const digits = phone.value.replace(/\D/g, "");
    const validPhone = /^[+0-9() -]+$/.test(phone.value) && digits.length >= 10 && digits.length <= 15;
    phone.setCustomValidity(validPhone ? "" : "Введите телефон: от 10 до 15 цифр, без букв.");
    const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
    email.setCustomValidity(validEmail ? "" : "Введите email в формате name@example.com.");
    customerName.setCustomValidity(customerName.value.trim().length >= 2 ? "" : "Введите имя: хотя бы два символа.");
    address.setCustomValidity(address.disabled || address.value.trim().length >= 5 ? "" : "Введите полный адрес доставки.");
  }

  function showMessage(title, message, isError) {
    document.querySelector("#feedback-title").textContent = title;
    document.querySelector("#feedback-message").textContent = message;
    modalElement.classList.toggle("feedback-error", isError);
    modal.show();
  }

  modalElement.addEventListener("hidden.bs.modal", () => {
    returnFocus.focus();
  });

  orderForm.addEventListener("input", () => {
    validateFields();
    updateSummary();
  });
  orderForm.addEventListener("change", () => {
    updateDelivery();
    validateFields();
    updateSummary();
  });

  // novalidate в HTML даёт обработчику submit показать своё окно даже при ошибке.
  orderForm.addEventListener("submit", (event) => {
    event.preventDefault();
    updateDelivery();
    validateFields();
    orderForm.classList.add("was-validated");
    if (!orderForm.checkValidity()) {
      returnFocus = orderForm.querySelector(":invalid");
      showMessage("Проверьте данные", `${returnFocus.labels[0].textContent}: ${returnFocus.validationMessage}`, true);
      return;
    }

    const data = Object.fromEntries(new FormData(orderForm));
    data.customerName = data.customerName.trim();
    data.quantity = quantity.valueAsNumber;
    data.bookTitle = bookSelect.selectedOptions[0].dataset.title;
    data.total = Number(bookSelect.selectedOptions[0].dataset.price) * data.quantity;
    console.log("Данные учебной формы CICADA:", data);
    returnFocus = submit;
    showMessage("Данные успешно обработаны", `Учебный заказ: «${data.bookTitle}», ${data.quantity} шт., ${rubles.format(data.total)}. Данные выведены в консоль. Отправка в магазин в учебной версии не выполняется.`, false);
  });

  updateSummary();
  updateDelivery();
  validateFields();
  submit.disabled = false;
}

// ID задаётся в analytics-config.js; без него сетевой запрос не выполняется.
const analyticsId = window.CICADA_GA_ID;
if (/^G-[A-Z0-9]+$/.test(analyticsId) && window.location.hostname === "moonlightovvl.github.io") {
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () {
    window.dataLayer.push(arguments);
  };
  window.gtag("js", new Date());
  window.gtag("config", analyticsId, {
    page_location: window.location.origin + window.location.pathname,
    allow_google_signals: false,
    allow_ad_personalization_signals: false,
  });
  const analyticsScript = document.createElement("script");
  analyticsScript.async = true;
  analyticsScript.src = `https://www.googletagmanager.com/gtag/js?id=${analyticsId}`;
  document.head.append(analyticsScript);
}
