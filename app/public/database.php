<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/views.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Доступ разрешён только с этого компьютера.');
}
$seed = json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/books.json'), true, 512, JSON_THROW_ON_ERROR);
$baseline = array_column($seed, null, 'id');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $error = 'Обновите страницу и повторите действие.';
    } else {
        $id = filter_var(field($_POST, 'id'), FILTER_VALIDATE_INT);
        $title = field($_POST, 'title');
        $price = str_replace(',', '.', field($_POST, 'price'));
        if (field($_POST, 'action') === 'restore' && isset($baseline[$id])) {
            $title = $baseline[$id]['title'];
            $price = $baseline[$id]['price'];
        }
        if (!$id || mb_strlen($title) < 1 || mb_strlen($title) > 255 || !preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $price)) {
            http_response_code(422);
            $error = 'Укажите название и корректную цену от 0 до 99999999.99.';
        } else {
            $query = db()->prepare('UPDATE books SET title = :title, price = :price WHERE id = :id');
            $query->execute(['title' => $title, 'price' => $price, 'id' => $id]);
            $_SESSION['db_notice'] = 'UPDATE выполнен для books.id=' . $id . '. Обновите каталог в другой вкладке (F5).';
            header('Location: database.php', true, 303);
            exit;
        }
    }
}
$notice = $_SESSION['db_notice'] ?? '';
unset($_SESSION['db_notice']);
$books = getBooks();
$orders = db()->query('SELECT id, book_title, unit_price, quantity, total, customer_name, email, phone, delivery, address, message, created_at FROM feedback ORDER BY id DESC LIMIT 50')->fetchAll();
pageHeader('Локальная база данных', '');
?>
<main class="container site-container" id="main">
  <h1>База данных: cicada_lab4</h1>
  <p class="db-description">Локальный инструмент для защиты. Изменение ниже выполняет настоящий SQL UPDATE в MySQL на 127.0.0.1:3307. После сохранения обновите <a href="catalog.php" target="_blank" rel="noopener">каталог в отдельной вкладке</a>.</p>
  <?php if ($notice): ?><div class="server-message" role="status"><?= e($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="server-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <h2>Таблица books</h2>
  <p>Для проверки удобно выбрать «1984» (id=7): цена 760,00 → 999,00 → 760,00. «Вернуть исходное» восстанавливает название и цену выбранной книги из исходного набора.</p>
  <div class="table-responsive"><table class="table db-table"><thead><tr><th>ID</th><th>Название и цена</th><th>Действия</th></tr></thead><tbody>
    <?php foreach ($books as $book): $formId = 'book-' . (int) $book['id']; ?>
      <tr><td><?= (int) $book['id'] ?></td><td>
        <form id="<?= $formId ?>" method="post" action="database.php">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
          <label class="form-label" for="title-<?= (int) $book['id'] ?>">Название</label><input class="form-control mb-2" id="title-<?= (int) $book['id'] ?>" name="title" maxlength="255" required value="<?= e($book['title']) ?>">
          <label class="form-label" for="price-<?= (int) $book['id'] ?>">Цена, ₽</label><input class="form-control" id="price-<?= (int) $book['id'] ?>" name="price" type="number" min="0" max="99999999.99" step="0.01" required value="<?= e($book['price']) ?>">
        </form>
      </td><td><button class="btn btn-primary mb-2" type="submit" form="<?= $formId ?>" name="action" value="save">Сохранить в БД</button> <button class="btn btn-secondary" type="submit" form="<?= $formId ?>" name="action" value="restore" formnovalidate>Вернуть исходное</button></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <h2>SQL для демонстрации</h2>
  <pre><code>SELECT id, title, price FROM books WHERE id = 7;
UPDATE books SET price = 999.00 WHERE id = 7;
-- Обновить каталог и страницу книги в браузере.
UPDATE books SET price = 760.00 WHERE id = 7;
-- Снова обновить страницы.
SELECT * FROM feedback ORDER BY id DESC;</code></pre>
  <h2>Таблица feedback: последние 50 записей</h2>
  <p><a href="order.php" target="_blank" rel="noopener">Отправить учебный заказ</a>, затем обновить эту страницу. Цена и название в заказе сохраняются на момент отправки.</p>
  <div class="table-responsive"><table class="table db-table"><thead><tr><th>ID / дата</th><th>Книга / сумма</th><th>Контакт</th><th>Получение / сообщение</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?>
    <tr><td><?= (int) $order['id'] ?><br><?= e($order['created_at']) ?></td><td><?= e($order['book_title']) ?><br><?= e(money($order['unit_price'])) ?> × <?= (int) $order['quantity'] ?> = <?= e(money($order['total'])) ?></td><td><?= e($order['customer_name']) ?><br><?= e($order['email']) ?><br><?= e($order['phone']) ?></td><td><?= e($order['delivery']) ?><br><?= e($order['address']) ?><br><?= e($order['message']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="4">Пока нет записей. Отправьте форму заказа.</td></tr><?php endif; ?>
  </tbody></table></div>
</main>
<?php pageFooter(); ?>
