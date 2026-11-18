<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/views.php';
$books = getBooks();
$values = ['customerName' => '', 'email' => '', 'phone' => '', 'delivery' => 'pickup', 'address' => '', 'message' => '', 'quantity' => 1];
$requested = filter_var($_GET['book'] ?? 7, FILTER_VALIDATE_INT);
$values['book'] = $requested ?: 7;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$values, $errors] = validateOrder($_POST);
    if (!validCsrf($_POST['csrf'] ?? null)) {
        $errors[] = 'Срок действия формы истёк. Обновите страницу и отправьте её снова.';
        http_response_code(403);
    }
    $requestToken = field($_POST, 'requestToken');
    if (!preg_match('/^[a-f0-9]{64}$/D', $requestToken) || !isset($_SESSION['order_tokens'][$requestToken])) {
        $errors[] = 'Недействительный идентификатор формы. Обновите страницу.';
        http_response_code(403);
    }
    if (!$errors) {
        $orderId = saveOrder($values, $requestToken);
        $_SESSION['order_success'] = $orderId;
        // POST -> Redirect -> GET: F5 does not repeat a database insert.
        header('Location: order.php?saved=1', true, 303);
        exit;
    }
    if (http_response_code() !== 403) {
        http_response_code(422);
    }
}
$successId = $_SESSION['order_success'] ?? null;
unset($_SESSION['order_success']);
$requestToken = bin2hex(random_bytes(32));
$_SESSION['order_tokens'][$requestToken] = time();
if (count($_SESSION['order_tokens']) > 30) {
    $_SESSION['order_tokens'] = array_slice($_SESSION['order_tokens'], -30, null, true);
}
$selected = null;
foreach ($books as $candidate) {
    if ((int) $candidate['id'] === (int) $values['book']) {
        $selected = $candidate;
    }
}
$selected ??= $books[0] ?? null;
pageHeader('Заказ и обратная связь', 'order.php');
?>
<main class="container site-container order-main" id="main">
  <h1>Оформление заказа и обратная связь</h1>
  <p class="order-notice">Учебная форма: данные сохраняются в локальную базу. Оплата не производится.</p>
  <?php if ($successId): ?><div class="server-message" role="status"><strong>Заказ №<?= (int) $successId ?> сохранён.</strong> Данные записаны в базу данных.</div><?php endif; ?>
  <?php if ($errors): ?><div class="server-message error" role="alert"><strong>Проверьте данные:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!$selected): ?>
    <p>В каталоге пока нет книг. Отправка заказа временно недоступна.</p>
  <?php else: ?>
  <form id="order-form" class="row g-4 g-lg-5" aria-label="Заказ и обратная связь" method="post" action="order.php">
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="requestToken" value="<?= e($requestToken) ?>">
    <div class="col-lg-7"><section class="customer-panel" aria-labelledby="customer-title">
      <h2 id="customer-title">Данные покупателя</h2>
      <div class="mb-3"><label class="form-label" for="customer-name">Имя *</label><input class="form-control" id="customer-name" name="customerName" type="text" autocomplete="name" minlength="2" maxlength="80" required value="<?= e($values['customerName']) ?>"></div>
      <div class="mb-3"><label class="form-label" for="customer-email">Email *</label><input class="form-control" id="customer-email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= e($values['email']) ?>"></div>
      <div class="mb-3"><label class="form-label" for="customer-phone">Телефон *</label><input class="form-control" id="customer-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" aria-describedby="phone-hint" required value="<?= e($values['phone']) ?>"><div id="phone-hint" class="form-text">От 10 до 15 цифр; допустимы +, пробелы, скобки и дефисы.</div></div>
      <div class="mb-3"><label class="form-label" for="delivery">Способ получения *</label><select class="form-select" id="delivery" name="delivery" required><option value="pickup"<?= $values['delivery'] === 'pickup' ? ' selected' : '' ?>>Самовывоз</option><option value="courier"<?= $values['delivery'] === 'courier' ? ' selected' : '' ?>>Доставка</option></select></div>
      <div class="mb-3" id="address-group"><label class="form-label" for="address">Адрес доставки</label><input class="form-control" id="address" name="address" type="text" autocomplete="street-address" maxlength="200" value="<?= e($values['address']) ?>"><div class="form-text">Обязателен при доставке; при самовывозе не сохраняется.</div></div>
      <div class="mb-3"><label class="form-label" for="message">Сообщение магазину</label><textarea class="form-control" id="message" name="message" rows="3" maxlength="1000"><?= e($values['message']) ?></textarea></div>
      <p class="form-text">* Обязательные поля. Стоимость доставки в учебной форме не рассчитывается.</p>
      <button class="btn btn-primary btn-lg" id="submit-order" type="submit">Отправить заказ</button>
      <noscript><p class="mt-3">Форма работает без JavaScript. Итог будет рассчитан сервером по цене из базы данных.</p></noscript>
    </section></div>
    <div class="col-lg-5"><aside class="summary-panel" aria-labelledby="summary-title">
      <h2 id="summary-title">Ваш заказ</h2>
      <div class="mb-3"><label class="form-label" for="book-select">Книга</label><select class="form-select" id="book-select" name="book" required>
        <?php foreach ($books as $book): ?>
          <option value="<?= (int) $book['id'] ?>" data-title="<?= e($book['title']) ?>" data-author="<?= e($book['author']) ?>" data-price="<?= e($book['price']) ?>" data-image="<?= e($book['image']) ?>"<?= (int) $book['id'] === (int) $selected['id'] ? ' selected' : '' ?>><?= e($book['title']) ?> — <?= e(money($book['price'])) ?></option>
        <?php endforeach; ?>
      </select></div>
      <div class="d-flex gap-3 mb-3 align-items-start"><img id="summary-cover" class="summary-cover" src="<?= e($selected['image']) ?>" alt="Обложка книги «<?= e($selected['title']) ?>»" width="80" height="122"><div><h3 id="summary-book-title"><?= e($selected['title']) ?></h3><p id="summary-author"><?= e($selected['author']) ?></p></div></div>
      <div class="mb-3"><label class="form-label" for="quantity">Количество *</label><input class="form-control quantity-input" id="quantity" name="quantity" type="number" min="1" max="99" step="1" value="<?= (int) $values['quantity'] ?>" required></div>
      <p class="summary-total" aria-live="polite">Итого: <output id="order-total" for="book-select quantity"><?= e(money((float) $selected['price'] * $values['quantity'])) ?></output></p>
    </aside></div>
  </form>
  <?php endif; ?>
</main>
<?php pageFooter(); ?>
