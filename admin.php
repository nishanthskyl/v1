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
    } elseif (isset($_POST['add_product'])) {
        // Insert product basic details (without image_path)
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['category_id']
        ]);
        $productId = $pdo->lastInsertId();
        $success = "Product details added successfully. ";

        // Handle multiple image uploads
        if (isset($_FILES['images'])) {
            $targetDir = "uploads/";
            $imagesUploadedCount = 0;
            $isFirstImage = true; // To set the first uploaded image as primary

            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $originalFileName = basename($_FILES["images"]["name"][$i]);
                    // Sanitize filename
                    $safeFileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalFileName);
                    if (empty($safeFileName) || $safeFileName === '.' || $safeFileName === '..') {
                        $error .= "Invalid image filename after sanitization: " . htmlspecialchars($originalFileName) . ". ";
                        continue;
                    }

                    $targetFile = $targetDir . $safeFileName;
                    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

                    // Basic check for common web image types
                    $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];
                    if (!in_array($imageFileType, $allowedExtensions)) {
                        $error .= "File '" . htmlspecialchars($safeFileName) . "' is not an allowed image type (JPG, JPEG, PNG, GIF, WEBP). ";
                        continue;
                    }

                    // Prevent overwriting by appending a number if necessary
                    $counter = 0;
                    $tempTargetFile = $targetFile;
                    while (file_exists($tempTargetFile)) {
                        $counter++;
                        $fileNameWithoutExt = pathinfo($safeFileName, PATHINFO_FILENAME);
                        $tempTargetFile = $targetDir . $fileNameWithoutExt . "_" . $counter . "." . $imageFileType;
                    }
                    $targetFile = $tempTargetFile;
                    $finalFileNameForDb = basename($targetFile);


                    if (move_uploaded_file($_FILES["images"]["tmp_name"][$i], $targetFile)) {
                        $stmtImage = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $stmtImage->execute([$productId, $finalFileNameForDb, $isFirstImage ? 1 : 0, $imagesUploadedCount]);

                        $imagesUploadedCount++;
                        $isFirstImage = false; // Only the very first successful upload is primary
                    } else {
                        $error .= "Error uploading file '" . htmlspecialchars($safeFileName) . "'. ";
                    }
                } elseif ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $error .= "Error with file '" . htmlspecialchars($_FILES["images"]["name"][$i]) . "': error code " . $_FILES['images']['error'][$i] . ". ";
                }
            }
            if ($imagesUploadedCount > 0) {
                $success .= "$imagesUploadedCount image(s) uploaded successfully.";
            } elseif (empty($error)) { // No files were selected, but no error occurred.
                 // $success .= "No images were uploaded for the product."; // Or just keep silent
            }
        } else {
             $success .= "No images provided or error in file data. ";
        }

        // Assign selected options to product and set their value states (This part remains largely the same)
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

                // $success variable is now built incrementally.
                // Ensure $error is checked before claiming full success.
                if (!empty($error)) {
                    // If there were errors, make sure the overall message reflects that.
                    // $success might still indicate partial success (product details added).
                    // The messages $error and $success will be displayed.
                } else if ($imagesUploadedCount == 0 && count($_FILES['images']['name']) > 0 && $_FILES['images']['error'][0] != UPLOAD_ERR_NO_FILE){
                    // This case means files were selected, but none uploaded successfully.
                    $error = "Product details added, but no images could be uploaded due to errors mentioned above.";
                } else if ($imagesUploadedCount == 0 && (count($_FILES['images']['name']) == 0 || $_FILES['images']['error'][0] == UPLOAD_ERR_NO_FILE) ){
                     $success .= "No images were selected for upload.";
                }
            } // This closing brace was for the old $uploadOk == 1, which is removed.
        // The new structure handles errors and success messages within the loop or after.
        // The final $success and $error messages will be displayed.
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

        // Add option values with price modifiers
        if (isset($_POST['option_value_text']) && is_array($_POST['option_value_text'])) {
            $valueTexts = $_POST['option_value_text'];
            $valueModifiers = $_POST['option_value_modifier'] ?? []; // Default to empty array if not set

            for ($i = 0; $i < count($valueTexts); $i++) {
                $text = trim($valueTexts[$i]);
                if (!empty($text)) {
                    // Ensure modifier is a valid decimal, default to 0.00 if not
                    $modifier = isset($valueModifiers[$i]) && is_numeric($valueModifiers[$i])
                                ? floatval($valueModifiers[$i])
                                : 0.00;

                    // Format modifier to ensure two decimal places for storage
                    $formattedModifier = number_format($modifier, 2, '.', '');

                    $stmtValue = $pdo->prepare("INSERT INTO option_values (option_id, value, price_modifier, display_order) VALUES (?, ?, ?, ?)");
                    // Assuming display_order for values is just their sequence for now
                    $stmtValue->execute([$optionId, $text, $formattedModifier, $i]);
                }
            }
        }
        $success = "Option and its values added successfully!";
    } elseif (isset($_POST['update_product'])) {
        $productIdToUpdate = $_POST['product_id'];
        $uploadOk = 1; // Assume okay initially for non-image parts

        // 1. Handle Image Deletions
        if (!empty($_POST['delete_images'])) {
            $targetDir = "uploads/";
            foreach ($_POST['delete_images'] as $imageIdToDelete) {
                $imageFilenameToDelete = $_POST['image_filenames'][$imageIdToDelete] ?? null;
                if ($imageFilenameToDelete) {
                    // Delete from product_images table
                    $stmtDelete = $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
                    $stmtDelete->execute([$imageIdToDelete, $productIdToUpdate]);

                    // Delete file from server
                    $filePath = $targetDir . $imageFilenameToDelete;
                    if (file_exists($filePath) && is_writable($filePath)) {
                        unlink($filePath);
                        $success .= "Image " . htmlspecialchars($imageFilenameToDelete) . " deleted. ";
                    } else {
                        $error .= "Could not delete file " . htmlspecialchars($imageFilenameToDelete) . " from server (not found or permission issue). ";
                    }
                }
            }
        }

        // 2. Handle Primary Image Change
        if (isset($_POST['primary_image_id'])) {
            $newPrimaryImageId = $_POST['primary_image_id'];
            // Set all images for this product to is_primary = 0
            $stmtClearPrimary = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $stmtClearPrimary->execute([$productIdToUpdate]);
            // Set the selected image to is_primary = 1
            $stmtSetPrimary = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
            $stmtSetPrimary->execute([$newPrimaryImageId, $productIdToUpdate]);
            $success .= "Primary image updated. ";
        }

        // 3. Handle New Image Uploads
        if (isset($_FILES['new_images'])) {
            $targetDir = "uploads/";
            $newImagesUploadedCount = 0;

            // Determine current max display_order for this product
            $stmtMaxOrder = $pdo->prepare("SELECT MAX(display_order) FROM product_images WHERE product_id = ?");
            $stmtMaxOrder->execute([$productIdToUpdate]);
            $currentMaxOrder = $stmtMaxOrder->fetchColumn();
            $nextDisplayOrder = ($currentMaxOrder === null) ? 0 : $currentMaxOrder + 1;

            for ($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                    $originalFileName = basename($_FILES["new_images"]["name"][$i]);
                    $safeFileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $originalFileName);
                     if (empty($safeFileName) || $safeFileName === '.' || $safeFileName === '..') {
                        $error .= "Invalid new image filename: " . htmlspecialchars($originalFileName) . ". ";
                        continue;
                    }

                    $targetFile = $targetDir . $safeFileName;
                    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
                    $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];

                    if (!in_array($imageFileType, $allowedExtensions)) {
                        $error .= "New file '" . htmlspecialchars($safeFileName) . "' is not an allowed image type. ";
                        continue;
                    }

                    $counter = 0;
                    $tempTargetFile = $targetFile;
                    while (file_exists($tempTargetFile)) {
                        $counter++;
                        $fileNameWithoutExt = pathinfo($safeFileName, PATHINFO_FILENAME);
                        $tempTargetFile = $targetDir . $fileNameWithoutExt . "_" . $counter . "." . $imageFileType;
                    }
                    $targetFile = $tempTargetFile;
                    $finalFileNameForDb = basename($targetFile);

                    if (move_uploaded_file($_FILES["new_images"]["tmp_name"][$i], $targetFile)) {
                        // Check if there are NO images currently for this product. If so, make this new one primary.
                        $stmtCheckExistingImages = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                        $stmtCheckExistingImages->execute([$productIdToUpdate]);
                        $hasExistingImages = $stmtCheckExistingImages->fetchColumn() > 0;

                        // If after deletions, no primary is set, or if no images existed, make the first new one primary.
                        $isNewPrimary = false;
                        if (!$hasExistingImages && $newImagesUploadedCount == 0) { // First new image and no prior images
                             $isNewPrimary = true;
                        } else {
                            // Check if a primary image still exists after potential deletions
                            $stmtCheckPrimary = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1");
                            $stmtCheckPrimary->execute([$productIdToUpdate]);
                            if ($stmtCheckPrimary->fetchColumn() == 0 && $newImagesUploadedCount == 0) {
                                $isNewPrimary = true; // No primary exists, make this new one primary
                            }
                        }

                        $stmtImage = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
                        $stmtImage->execute([$productIdToUpdate, $finalFileNameForDb, $isNewPrimary ? 1 : 0, $nextDisplayOrder]);

                        $newImagesUploadedCount++;
                        $nextDisplayOrder++;
                    } else {
                        $error .= "Error uploading new file '" . htmlspecialchars($safeFileName) . "'. ";
                        $uploadOk = 0; // Mark that an error occurred
                    }
                } elseif ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                     $error .= "Error with new file '" . htmlspecialchars($_FILES["new_images"]["name"][$i]) . "': error code " . $_FILES['new_images']['error'][$i] . ". ";
                     $uploadOk = 0;
                }
            }
            if ($newImagesUploadedCount > 0) {
                $success .= "$newImagesUploadedCount new image(s) uploaded. ";
            }
        }

        // Update product basic details (name, description, price, category_id)
        // This part is now independent of the old single image path logic.
        if ($uploadOk) { // Proceed if new image uploads (if any) were okay or no new images attempted
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                // $imagePath, // This line is removed, no more single image_path in products table
                $_POST['category_id'],
                $productIdToUpdate
            ]);
            $success .= "Product details updated. ";

            // Clear existing option assignments and value settings for this product (same as before)
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
$productsStmt = $pdo->query("
    SELECT p.*, c.name as category_name, pi.image_path as primary_image_path
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    ORDER BY p.id DESC
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

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
                                            <td>
                                                <img src="<?= htmlspecialchars($product['primary_image_path'] ? 'uploads/' . $product['primary_image_path'] : 'uploads/placeholder.png') ?>"
                                                     alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                                            </td>
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
                            // Fetch existing images for this product
                            $stmtProductImages = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order ASC, is_primary DESC");
                            $stmtProductImages->execute([$productIdToEdit]);
                            $productImagesToEdit = $stmtProductImages->fetchAll(PDO::FETCH_ASSOC);

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

                            <?php if (!$editMode): // Only show this for adding new product ?>
                            <div class="mb-3">
                                <label for="images" class="form-label">Product Images</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                                <div class="form-text">You can select multiple images. The first selected image will be set as primary.</div>
                            </div>
                            <?php endif; ?>

                            <?php if ($editMode && $productToEdit): ?>
                            <div class="mb-3">
                                <label class="form-label">Manage Existing Images</label>
                                <?php if (!empty($productImagesToEdit)): ?>
                                    <div class="row">
                                        <?php foreach ($productImagesToEdit as $img): ?>
                                            <div class="col-md-3 text-center mb-4 p-2 border rounded me-2">
                                                <img src="uploads/<?= htmlspecialchars($img['image_path']) ?>" class="img-thumbnail mb-2" style="max-width: 120px; max-height: 120px; object-fit: cover;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="primary_image_id" id="primary_<?= $img['id'] ?>" value="<?= $img['id'] ?>" <?= $img['is_primary'] ? 'checked' : '' ?>>
                                                    <label class="form-check-label" style="font-size: 0.9em;" for="primary_<?= $img['id'] ?>">Set Primary</label>
                                                </div>
                                                <div class="form-check mt-1">
                                                    <input class="form-check-input" type="checkbox" name="delete_images[]" id="delete_<?= $img['id'] ?>" value="<?= $img['id'] ?>">
                                                    <label class="form-check-label" style="font-size: 0.9em; color: #dc3545;" for="delete_<?= $img['id'] ?>">Delete</label>
                                                </div>
                                                <input type="hidden" name="image_filenames[<?= $img['id'] ?>]" value="<?= htmlspecialchars($img['image_path']) ?>">
                                                <?php /* Display order input - optional for now
                                                <div class="mt-1">
                                                    <input type="number" name="display_order[<?= $img['id'] ?>]" value="<?= htmlspecialchars($img['display_order']) ?>" class="form-control form-control-sm" title="Order" style="width: 70px; margin: auto;">
                                                </div>
                                                */ ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p>No images currently associated with this product.</p>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="new_images" class="form-label">Upload New Images</label>
                                <input type="file" class="form-control" id="new_images" name="new_images[]" multiple accept="image/*">
                                <div class="form-text">Add more images to this product.</div>
                            </div>
                            <?php endif; ?>

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
                                                        <?php
                                                        if (isset($value['price_modifier']) && $value['price_modifier'] != 0) {
                                                            $modifier = floatval($value['price_modifier']);
                                                            echo " (" . ($modifier > 0 ? '+' : '') . htmlspecialchars(number_format($modifier, 2)) . ")";
                                                        }
                                                        ?>
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
                                        <!-- <option value="text">Text Input</option> -->
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
                                <label class="form-label">Option Values</label>
                                <div id="option-values-container">
                                    <div class="row option-value-row mb-2">
                                        <div class="col-md-5">
                                            <input type="text" name="option_value_text[]" class="form-control" placeholder="Value Text">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="number" step="0.01" name="option_value_modifier[]" class="form-control" placeholder="Price Modifier (e.g., 5.00 or -2.50)">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger btn-sm remove-option-value-row" style="display:none;">Remove</button>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-option-value-row" class="btn btn-secondary btn-sm mt-2">Add Another Value</button>
                            </div>
                            <button type="submit" name="add_option" class="btn btn-primary">Add Option</button>
                        </form>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const container = document.getElementById('option-values-container');
                            const addRowButton = document.getElementById('add-option-value-row');

                            function updateRemoveButtons() {
                                const rows = container.querySelectorAll('.option-value-row');
                                rows.forEach((row, index) => {
                                    const removeButton = row.querySelector('.remove-option-value-row');
                                    if(removeButton) { // Make sure button exists
                                       removeButton.style.display = rows.length > 1 ? 'inline-block' : 'none';
                                    }
                                });
                            }

                            addRowButton.addEventListener('click', function() {
                                const newRow = container.querySelector('.option-value-row').cloneNode(true);
                                newRow.querySelectorAll('input').forEach(input => input.value = '');
                                container.appendChild(newRow);
                                updateRemoveButtons();
                            });

                            container.addEventListener('click', function(e) {
                                if (e.target.classList.contains('remove-option-value-row')) {
                                    if (container.querySelectorAll('.option-value-row').length > 1) {
                                        e.target.closest('.option-value-row').remove();
                                        updateRemoveButtons();
                                    }
                                }
                            });
                            updateRemoveButtons(); // Initial check
                        });
                        </script>

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
                                        // Fetch values with their price modifiers
                                        $valuesStmt = $pdo->prepare("SELECT value, price_modifier FROM option_values WHERE option_id = ? ORDER BY display_order");
                                        $valuesStmt->execute([$option['id']]);
                                        $optionValuesWithModifiers = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);

                                        $valueStrings = [];
                                        foreach ($optionValuesWithModifiers as $ov) {
                                            $valStr = htmlspecialchars($ov['value']);
                                            if (isset($ov['price_modifier']) && $ov['price_modifier'] != 0) {
                                                $modifier = floatval($ov['price_modifier']);
                                                $valStr .= " (" . ($modifier > 0 ? '+' : '') . htmlspecialchars(number_format($modifier, 2)) . ")";
                                            }
                                            $valueStrings[] = $valStr;
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $option['id'] ?></td>
                                            <td><?= htmlspecialchars($option['option_name']) ?></td>
                                            <td><?= ucfirst($option['option_type']) ?></td>
                                            <td><?= $option['is_required'] ? 'Yes' : 'No' ?></td>
                                            <td><?= $option['display_order'] ?></td>
                                            <td><?= implode(', ', $valueStrings) ?></td>
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