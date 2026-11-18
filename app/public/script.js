"use strict";

const catalogFilter = document.querySelector("#catalog-filter");
if (catalogFilter) {
  const search = document.querySelector("#search");
  const genre = document.querySelector("#genre");
  const cards = document.querySelectorAll(".book-item");
  const normalize = text => text.toLocaleLowerCase("ru").replaceAll("ё", "е").trim();
  function filterBooks() {
    let count = 0;
    for (const card of cards) {
      card.hidden = !(normalize(`${card.dataset.title} ${card.dataset.author}`).includes(normalize(search.value)) &&
        (!genre.value || card.dataset.genres.includes(genre.value)));
      if (!card.hidden) count++;
    }
    document.querySelector("#catalog-status").textContent = `Найдено книг: ${count}`;
    document.querySelector("#no-results").hidden = count !== 0;
  }
  catalogFilter.addEventListener("submit", event => { event.preventDefault(); filterBooks(); });
  search.addEventListener("input", filterBooks);
  genre.addEventListener("change", filterBooks);
  filterBooks();
}

const orderForm = document.querySelector("#order-form");
if (orderForm) {
  const bookSelect = document.querySelector("#book-select");
  const quantity = document.querySelector("#quantity");
  const delivery = document.querySelector("#delivery");
  const address = document.querySelector("#address");
  const formatter = new Intl.NumberFormat("ru-RU", {style: "currency", currency: "RUB", minimumFractionDigits: 2});
  function updateSummary() {
    const book = bookSelect.selectedOptions[0];
    if (!book) return;
    document.querySelector("#summary-book-title").textContent = book.dataset.title;
    document.querySelector("#summary-author").textContent = book.dataset.author;
    const cover = document.querySelector("#summary-cover");
    cover.src = book.dataset.image;
    cover.alt = `Обложка книги «${book.dataset.title}»`;
    const count = quantity.valueAsNumber;
    document.querySelector("#order-total").value = Number.isInteger(count) && count >= 1 && count <= 99
      ? formatter.format(Number(book.dataset.price) * count) : "Укажите от 1 до 99 книг";
  }
  function updateDelivery() {
    const needsAddress = delivery.value === "courier";
    document.querySelector("#address-group").hidden = !needsAddress;
    address.disabled = !needsAddress;
    address.required = needsAddress;
  }
  orderForm.addEventListener("input", updateSummary);
  orderForm.addEventListener("change", () => { updateDelivery(); updateSummary(); });
  // Normal HTTP POST: PHP validates and saves the form even with JavaScript disabled.
  updateDelivery();
  updateSummary();
}
