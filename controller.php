<?php

require_once 'database.php'; 

class Product extends Database {
    public function getAllProducts() {
        return $this->executeQuery("SELECT * FROM products", [], true);
    }

    public function getItemsForProduct($productId) {
        return $this->executeQuery("
            SELECT 
                *, 
                CASE status
                    WHEN '1' THEN 'active'
                    WHEN '0' THEN 'inactive'
                    ELSE 'unknown' -- Optional, handles unexpected values
                END as status
            FROM items 
            WHERE product_id = :product_id", 
            [':product_id' => $productId], 
            true);
    }
    

    public function getImagesForProduct($productId) {
        return $this->executeQuery("SELECT * FROM images WHERE product_id = :product_id", [':product_id' => $productId], true);
    }

    public function getProductById($id) {
        return $this->executeQuery("SELECT * FROM products WHERE id = :id", [':id' => $id], false);
    }

    public function addProduct($name, $description, $items) {
        try {
            $this->beginTransaction();

            $sql = "INSERT INTO products (name, description) VALUES (:name, :description)";
            $params = [':name' => $name, ':description' => $description];
            $newProductId = $this->executeQuery($sql, $params, false, true);

            foreach ($items as $item) {
                $this->insertItemForProduct($newProductId, $item);
            }

            $this->commit();

            return $newProductId;
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    private function insertItemForProduct($productId, $item) {
        $numericStatus = ($item['status'] === 'active') ? 1 : 0;
        $this->executeQuery("INSERT INTO items (product_id, size, color, status, sku, price) 
                             VALUES (:product_id, :size, :color, :status, :sku, :price)", [
            ':product_id' => $productId,
            ':size' => $item['size'],
            ':color' => $item['color'],
            ':status' => $numericStatus,
            ':sku' => $item['sku'],
            ':price' => $item['price']
        ], false);
    }

    public function addImageToProduct($productId, $image) {
        

        $imageFile = $image['tmp_name'];
        $imageFileName = $image['name'];

        $uploadFolder = __DIR__ . '/images/';

        if (move_uploaded_file($imageFile, $uploadFolder . $imageFileName)) {
            $this->executeQuery("INSERT INTO images (product_id, image) VALUES (:product_id, :image)", [
                ':product_id' => $productId, 
                ':image' => $imageFileName
            ], false);
        } else {
            throw new RuntimeException('Failed to upload image');
        }
    }
    public function updateProduct($id, $name, $description, $items) {
        $this->executeQuery(
            "UPDATE products SET name = :name, description = :description WHERE id = :id",
            [':id' => $id, ':name' => $name, ':description' => $description],
            false
        );
    
        foreach ($items as $item) {
            if (isset($item['id']) && $item['id']) {
                $this->updateItem($item);
            } else {
                $this->insertItemForProduct($id, $item);
            }
        }
    
        return true;
    }
    
    private function updateItem($item) {
        $sql = "UPDATE items SET ";
        $params = [];
    
        if (isset($item['size'])) {
            $sql .= "size = :size, ";
            $params[':size'] = $item['size'];
        }
    
        if (isset($item['color'])) {
            $sql .= "color = :color, ";
            $params[':color'] = $item['color'];
        }
    
        if (isset($item['status'])) {
            $sql .= "status = :status, ";
            $params[':status'] = $item['status'];
        }
    
        if (isset($item['sku'])) {
            $sql .= "sku = :sku, ";
            $params[':sku'] = $item['sku'];
        }
    
        if (isset($item['price'])) {
            $sql .= "price = :price, ";
            $params[':price'] = $item['price'];
        }
    
        $sql = rtrim($sql, ', ');
        $sql .= " WHERE id = :id";
        $params[':id'] = $item['id'];
    
        $this->executeQuery($sql, $params, false);
    }
    
    public function updateImage($productId, $imageId, $newImagePath) {
        
        $this->executeQuery(
            "UPDATE images SET image = :image WHERE id = :id AND product_id = :product_id",
            [':id' => $imageId, ':image' => $newImagePath, ':product_id' => $productId],
            false
        );
    
        return true;
    }
    public function deleteProduct($productId) {
       
    
        // Delete items associated with the product
        $this->executeQuery("DELETE FROM items WHERE product_id = :productId", ['productId' => $productId]);
    
        // Delete images associated with the product
        $this->executeQuery("DELETE FROM images WHERE product_id = :productId", ['productId' => $productId]);
    
        // Finally, delete the product itself
        $this->executeQuery("DELETE FROM products WHERE id = :productId", ['productId' => $productId]);
    
        return true; // Indicate that the product has been successfully deleted
    }
    public function deleteImage($imageId) {
        // Use executeQuery to delete the specified image
        $this->executeQuery("DELETE FROM images WHERE id = :imageId", ['imageId' => $imageId]);
    
        // Optionally, you might want to add code here to also delete the image file from the server.
    
        return true; // Indicate that the image has been successfully deleted
    }
    public function errorResponse($msg, $code = 400) 
{
    http_response_code($code); // Set HTTP response code
    echo json_encode([
        'status' => 'error',
        'code' => $code,
        'message' => $msg
    ]);
    exit(); // Halt further script execution after sending the error response
}
    
    
    

    // Update other methods in a similar fashion...
}
