<?php
session_start();

// Simple authentication (in a real app, use proper authentication)
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $error = "Invalid credentials";
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Admin Login</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?= $error ?></div>
                                <?php endif; ?>
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        // Create product directory
        $productId = uniqid('prod_');
        $productDir = "uploads/products/" . $productId;
        if (!file_exists($productDir)) {
            mkdir($productDir, 0777, true);
        }

        // Handle main product image
        if (isset($_FILES["main_image"]) && $_FILES["main_image"]["error"] == 0) {
            $mainImageName = basename($_FILES["main_image"]["name"]);
            $mainImagePath = $productDir . "/main_" . $mainImageName;
            move_uploaded_file($_FILES["main_image"]["tmp_name"], $mainImagePath);
        }

        // Handle additional images
        if (isset($_FILES["additional_images"])) {
            $totalFiles = count($_FILES["additional_images"]["name"]);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES["additional_images"]["error"][$i] == 0) {
                    $fileName = basename($_FILES["additional_images"]["name"][$i]);
                    $targetFile = $productDir . "/additional_" . $fileName;
                    move_uploaded_file($_FILES["additional_images"]["tmp_name"][$i], $targetFile);
                }
            }
        }

        // Save product data
        $productData = [
            'id' => $productId,
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'price' => $_POST['price'],
            'category' => $_POST['category'],
            'main_image' => $mainImagePath,
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents($productDir . "/product.json", json_encode($productData));
        $success = "Product added successfully!";
    }
    
    elseif (isset($_POST['delete_product'])) {
        $productDir = "uploads/products/" . $_POST['product_id'];
        if (is_dir($productDir)) {
            array_map('unlink', glob("$productDir/*.*"));
            rmdir($productDir);
            $success = "Product deleted successfully!";
        }
    }
    
    elseif (isset($_POST['delete_additional_image'])) {
        $imagePath = $_POST['image_path'];
        if (file_exists($imagePath) && is_file($imagePath)) {
            unlink($imagePath);
            $success = "Image deleted successfully!";
        }
    }
}

// Get all products
$products = [];
$productsDir = "uploads/products/";
if (is_dir($productsDir)) {
    foreach (glob($productsDir . "prod_*") as $productDir) {
        if (file_exists($productDir . "/product.json")) {
            $productData = json_decode(file_get_contents($productDir . "/product.json"), true);
            $products[] = $productData;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        .additional-images {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .additional-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <h2>Add New Product</h2>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" step="0.01" class="form-control" id="price" name="price" required>
            </div>
            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <input type="text" class="form-control" id="category" name="category" required>
            </div>
            <div class="mb-3">
                <label for="main_image" class="form-label">Main Product Image</label>
                <input type="file" class="form-control" id="main_image" name="main_image" accept="image/*" required>
            </div>
            <div class="mb-3">
                <label for="additional_images" class="form-label">Additional Images</label>
                <input type="file" class="form-control" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                <small class="text-muted">You can select multiple images</small>
            </div>
            <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
        </form>

        <h2 class="mt-5">Products</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Images</th>
                        <th>Price</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td>
                                <img src="<?= htmlspecialchars($product['main_image']) ?>" class="product-image" alt="Main Image">
                                <div class="additional-images">
                                    <?php
                                    $productDir = dirname($product['main_image']);
                                    foreach (glob($productDir . "/additional_*") as $additionalImage): ?>
                                        <div class="position-relative">
                                            <img src="<?= $additionalImage ?>" class="additional-image" alt="Additional Image">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="image_path" value="<?= $additionalImage ?>">
                                                <button type="submit" name="delete_additional_image" class="btn btn-danger btn-sm position-absolute top-0 end-0">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>₹<?= number_format($product['price'], 2) ?></td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>