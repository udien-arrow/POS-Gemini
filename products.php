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
        $stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($products);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
        $imagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) throw new Exception("Tipe file tidak valid. Hanya JPG, PNG, GIF yang diizinkan.");
            $fileName = uniqid() . '-' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) $imagePath = 'uploads/' . $fileName;
            else throw new Exception("Gagal mengunggah file gambar.");
        }

        switch ($action) {
            case 'create':
                if (empty($_POST['name']) || (int)($_POST['price'] ?? 0) <= 0) {
                    throw new Exception("Nama dan harga produk harus diisi dengan benar.");
                }
                $sql = "INSERT INTO products (name, price, category, image) VALUES (:name, :price, :category, :image)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $_POST['name'],
                    ':price' => (int)$_POST['price'],
                    ':category' => $_POST['category'] ?? 'Lainnya',
                    ':image' => $imagePath ?? 'uploads/default.png'
                ]);
                echo json_encode(['message' => 'Produk berhasil ditambahkan!']);
                break;

            case 'update':
                $idToUpdate = (int)($_POST['id'] ?? 0);
                if ($idToUpdate <= 0) throw new Exception("ID produk tidak valid untuk diupdate.");
                
                // Bangun query secara dinamis
                $fields = [];
                $params = [':id' => $idToUpdate];
                if (isset($_POST['name']) && !empty($_POST['name'])) { $fields[] = 'name = :name'; $params[':name'] = $_POST['name']; }
                if (isset($_POST['price']) && !empty($_POST['price'])) { $fields[] = 'price = :price'; $params[':price'] = (int)$_POST['price']; }
                if (isset($_POST['category']) && !empty($_POST['category'])) { $fields[] = 'category = :category'; $params[':category'] = $_POST['category']; }
                if ($imagePath !== null) { $fields[] = 'image = :image'; $params[':image'] = $imagePath; }

                if (!empty($fields)) {
                    $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                echo json_encode(['message' => 'Produk berhasil diperbarui!']);
                break;

            case 'delete':
                $idToDelete = (int)($_POST['id'] ?? 0);
                if ($idToDelete <= 0) throw new Exception("ID produk tidak valid untuk dihapus.");
                $sql = "DELETE FROM products WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $idToDelete]);
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