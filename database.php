<?php

/**
 * Menginisialisasi koneksi ke database SQLite dan membuat tabel jika belum ada.
 * @return PDO Objek koneksi PDO.
 */
function getDbConnection() {
    $dbFile = __DIR__ . '/pos_database.sqlite';
    $isFirstRun = !file_exists($dbFile);

    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        // Mengaktifkan foreign key constraints untuk SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        // Mengatur mode error untuk melempar exceptions, memudahkan debugging
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Gagal terhubung ke database
        die("Koneksi database gagal: " . $e->getMessage());
    }

    if ($isFirstRun) {
        // Jika ini adalah kali pertama file database dibuat, inisialisasi skema tabel.
        try {
            // Tabel untuk pengaturan toko
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                )
            ");

            // Tabel untuk produk
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    price INTEGER NOT NULL,
                    category TEXT NOT NULL,
                    image TEXT
                )
            ");

            // Tabel untuk transaksi utama
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS transactions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    transaction_id TEXT NOT NULL UNIQUE,
                    timestamp TEXT NOT NULL,
                    subtotal REAL NOT NULL,
                    tax REAL NOT NULL,
                    transaction_discount REAL NOT NULL,
                    total REAL NOT NULL,
                    payment_method TEXT NOT NULL,
                    amount_paid REAL NOT NULL,
                    change REAL NOT NULL
                )
            ");

            // Tabel untuk item di dalam setiap transaksi (Normalisasi)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS transaction_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    transaction_id INTEGER NOT NULL,
                    product_id INTEGER,
                    product_name TEXT NOT NULL,
                    quantity INTEGER NOT NULL,
                    price_at_sale REAL NOT NULL,
                    discount_per_item REAL NOT NULL,
                    FOREIGN KEY (transaction_id) REFERENCES transactions(id)
                )
            ");
        } catch (PDOException $e) {
            die("Gagal membuat tabel: " . $e->getMessage());
        }
    }

    return $pdo;
}