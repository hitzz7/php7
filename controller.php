<?php

require_once 'database.php';

// Create a new instance of the Database class
$database = new Database();


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
        
        $product = $this->getProductById($productId);

        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'No such product ID']);
            exit();
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
                // If 'id' is set, prepare the SQL statement
                $sql = "UPDATE items SET ";
                $params = [];
        
                if (isset($item['size'])) {
                    $sql .= "size = ?, ";
                    $params[] = $item['size'];
                }
        
                if (isset($item['color'])) {
                    $sql .= "color = ?, ";
                    $params[] = $item['color'];
                }
        
                if (isset($item['status'])) {
                    $sql .= "status = ?, ";
                    $params[] = $item['status'];
                }
        
                if (isset($item['sku'])) {
                    $sql .= "sku = ?, ";
                    $params[] = $item['sku'];
                }
        
                if (isset($item['price'])) {
                    $sql .= "price = ?, ";
                    $params[] = $item['price'];
                }
        
                // Remove the trailing comma and complete the SQL statement
                $sql = rtrim($sql, ', ') . " WHERE id = ?";
        
                // Add the item ID to the parameters
                $params[] = $item['id'];
        
                // Execute the prepared statement
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                // If 'id' is not set or empty, insert a new item
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
?>
