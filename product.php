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

// Load custom prices for this specific product
$customPricesFilePath = 'product_custom_prices.json';
$productSpecificCustomPrices = null; // Initialize

if (file_exists($customPricesFilePath)) {
    $jsonContent = file_get_contents($customPricesFilePath);
    $allCustomPrices = json_decode($jsonContent, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($allCustomPrices['product_' . $productId])) {
            $productSpecificCustomPrices = $allCustomPrices['product_' . $productId];
        }
        // If not set, $productSpecificCustomPrices remains null, which is fine.
    } elseif (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding product_custom_prices.json in product.php for product ID $productId: " . json_last_error_msg());
    }
}

// Create consolidated list of all product images
$allProductImages = [];
$mainImage = $product['image_path'];
if (!empty($mainImage)) {
    $allProductImages[] = htmlspecialchars($mainImage);
}

$additionalImagesDir = "uploads/products/" . $productId . "/";
if (is_dir($additionalImagesDir)) {
    $additionalFiles = glob($additionalImagesDir . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE);
    if ($additionalFiles) {
        foreach ($additionalFiles as $file) {
            $allProductImages[] = htmlspecialchars($file);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// Determine scheme and host for absolute URLs
$current_scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$current_host = $_SERVER['HTTP_HOST'];

// OG Title
$og_title = htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// OG Description
$og_description_raw = strip_tags($product['description'] ?? '');
if (mb_strlen($og_description_raw) > 155) {
    $og_description_text = mb_substr($og_description_raw, 0, 155) . "...";
} else {
    $og_description_text = $og_description_raw;
}
$og_description = htmlspecialchars($og_description_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// OG Image
$og_image_path = '/happilyyours-logo_N.png'; // Default fallback image
if (!empty($allProductImages) && !empty($allProductImages[0])) {
    // Ensure the path starts with a slash if it's a local path
    if (strpos($allProductImages[0], 'http') !== 0 && strpos($allProductImages[0], '/') !== 0) {
        $og_image_path = '/' . $allProductImages[0];
    } else {
        $og_image_path = $allProductImages[0];
    }
}
// Construct absolute URL for og:image
if (strpos($og_image_path, 'http') === 0) { // Already an absolute URL
    $og_image_url = htmlspecialchars($og_image_path);
} else {
    $og_image_url = $current_scheme . "://" . $current_host . htmlspecialchars($og_image_path);
}


// OG URL
$og_url = $current_scheme . "://" . $current_host . htmlspecialchars($_SERVER['REQUEST_URI']);

// OG Type
$og_type = "product";
?>
<meta property="og:title" content="<?php echo $og_title; ?>" />
<meta property="og:description" content="<?php echo $og_description; ?>" />
<meta property="og:image" content="<?php echo $og_image_url; ?>" />
<meta property="og:url" content="<?php echo $og_url; ?>" />
<meta property="og:type" content="<?php echo $og_type; ?>" />
    <title><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - Happilyyours</title>
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
            padding: 0.5rem 0;
            /*position: sticky; */ 
            /*top: 0;  */ 
            /*z-index: 1000; */ 
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .header .container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between; /* space between logo-section and social-links by default */
            padding: 1rem 0;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            align-self: center;
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-brown);
            text-decoration: none;
            align-self: center;
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

        .social-link.pinterest {
            background: linear-gradient(45deg, #BD081C, #E60023); /* Pinterest Red */
            color: white;
        }

        .social-link.facebook {
            background: linear-gradient(45deg, #3B5998, #4267B2); /* Facebook Blue */
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
                display: flex !important;          /* force flex display */
                justify-content: center !important; /* center horizontally */
                align-items: center;               /* vertically center if needed */
                gap: 10px;
                width: 100%;                      /* take full width */
                margin-top: 1rem;
            }
        
            .social-link {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                justify-content: center;
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
        .additional-images-container {
            margin-top: 20px;
            display: flex; /* Optional: for horizontal layout */
            flex-wrap: wrap; /* Optional: for wrapping */
            gap: 10px; /* Optional: for spacing */
        }
        .additional-images-container img {
            max-width: 100px; /* Or desired size */
            max-height: 100px; /* Or desired size */
            border: 1px solid #ddd;
            border-radius: 4px;
            object-fit: cover;
            cursor: pointer; /* Optional: if you add click-to-enlarge later */
        }

        /* New Image Gallery Styles */
        .main-image-gallery-viewer {
            position: relative; /* For arrow positioning */
            margin-bottom: 15px; /* Spacing below main image */
            background-color: #f8f9fa; /* Light background for the viewer area */
            border-radius: 15px; /* Consistent with product-image */
            overflow: hidden; /* Ensures arrows with slight padding don't overflow radius */
        }

        #galleryMainImage {
            display: block;
            max-width: 100%;
            height: auto;
            max-height: 550px; /* Max height for the main image */
            object-fit: contain; /* Show whole image, letterbox if aspect ratio differs */
            margin: 0 auto; /* Center image if it's narrower than container */
            border-radius: 15px; /* Match viewer radius if image is edge-to-edge */
            transition: opacity 0.3s ease-in-out; /* Smooth transition for image changes */
        }

        .gallery-nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.45);
            color: white;
            padding: 8px 12px; /* Slightly adjusted padding */
            border-radius: 50%;
            cursor: pointer;
            user-select: none; /* Prevent text selection */
            z-index: 10;
            font-size: 1.8rem; /* Adjusted size */
            line-height: 1;
            transition: background-color 0.2s ease;
            text-decoration: none; /* Remove underline if it's an <a> tag */
        }

        .gallery-nav-arrow:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }

        #galleryPrevBtn {
            left: 15px; /* Adjusted spacing */
        }

        #galleryNextBtn {
            right: 15px; /* Adjusted spacing */
        }

        #galleryThumbnailContainer {
            display: flex;
            flex-wrap: wrap;
            gap: 12px; /* Slightly increased gap */
            justify-content: center; /* Center thumbnails */
            margin-top: 15px; /* Ensure spacing from main image viewer */
            padding: 5px 0; /* Some padding if container might have a border/bg */
        }

        .gallery-thumbnail-link { /* Style for the <a> wrapper of thumbnails */
            display: inline-block; /* Important for layout */
            text-decoration: none;
            border: 2px solid transparent; /* Border will be on the link for active state */
            border-radius: 6px; /* Slightly larger radius for the link */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            padding: 2px; /* Small padding to make border appear around image */
        }

        .gallery-thumbnail { /* Styling for the <img> itself */
            width: 75px; /* Adjusted size */
            height: 75px; /* Adjusted size */
            object-fit: cover;
            border-radius: 4px; /* Inner radius for the image */
            cursor: pointer;
            display: block; /* Remove any extra space below image if it's inline */
            transition: opacity 0.3s ease;
        }

        .gallery-thumbnail-link:hover .gallery-thumbnail {
            opacity: 0.7;
        }

        .gallery-thumbnail-link.active { /* Active state on the link wrapper */
            border-color: var(--primary-brown, #8B4513); /* Use CSS variable or fallback */
            box-shadow: 0 0 8px rgba(139, 69, 19, 0.5); /* Optional: add a glow */
        }

        .gallery-thumbnail-link.active .gallery-thumbnail {
            opacity: 1;
        }

        /* Hide arrows and thumbnail container if only one image or no images (JS handles display:none) */
        /* This is mostly a fallback or if JS fails, JS should be primary control */
        .main-image-gallery-viewer[data-single-image="true"] .gallery-nav-arrow,
        .main-image-gallery-viewer[data-no-images="true"] .gallery-nav-arrow {
            display: none;
        }
        #galleryThumbnailContainer[data-single-image="true"],
        #galleryThumbnailContainer[data-no-images="true"] {
            display: none;
        }

        /* Responsive adjustments for thumbnail gallery on mobile */
        @media (max-width: 767px) {
            #galleryThumbnailContainer {
                flex-wrap: nowrap;      /* Prevent wrapping to ensure a single scrollable row */
                overflow-x: auto;       /* Enable horizontal scrolling */
                justify-content: flex-start; /* Align items to the start for H-scroll */
                padding-bottom: 10px;   /* Add some space for the scrollbar if it appears */
                /* Optional: Custom scrollbar styling for Webkit browsers */
                -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            }

            #galleryThumbnailContainer::-webkit-scrollbar {
                height: 6px; /* Height of the horizontal scrollbar */
            }

            #galleryThumbnailContainer::-webkit-scrollbar-thumb {
                background: #ccc; /* Color of the scrollbar thumb */
                border-radius: 3px;
            }

            #galleryThumbnailContainer::-webkit-scrollbar-track {
                background: #f1f1f1; /* Color of the scrollbar track */
            }

            .gallery-thumbnail-link {
                flex-shrink: 0; /* Prevent thumbnails from shrinking in the flex container */
            }
        }
    </style>
    <script>
        const productImages = <?= json_encode($allProductImages); ?>;
        const baseProductPrice = <?= (float)$product['price'] ?>;
        const productCustomPricing = <?= $productSpecificCustomPrices ? json_encode($productSpecificCustomPrices, JSON_UNESCAPED_SLASHES) : 'null' ?>;
    </script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <a href="/" aria-label="Happilyyours Home">
                    <img src="/happilyyours-logo_N.png" alt="Happilyyours Logo" class="logo-img">
                </a>
                <a href="/" class="brand-name">Happilyyours.Creators</a>
            </div>
            
            <div class="social-links">
                <a href="https://youtube.com/@Happilyyours.Creators" class="social-link youtube" target="_blank" aria-label="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
                <a href="https://www.instagram.com/happilyyours.creators" class="social-link instagram" target="_blank" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://in.pinterest.com/happilyyourscreators" class="social-link pinterest" target="_blank" aria-label="Pinterest">
                    <i class="fab fa-pinterest"></i>
                </a>
                <a href="https://www.facebook.com/happilyyours.creators" class="social-link facebook" target="_blank" aria-label="Facebook">
                    <i class="fab fa-facebook"></i>
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
                                    <li class="breadcrumb-item"><a href="index.php?category=<?= $product['parent_id'] ?>"><?= htmlspecialchars($product['parent_category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></li>
                                <?php endif; ?>
                                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['category_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                            </ol>
                        </nav>
                        
                        <!-- New Image Gallery Structure -->
                        <div class="main-image-gallery-viewer">
                            <img src="<?= !empty($allProductImages) ? $allProductImages[0] : '' ?>"
                                 class="img-fluid"
                                 id="galleryMainImage"
                                 alt="<?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

                            <span class="gallery-nav-arrow" id="galleryPrevBtn" aria-label="Previous Image">&#10094;</span>
                            <span class="gallery-nav-arrow" id="galleryNextBtn" aria-label="Next Image">&#10095;</span>
                        </div>

                        <div id="galleryThumbnailContainer" class="mt-2">
                            <!-- Thumbnails will be populated here by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Product Info -->
                        <h1 class="product-title"><?= htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                        <p class="product-category"><?= htmlspecialchars($product['parent_category'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= !empty($product['parent_category']) ? '>' : '' ?> <?= htmlspecialchars($product['category_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                        <div class="product-price" id="displayedProductPrice">₹<?= number_format($product['price'], 2) ?></div>
                        <div id="productDescriptionArea" class="product-description"><?= htmlspecialchars($product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

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
                                $optionData[htmlspecialchars($opt['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')] = $opt;
                            }

                            // Generate HTML for Shape
                            ob_start();
                            $currentOption = $optionData['Shape'] ?? null;
                            if ($currentOption) {
                                echo "<div class='option-section'>";
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' data-option-name='" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</label>";
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
                                echo "<h5 class='option-title'>" . htmlspecialchars($sizeOptionData['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h5>";
                                echo "<select class='form-select option-select' name='option_{$sizeOptionData['id']}' data-option-name='" . htmlspecialchars($sizeOptionData['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>";
                                
                                if (!empty($sizeOptionData['values']) && is_array($sizeOptionData['values'])) {
                                    foreach ($sizeOptionData['values'] as $value_idx => $value_obj) {
                                        $displayValue = htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' data-option-name='" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</label>";
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
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' data-option-name='" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</label>";
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
                                echo "<h5 class='option-title'>" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h5>";
                                echo "<div class='selectable-box-group'>";
                                if (!empty($currentOption['values']) && is_array($currentOption['values'])) {
                                    foreach ($currentOption['values'] as $v_idx => $value_obj) {
                                        echo "<div class='selectable-box-item'>";
                                        echo "<input class='form-check-input option-radio visually-hidden' type='radio' name='option_{$currentOption['id']}' id='option_{$currentOption['id']}_{$value_obj['id']}' value='" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' data-option-name='" . htmlspecialchars($currentOption['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'" . ($v_idx === 0 ? ' checked' : '') . ">";
                                        echo "<label class='selectable-box-label' for='option_{$currentOption['id']}_{$value_obj['id']}'>" . htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</label>";
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
                                if (!in_array(htmlspecialchars($option['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $definedPairedNames)) {
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
                                                <h5 class="option-title"><?= htmlspecialchars($option['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h5>
                                                <?php if ($option['option_type'] === 'checkbox'): ?>
                                                    <div class="selectable-box-group">
                                                        <?php foreach ($option['values'] as $value): ?>
                                                            <div class="selectable-box-item">
                                                                <input class="form-check-input option-checkbox visually-hidden" type="checkbox" name="option_<?= $option['id'] ?>[]" id="option_<?= $option['id'] ?>_<?= $value['id'] ?>" value="<?= htmlspecialchars($value['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-option-name="<?= htmlspecialchars($option['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                                                <label class="selectable-box-label" for="option_<?= $option['id'] ?>_<?= $value['id'] ?>"><?= htmlspecialchars($value['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="selectable-box-group">
                                                        <?php foreach ($option['values'] as $v_idx => $value_obj): ?>
                                                            <div class="selectable-box-item">
                                                                <input class="form-check-input option-radio visually-hidden" type="radio" name="option_<?= $option['id'] ?>" id="option_<?= $option['id'] ?>_<?= $value_obj['id'] ?>" value="<?= htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-option-name="<?= htmlspecialchars($option['option_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= ($v_idx === 0 ? 'checked' : '') ?>>
                                                                <label class="selectable-box-label" for="option_<?= $option['id'] ?>_<?= $value_obj['id'] ?>"><?= htmlspecialchars($value_obj['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
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
            <p>&copy; <?= date('Y') ?>. Happilyyours.in .All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const productImagePath = '<?= addslashes(htmlspecialchars($product['image_path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>';
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
            const priceDisplayElement = document.getElementById('displayedProductPrice');
            const frameOptionsContainer = document.getElementById('dynamicFrameSizeDropdownContainer'); // Used for event delegation
            const frameRadioButtons = document.querySelectorAll('input[type="radio"][data-option-name="Frame"]');
            const dynamicDropdownContainer = document.getElementById('dynamicFrameSizeDropdownContainer');

            function updateDisplayedPrice() {
                if (!priceDisplayElement) return;

                let newPrice = baseProductPrice; // Default to base price
                let priceSetByCustomOption = false;

                const woodSelect = document.querySelector('select[name="wood_size_selection"]');
                // woodSelect.value will be empty if "Select..." is chosen
                if (woodSelect && woodSelect.value && woodSelect.parentElement.parentElement.style.display !== 'none') {
                    const selectedOption = woodSelect.options[woodSelect.selectedIndex];
                    if (selectedOption) { 
                        // MODIFICATION START
                        if (selectedOption.dataset.textValue && selectedOption.dataset.textValue.trim() !== "") {
                            priceDisplayElement.textContent = selectedOption.dataset.textValue;
                            priceSetByCustomOption = true;
                            newPrice = NaN; // Mark that text was displayed
                        } else if (selectedOption.dataset.price && selectedOption.dataset.price.trim() !== "") { 
                            newPrice = parseFloat(selectedOption.dataset.price) || baseProductPrice;
                            priceSetByCustomOption = true;
                        }
                        // MODIFICATION END
                    }
                }

                // Only check mould price if wood price wasn't set by a specific selection
                // AND if textValue wasn't already set from woodSelect
                if (!priceSetByCustomOption || (priceSetByCustomOption && !isNaN(newPrice))) {
                    const mouldSelect = document.querySelector('select[name="mould_size_selection"]');
                     // mouldSelect.value will be empty if "Select..." is chosen
                    if (mouldSelect && mouldSelect.value && mouldSelect.parentElement.parentElement.style.display !== 'none') {
                        const selectedOption = mouldSelect.options[mouldSelect.selectedIndex];
                        if (selectedOption) { // Check selectedOption exists
                             // MODIFICATION START (similar logic for mould)
                            if (selectedOption.dataset.textValue && selectedOption.dataset.textValue.trim() !== "") {
                                priceDisplayElement.textContent = selectedOption.dataset.textValue;
                                priceSetByCustomOption = true;
                                newPrice = NaN; // Mark that text was displayed
                            } else if (selectedOption.dataset.price && selectedOption.dataset.price.trim() !== "") { 
                                newPrice = parseFloat(selectedOption.dataset.price) || baseProductPrice;
                                priceSetByCustomOption = true;
                            }
                             // MODIFICATION END
                        }
                    }
                }
                
                // Revised logic for the final display update:
                if (priceSetByCustomOption && isNaN(newPrice)) {
                    // Text was already set, do nothing here.
                } else if (priceSetByCustomOption && !isNaN(newPrice)) {
                    // Numeric custom price was set
                    priceDisplayElement.textContent = `₹${newPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                } else {
                    // Default base price if no custom option was effectively selected or processed
                    priceDisplayElement.textContent = `₹${baseProductPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }
            }

            // Event listener for dynamic dropdowns
            if (frameOptionsContainer) {
                frameOptionsContainer.addEventListener('change', function(event) {
                    if (event.target.classList.contains('custom-options-select')) {
                        updateDisplayedPrice();
                    }
                });
            }

            // Link updateDisplayedPrice to frameRadioButtons change
            frameRadioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    // updateFrameSizeDropdown is already called by existing logic when frame type changes.
                    // That function rebuilds the dropdown. We need to update price *after* it's rebuilt.
                    // A direct call here should work as JS execution will be sequential.
                    updateDisplayedPrice();
                });
            });

            // These arrays are now primarily for looking up 'text' based on 'value' from productCustomPricing
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

            function updateFrameSizeDropdown(selectedFrameType) { // selectedFrameType is 'Wood' or 'Mould'
                if (!dynamicDropdownContainer || typeof productCustomPricing === 'undefined') { // Check productCustomPricing existence
                    if (dynamicDropdownContainer) dynamicDropdownContainer.style.display = 'none';
                    return;
                }

                dynamicDropdownContainer.innerHTML = ''; // Clear previous
                dynamicDropdownContainer.style.display = 'none'; // Hide by default

                let optionsToDisplay = [];
                let selectName = '';
                let selectLabel = '';
                let pricesObject = null;
                let selectedSizesByAdmin = null;
                let mainOptionSelectedByAdmin = false;

                if (selectedFrameType === 'Wood' && productCustomPricing && productCustomPricing.wood_option_selected) {
                    mainOptionSelectedByAdmin = true;
                    selectName = 'wood_size_selection';
                    selectLabel = 'Wood Size';
                    pricesObject = productCustomPricing.wood_prices || {};
                    selectedSizesByAdmin = productCustomPricing.selected_wood_sizes || [];
                } else if (selectedFrameType === 'Mould' && productCustomPricing && productCustomPricing.mould_option_selected) {
                    mainOptionSelectedByAdmin = true;
                    selectName = 'mould_size_selection';
                    selectLabel = 'Mould Size';
                    pricesObject = productCustomPricing.mould_prices || {};
                    selectedSizesByAdmin = productCustomPricing.selected_mould_sizes || [];
                }

                if (mainOptionSelectedByAdmin) {
                    selectedSizesByAdmin.forEach(sizeValue => {
                        if (pricesObject.hasOwnProperty(sizeValue) && pricesObject[sizeValue] !== null && String(pricesObject[sizeValue]).trim() !== '') {
                            let sizeText = sizeValue; // Default text is the value itself
                            const allSizesForType = (selectedFrameType === 'Wood') ? woodSizes : mouldSizes;
                            const foundSize = allSizesForType.find(s => s.value === sizeValue);
                            if (foundSize) {
                                sizeText = foundSize.text; // Use predefined text if available (e.g., "6x6 inch")
                            }

                            // MODIFICATION START
                            let priceOrText = pricesObject[sizeValue];
                            let dataPriceAttr = `data-price="${parseFloat(priceOrText) || 0}"`; // Default numeric price
                            let dataTextAttr = "";

                            if (sizeValue === "Customized size" && isNaN(parseFloat(priceOrText))) {
                                dataTextAttr = `data-text-value="${String(priceOrText).replace(/"/g, '&quot;')}"`; // Store text, ensure quotes are handled
                                dataPriceAttr = `data-price=""`; // Set price to empty for text-based options
                            }
                            // MODIFICATION END

                            optionsToDisplay.push({
                                value: sizeValue,
                                text: sizeText,
                                dataPrice: dataPriceAttr, // Pass attributes to be used in HTML generation
                                dataText: dataTextAttr   // Pass attributes to be used in HTML generation
                            });
                        }
                    });
                }

                if (optionsToDisplay.length > 0) {
                    let dropdownHtml = `<h5>${selectLabel}</h5><div class="form-group">` +
                                       `<select class="form-select option-select custom-options-select" name="${selectName}" data-option-name="${selectLabel}">`;
                    dropdownHtml += `<option value="">Select ${selectLabel}...</option>`;
                    optionsToDisplay.forEach(opt => {
                        // Use opt.dataPrice and opt.dataText here
                        dropdownHtml += `<option value="${opt.value}" ${opt.dataPrice} ${opt.dataText}>${opt.text}</option>`;
                    });
                    dropdownHtml += `</select></div>`;
                    
                    dynamicDropdownContainer.innerHTML = dropdownHtml;
                    dynamicDropdownContainer.style.display = 'block';
                } else if (mainOptionSelectedByAdmin) {
                    dynamicDropdownContainer.innerHTML = `<p><small>No specific ${selectedFrameType.toLowerCase()} sizes available or priced for this product.</small></p>`;
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
            updateDisplayedPrice(); // Initial call after setup
            
            // Set up WhatsApp button
            const newWhatsAppBtn = document.getElementById('newWhatsAppBtn'); 
            if (newWhatsAppBtn) { 
                newWhatsAppBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productName = "<?= addslashes(htmlspecialchars($product['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>";
                    const categoryName = "<?= addslashes(htmlspecialchars($product['category_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>";
                    const productPrice = "<?= addslashes(htmlspecialchars(number_format($product['price'], 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>";

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
                    const dynamicFrameSizeContainer = document.getElementById('dynamicFrameSizeDropdownContainer'); // Re-fetch or ensure it's in scope

                    // >> START NEW REQUIRED CHECK LOGIC FOR DYNAMIC FRAME SIZE <<
                    if (dynamicFrameSizeContainer && dynamicFrameSizeContainer.style.display !== 'none') {
                        const woodSizeSelectCheck = dynamicFrameSizeContainer.querySelector('select[name="wood_size_selection"]');
                        const mouldSizeSelectCheck = dynamicFrameSizeContainer.querySelector('select[name="mould_size_selection"]');
                        let dynamicSizeLabel = '';
                        let isMissing = false;

                        if (woodSizeSelectCheck && woodSizeSelectCheck.offsetParent !== null) { // Check if wood select is part of the visible DOM
                            dynamicSizeLabel = woodSizeSelectCheck.dataset.optionName || 'Wood Size';
                            if (!woodSizeSelectCheck.value) {
                                isMissing = true;
                            }
                        } else if (mouldSizeSelectCheck && mouldSizeSelectCheck.offsetParent !== null) { // Check if mould select is part of the visible DOM
                            dynamicSizeLabel = mouldSizeSelectCheck.dataset.optionName || 'Mould Size';
                            if (!mouldSizeSelectCheck.value) {
                                isMissing = true;
                            }
                        }

                        if (isMissing) {
                            alert(`Please select a ${dynamicSizeLabel} before ordering via WhatsApp.`);
                            return; // Stop message generation
                        }
                    }
                    // >> END NEW REQUIRED CHECK LOGIC FOR DYNAMIC FRAME SIZE <<

                    // Existing logic to ADD dynamic frame size to message (this will only run if the check above passes)
                    if (dynamicFrameSizeContainer && dynamicFrameSizeContainer.style.display !== 'none') {
                        const woodSizeSelect = dynamicFrameSizeContainer.querySelector('select[name="wood_size_selection"]');
                        const mouldSizeSelect = dynamicFrameSizeContainer.querySelector('select[name="mould_size_selection"]');

                        if (woodSizeSelect && woodSizeSelect.value) {
                            const optionName = woodSizeSelect.dataset.optionName || 'Wood Size';
                            const selectedText = woodSizeSelect.options[woodSizeSelect.selectedIndex].text;
                            message += `*${optionName}:* ${selectedText}\n`;
                            processedOptionNames.push(optionName);
                        } else if (mouldSizeSelect && mouldSizeSelect.value) {
                            const optionName = mouldSizeSelect.dataset.optionName || 'Mould Size';
                            const selectedText = mouldSizeSelect.options[mouldSizeSelect.selectedIndex].text;
                            message += `*${optionName}:* ${selectedText}\n`;
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mainImage = document.getElementById('galleryMainImage');
            const prevBtn = document.getElementById('galleryPrevBtn');
            const nextBtn = document.getElementById('galleryNextBtn');
            const thumbnailContainer = document.getElementById('galleryThumbnailContainer');

            // productImages is globally available from PHP (json_encode from previous step)
            if (typeof productImages === 'undefined' || !Array.isArray(productImages) || productImages.length === 0) {
                if (mainImage && (!mainImage.getAttribute('src') || mainImage.getAttribute('src') === '')) {
                     // If no images at all, and main image src is empty, optionally hide viewer or show placeholder.
                     // For now, we assume CSS might handle a broken image icon if src is truly empty.
                     // Or, hide the entire gallery section if desired.
                     // document.querySelector('.main-image-gallery-viewer').style.display = 'none';
                }
                if (prevBtn) prevBtn.style.display = 'none';
                if (nextBtn) nextBtn.style.display = 'none';
                if (thumbnailContainer) thumbnailContainer.style.display = 'none';
                return;
            }

            let currentImageIndex = 0;

            function showImage(index) {
                if (index < 0 || index >= productImages.length) {
                    console.error('Invalid image index:', index);
                    return;
                }
                if (mainImage) { // Check if mainImage element exists
                    mainImage.src = productImages[index];
                    mainImage.alt = `Product image ${index + 1}`; // Update alt text
                }
                currentImageIndex = index;

                if (thumbnailContainer) { // Check if thumbnailContainer element exists
                    const thumbnails = thumbnailContainer.querySelectorAll('.gallery-thumbnail');
                    thumbnails.forEach((thumb, idx) => {
                        if (idx === index) {
                            thumb.classList.add('active');
                            // CSS will define .active (e.g., border, opacity)
                        } else {
                            thumb.classList.remove('active');
                        }
                    });
                }
            }

            function populateThumbnails() {
                if (!thumbnailContainer) return; // Exit if container not found
                thumbnailContainer.innerHTML = ''; // Clear existing
                productImages.forEach((imagePath, index) => {
                    const thumbLink = document.createElement('a'); // Use <a> for better accessibility/semantics if desired
                    thumbLink.href = '#'; // Prevent page jump
                    thumbLink.classList.add('gallery-thumbnail-link'); // For styling wrapper if needed

                    const thumb = document.createElement('img');
                    thumb.src = imagePath;
                    thumb.alt = `Thumbnail ${index + 1} for ${productImages[0].alt || 'product'}`; // Use main image alt if available
                    thumb.classList.add('gallery-thumbnail');
                    thumb.dataset.index = index;

                    thumbLink.addEventListener('click', function(e) {
                        e.preventDefault(); // Prevent anchor jump
                        showImage(parseInt(this.querySelector('img').dataset.index));
                    });

                    thumbLink.appendChild(thumb);
                    thumbnailContainer.appendChild(thumbLink);
                });
            }

            // Initialization
            if (productImages.length > 0) {
                populateThumbnails();
                showImage(0); // Display the first image and set active thumbnail

                if (productImages.length <= 1) {
                    if (prevBtn) prevBtn.style.display = 'none';
                    if (nextBtn) nextBtn.style.display = 'none';
                    if (thumbnailContainer) thumbnailContainer.style.display = 'none'; // Also hide thumbs if only one image
                } else {
                    if (prevBtn) prevBtn.style.display = 'block'; // Or 'inline-flex' etc. based on CSS
                    if (nextBtn) nextBtn.style.display = 'block';
                }
            }


            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    let newIndex = currentImageIndex - 1;
                    if (newIndex < 0) {
                        newIndex = productImages.length - 1;
                    }
                    showImage(newIndex);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    let newIndex = currentImageIndex + 1;
                    if (newIndex >= productImages.length) {
                        newIndex = 0;
                    }
                    showImage(newIndex);
                });
            }
        });
    </script>
</body>
</html>
