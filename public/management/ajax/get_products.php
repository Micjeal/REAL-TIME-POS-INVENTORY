<?php
require_once('../../config.php');

try {
    $stmt = $pdo->prepare("SELECT * FROM products ORDER BY name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        echo '<div class="product-card">';
        echo '<div class="d-flex justify-content-between align-items-start mb-2">';
        echo '<h5 class="mb-0">' . htmlspecialchars($product['name']) . '</h5>';
        echo '<span class="status-badge ' . ($product['active'] ? 'status-active' : 'status-inactive') . '">';
        echo $product['active'] ? 'Active' : 'Inactive';
        echo '</span>';
        echo '</div>';
        echo '<p class="text-muted mb-2">Code: ' . htmlspecialchars($product['code']) . '</p>';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<div>';
        echo '<strong>Price:</strong> ' . number_format($product['price'], 2);
        echo '</div>';
        echo '<div>';
        echo '<strong>Stock:</strong> ' . $product['stock'];
        echo '</div>';
        echo '</div>';
        echo '<div class="mt-3 d-flex gap-2">';
        echo '<button class="btn btn-sm btn-outline-primary edit-product" data-id="' . $product['id'] . '">';
        echo '<i class="bx bx-edit"></i> Edit';
        echo '</button>';
        echo '<button class="btn btn-sm btn-outline-danger delete-product" data-id="' . $product['id'] . '">';
        echo '<i class="bx bx-trash"></i> Delete';
        echo '</button>';
        echo '</div>';
        echo '</div>';
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
