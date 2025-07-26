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
        // Karena kita mengirim file, data tidak lagi dalam format JSON, tapi multipart/form-data
        $action = $_POST['action'] ?? null;
        $products = getProducts($productsFile);
        $imagePath = null;

        // Handle file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Tipe file tidak valid. Hanya JPG, PNG, GIF yang diizinkan.");
            }

            $fileName = uniqid() . '-' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imagePath = 'uploads/' . $fileName;
            } else {
                throw new Exception("Gagal mengunggah file gambar.");
            }
        }

        switch ($action) {
            case 'create':
                $newProduct = [
                    'id' => empty($products) ? 1 : max(array_column($products, 'id')) + 1,
                    'name' => $_POST['name'] ?? '',
                    'price' => (int)($_POST['price'] ?? 0),
                    'image' => $imagePath ?? 'uploads/default.png', // Gunakan gambar baru atau default
                    'category' => $_POST['category'] ?? 'Lainnya'
                ];
                if (empty($newProduct['name']) || $newProduct['price'] <= 0) {
                    throw new Exception("Nama dan harga produk harus diisi dengan benar.");
                }
                $products[] = $newProduct;
                saveProducts($productsFile, $products);
                echo json_encode(['message' => 'Produk berhasil ditambahkan!', 'product' => $newProduct]);
                break;

            case 'update':
                $idToUpdate = (int)($_POST['id'] ?? 0);
                if ($idToUpdate <= 0) throw new Exception("ID produk tidak valid untuk diupdate.");
                
                $updated = false;
                foreach ($products as &$product) {
                    if ($product['id'] === $idToUpdate) {
                        $product['name'] = $_POST['name'] ?? $product['name'];
                        $product['price'] = (int)($_POST['price'] ?? $product['price']);
                        $product['category'] = $_POST['category'] ?? $product['category'];
                        if ($imagePath !== null) {
                            $product['image'] = $imagePath;
                        }
                        $updated = true;
                        break;
                    }
                }
                if (!$updated) throw new Exception("Produk dengan ID $idToUpdate tidak ditemukan.");
                
                saveProducts($productsFile, $products);
                echo json_encode(['message' => 'Produk berhasil diperbarui!']);
                break;

            case 'delete':
                $idToDelete = (int)($_POST['id'] ?? 0);
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