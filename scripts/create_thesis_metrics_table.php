<?php
$host='127.0.0.1'; $port=3306; $db='agrolixisync'; $user='root'; $pass='';
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS thesis_metrics (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  location_id BIGINT UNSIGNED NOT NULL,
  tar_minutes DECIMAL(8,2) NULL,
  tar_sample_count INT DEFAULT 0,
  tar_calculated_at TIMESTAMP NULL,
  pds_percentage DECIMAL(5,2) NULL,
  pds_total_tests INT DEFAULT 0,
  pds_correct_detections INT DEFAULT 0,
  pds_false_positives INT DEFAULT 0,
  pds_false_negatives INT DEFAULT 0,
  pds_calculated_at TIMESTAMP NULL,
  nces_control_avg DECIMAL(8,2) NULL,
  nces_experimental_avg DECIMAL(8,2) NULL,
  nces_difference DECIMAL(8,2) NULL,
  nces_control_samples INT DEFAULT 0,
  nces_experimental_samples INT DEFAULT 0,
  nces_calculated_at TIMESTAMP NULL,
  period_start_date DATE,
  period_end_date DATE,
  notes TEXT NULL,
  calculated_by VARCHAR(191) DEFAULT 'system',
  is_verified TINYINT(1) DEFAULT 0,
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_thesis_location (location_id),
  INDEX idx_thesis_period_start (period_start_date),
  INDEX idx_thesis_period_end (period_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $pdo->exec($sql);
    echo "OK\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
