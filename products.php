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

$productsFile = __DIR__ . '/products.json';

// Fungsi helper untuk membaca produk
function getProducts($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true);
}

// Fungsi helper untuk menyimpan produk
function saveProducts($file, $products) {
    // Gunakan array_values untuk memastikan array tetap di-encode sebagai array JSON, bukan objek
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT);
    if (file_put_contents($file, $json) === false) {
        throw new Exception("Gagal menyimpan file produk. Periksa izin folder.");
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(getProducts($productsFile));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Data JSON tidak valid.");
        }

        $action = $input['action'] ?? null;
        $products = getProducts($productsFile);

        switch ($action) {
            case 'create':
                $newProduct = [
                    'id' => empty($products) ? 1 : max(array_column($products, 'id')) + 1,
                    'name' => $input['name'] ?? '',
                    'price' => (int)($input['price'] ?? 0)
                ];
                if (empty($newProduct['name']) || $newProduct['price'] <= 0) {
                    throw new Exception("Nama dan harga produk harus diisi dengan benar.");
                }
                $products[] = $newProduct;
                saveProducts($productsFile, $products);
                echo json_encode(['message' => 'Produk berhasil ditambahkan!', 'product' => $newProduct]);
                break;

            case 'update':
                $idToUpdate = (int)($input['id'] ?? 0);
                if ($idToUpdate <= 0) throw new Exception("ID produk tidak valid untuk diupdate.");
                
                $updated = false;
                foreach ($products as &$product) {
                    if ($product['id'] === $idToUpdate) {
                        $product['name'] = $input['name'] ?? $product['name'];
                        $product['price'] = (int)($input['price'] ?? $product['price']);
                        $updated = true;
                        break;
                    }
                }
                if (!$updated) throw new Exception("Produk dengan ID $idToUpdate tidak ditemukan.");
                
                saveProducts($productsFile, $products);
                echo json_encode(['message' => 'Produk berhasil diperbarui!']);
                break;

            case 'delete':
                $idToDelete = (int)($input['id'] ?? 0);
                if ($idToDelete <= 0) throw new Exception("ID produk tidak valid untuk dihapus.");

                $initialCount = count($products);
                $products = array_filter($products, fn($p) => $p['id'] !== $idToDelete);
                if (count($products) === $initialCount) throw new Exception("Produk dengan ID $idToDelete tidak ditemukan.");

                saveProducts($productsFile, $products);
                echo json_encode(['message' => 'Produk berhasil dihapus!']);
                break;

            default:
                throw new Exception('Aksi tidak valid.');
        }
        exit;
    }

    throw new Exception('Method Not Allowed');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}