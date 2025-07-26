<?php
require __DIR__ . '/vendor/autoload.php';
require_once 'database.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

// Mengizinkan request dari asal manapun (untuk development)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// --- Membaca Konfigurasi Toko ---
$pdo = getDbConnection();
$stmt = $pdo->query("SELECT key, value FROM settings");
$storeSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$storeSettings['store_name'] = $storeSettings['store_name'] ?? 'Toko Keren (PHP)';
$storeSettings['store_address'] = $storeSettings['store_address'] ?? 'Jl. Kode No. 123';

try {
    $data = json_decode(file_get_contents('php://input'));
    $action = $data->action ?? null;

    if (!$action) {
        throw new Exception("Action not specified.");
    }

    // Pindahkan koneksi printer ke dalam blok try agar tidak selalu dibuat
    $connector = null;

    // Pengecekan izin tulis pada direktori saat ini
    if (!is_writable(__DIR__)) {
        throw new Exception("Server tidak memiliki izin untuk menulis file di direktori ini. Periksa izin folder untuk: " . __DIR__);
    }

    // =================================================================
    // KONFIGURASI KONEKTOR - PENTING!
    // Pilih salah satu konektor yang sesuai dengan printer Anda dan hapus/komentari yang lain.
    //
    // - Windows: Gunakan nama share printer Anda. Temukan di Control Panel > Devices and Printers.
    //   $connector = new WindowsPrintConnector("EPSON-TM-T82");
    //
    // - Uji Coba (Cetak ke File): Menyimpan output printer ke file untuk dianalisis.
    //   File output akan bernama 'receipt.bin' di direktori yang sama.
    // $connector = new FilePrintConnector("receipt.bin");
    //
    // - Network: Gunakan alamat IP dan port printer.
    // $connector = new NetworkPrintConnector("192.168.1.234", 9100);
    // =================================================================

    switch ($action) {
        case 'print':
            $cart = $data->cart ?? [];
            if (empty($cart)) {
                // Meskipun frontend sudah validasi, ada baiknya backend juga melakukan hal yang sama.
                throw new Exception("Keranjang belanja kosong.");
            }
            
            // Ambil status PPN dari data yang dikirim, default ke false jika tidak ada
            $taxEnabled = $data->tax_enabled ?? false;
            $transactionDiscount = $data->transaction_discount ?? 0;
            $paymentMethod = $data->payment_method ?? 'N/A';
            $amountPaid = $data->amount_paid ?? 0;
            $change = $data->change ?? 0;

            $connector = new FilePrintConnector("receipt.bin"); // Inisialisasi konektor di sini
            $printer = new Printer($connector);

            // --- Mulai mencetak ---

            // Header Struk
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($storeSettings['store_name'] . "\n");
            $printer->text($storeSettings['store_address'] . "\n");
            $printer->text(date('d-m-Y H:i:s') . "\n");
            $printer->text("--------------------------------\n");

            // Detail Item
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $subtotal = 0;
            foreach ($cart as $item) {
                $itemName = $item->name;
                $itemDiscount = $item->discount ?? 0;
                $quantity = $item->quantity;
                $price = $item->price;
                $itemTotal = ($price - $itemDiscount) * $quantity;
                $subtotal += $itemTotal;

                // Cetak harga asli jika ada diskon
                if ($itemDiscount > 0) {
                    $printer->text(sprintf('%-32s', "$itemName x$quantity") . "\n");
                    $printer->text(sprintf('%20s %12s', "  @ " . number_format($price) . " - " . number_format($itemDiscount), number_format($itemTotal)) . "\n");
                    continue; // Lanjut ke item berikutnya
                }

                // Format baris: Nama item di kiri, total harga di kanan
                // Asumsi lebar kertas 32 karakter
                $lineItem = sprintf('%-20s', "$itemName x$quantity");
                $linePrice = sprintf('%12s', number_format($itemTotal));
                $printer->text($lineItem . $linePrice . "\n");
            }
            
            // Ringkasan Total
            $printer->text("--------------------------------\n");
            // Hitung pajak hanya jika diaktifkan dari frontend
            $tax = $taxEnabled ? ($subtotal * 0.11) : 0;
            $total = $subtotal - $transactionDiscount + $tax;

            $printer->text(sprintf('%-20s %12s', 'Subtotal', number_format($subtotal)) . "\n");
            
            if ($transactionDiscount > 0) {
                $printer->text(sprintf('%-20s %12s', 'Diskon', '-' . number_format($transactionDiscount)) . "\n");
            }

            // Hanya cetak baris PPN jika PPN diterapkan
            if ($taxEnabled) {
                $printer->text(sprintf('%-20s %12s', 'PPN (11%)', number_format($tax)) . "\n");
            }
            
            $printer->setEmphasis(true);
            $printer->text(sprintf('%-20s %12s', 'TOTAL', number_format($total)) . "\n");
            $printer->setEmphasis(false);

            // Detail Pembayaran
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(sprintf('%-20s %12s', 'Metode Bayar:', $paymentMethod) . "\n");
            if ($paymentMethod === 'Tunai') {
                $printer->text(sprintf('%-20s %12s', 'Bayar:', number_format($amountPaid)) . "\n");
                $printer->text(sprintf('%-20s %12s', 'Kembali:', number_format($change)) . "\n");
            }

            // Footer Struk
            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Terima kasih!\n");
            
            // Potong kertas dan tutup koneksi
            $printer->cut();
            $printer->close();

            // --- Pencatatan Transaksi (Logging) ---
            try {
                $pdo->beginTransaction();

                $sql = "INSERT INTO transactions (transaction_id, timestamp, subtotal, tax, transaction_discount, total, payment_method, amount_paid, change) 
                        VALUES (:transaction_id, :timestamp, :subtotal, :tax, :transaction_discount, :total, :payment_method, :amount_paid, :change)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':transaction_id' => uniqid('trx_'),
                    ':timestamp' => date('c'),
                    ':subtotal' => $subtotal,
                    ':tax' => $tax,
                    ':transaction_discount' => $transactionDiscount,
                    ':total' => $total,
                    ':payment_method' => $paymentMethod,
                    ':amount_paid' => $amountPaid,
                    ':change' => $change
                ]);

                $transactionDbId = $pdo->lastInsertId();

                $itemSql = "INSERT INTO transaction_items (transaction_id, product_id, product_name, quantity, price_at_sale, discount_per_item)
                            VALUES (:transaction_id, :product_id, :product_name, :quantity, :price_at_sale, :discount_per_item)";
                $itemStmt = $pdo->prepare($itemSql);

                foreach ($cart as $item) {
                    $itemStmt->execute([
                        ':transaction_id' => $transactionDbId,
                        ':product_id' => $item->id,
                        ':product_name' => $item->name,
                        ':quantity' => $item->quantity,
                        ':price_at_sale' => $item->price,
                        ':discount_per_item' => $item->discount ?? 0
                    ]);
                }

                $pdo->commit();
            } catch (Exception $dbException) {
                $pdo->rollBack();
                // Jika logging gagal, jangan hentikan proses utama. Cukup catat error di log PHP.
                error_log("Gagal menyimpan transaksi ke DB: " . $dbException->getMessage());
            }

            echo json_encode(['message' => 'Struk berhasil dicetak dengan detail pesanan!']);
            break;

        case 'open_drawer':
            $connector = new FilePrintConnector("php://output"); // Use a dummy connector for non-printing actions
            $printer = new Printer($connector);
            $printer->pulse(); // Perintah untuk membuka laci kasir
            $printer->close();
            echo json_encode(['message' => 'Perintah membuka laci terkirim!']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}