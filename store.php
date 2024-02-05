<?php

class Database {
    private $host = '127.0.0.1';
    private $dbname = 'php7';
    private $username = 'root';
    private $password = 'root';
    protected $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            die();
        }
    }
}

class Product extends Database {
    public function getAllProducts() {
        $stmt = $this->pdo->query("SELECT * FROM products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemsForProduct($productId) {
        $stmt = $this->pdo->prepare("SELECT * FROM items WHERE product_id = :product_id");
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getImagesForProduct($productId) {
        $stmt = $this->pdo->prepare("SELECT * FROM images WHERE product_id = :product_id");
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProductById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addProduct($name, $description, $items) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO products (name, description) VALUES (:name, :description)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->execute();

            $newProductId = $this->pdo->lastInsertId();

            foreach ($items as $item) {
                $this->insertItemForProduct($newProductId, $item);
            }

            $this->pdo->commit();
            

            return $newProductId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertItemForProduct($productId, $item) {
        $stmt = $this->pdo->prepare("INSERT INTO items (product_id, size, color, status, sku, price) 
                                    VALUES (:product_id, :size, :color, :status, :sku, :price)");
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindParam(':size', $item['size'], PDO::PARAM_STR);
        $stmt->bindParam(':color', $item['color'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $item['status'], PDO::PARAM_STR);
        $stmt->bindParam(':sku', $item['sku'], PDO::PARAM_STR);
        $stmt->bindParam(':price', $item['price'], PDO::PARAM_STR);
        $stmt->execute();
    }

    public function addImageToProduct($productId, $image) {
        if (!$productId || !is_numeric($productId)) {
            throw new InvalidArgumentException('Invalid Product ID');
        }

        if (!isset($image['tmp_name'])) {
            throw new InvalidArgumentException('Image not provided');
        }

        $imageFile = $image['tmp_name'];
        $imageFileName = $image['name'];

        $uploadFolder = __DIR__ . '/images/';

        if (move_uploaded_file($imageFile, $uploadFolder . $imageFileName)) {
            $stmt = $this->pdo->prepare("INSERT INTO images (product_id, image) VALUES (:product_id, :image)");
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':image', $imageFileName, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            throw new RuntimeException('Failed to upload image');
        }
    }

    public function updateProduct($id, $name, $description, $items) {
        $stmt = $this->pdo->prepare("UPDATE products SET name = :name, description = :description WHERE id = :id");
        $stmt->execute([':id' => $id, ':name' => $name, ':description' => $description]);

        foreach ($items as $item) {
            if (isset($item['id']) && $item['id']) {
                $stmt = $this->pdo->prepare("UPDATE items SET size = ?, color = ?, status = ?, sku = ?, price = ? WHERE id = ?");
                $stmt->execute([$item['size'], $item['color'], $item['status'], $item['sku'], $item['price'], $item['id']]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO items (product_id, size, color, status, sku, price) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id, $item['size'], $item['color'], $item['status'], $item['sku'], $item['price']]);
            }
        }

        return true;
    }

    public function updateImage($imageId, $newImagePath) {
        $stmt = $this->pdo->prepare("UPDATE images SET image_path = :image_path WHERE id = :id");
        $stmt->execute([':id' => $imageId, ':image_path' => $newImagePath]);
        return true;
    }

    public function deleteProduct($productId) {
        $stmt = $this->pdo->prepare("DELETE FROM items WHERE product_id = ?");
        $stmt->execute([$productId]);

        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);

        return true;
    }

    public function deleteImage($imageId) {
        $stmt = $this->pdo->prepare("DELETE FROM images WHERE id = ?");
        $stmt->execute([$imageId]);
        return true;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

$parts = explode('/', trim($request, '/'));
$endpoint = $parts[1];
$id = isset($parts[2]) ? $parts[2] : null;

$productAPI = new Product();

switch ($method) {
    case 'GET':
        if ($endpoint === 'product' && is_numeric($id)) {
            $product = $productAPI->getProductById($id);

            if ($product) {
                $product['items'] = $productAPI->getItemsForProduct($id);
                $product['images'] = $productAPI->getImagesForProduct($id);
                echo json_encode($product);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            }
        } elseif ($endpoint === 'product') {
            $products = $productAPI->getAllProducts();

            foreach ($products as &$product) {
                $product['items'] = $productAPI->getItemsForProduct($product['id']);
                $product['images'] = $productAPI->getImagesForProduct($product['id']);
            }

            echo json_encode($products);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
    case 'POST':
        if ($endpoint === 'product') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name']) || empty($data['description']) || empty($data['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Incomplete data']);
                exit();
            }

            try {
                $newProductId = $productAPI->addProduct($data['name'], $data['description'], $data['items']);
                echo json_encode(['message' => 'Product added successfully', 'id' => $newProductId]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } elseif ($endpoint === 'image') {
            try {
                $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
                $productAPI->addImageToProduct($product_id, $_FILES['image']);
                echo json_encode(['message' => 'Image added successfully', 'product_id' => $product_id]);
            } catch (InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            } catch (RuntimeException $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
    case 'PUT':
        if ($endpoint === 'product' && is_numeric($id)) {
            $data = json_decode(file_get_contents('php://input'), true);

            try {
                $result = $productAPI->updateProduct($id, $data['name'], $data['description'], $data['items']);

                if ($result) {
                    echo json_encode(['message' => 'Product updated successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal Server Error']);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
    case 'DELETE':
        if ($endpoint === 'product' && is_numeric($id)) {
            try {
                $result = $productAPI->deleteProduct($id);

                if ($result) {
                    echo json_encode(['message' => 'Product deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal Server Error']);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } elseif ($endpoint === 'image' && is_numeric($id)) {
            try {
                $result = $productAPI->deleteImage($id);

                if ($result) {
                    echo json_encode(['message' => 'Image deleted successfully']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal Server Error']);
                }
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}
?>
