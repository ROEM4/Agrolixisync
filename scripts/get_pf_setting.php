<?php
$host='127.0.0.1'; $port=3306; $db='agrolixisync'; $user='root'; $pass='';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT * FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute(['pf_reference_ce']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode($row, JSON_PRETTY_PRINT);
    } else {
        echo "NOT_FOUND";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
