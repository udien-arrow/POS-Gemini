<?php
require_once 'database.php';

// Mengizinkan request dari asal manapun (untuk development)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    $pdo = getDbConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT key, value FROM settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Memberikan nilai default jika belum ada di DB
        $settings['store_name'] = $settings['store_name'] ?? 'Toko Anda';
        $settings['store_address'] = $settings['store_address'] ?? 'Jalan Alamat No. 1';
        echo json_encode($settings);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Data JSON yang diterima tidak valid.");
        }
        
        // Gunakan INSERT OR REPLACE untuk menyederhanakan (UPSERT)
        $sql = "INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }

        echo json_encode(['message' => 'Pengaturan berhasil disimpan!']);
        exit;
    }

    throw new Exception('Method Not Allowed');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}