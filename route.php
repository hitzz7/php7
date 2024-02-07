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
                $productAPI->errorResponse('Product not found', 404);
            }
        } elseif ($endpoint === 'product') {
            $products = $productAPI->getAllProducts();

            foreach ($products as &$product) {
                $product['items'] = $productAPI->getItemsForProduct($product['id']);
                $product['images'] = $productAPI->getImagesForProduct($product['id']);
            }

            echo json_encode($products);
        } else {
            $productAPI->errorResponse('Endpoint not found', 404);
        }
        break;
    case 'POST':
        if ($endpoint === 'product') {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name']) || empty($data['description']) || empty($data['items'])) {
                $productAPI->errorResponse('Product name, description, and items are required.', 400);
            }

            try {
                $newProductId = $productAPI->addProduct($data['name'], $data['description'], $data['items']);
                echo json_encode(['message' => 'Product added successfully', 'id' => $newProductId]);
            } catch (Exception $e) {
                $productAPI->errorResponse('Internal server error', 500);
            }
        } elseif ($endpoint === 'image') {
            // Handle image upload (omitted for brevity)
            // Ensure to use errorResponse in appropriate error scenarios
        } else {
            $productAPI->errorResponse('Endpoint not found', 404);
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
                    $productAPI->errorResponse('Product not found', 404);
                }
            } catch (Exception $e) {
                $productAPI->errorResponse('Internal server error', 500);
            }
        } else {
            $productAPI->errorResponse('Endpoint not found', 404);
        }
        break;
    case 'DELETE':
        if ($endpoint === 'product' && is_numeric($id)) {
            // Handle product deletion (omitted for brevity)
            // Ensure to use errorResponse in appropriate error scenarios
        } elseif ($endpoint === 'image' && is_numeric($id)) {
            // Handle image deletion (omitted for brevity)
            // Ensure to use errorResponse in appropriate error scenarios
        } else {
            $productAPI->errorResponse('Endpoint not found', 404);
        }
        break;
    default:
        $productAPI->errorResponse('Method not allowed', 405);
}
