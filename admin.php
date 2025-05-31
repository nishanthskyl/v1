<?php
include 'db.php';

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
    if (isset($_POST['upload_wall_of_love_image'])) {
        if (isset($_FILES['wall_of_love_image']) && $_FILES['wall_of_love_image']['error'] == 0) {
            $targetDir = "uploads/wall_of_love/";
            // Sanitize the filename
            $imageName = basename($_FILES["wall_of_love_image"]["name"]);
            $safeImageName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $imageName); // Replace disallowed chars with underscore
            
            // Ensure filename is not empty after sanitization
            if (empty($safeImageName) || $safeImageName === '.' || $safeImageName === '..') {
                 $error = "Invalid image filename after sanitization.";
            } else {
                $targetFile = $targetDir . $safeImageName;
                $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

                // Basic check for common web image types
                $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];
                // if (!in_array($imageFileType, $allowedExtensions)) {
                    // As per requirement, all file types are allowed.
                    // For a production system, you might want to add more validation here
                    // or ensure that non-image files are handled appropriately (e.g., not displayed directly as images).
                // }

                // Prevent overwriting existing files by appending a number if necessary
                $counter = 0;
                $originalTargetFile = $targetFile;
                $originalSafeImageName = $safeImageName; // Store original safe name for success message

                while (file_exists($targetFile)) {
                    $counter++;
                    $fileNameWithoutExt = pathinfo($originalSafeImageName, PATHINFO_FILENAME); // Use original safe name for base
                    $fileExt = pathinfo($originalSafeImageName, PATHINFO_EXTENSION);
                    $safeImageName = $fileNameWithoutExt . "_" . $counter . "." . $fileExt; // update safe name for db/message
                    $targetFile = $targetDir . $safeImageName; 
                }

                if (move_uploaded_file($_FILES["wall_of_love_image"]["tmp_name"], $targetFile)) {
                    $success = "Image \"" . htmlspecialchars($safeImageName) . "\" uploaded successfully to Wall of Love.";

                    // Handle caption
                    $caption = ""; 
                    if (isset($_POST['wall_of_love_caption'])) {
                        $caption = trim($_POST['wall_of_love_caption']);
                        $caption = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); 
                    }

                    // Save caption to a .txt file named after the image
                    $imageFilenameWithoutExt = pathinfo($safeImageName, PATHINFO_FILENAME);
                    $captionFilePath = $targetDir . $imageFilenameWithoutExt . ".txt";

                    if (file_put_contents($captionFilePath, $caption) === false) {
                        // Optionally, append to error or log, but don't overwrite main success message for image
                        // For example: $success .= " (Caption saving failed)"; // Or set a secondary warning
                        error_log("Failed to save caption for " . $safeImageName . " to " . $captionFilePath);
                    }
                    // If you want to inform about successful caption saving as well:
                    // else { $success .= " Caption saved."; }

                } else {
                    $error = "Sorry, there was an error uploading your file. Check directory permissions.";
                }
            }
        } else {
            $error = "No file uploaded or an error occurred during upload.";
            if (isset($_FILES['wall_of_love_image']['error']) && $_FILES['wall_of_love_image']['error'] != UPLOAD_ERR_NO_FILE) {
                 $error .= " Error code: " . $_FILES['wall_of_love_image']['error'];
            }
        }
    } elseif (isset($_POST['delete_wall_of_love_image_name'])) {
        $imageNameToDelete = basename($_POST['delete_wall_of_love_image_name']); // Sanitize by getting basename
        $wallOfLoveDir = "uploads/wall_of_love/";
        $filePathToDelete = $wallOfLoveDir . $imageNameToDelete;

        // Security checks:
        // 1. Ensure the resolved path is actually within the wallOfLoveDir to prevent directory traversal.
        // realpath() resolves symbolic links, '..' and '.' dots.
        // Also check if realpath($wallOfLoveDir) and realpath($filePathToDelete) are not false (i.e. path exists)
        $realWallOfLoveDir = realpath($wallOfLoveDir);
        $realFilePathToDelete = realpath($filePathToDelete);

        if ($realWallOfLoveDir !== false && $realFilePathToDelete !== false && strpos($realFilePathToDelete, $realWallOfLoveDir) === 0) {
            // Check if file exists (realpath check above already implies this, but good for clarity)
            if (file_exists($filePathToDelete)) { 
                if (is_writable($filePathToDelete)) { // Check if file is writable before attempting to delete
                    if (unlink($filePathToDelete)) {
                        $success = "Image \"" . htmlspecialchars($imageNameToDelete) . "\" deleted successfully.";

                        // Also delete the corresponding caption file, if it exists
                        $captionFilename = pathinfo($imageNameToDelete, PATHINFO_FILENAME) . ".txt";
                        $captionFilePathToDelete = $wallOfLoveDir . $captionFilename;

                        if (file_exists($captionFilePathToDelete)) {
                            if (!unlink($captionFilePathToDelete)) {
                                // Optional: Log this error, or append a warning to the success message
                                error_log("Failed to delete caption file: " . $captionFilePathToDelete . " for image " . $imageNameToDelete);
                                // $success .= " (Warning: corresponding caption file could not be deleted.)";
                            }
                        }
                    } else {
                        $error = "Could not delete the image \"" . htmlspecialchars($imageNameToDelete) . "\". Deletion failed.";
                    }
                } else {
                     $error = "The image file \"" . htmlspecialchars($imageNameToDelete) . "\" is not writable. Check permissions.";
                }
            } else {
                $error = "Image \"" . htmlspecialchars($imageNameToDelete) . "\" not found or already deleted.";
            }
        } else {
            // Log this attempt, as it might be malicious
            error_log("Attempt to delete file outside designated directory or invalid path: User provided=" . $_POST['delete_wall_of_love_image_name'] . ", Resolved=" . $filePathToDelete);
            $error = "Invalid file path or permission denied for deletion. File may not exist or path is incorrect.";
        }
    } elseif (isset($_POST['add_product'])) { // Note: Changed to elseif
        // Handle file upload
        $targetDir = "uploads/";
        $targetFile = $targetDir . basename($_FILES["image"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false) {
            $error = "File is not an image.";
            $uploadOk = 0;
        }

        // Check file size (5MB max)
        if ($_FILES["image"]["size"] > 9000000) {
            $error = "Sorry, your file is too large.";
            $uploadOk = 0;
        }

        // Allow certain file formats
        if (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                // Insert product
                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, image_path, category_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['description'],
                    $_POST['price'],
                    $targetFile,
                    $_POST['category_id']
                ]);

                $productId = $pdo->lastInsertId();

                // Assign selected options to product and set their value states
                if (isset($_POST['options'])) {
                    foreach ($_POST['options'] as $optionId) {
                        // Assign option to product
                        $stmtAssign = $pdo->prepare("INSERT INTO product_option_assignments (product_id, option_id) VALUES (?, ?)");
                        $stmtAssign->execute([$productId, $optionId]);

                        // Fetch all global values for this option to ensure we process all of them
                        $stmtValues = $pdo->prepare("SELECT id FROM option_values WHERE option_id = ?");
                        $stmtValues->execute([$optionId]);
                        $allOptionValues = $stmtValues->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($allOptionValues as $value) {
                            $valueId = $value['id'];
                            // If an option is selected, its values are enabled by default unless specifically disabled.
                            // Checkbox name: option_value_enabled[option_id][value_id]
                            $isEnabled = isset($_POST['option_value_enabled'][$optionId][$valueId]);

                            $stmtSettings = $pdo->prepare("INSERT INTO product_option_value_settings (product_id, option_value_id, is_enabled) VALUES (?, ?, ?)");
                            $stmtSettings->execute([$productId, $valueId, $isEnabled ? 1 : 0]);
                        }
                    }
                } else {
                    // If no options are selected for the product, ensure no settings are lingering (though for a new product, this is less critical)
                    // This part is more relevant for product editing.
                }

                $success = "Product added successfully!";
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    } elseif (isset($_POST['delete_product'])) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $success = "Product deleted successfully!";
    } elseif (isset($_POST['add_option'])) {
        $stmt = $pdo->prepare("INSERT INTO product_options (option_name, option_type, is_required, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['option_name'],
            $_POST['option_type'],
            isset($_POST['is_required']) ? 1 : 0,
            $_POST['display_order']
        ]);

        $optionId = $pdo->lastInsertId();

        // Add option values
        if (isset($_POST['option_values'])) {
            foreach ($_POST['option_values'] as $value) {
                if (!empty(trim($value))) {
                    $stmt = $pdo->prepare("INSERT INTO option_values (option_id, value) VALUES (?, ?)");
                    $stmt->execute([$optionId, trim($value)]);
                }
            }
        }

        $success = "Option added successfully!";
    } elseif (isset($_POST['update_product'])) {
        $productIdToUpdate = $_POST['product_id'];

        // Handle file upload if a new image is provided
        $imagePath = $_POST['current_image_path']; // Keep current if not updated
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0 && !empty($_FILES["image"]["name"])) {
            $targetDir = "uploads/";
            $targetFile = $targetDir . basename($_FILES["image"]["name"]);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check === false) {
                $error = "File is not an image.";
                $uploadOk = 0;
            }
            if ($_FILES["image"]["size"] > 5000000) { // 5MB
                $error = "Sorry, your file is too large.";
                $uploadOk = 0;
            }
            if (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
                $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $uploadOk = 0;
            }

            if ($uploadOk == 1) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                    $imagePath = $targetFile;
                    // Optionally, delete the old image if it's different and no other product uses it.
                } else {
                    $error = "Sorry, there was an error uploading your new file.";
                    $uploadOk = 0; // Prevent further processing if image upload failed
                }
            }
        } else {
            // No new file uploaded or file upload error not critical (e.g. no file given)
            // $imagePath remains the current_image_path or will be updated if it was empty
             $stmt = $pdo->prepare("SELECT image_path FROM products WHERE id = ?");
             $stmt->execute([$productIdToUpdate]);
             $imagePath = $stmt->fetchColumn();
        }


        if (!isset($error) || $uploadOk == 1) { // Proceed if no critical error
            // Update product details
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, image_path = ?, category_id = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $imagePath,
                $_POST['category_id'],
                $productIdToUpdate
            ]);

            // Clear existing option assignments and value settings for this product
            $stmtDelAssignments = $pdo->prepare("DELETE FROM product_option_assignments WHERE product_id = ?");
            $stmtDelAssignments->execute([$productIdToUpdate]);

            $stmtDelSettings = $pdo->prepare("DELETE FROM product_option_value_settings WHERE product_id = ?");
            $stmtDelSettings->execute([$productIdToUpdate]);

            // Re-assign selected options and set their value states
            if (isset($_POST['options'])) {
                foreach ($_POST['options'] as $optionId) {
                    // Assign option to product
                    $stmtAssign = $pdo->prepare("INSERT INTO product_option_assignments (product_id, option_id) VALUES (?, ?)");
                    $stmtAssign->execute([$productIdToUpdate, $optionId]);

                    // Fetch all global values for this option
                    $stmtValues = $pdo->prepare("SELECT id FROM option_values WHERE option_id = ?");
                    $stmtValues->execute([$optionId]);
                    $allOptionValues = $stmtValues->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($allOptionValues as $value) {
                        $valueId = $value['id'];
                        $isEnabled = isset($_POST['option_value_enabled'][$optionId][$valueId]);

                        $stmtSettings = $pdo->prepare("INSERT INTO product_option_value_settings (product_id, option_value_id, is_enabled) VALUES (?, ?, ?)");
                        $stmtSettings->execute([$productIdToUpdate, $valueId, $isEnabled ? 1 : 0]);
                    }
                }
            }
            $success = "Product updated successfully!";
            // Redirect to prevent form resubmission and show updated list or product
            // header("Location: admin.php?product_updated_id=" . $productIdToUpdate . "&success=1");
            // For now, just setting success message is fine.
        }
    }
}

