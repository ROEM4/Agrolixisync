<?php
$host='127.0.0.1'; $port=3306; $db='information_schema'; $user='root'; $pass='';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute(['agrolixisync', 'thesis_metrics']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['cnt'];
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
