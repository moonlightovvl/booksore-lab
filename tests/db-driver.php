<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
$settings = json_decode(file_get_contents($root . '/runtime/settings.json'), true, 512, JSON_THROW_ON_ERROR);
$secrets = json_decode(file_get_contents($settings['state'] . '/secrets.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=cicada_lab4;charset=utf8mb4', 'root', $secrets['root'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$token = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new InvalidArgumentException('Invalid test token'); }
if (($argv[1] ?? '') === 'read') {
    $query = $pdo->prepare('SELECT * FROM feedback WHERE request_token = ?');
    $query->execute([$token]);
    echo json_encode($query->fetchAll(), JSON_UNESCAPED_UNICODE);
} elseif (($argv[1] ?? '') === 'cleanup') {
    $query = $pdo->prepare('DELETE FROM feedback WHERE request_token = ? AND email LIKE ?');
    $query->execute([$token, 'lab4-integration-%@example.test']);
    echo json_encode(['deleted' => $query->rowCount()]);
} else { throw new InvalidArgumentException('Unknown test action'); }
