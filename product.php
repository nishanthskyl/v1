<?php
// Get product ID from URL
$productId = $_GET['id'] ?? '';
if (empty($productId)) {
    header("Location: index.php");
    exit();
}

// Load product data
$productDir = "uploads/products/" . $productId;
$productJsonPath = $productDir . "/product.json";

if (!file_exists($productJsonPath)) {
    header("Location: index.php");
    exit();
}

$product = json_decode(file_get_contents($productJsonPath), true);

// Get additional images
$additionalImages = glob($productDir . "/additional_*");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .product-image {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s;
        }
        .thumbnail.active {
            border-color: #0d6efd;
        }
        .thumbnails-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            overflow-x: auto;
            padding: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6">
                <img src="<?= htmlspecialchars($product['main_image']) ?>" id="mainImage" class="product-image mb-3" alt="<?= htmlspecialchars($product['name']) ?>">
                
                <div class="thumbnails-container">
                    <img src="<?= htmlspecialchars($product['main_image']) ?>" 
                         class="thumbnail active" 
                         onclick="updateMainImage(this.src)" 
                         alt="Main Image">
                    
                    <?php foreach ($additionalImages as $image): ?>
                        <img src="<?= htmlspecialchars($image) ?>" 
                             class="thumbnail" 
                             onclick="updateMainImage(this.src)" 
                             alt="Additional Image">
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <p class="text-muted"><?= htmlspecialchars($product['category']) ?></p>
                <h2 class="text-primary">₹<?= number_format($product['price'], 2) ?></h2>
                <div class="mt-4">
                    <h3>Description</h3>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateMainImage(src) {
            document.getElementById('mainImage').src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
                if (thumb.src === src) {
                    thumb.classList.add('active');
                }
            });
        }
    </script>
</body>
</html>