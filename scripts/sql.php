<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/script.php';
if (empty($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/sql.php database/defense-change.sql\n");
    exit(1);
}
$sql = file_get_contents($argv[1]);
foreach (explode(';', $sql) as $statement) {
    if (trim($statement) === '') { continue; }
    $result = db()->query($statement);
    if ($result->columnCount()) {
        echo json_encode($result->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
}
