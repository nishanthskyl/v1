<?php
include 'db.php';

// Handle AJAX requests for filtering products
if (isset($_GET['action']) && $_GET['action'] === 'filter_products') {
    header('Content-Type: application/json');
    
    try {
        $filterType = $_GET['filter_type'] ?? 'all';
        $id = $_GET['id'] ?? null;
        
        $products = [];
        
        switch ($filterType) {
            case 'all':
                $stmt = $pdo->query("
                    SELECT p.*, c.name as category_name 
                    FROM products p 
                    JOIN categories c ON p.category_id = c.id 
                    ORDER BY p.name
                ");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'parentDirect':
                if ($id) {
                    $stmt = $pdo->prepare("
                        SELECT p.*, c.name as category_name 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE c.id = ? OR c.parent_id = ?
                        ORDER BY p.name
                    ");
                    $stmt->execute([$id, $id]);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'allForParent':
                if ($id) {
                    $stmt = $pdo->prepare("
                        SELECT p.*, c.name as category_name 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE c.parent_id = ?
                        ORDER BY p.name
                    ");
                    $stmt->execute([$id]);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'subcategory':
                if ($id) {
                    $stmt = $pdo->prepare("
                        SELECT p.*, c.name as category_name 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE c.id = ?
                        ORDER BY p.name
                    ");
                    $stmt->execute([$id]);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
        }
        
        echo json_encode($products);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch all categories once
$allCategoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY parent_id, name");
$allCategoriesData = $allCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$allParentCategories = [];
$allSubCategoriesByParentId = [];

foreach ($allCategoriesData as $category) {
    if ($category['parent_id'] === null) {
        $allParentCategories[] = $category;
    } else {
        if (!isset($allSubCategoriesByParentId[$category['parent_id']])) {
            $allSubCategoriesByParentId[$category['parent_id']] = [];
        }
        $allSubCategoriesByParentId[$category['parent_id']][] = $category;
    }
}

// Reorder "Preservation" and "Others" categories
$othersKey = null;
$preservationKey = null;

foreach ($allParentCategories as $key => $category) {
    if ($category['name'] === 'Others') {
        $othersKey = $key;
    } elseif ($category['name'] === 'Preservation') {
        $preservationKey = $key;
    }
    // If both found, no need to continue looping
    if ($othersKey !== null && $preservationKey !== null) {
        break;
    }
}

// Swap if both categories were found and Preservation is currently after Others
if ($othersKey !== null && $preservationKey !== null && $preservationKey > $othersKey) {
    $temp = $allParentCategories[$preservationKey];
    $allParentCategories[$preservationKey] = $allParentCategories[$othersKey];
    $allParentCategories[$othersKey] = $temp;
}

$parentCategories = $allParentCategories;

// Get featured products
$featuredProducts = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    ORDER BY RAND() LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<meta property="og:title" content="Happilyyours" />
<meta property="og:description" content="Handcrafted with Love" />
<meta property="og:image" content="<?php echo $og_image_url; ?>" />
<meta property="og:image:width" content="400" />
<meta property="og:image:height" content="400" />
<meta property="og:url" content="<?php echo $og_url; ?>" />
<meta property="og:type" content="website" />
    <title>Happilyyours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192"  href="/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
    <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
    <style>
        :root {
            --cream-bg: #f5e6d3;
            --cream-light: #faf5f0;
            --cream-dark: #e8d5c4;
            --brown-text: #8b4513;
            --brown-dark: #654321;
            --accent-gold: #d4af37;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--cream-bg) 0%, var(--cream-light) 100%);
            font-family: 'Inter', sans-serif;
            color: var(--brown-text);
            min-height: 100vh;
        }

        .navbar {
            background: transparent !important;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(139, 69, 19, 0.1);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--brown-dark) !important;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .nav-link {
            color: var(--brown-text) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--brown-dark) !important;
        }

        .btn-outline-light {
            border-color: var(--brown-text);
            color: var(--brown-text);
            background: transparent;
        }

        .btn-outline-light:hover {
            background-color: var(--brown-text);
            border-color: var(--brown-text);
            color: white;
        }

        /* Header Section */
.hero-section {
  position: relative;
  text-align: center;
  padding: 2rem 0 1.5rem;
  min-height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, 
    #FFF8E1 0%, 
    #FEFEFE 50%, 
    #F5F5F5 100%);
  overflow: hidden;
}

.hero-section::before {
  content: '';
  position: absolute;
  top: -10px;
  left: -10px;
  right: -10px;
  bottom: -10px;
  background: radial-gradient(circle at 30% 20%, rgba(232, 180, 184, 0.03) 0%, transparent 40%),
              radial-gradient(circle at 70% 80%, rgba(255, 183, 77, 0.02) 0%, transparent 40%);
  animation: float 15s ease-in-out infinite;
  z-index: 0;
}

.hero-section .container {
  position: relative;
  z-index: 10;
}

.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 5vw, 3rem);
  font-weight: 700;
  background: linear-gradient(135deg, var(--brown-dark) 0%, #8D6E63 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 0.5rem;
  position: relative;
  animation: titleSlide 1s ease-out;
  letter-spacing: -0.02em;
  z-index: 15;
}

.hero-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 40px;
  height: 2px;
  background: linear-gradient(90deg, transparent, #FFB74D, transparent);
  border-radius: 1px;
  animation: underlineGrow 1.2s ease-out 0.3s both;
}


.hero-subtitle {
  font-size: clamp(0.9rem, 2vw, 1.1rem);
  color: var(--brown-text);
  font-weight: 400;
  opacity: 0.85;
  font-family: 'Inter', sans-serif;
  letter-spacing: 0.3px;
  animation: subtitleFade 1s ease-out 0.2s both;
  position: relative;
  margin-bottom: 0;
  z-index: 15;
}

.hero-subtitle::before {
  content: '✨';
  margin-right: 6px;
  opacity: 0.6;
  font-size: 0.8em;
  animation: sparkle 2s ease-in-out infinite;
}

.hero-subtitle::after {
  content: '✨';
  margin-left: 6px;
  opacity: 0.6;
  font-size: 0.8em;
  animation: sparkle 2s ease-in-out infinite 1s;
}

/* Simplified animations for mobile */
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

@keyframes titleSlide {
  0% {
    opacity: 0;
    transform: translateY(20px);
  }
  100% {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes subtitleFade {
  0% {
    opacity: 0;
    transform: translateY(10px);
  }
  100% {
    opacity: 0.85;
    transform: translateY(0);
  }
}

@keyframes underlineGrow {
  0% {
    width: 0;
    opacity: 0;
  }
  100% {
    width: 40px;
    opacity: 1;
  }
}

@keyframes sparkle {
  0%, 100% { transform: scale(1); opacity: 0.6; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

/* Hover effects - minimal for mobile */
.hero-title:hover {
  transform: scale(1.01);
  transition: transform 0.2s ease;
}

/* Mobile-specific optimizations */
@media (max-width: 768px) {
  .hero-section {
    padding: 1.5rem 0 1rem;
  }
  
  .hero-title {
    margin-bottom: 0.3rem;
  }
  
  .hero-title::after {
    width: 30px;
    height: 1.5px;
  }
  
  .hero-subtitle {
    font-size: 0.9rem;
    letter-spacing: 0.2px;
  }
  
  .hero-subtitle::before,
  .hero-subtitle::after {
    font-size: 0.7em;
    margin: 0 4px;
  }
}

@media (max-width: 480px) {
  .hero-section {
    padding: 1rem 0 0.8rem;
  }
  
  .hero-title {
    font-size: 1.8rem;
    margin-bottom: 0.2rem;
  }
  
  .hero-subtitle {
    font-size: 0.85rem;
  }
}

        /* Category Navigation */
        #secondary-navbar {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 2rem 0;
            flex-wrap: wrap;
        }

        .parent-category-link {
            background: rgba(255, 255, 255, 0.7);
            border: 2px solid transparent;
            color: var(--brown-text);
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .parent-category-link:hover,
        .parent-category-link.active {
            background: var(--brown-text);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.3);
        }

        #dynamic-subcategory-area {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            padding: 1rem 0 2rem;
            min-height: 60px;
        }

        .subcategory-button {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(139, 69, 19, 0.2);
            color: var(--brown-text);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .subcategory-button:hover,
        .subcategory-button.active {
            background: var(--accent-gold);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        /* Product Cards */
        .product-card {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .product-card .card-img-top {
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .card-img-top {
            transform: scale(1.05);
        }

        .product-card .card-body {
            padding: 1.5rem;
            text-align: center;
        }

        .product-card .card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--brown-dark);
            margin-bottom: 0.5rem;
        }

        .product-card .card-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent-gold);
        }

        .product-card a {
            text-decoration: none;
            color: inherit;
        }

        /* Section Titles */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--brown-dark);
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent-gold);
            border-radius: 2px;
        }

        /* Category Cards in Shop Section */
        .category-card {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            height: 100%;
        }

        .category-card:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .category-card .card-title {
            font-family: 'Playfair Display', serif;
            color: var(--brown-dark);
            font-weight: 600;
        }

        .list-group-item {
            background: transparent;
            border: 1px solid rgba(139, 69, 19, 0.1);
            color: var(--brown-text);
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background: var(--cream-dark);
            color: var(--brown-dark);
        }

        .list-group-item.text-primary {
            color: var(--accent-gold) !important;
            font-weight: 600;
        }

        /* Footer */
        footer {
            background: rgba(139, 69, 19, 0.9) !important;
            color: var(--cream-light) !important;
            margin-top: 4rem;
            backdrop-filter: blur(10px);
        }

        /* WhatsApp Float */
        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(37, 211, 102, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            #secondary-navbar {
                padding: 1rem;
            }
            
            .parent-category-link {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
        }

        /* Loading Animation */
        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--brown-text);
        }

        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(139, 69, 19, 0.3);
            border-radius: 50%;
            border-top-color: var(--brown-text);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

/* Wall of Love Gallery Styles */
.wol-gallery-grid {
    width: 100%;
    margin: 0 auto;
}

.wol-gallery-item {
    width: 100%;
    margin-bottom: 15px;
    box-sizing: border-box;
    min-height: 150px;
    background-color: var(--cream-light);
    border-radius: 5px;
    overflow: hidden;
}

/* Responsive columns */
@media (min-width: 768px) {
    .wol-gallery-item {
        width: calc(50% - 10px); /* 2 columns with gutter */
    }
}

@media (min-width: 992px) {
    .wol-gallery-item {
        width: calc(33.333% - 10px); /* 3 columns with gutter */
    }
}

.wol-gallery-item img {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: opacity 0.3s ease-in-out;
}
        /* Responsive adjustments for Masonry */
        /* Masonry will use the width of .wol-gallery-item for its columnWidth calculation if specified as such.
           The gutter option then adds space between these columns.
           So, the item width should be the desired percentage of the container.
        */
        @media (min-width: 992px) { /* Large devices (desktops, 992px and up) */
            .wol-gallery-item {
                width: 32.0%; /* (100% - 2 * 10px_gutter_approx) / 3 columns. Or simpler: 33.333% and let gutter push. Let's use a value that often works well with 10px gutter. */
                                /* A common way is (100% - (N-1)*gutter) / N. For 3 cols, (100% - 20px) / 3.
                                   However, with percentPosition:true and columnWidth pointing to this item,
                                   setting width to the ideal percentage like 33.333% is cleaner.
                                   Let's assume Masonry's gutter will correctly space items of this width.
                                */
                 width: 32% !important; /* ADDED !important */

            }
        }
        @media (min-width: 576px) and (max-width: 991.98px) { /* Medium devices */
            .wol-gallery-item {
                 width: 48% !important; /* ADDED !important */
            }
        }
        @media (max-width: 575.98px) { /* Small devices */
            .wol-gallery-item {
                width: 100% !important; /* ADDED !important */
                min-height: 200px; /* This was an existing specific min-height */
            }
        }

        /* Fancybox Close Button Styling - Ensure Visibility */
        .fancybox__button--close {
            display: inline-flex !important; /* Or 'flex' if that's what Fancybox uses, !important to override conflicts */
            visibility: visible !important;
            opacity: 1 !important;
            position: absolute !important; /* Default is usually absolute */
            top: 10px !important; /* Adjust as needed */
            right: 10px !important; /* Adjust as needed */
            width: 36px !important; /* Adjust as needed */
            height: 36px !important; /* Adjust as needed */
            cursor: pointer !important;
            background: rgba(0, 0, 0, 0.3) !important; /* A semi-transparent background for visibility */
            border-radius: 50% !important; /* Make it circular */
            color: #fff !important; /* White 'X' */
            z-index: 99999 !important; /* Ensure it's on top */
            padding: 0 !important; /* Reset padding */
            align-items: center !important; /* Center the SVG icon */
            justify-content: center !important; /* Center the SVG icon */
        }

        /* Styling for the SVG icon itself if it's not inheriting color */
        .fancybox__button--close svg {
            fill: #fff !important; /* White 'X' */
            width: 60% !important; /* Adjust size of 'X' within the button */
            height: 60% !important; /* Adjust size of 'X' within the button */
        }

        /* Optional: Hover effect for better UX */
        .fancybox__button--close:hover {
            background: rgba(0, 0, 0, 0.6) !important;
        }

        /* Temporary Debug Borders - Add to the end of the <style> block */
        /* #products-display-area {
            border: 5px solid red !important; 
            padding-top: 5px !important;
            padding-bottom: 5px !important;
            margin-top: 10px !important; 
            margin-bottom: 10px !important;
        }

        .wol-gallery-grid { 
            border: 5px solid blue !important;
            padding: 5px !important; 
            min-height: 50px !important; 
        }

        .wol-gallery-item {
            border: 2px solid limegreen !important; 
        } */
        /* End of Temporary Debug Borders */

        /* Fancybox Close Button Styling - Ensure Visibility - Re-attempt */
        /* Ensure this is at the VERY END of all other <style> content */

        .fancybox__button--close {
            display: inline-flex !important; 
            visibility: visible !important;
            opacity: 1 !important;
            position: absolute !important; 
            top: 15px !important; /* Adjusted slightly for better visibility from edge */
            right: 15px !important; /* Adjusted slightly */
            width: 44px !important; /* Slightly larger for easier clicking */
            height: 44px !important; /* Slightly larger */
            cursor: pointer !important;
            background: rgba(30, 30, 30, 0.7) !important; /* Darker background for more contrast */
            border-radius: 50% !important;
            color: #ffffff !important; 
            z-index: 999999 !important; /* Max z-index */
            padding: 0 !important;
            align-items: center !important;
            justify-content: center !important;
            border: 1px solid white !important; /* Added a white border for more visibility */
        }

        .fancybox__button--close svg {
            display: block !important; /* Ensure SVG is display block */
            fill: #ffffff !important; 
            width: 60% !important; 
            height: 60% !important;
            margin: auto !important; /* Center SVG if its container is flex */
        }

        .fancybox__button--close:hover {
            background: rgba(0, 0, 0, 0.9) !important;
        }
    </style>
</head>
<body>
 <!-- Navigation -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="/">
      <img src="/happilyyours-logo_N.png"
           alt="Happilyyours Logo"
           style="height: 80px; margin-right: 10px; border-radius: 60%;">
      Happilyyours.Creators
    </a>

    <ul class="navbar-nav flex-row ms-lg-auto mx-auto mx-lg-0">
      <li class="nav-item me-3">
        <a class="nav-link" href="https://m.youtube.com/@Happilyyours.Creators" target="_blank">
          <i class="fab fa-youtube fa-lg" style="color: #FF0000;"></i>
        </a>
      </li>
      <li class="nav-item me-3">
        <a class="nav-link" href="https://wa.me/919043011295" target="_blank">
          <i class="fab fa-whatsapp fa-lg" style="color: #25D366;"></i>
        </a>
      </li>
      <li class="nav-item me-3">
        <a class="nav-link" href="https://www.instagram.com/happilyyours.creators" target="_blank">
          <i class="fab fa-instagram fa-lg" style="color: #E1306C;"></i>
        </a>
      </li>
      <li class="nav-item me-3">
        <a class="nav-link" href="https://in.pinterest.com/happilyyourscreators" target="_blank">
          <i class="fab fa-pinterest fa-lg" style="color: #BD081C;"></i>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="https://www.facebook.com/happilyyours.creators" target="_blank">
          <i class="fab fa-facebook fa-lg" style="color: #3B5998;"></i>
        </a>
      </li>
    </ul>
  </div>
</nav>




    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <h1 class="hero-title">Happilyyours</h1><br>
            <p class="hero-subtitle">Handcrafted with Love</p>
        </div>
    </div>

    <!-- Category Navigation -->
    <div id="secondary-navbar" class="container-fluid">
        <?php foreach ($allParentCategories as $category): ?>
            <a href="index.php?category=<?= $category['id'] ?>" 
               class="parent-category-link" 
               data-category-id="<?= $category['id'] ?>"> 
                <?= htmlspecialchars($category['name']) ?>
            </a>
        <?php endforeach; ?>
        <a href="#" class="parent-category-link" id="wall-of-love-btn">Wall of Love</a>
        <a href="#" class="parent-category-link" id="about-us-btn">About Us</a>
    </div>

    <!-- Subcategory Area -->
    <div id="dynamic-subcategory-area" class="container-fluid">
        <!-- Sub-category buttons will be dynamically inserted here -->
    </div>

    <!-- Products Section -->
    <div class="container">
        <h2 id="product-section-title" class="section-title">Products</h2>
        <div id="products-display-area" class="row">
            <div class="loading">
                <div class="spinner"></div>
                <p>Loading products...</p>
            </div>
        </div>
    </div>

 

    <!-- Footer -->
    <footer class="text-white py-4">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?>. Happilyyours.in .All rights reserved.</p>
        </div>
    </footer>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/919043011295?text=Hi,%20I'm%20interested%20in%20your%20resin%20art%20products.%20Can%20you%20help%20me?" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- JavaScript Data -->
    <script>
        const allSubCategoriesByParentId = <?= json_encode($allSubCategoriesByParentId); ?>;
        const allParentCategories = <?= json_encode($allParentCategories); ?>;
    </script>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const parentCategoryLinks = document.querySelectorAll('.parent-category-link');
            const dynamicSubcategoryArea = document.getElementById('dynamic-subcategory-area');
            const productsDisplayArea = document.getElementById('products-display-area');
            const productSectionTitle = document.getElementById('product-section-title');
            const aboutUsBtn = document.getElementById('about-us-btn');
            const wallOfLoveBtn = document.getElementById('wall-of-love-btn');

            const aboutUsContentHTML = `
<p>Welcome to Happilyyours,</p>

<p>At Happilyyours, based in the heart of Chennai, Tamil Nadu, we blend creativity, craftsmanship, and emotional connection to create stunning resin art pieces. We specialize in transforming your most precious memories into timeless keepsakes — from custom resin photo frames and preserved flowers to personalized gifting solutions that speak straight from the heart.</p><br>

<h4 style="text-align: center;">Where Memories Turn Into Art</h4>

<p>What started as a passion for capturing life's beautiful moments has grown into a trusted resin art brand in Chennai, known for innovation, quality, and deep attention to detail. Each piece is carefully handcrafted to reflect your story — making it more than just a product, but a celebration of love, memory, and individuality.</p><br>

<h4 style="text-align: center;">Custom Resin Gifts Made with Love</h4>

<p>We believe that the perfect gift should be personal, meaningful, and memorable. That’s why every creation at Happilyyours is infused with love, care, and a deep appreciation for artistic expression. Whether you're preserving wedding flowers, surprising a loved one with a custom resin clock, or immortalizing a heartfelt moment, our pieces are designed to inspire and delight.</p><br>

<h4>Why Choose Happilyyours?</h4>
<ul>
    <li>🖌️ Handcrafted Resin Art in Chennai</li>
    <li>🎁 Unique and Personalized Gifting Solutions</li>
    <li>🌸 Preservation of Real Flowers and Mementos</li>
    <li>📷 Custom Photo Frames, Keychains, Coasters, Clocks & More</li>
    <li>💖 Perfect for Birthdays, Anniversaries, Weddings, and Special Occasions</li>
</ul>
<br>
<h4 style="text-align: center;">Let’s Create Something Beautiful Together</h4>

<p>At Happilyyours, we don’t just make resin products — we help you express what words often cannot. Explore our collection or get in touch to create your own custom resin art that reflects love, emotion, and memory in the most beautiful way.</p>
`;

            function renderProducts(productsArray) {
                productsDisplayArea.innerHTML = '';

                if (!productsArray || !Array.isArray(productsArray) || productsArray.length === 0) {
                    productsDisplayArea.innerHTML = '<div class="col-12 text-center"><p class="text-muted">No products found matching your selection.</p></div>';
                    return;
                }

                productsArray.forEach(product => {
                    const cardHtml = `
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card product-card">
                                <a href="product.php?id=${product.id}">
                                    <img src="${product.image_path}" class="card-img-top" alt="${product.name}" onerror="this.src='https://via.placeholder.com/250x250/f5e6d3/8b4513?text=No+Image'">
                                </a>
                                <div class="card-body">
                                    <a href="product.php?id=${product.id}">
                                        <h5 class="card-title">${product.name}</h5>
                                        <p class="card-text">₹${parseFloat(product.price).toFixed(2)}</p>
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    productsDisplayArea.insertAdjacentHTML('beforeend', cardHtml);
                });
            }

            async function fetchAndDisplayProducts(filterType, id) {
                // Show loading
                productsDisplayArea.innerHTML = `
                    <div class="col-12 loading">
                        <div class="spinner"></div>
                        <p>Loading products...</p>
                    </div>
                `;

                let url = `index.php?action=filter_products&filter_type=${filterType}`;
                if (id) {
                    url += `&id=${id}`;
                }

                try {
                    const response = await fetch(url);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const products = await response.json();
                    if (products.error) {
                        console.error("Error from server:", products.error);
                        productsDisplayArea.innerHTML = `<div class="col-12 text-center"><p class="text-danger">Error loading products: ${products.error}</p></div>`;
                    } else {
                        renderProducts(products);
                    }
                } catch (error) {
                    console.error('Error fetching products:', error);
                    productsDisplayArea.innerHTML = '<div class="col-12 text-center"><p class="text-danger">Error loading products. Please try again later.</p></div>';
                }
            }
            
            function initiateProductLoad(filterType, id, title = 'Products') {
                if (productSectionTitle) {
                    productSectionTitle.textContent = title;
                }
                fetchAndDisplayProducts(filterType, id);
            }

            if (!dynamicSubcategoryArea || !productsDisplayArea || !productSectionTitle) {
                console.error('Error: One or more key DOM elements are missing for product display.');
                return; 
            }
            
            if (typeof allSubCategoriesByParentId === 'undefined' || typeof allParentCategories === 'undefined') {
                console.error('Error: Category data is not defined.');
                return; 
            }

            parentCategoryLinks.forEach(link => {
                // Exclude aboutUsBtn and wallOfLoveBtn from this specific logic 
                if (link.id === 'about-us-btn' || link.id === 'wall-of-love-btn') return; 

                link.addEventListener('click', function(event) {
                    if (aboutUsBtn && aboutUsBtn.classList.contains('active')) {
                        aboutUsBtn.classList.remove('active');
                    }
                    if (wallOfLoveBtn && wallOfLoveBtn.classList.contains('active')) {
                        wallOfLoveBtn.classList.remove('active');
                    }
                    const clickedParentLink = this;
                    const parentId = clickedParentLink.dataset.categoryId;
                    const subCategories = allSubCategoriesByParentId[parentId] || [];
                    const wasActive = clickedParentLink.classList.contains('active');

                    parentCategoryLinks.forEach(plink => {
                        if (plink !== clickedParentLink) {
                            plink.classList.remove('active');
                        }
                    });
                    
                    dynamicSubcategoryArea.innerHTML = '';

                    if (subCategories.length > 0) {
                        event.preventDefault();

                        if (wasActive) {
                            clickedParentLink.classList.remove('active');
                        } else {
                            clickedParentLink.classList.add('active');
                            
                            const allButton = document.createElement('a');
                            allButton.href = clickedParentLink.href; 
                            allButton.classList.add('subcategory-button', 'all-subcategories-btn');
                            allButton.textContent = 'All';
                            allButton.dataset.parentId = parentId;
                            dynamicSubcategoryArea.appendChild(allButton);

                            subCategories.forEach(subCategory => {
                                const subCategoryButton = document.createElement('a');
                                subCategoryButton.href = `index.php?category=${subCategory.id}`;
                                subCategoryButton.classList.add('subcategory-button');
                                subCategoryButton.dataset.subcategoryId = subCategory.id;
                                subCategoryButton.textContent = subCategory.name;
                                dynamicSubcategoryArea.appendChild(subCategoryButton);
                            });
                            
                            initiateProductLoad('allForParent', parentId, 'All ' + clickedParentLink.textContent.trim());
                            if(allButton) allButton.classList.add('active'); 
                        }
                    } else {
                        event.preventDefault(); 
                        clickedParentLink.classList.add('active'); 
                        initiateProductLoad('parentDirect', parentId, clickedParentLink.textContent.trim());
                    }
                });
            });

            dynamicSubcategoryArea.addEventListener('click', function(event) {
                const clickedButton = event.target.closest('.subcategory-button');
                if (!clickedButton) return;

                if (aboutUsBtn && aboutUsBtn.classList.contains('active')) {
                    aboutUsBtn.classList.remove('active');
                }
                if (wallOfLoveBtn && wallOfLoveBtn.classList.contains('active')) {
                    wallOfLoveBtn.classList.remove('active');
                }

                event.preventDefault();

                dynamicSubcategoryArea.querySelectorAll('.subcategory-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                clickedButton.classList.add('active');

                if (clickedButton.classList.contains('all-subcategories-btn')) {
                    const parentId = clickedButton.dataset.parentId;
                    const parentLink = document.querySelector(`.parent-category-link[data-category-id="${parentId}"]`);
                    const title = parentLink ? 'All ' + parentLink.textContent.trim() : 'All Products';
                    initiateProductLoad('allForParent', parentId, title);
                } else {
                    const subcategoryId = clickedButton.dataset.subcategoryId || new URL(clickedButton.href).searchParams.get('category');
                    initiateProductLoad('subcategory', subcategoryId, clickedButton.textContent.trim());
                }
            });

            // Initial load
            if (allParentCategories.length > 0) {
                const firstParentLink = Array.from(parentCategoryLinks).find(link => !link.id || (link.id && link.id !== 'about-us-btn' && link.id !== 'wall-of-love-btn'));
                if (firstParentLink) {
                    firstParentLink.click();
                } else {
                    initiateProductLoad('all', null, 'All Products');
                }
            } else {
                initiateProductLoad('all', null, 'All Products');
            }

            if (aboutUsBtn) {
                aboutUsBtn.addEventListener('click', function(event) {
                    event.preventDefault();

                    // Clear product and subcategory areas
                    productsDisplayArea.innerHTML = '';
                    dynamicSubcategoryArea.innerHTML = '';

                    // Set title
                    if (productSectionTitle) {
                        productSectionTitle.textContent = 'About Us';
                    }

                    // Create and inject About Us content
                    const contentWrapper = document.createElement('div');
                    contentWrapper.style.padding = '1rem';
                    contentWrapper.style.textAlign = 'left';
                    contentWrapper.style.maxWidth = '800px'; // For better readability on wide screens
                    contentWrapper.style.margin = '0 auto';   // Center the content block
                    contentWrapper.innerHTML = aboutUsContentHTML;
                    productsDisplayArea.appendChild(contentWrapper);

                    // Manage active states
                    document.querySelectorAll('.parent-category-link.active, .subcategory-button.active').forEach(activeEl => {
                        activeEl.classList.remove('active');
                    });
                    if (wallOfLoveBtn && wallOfLoveBtn.classList.contains('active')) { // Deactivate WoL if About Us is clicked
                        wallOfLoveBtn.classList.remove('active');
                    }
                    aboutUsBtn.classList.add('active');
                });
            }

            if (wallOfLoveBtn) {
                wallOfLoveBtn.addEventListener('click', function(event) {
                    event.preventDefault();

                    productsDisplayArea.innerHTML = '';
                    dynamicSubcategoryArea.innerHTML = '';
                    if (productSectionTitle) {
                        productSectionTitle.textContent = 'Wall of Love';
                    }

                    // ***** NEW CODE: Add loading spinner *****
                    if (productsDisplayArea) { // Check if productsDisplayArea exists
                        productsDisplayArea.innerHTML = '<div class="col-12 loading" style="text-align: center; padding: 2rem;"><div class="spinner"></div><p style="color: var(--brown-text); margin-top: 1rem;">Loading Wall of Love...</p></div>';
                    }
                    // ***** END OF NEW CODE *****

                    fetch('fetch_wall_of_love_images.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('Error from server:', data.error);
                                // Display error message, replacing spinner
                                if (productsDisplayArea) {
                                    productsDisplayArea.innerHTML = '<div class="col-12 text-center"><p class="text-danger">' + data.error + '</p></div>';
                                }
                            } else if (data.images && data.images.length > 0) {
                                if (productsDisplayArea) { // Check if productsDisplayArea exists
                                     productsDisplayArea.innerHTML = ''; // Clear spinner before adding gallery
                                }

                                const galleryContainer = document.createElement('div');
                                galleryContainer.className = 'wol-gallery-grid col-12';
                                // galleryContainer.style.opacity = 0; // Start hidden for fade-in effect after Masonry
                                
                                data.images.forEach(image => {
                                    if (image && typeof image.imagePath === 'string' && image.imagePath.trim() !== '') {
                                        const galleryItem = document.createElement('div');
                                        galleryItem.className = 'wol-gallery-item';

                                        // Create and add caption if it exists
                                        if (image.caption && image.caption.trim() !== "") {
                                            const captionDiv = document.createElement('div');
                                            captionDiv.textContent = image.caption;
                                            captionDiv.style.fontStyle = 'italic';
                                            captionDiv.style.textAlign = 'center';
                                            captionDiv.style.marginBottom = '5px'; // Adjust as needed
                                            captionDiv.style.fontSize = '1em'; // Change size as needed (e.g., 1em, 14px, etc.)
                                            captionDiv.style.fontWeight = 'bold'; // Make the caption bold
                                            captionDiv.style.color = 'var(--brown-text)'; // Match theme
                                            galleryItem.appendChild(captionDiv);
                                        }
                                        
                                        
                                        const link = document.createElement('a');
                                        link.href = image.imagePath; // Use image.imagePath
                                        link.setAttribute('data-fancybox', 'wall-of-love-gallery');
                                        
                                        const filename = image.imagePath.substring(image.imagePath.lastIndexOf('/') + 1);
                                        link.setAttribute('data-caption', filename); 

                                        const img = document.createElement('img');
                                        img.src = image.imagePath; // Use image.imagePath
                                        img.alt = 'Wall of Love: ' + filename; 
                                        
                                        link.appendChild(img); 
                                        galleryItem.appendChild(link); 
                                        galleryContainer.appendChild(galleryItem);
                                    } else {
                                        console.warn('Skipping invalid image data item:', image);
                                    }
                                });
                                
                                if (productsDisplayArea) { // Check if productsDisplayArea exists
                                    productsDisplayArea.appendChild(galleryContainer);
                                }

                                // Set initial opacity for fade-in effect
                                galleryContainer.style.opacity = 0;

                                // Initialize Masonry after images are loaded
                                imagesLoaded(galleryContainer, function() {
                                    const msnry = new Masonry(galleryContainer, { // Store Masonry instance
                                        itemSelector: '.wol-gallery-item',
                                        columnWidth: '.wol-gallery-item', // Using item itself for column width definition
                                        percentPosition: true,
                                        gutter: 10
                                    });
                                    // Explicitly layout Masonry before fade-in
                                    msnry.layout(); 
                                    // Fade in the gallery smoothly
                                    galleryContainer.style.transition = 'opacity 0.5s ease-in-out';
                                    galleryContainer.style.opacity = 1;
                                });

                            } else {
                                // No images, replace spinner with message
                                 if (productsDisplayArea) { // Check if productsDisplayArea exists
                                    productsDisplayArea.innerHTML = '<div class="col-12 text-center"><p class="text-muted">Our Wall of Love is growing! Check back soon for beautiful creations.</p></div>';
                                 }
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            // Display error message, replacing spinner
                            if (productsDisplayArea) { // Check if productsDisplayArea exists
                                productsDisplayArea.innerHTML = '<div class="col-12 text-center"><p class="text-danger">Could not load Wall of Love images. Please try again later.</p></div>';
                            }
                        });

                    document.querySelectorAll('.parent-category-link.active, .subcategory-button.active').forEach(activeEl => {
                        activeEl.classList.remove('active');
                    });
                     if (aboutUsBtn && aboutUsBtn.classList.contains('active')) { // Deactivate About Us if WoL is clicked
                        aboutUsBtn.classList.remove('active');
                    }
                    wallOfLoveBtn.classList.add('active');
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
</body>
</html>

