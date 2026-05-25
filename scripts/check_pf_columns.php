<?php
$host='127.0.0.1'; $port=3306; $db='information_schema'; $user='root'; $pass='';
$checks = [
    ['schema'=>'agrolixisync','table'=>'thesis_metrics','column'=>'pf_percentage'],
    ['schema'=>'agrolixisync','table'=>'thesis_metrics','column'=>'pf_reference_ce'],
    ['schema'=>'agrolixisync','table'=>'thesis_metrics','column'=>'pf_measured_ce'],
    ['schema'=>'agrolixisync','table'=>'lotes','column'=>'reference_ce'],
];
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach ($checks as $c) {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$c['schema'],$c['table'],$c['column']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "{$c['table']}.{$c['column']}: " . ($row['cnt']? 'exists':'MISSING') . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
