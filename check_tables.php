<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=agrolixisync', 'root', '');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo implode(PHP_EOL, $tables);
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