// Get all products
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id")->fetchAll(PDO::FETCH_ASSOC);

// Get all categories
$categories = $pdo->query("SELECT * FROM categories WHERE parent_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

// Get all product options and their values
$productOptionsStmt = $pdo->query("SELECT * FROM product_options ORDER BY display_order");
$productOptions = $productOptionsStmt->fetchAll(PDO::FETCH_ASSOC);

$optionsWithValues = [];
foreach ($productOptions as $option) {
    $valuesStmt = $pdo->prepare("SELECT * FROM option_values WHERE option_id = ? ORDER BY display_order");
    $valuesStmt->execute([$option['id']]);
    $values = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);
    $option['values'] = $values;
    $optionsWithValues[] = $option;
}
$productOptions = $optionsWithValues; // Replace original $productOptions with the enriched one
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Happilyyours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .product-image {
            max-width: 100px;
            max-height: 100px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar p-0">
                <div class="p-3 text-white">
                    <h4>Happilyyours Admin</h4>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#products" data-bs-toggle="tab">
                            <i class="fas fa-box me-2"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#add-product" data-bs-toggle="tab">
                            <i class="fas fa-plus-circle me-2"></i>Add Product
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#product-options" data-bs-toggle="tab">
                            <i class="fas fa-cog me-2"></i>Product Options
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#wall-of-love" data-bs-toggle="tab">
                            <i class="fas fa-heart me-2"></i>Wall of Love
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-md-10 p-4">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="tab-content">
                    <div class="tab-pane active" id="products">
                        <h2>Products</h2>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?= $product['id'] ?></td>
                                            <td><img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image"></td>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= htmlspecialchars($product['category_name']) ?></td>
                                            <td><?= htmlspecialchars($product['price']) ?></td>
                                            <td>
                                                <a href="admin.php?action=edit_product&product_id=<?= $product['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-sm btn-danger">
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

                    <?php
                    $editMode = false;
                    $productToEdit = null;
                    $productAssignedOptions = [];
                    $productOptionValueSettings = [];

                    if (isset($_GET['action']) && $_GET['action'] === 'edit_product' && isset($_GET['product_id'])) {
                        $editMode = true;
                        $productIdToEdit = $_GET['product_id'];

                        // Fetch product details
                        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                        $stmt->execute([$productIdToEdit]);
                        $productToEdit = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($productToEdit) {
                            // Fetch assigned options
                            $stmt = $pdo->prepare("SELECT option_id FROM product_option_assignments WHERE product_id = ?");
                            $stmt->execute([$productIdToEdit]);
                            $assignedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            $productAssignedOptions = $assignedOptions;

                            // Fetch option value settings
                            $stmt = $pdo->prepare("SELECT option_value_id, is_enabled FROM product_option_value_settings WHERE product_id = ?");
                            $stmt->execute([$productIdToEdit]);
                            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($settings as $setting) {
                                $productOptionValueSettings[$setting['option_value_id']] = $setting['is_enabled'];
                            }
                        } else {
                            $editMode = false; // Product not found
                            $error = "Product not found for editing.";
                        }
                    }
                    ?>

                    <div class="tab-pane <?= $editMode ? 'active' : '' ?>" id="add-product">
                        <h2><?= $editMode ? 'Edit Product' : 'Add New Product' ?></h2>
                        <?php if ($editMode && !$productToEdit): ?>
                            <div class="alert alert-danger">Product not found. <a href="admin.php">Return to product list.</a></div>
                        <?php else: ?>
                        <form method="post" enctype="multipart/form-data" action="admin.php<?= $editMode ? '?action=edit_product&product_id='.$productIdToEdit : '' ?>">
                            <?php if ($editMode): ?>
                                <input type="hidden" name="product_id" value="<?= $productIdToEdit ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= $editMode ? htmlspecialchars($productToEdit['name']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?= $editMode ? htmlspecialchars($productToEdit['description']) : '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Price</label>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= $editMode ? htmlspecialchars($productToEdit['price']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= ($editMode && $productToEdit['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Product Image <?= $editMode ? '(leave empty to keep current image)' : '' ?></label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" <?= $editMode ? '' : 'required' ?>>
                                <?php if ($editMode && $productToEdit['image_path']): ?>
                                    <input type="hidden" name="current_image_path" value="<?= htmlspecialchars($productToEdit['image_path']) ?>">
                                    <img src="<?= htmlspecialchars($productToEdit['image_path']) ?>" alt="Current Image" class="mt-2" style="max-width: 100px;">
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Product Options</label>
                                <?php foreach ($productOptions as $option): 
                                    $optionIsAssigned = $editMode && in_array($option['id'], $productAssignedOptions);
                                ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="options[]" id="option_<?= $option['id'] ?>" value="<?= $option['id'] ?>" 
                                               <?= $optionIsAssigned ? 'checked' : '' ?> 
                                               onchange="toggleOptionValues(this, 'option_values_<?= $option['id'] ?>')">
                                        <label class="form-check-label" for="option_<?= $option['id'] ?>">
                                            <strong><?= htmlspecialchars($option['option_name']) ?></strong> (<?= $option['option_type'] ?>)
                                        </label>
                                    </div>
                                    <div id="option_values_<?= $option['id'] ?>" class="ms-4 mb-3" style="<?= $optionIsAssigned ? 'display: block;' : 'display: none;' ?>">
                                        <?php if (!empty($option['values'])): ?>
                                            <small class="form-text text-muted">Enable/disable values for this option:</small>
                                            <?php foreach ($option['values'] as $value): 
                                                // Default to enabled if no specific setting exists for an assigned option, or if adding new
                                                $valueIsEnabled = true; // Default for new products or unassigned options if they were to be shown
                                                if ($editMode && $optionIsAssigned) {
                                                    // If editing and option is assigned, check saved setting
                                                    // If a setting exists, use it. Otherwise, default to true (enabled).
                                                    $valueIsEnabled = isset($productOptionValueSettings[$value['id']]) ? (bool)$productOptionValueSettings[$value['id']] : true;
                                                }
                                            ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="option_value_enabled[<?= $option['id'] ?>][<?= $value['id'] ?>]" id="option_value_<?= $option['id'] ?>_<?= $value['id'] ?>" value="1" <?= $valueIsEnabled ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="option_value_<?= $option['id'] ?>_<?= $value['id'] ?>">
                                                        <?= htmlspecialchars($value['value']) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small class="form-text text-muted">No values defined for this option. Add them in the 'Product Options' section.</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="<?= $editMode ? 'update_product' : 'add_product' ?>" class="btn btn-primary">
                                <?= $editMode ? 'Update Product' : 'Add Product' ?>
                            </button>
                            <?php if ($editMode): ?>
                                <a href="admin.php" class="btn btn-secondary">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?> <!-- End of product found check -->
                    </div>

                    <script>
                        // Ensure this script is defined once, preferably at the end of the body or in a separate JS file.
                        // Moved the script to be defined only once after all potential form instantiations.
                        function toggleOptionValues(optionCheckbox, valuesDivId) {
                            const valuesDiv = document.getElementById(valuesDivId);
                            const allValueCheckboxes = valuesDiv.querySelectorAll('input[type="checkbox"]');
                            if (optionCheckbox.checked) {
                                valuesDiv.style.display = 'block';
                                // Optional: When a main option is checked, ensure all its value checkboxes are also checked by default
                                // This might be desired if checking the parent option implies all children are initially selected.
                                // For edit mode, this might re-check previously unchecked values if not handled carefully.
                                // The current PHP logic correctly pre-checks based on saved state, so this JS part might not be needed
                                // or should be conditional. For now, just showing/hiding is fine.
                            } else {
                                valuesDiv.style.display = 'none';
                                // Optional: When a main option is unchecked, uncheck all its value checkboxes
                                // allValueCheckboxes.forEach(cb => cb.checked = false);
                            }
                        }

                        // Initialize visibility for edit mode on page load
                        document.addEventListener('DOMContentLoaded', function() {
                            <?php if ($editMode && $productToEdit): ?>
                                <?php foreach ($productOptions as $option): 
                                    $optionIsAssigned = in_array($option['id'], $productAssignedOptions);
                                    if ($optionIsAssigned): ?>
                                        // Manually trigger onchange for initially checked options to ensure values are displayed
                                        // No, this is not needed because style is set inline now.
                                        // const mainOptionCheckbox = document.getElementById('option_<?= $option['id'] ?>');
                                        // if (mainOptionCheckbox && mainOptionCheckbox.checked) {
                                        //     toggleOptionValues(mainOptionCheckbox, 'option_values_<?= $option['id'] ?>');
                                        // }
                                    <?php endif; 
                                endforeach; ?>
                            <?php endif; ?>
                        });
                    </script>

                    <div class="tab-pane" id="product-options">
                        <h2>Product Options</h2>
                        <form method="post">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="option_name" class="form-label">Option Name</label>
                                    <input type="text" class="form-control" id="option_name" name="option_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="option_type" class="form-label">Option Type</label>
                                    <select class="form-select" id="option_type" name="option_type" required>
                                        <option value="dropdown">Dropdown</option>
                                        <option value="radio">Radio Button</option>
                                        <option value="checkbox">Checkbox</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="display_order" class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_required" name="is_required" checked>
                                        <label class="form-check-label" for="is_required">Required</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Option Values (one per line, leave empty for text input)</label>
                                <textarea class="form-control" name="option_values" rows="5" placeholder="Enter one value per line"></textarea>
                            </div>
                            <button type="submit" name="add_option" class="btn btn-primary">Add Option</button>
                        </form>

                        <h3 class="mt-5">Existing Options</h3>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th>Order</th>
                                        <th>Values</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productOptions as $option): 
                                        $values = $pdo->prepare("SELECT value FROM option_values WHERE option_id = ?");
                                        $values->execute([$option['id']]);
                                        $optionValues = $values->fetchAll(PDO::FETCH_COLUMN);
                                    ?>
                                        <tr>
                                            <td><?= $option['id'] ?></td>
                                            <td><?= htmlspecialchars($option['option_name']) ?></td>
                                            <td><?= ucfirst($option['option_type']) ?></td>
                                            <td><?= $option['is_required'] ? 'Yes' : 'No' ?></td>
                                            <td><?= $option['display_order'] ?></td>
                                            <td><?= implode(', ', array_map('htmlspecialchars', $optionValues)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="wall-of-love">
                        <h2>Wall of Love Management</h2>
                        <form method="post" enctype="multipart/form-data" action="admin.php">
                            <div class="mb-3">
                                <label for="wall_of_love_image" class="form-label">Upload Image</label>
                                <input type="file" name="wall_of_love_image" id="wall_of_love_image" class="form-control" required>
                                <div class="form-text">Upload images for the Wall of Love. All image types are currently permitted.</div>
                            </div>
                            <div class="mb-3">
                                <label for="wall_of_love_caption" class="form-label">Image Name/Caption (optional)</label>
                                <input type="text" name="wall_of_love_caption" id="wall_of_love_caption" class="form-control" maxlength="255">
                                <div class="form-text">Enter a short name or caption for the image. This will be displayed on the image in the gallery. Max 255 characters.</div>
                            </div>
                            <button type="submit" name="upload_wall_of_love_image" class="btn btn-primary">Upload Image</button>
                        </form>
                        <hr>
                        <h3 class="mt-4">Uploaded Images</h3>
                        <?php
                        $wallOfLoveDir = "uploads/wall_of_love/";
                        $wolImages = [];
                        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                        if (is_dir($wallOfLoveDir)) {
                            if ($dh = opendir($wallOfLoveDir)) {
                                while (($file = readdir($dh)) !== false) {
                                    if ($file !== '.' && $file !== '..') {
                                        $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        if (in_array($fileExtension, $allowedImageExtensions)) {
                                            $wolImages[] = $file;
                                        }
                                    }
                                }
                                closedir($dh);
                            } else {
                                echo '<p class="text-danger">Error: Could not open the Wall of Love directory.</p>';
                            }
                        } else {
                            echo '<p class="text-danger">Error: Wall of Love directory does not exist.</p>';
                        }

                        if (!empty($wolImages)):
                        ?>
                            <div class="row">
                                <?php foreach ($wolImages as $imageFile): ?>
                                    <div class="col-md-3 col-sm-4 col-6 mb-3 text-center">
                                        <img src="<?= htmlspecialchars($wallOfLoveDir . $imageFile) ?>" 
                                             alt="<?= htmlspecialchars($imageFile) ?>" 
                                             style="width: 100%; max-width: 150px; height: auto; max-height:150px; object-fit: cover; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;">
                                        <p style="font-size: 0.9em; word-break: break-all;"><strong>Filename:</strong> <?= htmlspecialchars($imageFile) ?></p>
                                        <?php
                                        $captionFilename = pathinfo($imageFile, PATHINFO_FILENAME) . ".txt";
                                        $captionFilePath = $wallOfLoveDir . $captionFilename;
                                        $captionText = "";
                                        if (file_exists($captionFilePath)) {
                                            $captionText = file_get_contents($captionFilePath);
                                            // Caption was saved with htmlspecialchars, so it's already "safe" for direct HTML output.
                                            // nl2br is good for respecting newlines entered in the caption.
                                        }
                                        ?>
                                        <?php if (!empty(trim($captionText))): // Use trim to check if caption is not just whitespace ?>
                                            <p style="font-size: 0.8em; font-style: italic; color: #555; word-break: break-word; white-space: pre-wrap; text-align: left; margin-top: 5px; padding: 5px; background-color: #f8f9fa; border-radius: 3px;">
                                                <strong>Caption:</strong><br><?= nl2br($captionText) ?>
                                            </p>
                                        <?php else: ?>
                                            <p style="font-size: 0.8em; color: #777; margin-top:5px;"><em>No caption.</em></p>
                                        <?php endif; ?>
                                        <form method="post" action="admin.php#wall-of-love" style="display: inline-block; margin-top: 5px;" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                            <input type="hidden" name="delete_wall_of_love_image_name" value="<?= htmlspecialchars($imageFile) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mt-3">No images found in the Wall of Love gallery yet.</p>
                        <?php endif; ?>
                        <!-- Image listing and deletion will go here in a future step -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>