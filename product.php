<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$productId = $_GET['id'];
$product = $pdo->prepare("SELECT p.*, c.name as category_name, parent.name as parent_category 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         LEFT JOIN categories parent ON c.parent_id = parent.id 
                         WHERE p.id = ?");
$product->execute([$productId]);
$product = $product->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: index.php");
    exit();
}

// Get all options assigned to this product
$options = $pdo->prepare("
    SELECT po.* 
    FROM product_options po
    JOIN product_option_assignments poa ON po.id = poa.option_id
    WHERE poa.product_id = ?
    ORDER BY po.display_order
");
$options->execute([$productId]);
$options = $options->fetchAll(PDO::FETCH_ASSOC);

// Get values for each option, considering product-specific settings
foreach ($options as &$option) {
    $stmtValues = $pdo->prepare("
        SELECT ov.*
        FROM option_values ov
        LEFT JOIN product_option_value_settings povs ON ov.id = povs.option_value_id AND povs.product_id = :product_id
        WHERE ov.option_id = :option_id
          AND (povs.is_enabled = TRUE OR povs.is_enabled IS NULL)
        ORDER BY ov.display_order
    ");
    $stmtValues->execute([
        ':product_id' => $productId,
        ':option_id' => $option['id']
    ]);
    $option['values'] = $stmtValues->fetchAll(PDO::FETCH_ASSOC);
}
unset($option); // release the reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Happilyyours</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-brown: #8B4513;
            --light-brown: #D2B48C;
            --cream: #F5F5DC;
            --warm-beige: #F4E9D9;
            --dark-brown: #654321;
            --accent-gold: #DAA520;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--warm-beige) 0%, var(--cream) 100%);
            min-height: 100vh;
            color: var(--dark-brown);
        }

        /* Header Styling */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(139, 69, 19, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-brown);
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-brown);
            text-decoration: none;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-link {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .social-link.youtube {
            background: linear-gradient(45deg, #FF0000, #FF4444);
            color: white;
        }

        .social-link.instagram {
            background: linear-gradient(45deg, #833AB4, #FD1D1D, #FCB045);
            color: white;
        }

        .social-link.whatsapp {
            background: linear-gradient(45deg, #25D366, #128C7E);
            color: white;
        }

        .social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        /* Main Content */
        .main-content {
            padding: 3rem 0;
        }

        .product-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        /* Breadcrumb */
        .custom-breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
        }

        .custom-breadcrumb .breadcrumb-item a {
            color: var(--primary-brown);
            text-decoration: none;
            font-weight: 500;
        }

        .custom-breadcrumb .breadcrumb-item.active {
            color: var(--dark-brown);
            font-weight: 600;
        }

        /* Product Image */
        .product-image {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.02);
        }

        /* Product Info */
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-brown);
            margin-bottom: 0.5rem;
        }

        .product-category {
            color: var(--light-brown);
            font-size: 1.1rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-gold);
            margin-bottom: 1.5rem;
        }

        .product-description {
            line-height: 1.6;
            color: var(--dark-brown);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            white-space: pre-wrap; /* Preserve both spaces and line breaks */
        }
        /* Options Styling */
        .option-section {
            margin-bottom: 2rem;
        }

        .option-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-brown);
            margin-bottom: 1rem;
        }

        .selectable-box-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .selectable-box-label {
            display: inline-block;
            padding: 0.7rem 1.2rem;
            border: 2px solid var(--light-brown);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            background: white;
            color: var(--dark-brown);
            text-align: center;
            user-select: none;
            min-width: 80px;
        }

        .selectable-box-label:hover {
            border-color: var(--primary-brown);
            background: var(--warm-beige);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        input[type="radio"].visually-hidden:checked + .selectable-box-label {
            border-color: var(--primary-brown);
            background: linear-gradient(45deg, var(--primary-brown), var(--accent-gold));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .form-select {
            border: 2px solid var(--light-brown);
            border-radius: 15px;
            padding: 0.7rem 1rem;
            font-weight: 500;
            color: var(--dark-brown);
            background: white;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--primary-brown);
            box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
        }

        /* WhatsApp Button */
        .whatsapp-btn {
            background: linear-gradient(45deg, #25D366, #128C7E);
            border: none;
            color: white;
            padding: 1rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(37, 211, 102, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 2rem auto;
            text-decoration: none;
        }

        .whatsapp-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
            background: linear-gradient(45deg, #128C7E, #25D366);
        }

        .whatsapp-btn i {
            font-size: 1.4rem;
        }

        /* Dynamic dropdown container */
        #dynamicFrameSizeDropdownContainer {
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .brand-name {
                font-size: 1.4rem;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .social-links {
                gap: 10px;
            }
            
            .social-link {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .product-container {
                padding: 1.5rem;
                margin: 1rem;
            }
        }

        /* Footer */
        .footer {
            background: var(--primary-brown);
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        /* Description toggle */
        .description-toggle {
            color: var(--primary-brown);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            margin-top: 0.5rem;
            display: inline-block;
        }

        .description-toggle:hover {
            text-decoration: underline;
            color: var(--accent-gold);
        }

        #productDescriptionArea.description-truncated {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            max-height: 4.5em;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <img src="https://yt3.googleusercontent.com/qD6aNz-NYU0dqau_9IKKvr7uAtZrIjQVTKyI59dX--oL8r5IQj5hjZ2iP_Ss9vN3LbOqdNhYS8Y=s120-c-k-c0x00ffffff-no-rj" alt="Logo" class="logo-img">
                <a href="/" class="brand-name">Happilyyours.Creators</a>
            </div>
            
            <div class="social-links">
                <a href="https://youtube.com/@Happilyyours.Creators" class="social-link youtube" target="_blank" aria-label="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
                <a href="https://www.instagram.com/happilyyours.creators" class="social-link instagram" target="_blank" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://wa.me/919043011295" class="social-link whatsapp" target="_blank" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="product-container">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb" class="custom-breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <?php if (!empty($product['parent_id']) && !empty($product['parent_category'])): ?>
                                    <li class="breadcrumb-item"><a href="index.php?category=<?= $product['parent_id'] ?>"><?= htmlspecialchars($product['parent_category']) ?></a></li>
                                <?php endif; ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['category_name']) ?></li>
                            </ol>
                        </nav>
                        
                        <!-- Product Image -->
                        <img src="<?= htmlspecialchars($product['image_path']) ?>" class="img-fluid product-image" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Product Info -->
                        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                        <p class="product-category"><?= htmlspecialchars($product['parent_category'] ?? '') ?> <?= !empty($product['parent_category']) ? '>' : '' ?> <?= htmlspecialchars($product['category_name']) ?></p>
                        <div class="product-price">₹<?= number_format($product['price'], 2) ?></div>
                        <div id="productDescriptionArea" class="product-description"><?= htmlspecialchars($product['description']) ?></div>

                        <!-- Product Options Form -->
                        <form id="productOptionsForm">
                            <?php
                            $shapeHtml = '';
                            $sizeHtml = '';
                            $lightHtml = '';
                            $frameHtml = '';
                            $colorHtml = '';
                            $otherOptions = [];
                            $optionData = [];

                            // Store all options data, keyed by name for easier lookup
                            foreach ($options as $opt) {
                                $optionData[htmlspecialchars($opt['option_name'])] = $opt;
                            }

                            // Generate HTML for Shape
                            ob_start();
                            $currentOption = $optionData['Shape'] ?? null;
                            if ($currentOption) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name']) . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value']) . "' data-option-name='" . htmlspecialchars($currentOption['option_name']) . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value']) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            $shapeHtml = ob_get_clean();

                            // Generate HTML for Size (Dropdown)
                            ob_start();
                            $sizeOptionData = $optionData['Size'] ?? null;
                            if ($sizeOptionData) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($sizeOptionData['option_name']) . "</h5>";
                                echo "<select class='form-select option-select' name='option_{$sizeOptionData['id']}' data-option-name='" . htmlspecialchars($sizeOptionData['option_name']) . "'>";
                                
                                if (!empty($sizeOptionData['values']) && is_array($sizeOptionData['values'])) {
                                    foreach ($sizeOptionData['values'] as $value_idx => $value_obj) {
                                        $displayValue = htmlspecialchars($value_obj['value']);
                                        echo "<option value='{$displayValue}'>" . $displayValue . "</option>";
                                    }
                                } else {
                                    echo "<option value=''>N/A</option>";
                                }
                                
                                echo "</select>";
                                echo "</div>";
                            }
                            $sizeHtml = ob_get_clean();

                            // Generate HTML for Color
                            ob_start();
                            $currentOption = $optionData['Color'] ?? null;
                            if ($currentOption) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name']) . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value']) . "' data-option-name='" . htmlspecialchars($currentOption['option_name']) . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value']) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            $colorHtml = ob_get_clean();

                            // Generate HTML for Light
                            ob_start();
                            $currentOption = $optionData['Light'] ?? null;
                            if ($currentOption) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name']) . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value']) . "' data-option-name='" . htmlspecialchars($currentOption['option_name']) . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value']) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            $lightHtml = ob_get_clean();

                            // Generate HTML for Frame
                            ob_start();
                            $currentOption = $optionData['Frame'] ?? null;
                            if ($currentOption) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name']) . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value']) . "' data-option-name='" . htmlspecialchars($currentOption['option_name']) . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value']) . "</label>";
                                        echo "</div>";
                                    }
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            $frameHtml = ob_get_clean();

                            // Collect Other Options
                            $definedPairedNames = ['Shape', 'Size', 'Light', 'Frame', 'Color'];
                            foreach ($options as $option) {
                                if (!in_array(htmlspecialchars($option['option_name']), $definedPairedNames)) {
                                    $otherOptions[] = $option;
                                }
                            }

                            // Display options in organized rows
                            if (!empty($shapeHtml) || !empty($sizeHtml)) {
                                echo '<div class="row">';
                                if (!empty($shapeHtml)) echo '<div class="col-md-6">' . $shapeHtml . '</div>';
                                if (!empty($sizeHtml)) echo '<div class="col-md-6">' . $sizeHtml . '</div>';
                                echo '</div>';
                            }

                            if (!empty($colorHtml)) {
                                echo '<div class="row"><div class="col-12">' . $colorHtml . '</div></div>';
                            }

                            if (!empty($lightHtml) || !empty($frameHtml)) {
                                echo '<div class="row">';
                                if (!empty($lightHtml)) echo '<div class="col-md-6">' . $lightHtml . '</div>';
                                if (!empty($frameHtml)) {
                                    echo '<div class="col-md-6">' . $frameHtml;
                                    echo '<div id="dynamicFrameSizeDropdownContainer" style="display: none;"></div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            }
                            ?>

                            <!-- WhatsApp Button -->
                            <div class="text-center">
                                <button type="button" id="newWhatsAppBtn" class="whatsapp-btn">
                                    <i class="fab fa-whatsapp"></i>
                                    Order via WhatsApp
                                </button>
                            </div>

                            <!-- Other Options -->
                            <?php if (!empty($otherOptions)): ?>
                                <div class="row">
                                    <?php foreach ($otherOptions as $idx => $option): ?>
                                        <div class="col-lg-4 col-md-6 mb-3">
                                            <div class="option-section">
                                                <h5 class="option-title"><?= htmlspecialchars($option['option_name']) ?></h5>
                                                <?php if ($option['option_type'] === 'checkbox'): ?>
                                                    <div class="selectable-box-group">
                                                        <?php foreach ($option['values'] as $value): ?>
                                                            <div class="selectable-box-item">
                                                                <input class="form-check-input option-checkbox visually-hidden" type="checkbox" name="option_<?= $option['id'] ?>[]" id="option_<?= $option['id'] ?>_<?= $value['id'] ?>" value="<?= htmlspecialchars($value['value']) ?>" data-option-name="<?= htmlspecialchars($option['option_name']) ?>">
                                                                <label class="selectable-box-label" for="option_<?= $option['id'] ?>_<?= $value['id'] ?>"><?= htmlspecialchars($value['value']) ?></label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="selectable-box-group">
                                                        <?php foreach ($option['values'] as $v_idx => $value_obj): ?>
                                                            <div class="selectable-box-item">
                                                                <input class="form-check-input option-radio visually-hidden" type="radio" name="option_<?= $option['id'] ?>" id="option_<?= $option['id'] ?>_<?= $value_obj['id'] ?>" value="<?= htmlspecialchars($value_obj['value']) ?>" data-option-name="<?= htmlspecialchars($option['option_name']) ?>" <?= ($v_idx === 0 ? 'checked' : '') ?>>
                                                                <label class="selectable-box-label" for="option_<?= $option['id'] ?>_<?= $value_obj['id'] ?>"><?= htmlspecialchars($value_obj['value']) ?></label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Happilyyours.Creators. All rights reserved. | Handcrafted with Love</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productImagePath = '<?= addslashes(htmlspecialchars($product['image_path'])) ?>';
        let productImageUrl = '';
        if (productImagePath) {
            if (productImagePath.startsWith('http://') || productImagePath.startsWith('https://')) {
                productImageUrl = productImagePath;
            } else {
                const cleanPath = productImagePath.startsWith('/') ? productImagePath.substring(1) : productImagePath;
                productImageUrl = window.location.origin + '/' + cleanPath;
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const frameRadioButtons = document.querySelectorAll('input[type="radio"][data-option-name="Frame"]');
            const dynamicDropdownContainer = document.getElementById('dynamicFrameSizeDropdownContainer');

            const woodSizes = [
                { value: "6x6", text: "6x6" }, { value: "8x8", text: "8x8" },
                { value: "10x10", text: "10x10" }, { value: "10x12", text: "10x12" },
                { value: "12x12", text: "12x12" }, { value: "12x14", text: "12x14" },
                { value: "14x16", text: "14x16" }, { value: "Customized size", text: "Customized size" }
            ];
            const mouldSizes = [
                { value: "4", text: "4 inch" }, { value: "5", text: "5 inch" }, 
                { value: "6", text: "6 inch" }, { value: "8", text: "8 inch" },
                { value: "10", text: "10 inch" }, { value: "12", text: "12 inch" }
            ];

            function updateFrameSizeDropdown(selectedValue) {
                if (!dynamicDropdownContainer) return; // Safety check

                dynamicDropdownContainer.innerHTML = ''; // Clear previous content
                dynamicDropdownContainer.style.display = 'none'; // Hide by default

                let dropdownHtml = '';
                let optionsArray = [];
                let selectName = '';
                let selectLabel = '';

                if (selectedValue === 'Wood') {
                    optionsArray = woodSizes;
                    selectName = 'wood_size';
                    selectLabel = 'Wood Size';
                } else if (selectedValue === 'Mould') {
                    optionsArray = mouldSizes;
                    selectName = 'mould_size';
                    selectLabel = 'Mould Size';
                }

                if (selectName && optionsArray.length > 0) {
                    dropdownHtml = `<h5>${selectLabel}</h5><div class="form-group">` +
                                   `<select class="form-select option-select" name="${selectName}" data-option-name="${selectLabel}">`;
                    optionsArray.forEach(opt => {
                        dropdownHtml += `<option value="${opt.value}">${opt.text}</option>`;
                    });
                    dropdownHtml += `</select></div>`;
                    
                    dynamicDropdownContainer.innerHTML = dropdownHtml;
                    dynamicDropdownContainer.style.display = 'block';
                }
            }

            frameRadioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateFrameSizeDropdown(this.value);
                });
            });

            // Trigger update on page load based on the initially checked "Frame" radio
            const initiallyCheckedFrameRadio = document.querySelector('input[type="radio"][data-option-name="Frame"]:checked');
            if (initiallyCheckedFrameRadio) {
                updateFrameSizeDropdown(initiallyCheckedFrameRadio.value);
            }
            
            // Set up WhatsApp button
            const newWhatsAppBtn = document.getElementById('newWhatsAppBtn'); 
            if (newWhatsAppBtn) { 
                newWhatsAppBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productName = "<?= addslashes(htmlspecialchars($product['name'])) ?>";
                    const categoryName = "<?= addslashes(htmlspecialchars($product['category_name'])) ?>";
                    const productPrice = "<?= addslashes(htmlspecialchars(number_format($product['price'], 2))) ?>";

                    let message = `Hi,\nI'm interested in purchasing the following product:\n\n`; 
                    message += `*Product Name:* ${productName}\n`; 
                    message += `*Category:* ${categoryName}\n`;
                    
                    if (typeof productImageUrl !== 'undefined' && productImageUrl) { 
                        message += `*Product Image:* ${productImageUrl}\n`;
                    }
                    message += `\n--- Options Selected ---\n`;

                    const optionsOrder = [
                        { name: 'Shape', type: 'radio' },
                        { name: 'Size', type: 'select' },
                        { name: 'Color', type: 'radio' },
                        { name: 'Light', type: 'radio' },
                        { name: 'Frame', type: 'radio' }
                        // Dynamic Wood/Mould size will be handled separately
                    ];

                    const processedOptionNames = []; // Keep track of options already added

                    optionsOrder.forEach(optConfig => {
                        let element;
                        let value = '';
                        let optionDisplayName = optConfig.name; // Default to config name

                        if (optConfig.type === 'radio') {
                            element = document.querySelector(`input[type="radio"][data-option-name="${optConfig.name}"]:checked`);
                            if (element) {
                                value = element.value;
                                optionDisplayName = element.dataset.optionName; // Get actual name from data attribute
                            }
                        } else if (optConfig.type === 'select') {
                            element = document.querySelector(`select[data-option-name="${optConfig.name}"]`);
                            if (element && element.value) {
                                value = element.value;
                                optionDisplayName = element.dataset.optionName;
                            }
                        }
                        
                        if (value) {
                            message += `*${optionDisplayName}:* ${value}\n`;
                            processedOptionNames.push(optionDisplayName);
                        }
                    });

                    // Handle dynamic Wood/Mould Size Dropdown
                    const dynamicFrameSizeContainer = document.getElementById('dynamicFrameSizeDropdownContainer');
                    if (dynamicFrameSizeContainer && dynamicFrameSizeContainer.style.display !== 'none') {
                        const woodSizeSelect = dynamicFrameSizeContainer.querySelector('select[name="wood_size"]');
                        const mouldSizeSelect = dynamicFrameSizeContainer.querySelector('select[name="mould_size"]');

                        if (woodSizeSelect && woodSizeSelect.value) {
                            const optionName = woodSizeSelect.dataset.optionName || 'Wood Size';
                            message += `*${optionName}:* ${woodSizeSelect.value}\n`;
                            processedOptionNames.push(optionName);
                        } else if (mouldSizeSelect && mouldSizeSelect.value) {
                            const optionName = mouldSizeSelect.dataset.optionName || 'Mould Size';
                            message += `*${optionName}:* ${mouldSizeSelect.value}\n`;
                            processedOptionNames.push(optionName);
                        }
                    }
                    
                    // Collect any 'Other' options (radios and checkboxes not already processed)
                    // This ensures options not in the predefined order are still captured.
                    // Radios:
                    const otherRadios = document.querySelectorAll('.option-radio:checked');
                    otherRadios.forEach(radio => {
                        const optionName = radio.dataset.optionName;
                        if (!processedOptionNames.includes(optionName)) {
                            message += `*${optionName}:* ${radio.value}\n`;
                            processedOptionNames.push(optionName); 
                        }
                    });
                    // Checkboxes:
                    const otherCheckboxes = document.querySelectorAll('.option-checkbox:checked');
                    otherCheckboxes.forEach(checkbox => {
                        const optionName = checkbox.dataset.optionName;
                        if (!processedOptionNames.includes(optionName) || checkbox.name.endsWith('[]')) { 
                            message += `*${optionName}:* ${checkbox.value}\n`;
                        }
                    });
                    
                    message += `\n*Price:* ₹${productPrice}\n\n`; 
                    message += `I'd like to know more and proceed with this order.`;
                    
                    // Encode the entire message string before sending
                    window.open(`https://wa.me/919043011295?text=${encodeURIComponent(message)}`, '_blank');
                });
            }

            // Show more/less for product description
            const descriptionArea = document.getElementById('productDescriptionArea');
            if (descriptionArea) {
                descriptionArea.classList.add('description-truncated');
                const isOverflowing = descriptionArea.scrollHeight > descriptionArea.clientHeight;

                if (isOverflowing) {
                    const toggleLink = document.createElement('a');
                    toggleLink.href = '#'; 
                    toggleLink.id = 'toggleDescription';
                    toggleLink.classList.add('description-toggle');
                    toggleLink.textContent = 'Show more';

                    if (descriptionArea.parentNode) {
                         descriptionArea.parentNode.insertBefore(toggleLink, descriptionArea.nextSibling);
                    }

                    toggleLink.addEventListener('click', function(event) {
                        event.preventDefault(); 
                        if (descriptionArea.classList.contains('description-truncated')) {
                            descriptionArea.classList.remove('description-truncated');
                            descriptionArea.classList.add('description-expanded');
                            this.textContent = 'Show less';
                        } else {
                            descriptionArea.classList.remove('description-expanded');
                            descriptionArea.classList.add('description-truncated');
                            this.textContent = 'Show more';
                        }
                    });
                } else {
                    descriptionArea.classList.remove('description-truncated');
                }
            }
        });
    </script>
</body>
</html>