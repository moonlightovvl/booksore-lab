<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$config = require $root . '/app/config.local.php';
$rootPassword = getenv('CICADA_MYSQL_ROOT_PASSWORD');
if ($rootPassword === false || $rootPassword === '') {
    throw new RuntimeException('CICADA_MYSQL_ROOT_PASSWORD is required.');
}
$pdo = new PDO(sprintf('mysql:host=127.0.0.1;port=%d;charset=utf8mb4', $config['port']), 'root', $rootPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents($root . '/database/schema.sql'));
$quotedPassword = $pdo->quote($config['password']);
$pdo->exec("CREATE USER IF NOT EXISTS 'cicada'@'127.0.0.1' IDENTIFIED BY " . $quotedPassword);
$pdo->exec("ALTER USER 'cicada'@'127.0.0.1' IDENTIFIED BY " . $quotedPassword);
$pdo->exec("GRANT SELECT, UPDATE ON cicada_lab4.books TO 'cicada'@'127.0.0.1'");
$pdo->exec("GRANT SELECT, INSERT ON cicada_lab4.feedback TO 'cicada'@'127.0.0.1'");
$books = json_decode(file_get_contents($root . '/database/books.json'), true, 512, JSON_THROW_ON_ERROR);
$insert = $pdo->prepare('INSERT IGNORE INTO books (id,title,author,price,image,description,genre,language,binding,featured,sort_order)
    VALUES (:id,:title,:author,:price,:image,:description,:genre,:language,:binding,:featured,:sort_order)');
foreach ($books as $book) { $insert->execute($book); }
echo "Database initialized. Existing book changes and feedback were preserved.\n";
