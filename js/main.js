"use strict";

// Поиск по каталогу работает только на странице, где есть форма фильтра.
const catalogFilter = document.querySelector("#catalog-filter");
if (catalogFilter) {
  const search = document.querySelector("#search");
  const genre = document.querySelector("#genre");
  const cards = Array.from(document.querySelectorAll(".book-item"));
  const status = document.querySelector("#catalog-status");
  const noResults = document.querySelector("#no-results");

  // Регистр, лишние пробелы и различие между е/ё не мешают поиску.
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

  // Отменяем перезагрузку страницы и обновляем список сразу при вводе.
  catalogFilter.addEventListener("submit", (event) => {
    event.preventDefault();
    filterBooks();
  });
  search.addEventListener("input", filterBooks);
  genre.addEventListener("change", filterBooks);
  filterBooks();
}

// Код заказа выполняется только при наличии формы заказа на странице.
const orderForm = document.querySelector("#order-form");
if (orderForm) {
  const bookSelect = document.querySelector("#book-select");
  const quantity = document.querySelector("#quantity");
  const delivery = document.querySelector("#delivery");
  const addressGroup = document.querySelector("#address-group");
  const address = document.querySelector("#address");
  const phone = document.querySelector("#customer-phone");
  const customerName = document.querySelector("#customer-name");
  const total = document.querySelector("#order-total");
  const result = document.querySelector("#order-result");
  const submit = document.querySelector("#submit-order");

  // Параметр book берём из ссылки «Оформить заказ» и сверяем с готовыми option.
  const requestedBook = new URLSearchParams(window.location.search).get("book");
  if (Array.from(bookSelect.options).some((option) => option.value === requestedBook)) {
    bookSelect.value = requestedBook;
  }

  const rubles = new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 0,
  });

  // Данные выбранной книги читаем из HTML, чтобы не дублировать прайс в JS.
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

  // Скрытое поле адреса не должно мешать оформлению самовывоза.
  function updateDelivery() {
    const needsAddress = delivery.value === "courier";
    addressGroup.hidden = !needsAddress;
    address.disabled = !needsAddress;
    address.required = needsAddress;
  }

  // type="tel" сам не проверяет количество цифр, поэтому уточняем проверку.
  function validatePhone() {
    const digits = phone.value.replace(/\D/g, "");
    const valid = digits.length >= 10 && digits.length <= 15;
    phone.setCustomValidity(valid ? "" : "Введите телефон: от 10 до 15 цифр.");
  }

  function validateText() {
    customerName.setCustomValidity(customerName.value.trim().length >= 2 ? "" : "Введите имя: хотя бы два символа.");
    address.setCustomValidity(address.disabled || address.value.trim().length >= 5 ? "" : "Введите полный адрес доставки.");
  }

  // После изменения данных старое сообщение о проверке больше не актуально.
  orderForm.addEventListener("input", () => {
    result.hidden = true;
    validatePhone();
    validateText();
    updateSummary();
  });
  orderForm.addEventListener("change", () => {
    result.hidden = true;
    updateDelivery();
    validateText();
    updateSummary();
  });

  // Это локальная демонстрация: сетевого запроса и сохранения личных данных нет.
  orderForm.addEventListener("submit", (event) => {
    event.preventDefault();
    validatePhone();
    validateText();
    if (!orderForm.reportValidity()) {
      return;
    }
    const title = bookSelect.selectedOptions[0].dataset.title;
    const method = delivery.value === "courier" ? "Доставка" : "Самовывоз";
    result.textContent = `Учебный заказ проверен: «${title}», ${quantity.value} шт., ${total.value}. ${method}. Данные никуда не отправлены.`;
    result.hidden = false;
    result.focus();
  });

  updateSummary();
  updateDelivery();
  validatePhone();
  validateText();
  // Активируем кнопку лишь после установки обработчика, исключая отправку без JS.
  submit.disabled = false;
}
