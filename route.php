<?php
require_once 'controller.php';
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

            if (empty($data['name']))  {
                http_response_code(400);
                echo json_encode(['error' => 'Product name required']);
                exit();
            }
            if (empty($data['description'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Product Description required']);
                exit();
            }
            if (empty($data['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Product items required ']);
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