<?php
// Mengizinkan request dari asal manapun (untuk development)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$logFile = __DIR__ . '/transactions.log';
$transactions = [];
$dailySummaries = [];

if (file_exists($logFile)) {
    // Baca file log baris per baris
    // FILE_IGNORE_NEW_LINES: hapus karakter newline di akhir baris
    // FILE_SKIP_EMPTY_LINES: lewati baris kosong
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines !== false) {
        foreach ($lines as $line) {
            // Decode setiap baris JSON menjadi array PHP
            $decodedLine = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $transactions[] = $decodedLine;

                // Proses untuk ringkasan harian
                $date = date('Y-m-d', strtotime($decodedLine['timestamp']));
                if (!isset($dailySummaries[$date])) {
                    $dailySummaries[$date] = [
                        'total_sales' => 0,
                        'total_tax' => 0,
                        'transaction_count' => 0
                    ];
                }
                $dailySummaries[$date]['total_sales'] += $decodedLine['summary']['total'];
                $dailySummaries[$date]['total_tax'] += $decodedLine['summary']['tax'];
                $dailySummaries[$date]['transaction_count']++;
            }
        }
    }
}

// Balik urutan array agar transaksi terbaru muncul di atas
$response = [
    'transactions' => array_reverse($transactions),
    'summaries' => $dailySummaries
];

echo json_encode($response);