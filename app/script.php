<?php
declare(strict_types=1);

// The separate database script required by laboratory work 4.
function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $file = __DIR__ . '/config.local.php';
    if (!is_file($file)) {
        throw new RuntimeException('Сначала выполните локальную настройку: scripts/setup.ps1.');
    }
    $config = require $file;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'], $config['port'], $config['database']);
    $connection = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function getBooks(bool $featured = false): array
{
    $sql = 'SELECT * FROM books' . ($featured ? ' WHERE featured = 1' : '') . ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

function getBook(int $id): ?array
{
    $query = db()->prepare('SELECT * FROM books WHERE id = :id');
    $query->execute(['id' => $id]);
    return $query->fetch() ?: null;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $value): string
{
    return number_format((float) $value, 2, ',', ' ') . ' ₽';
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function validCsrf(mixed $value): bool
{
    return is_string($value) && hash_equals(csrfToken(), $value);
}

// Scalar values only: malformed field[]=... input must not trigger a PHP exception.
function field(array $input, string $name): string
{
    return isset($input[$name]) && is_string($input[$name]) ? trim($input[$name]) : '';
}

function validateOrder(array $input): array
{
    $data = [];
    foreach (['customerName', 'email', 'phone', 'delivery', 'address', 'message', 'book', 'quantity'] as $name) {
        $data[$name] = field($input, $name);
    }
    $errors = [];
    if (mb_strlen($data['customerName']) < 2 || mb_strlen($data['customerName']) > 80) {
        $errors[] = 'Имя должно содержать от 2 до 80 символов.';
    }
    if (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }
    $digits = preg_replace('/\D/', '', $data['phone']);
    if (strlen($data['phone']) > 30 || !preg_match('/^[+0-9() -]+$/D', $data['phone']) || strlen($digits) < 10 || strlen($digits) > 15) {
        $errors[] = 'Телефон должен содержать от 10 до 15 цифр.';
    }
    if (!in_array($data['delivery'], ['pickup', 'courier'], true)) {
        $errors[] = 'Выберите способ получения.';
    }
    if ($data['delivery'] === 'courier' && (mb_strlen($data['address']) < 5 || mb_strlen($data['address']) > 200)) {
        $errors[] = 'Для доставки укажите адрес от 5 до 200 символов.';
    }
    if ($data['delivery'] === 'pickup') {
        $data['address'] = '';
    }
    if (mb_strlen($data['message']) > 1000) {
        $errors[] = 'Сообщение не должно превышать 1000 символов.';
    }
    $quantity = filter_var($data['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    $bookId = filter_var($data['book'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($quantity === false) {
        $errors[] = 'Количество должно быть целым числом от 1 до 99.';
    }
    $book = $bookId !== false ? getBook($bookId) : null;
    if (!$book) {
        $errors[] = 'Выбранная книга не найдена в базе данных.';
    }
    $data['quantity'] = $quantity ?: 1;
    $data['book'] = $bookId ?: 0;
    return [$data, $errors, $book];
}

function saveOrder(array $data, string $requestToken): int
{
    // INSERT ... SELECT takes the actual title and price from MySQL, never from the browser.
    // The UNIQUE token prevents duplicate inserts after retries or simultaneous submissions.
    $query = db()->prepare('INSERT INTO feedback
        (book_id, book_title, unit_price, quantity, customer_name, email, phone, delivery, address, message, request_token)
        SELECT id, title, price, :quantity, :customer, :email, :phone, :delivery, :address, :message, :token
        FROM books WHERE id = :book');
    try {
        $query->execute([
            'quantity' => $data['quantity'], 'customer' => $data['customerName'],
            'email' => $data['email'], 'phone' => $data['phone'], 'delivery' => $data['delivery'],
            'address' => $data['address'] ?: null, 'message' => $data['message'],
            'token' => $requestToken, 'book' => $data['book'],
        ]);
        if ($query->rowCount() !== 1) {
            throw new RuntimeException('Книга была удалена. Обновите страницу и выберите другую.');
        }
        return (int) db()->lastInsertId();
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) !== 1062) {
            throw $exception;
        }
        $existing = db()->prepare('SELECT id FROM feedback WHERE request_token = ?');
        $existing->execute([$requestToken]);
        $id = $existing->fetchColumn();
        if ($id === false) {
            throw $exception;
        }
        return (int) $id;
    }
}
