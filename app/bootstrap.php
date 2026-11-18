<?php
declare(strict_types=1);
require_once __DIR__ . '/script.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
    set_exception_handler(function (Throwable $exception): void {
        error_log((string) $exception);
        http_response_code(503);
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>CICADA — сервер недоступен</title>';
        echo '<h1>Не удалось получить данные</h1><p>Запустите локальные серверы через START-LAB.cmd. Подробности записаны в runtime/logs.</p></html>';
    });
}
