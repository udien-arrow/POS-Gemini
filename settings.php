<?php
// Mengizinkan request dari asal manapun (untuk development)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Menangani preflight OPTIONS request untuk CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$configFile = __DIR__ . '/config.json';

// Pengaturan default jika file config tidak ada
$defaultSettings = [
    'store_name' => 'Toko Anda',
    'store_address' => 'Jalan Alamat No. 1'
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!file_exists($configFile)) {
            // Buat file dengan pengaturan default jika belum ada
            file_put_contents($configFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
        }
        // Baca dan kembalikan pengaturan saat ini
        $settingsJson = file_get_contents($configFile);
        echo $settingsJson;
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Data JSON yang diterima tidak valid.");
        }

        // Ambil pengaturan saat ini untuk digabungkan dengan data baru
        $currentSettings = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : $defaultSettings;

        // Perbarui pengaturan dengan data baru
        $newSettings = [
            'store_name' => $data['store_name'] ?? $currentSettings['store_name'],
            'store_address' => $data['store_address'] ?? $currentSettings['store_address']
        ];

        // Simpan pengaturan yang telah diperbarui
        if (file_put_contents($configFile, json_encode($newSettings, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Gagal menyimpan file konfigurasi. Periksa izin folder.");
        }

        echo json_encode(['message' => 'Pengaturan berhasil disimpan!']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}