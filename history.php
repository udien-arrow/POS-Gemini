<?php
require_once 'database.php';

// Mengizinkan request dari asal manapun (untuk development)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    $pdo = getDbConnection();

    // 1. Ambil semua transaksi utama, diurutkan dari yang terbaru
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY timestamp DESC");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Ambil semua item transaksi dan kelompokkan berdasarkan ID transaksi
    $itemsStmt = $pdo->query("SELECT * FROM transaction_items");
    $itemsByTransaction = [];
    while ($item = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
        $itemsByTransaction[$item['transaction_id']][] = $item;
    }

    // 3. Gabungkan item ke dalam setiap transaksi
    // (Ini diperlukan agar modal detail di frontend tetap berfungsi)
    foreach ($transactions as &$trx) {
        $trx['items'] = $itemsByTransaction[$trx['id']] ?? [];
    }
    unset($trx); // Hapus referensi

    // 4. Hitung ringkasan harian menggunakan query SQL
    $summaryStmt = $pdo->query("
        SELECT 
            strftime('%Y-%m-%d', timestamp) as date,
            SUM(total) as total_sales,
            SUM(tax) as total_tax,
            COUNT(id) as transaction_count
        FROM transactions
        GROUP BY date
        ORDER BY date DESC
    ");
    $dailySummaries = $summaryStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $response = [
        'transactions' => $transactions,
        'summaries' => $dailySummaries
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil riwayat: ' . $e->getMessage()]);
}