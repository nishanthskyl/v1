<?php
$host = 'sql107.infinityfree.com';
$dbname = 'if0_39069958_test1';
$username = 'if0_39069958';
$password = 'ubScQ3khWRN';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Create tables if they don't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id INT DEFAULT NULL,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_path VARCHAR(255),
        category_id INT NOT NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    );

    ALTER TABLE products DROP COLUMN IF EXISTS image_path;

    CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS product_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        option_name VARCHAR(255) NOT NULL,
        option_type ENUM('dropdown', 'radio', 'checkbox', 'text') NOT NULL,
        is_required BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS option_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        option_id INT NOT NULL,
        value VARCHAR(255) NOT NULL,
        display_order INT DEFAULT 0,
        FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS product_option_assignments (
        product_id INT NOT NULL,
        option_id INT NOT NULL,
        PRIMARY KEY (product_id, option_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES product_options(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS product_option_value_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        option_value_id INT NOT NULL,
        is_enabled BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (option_value_id) REFERENCES option_values(id) ON DELETE CASCADE,
        UNIQUE (product_id, option_value_id)
    );
");

// Check and add price_modifier to option_values
$stmtCheckPriceModifier = $pdo->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = ?
    AND TABLE_NAME = 'option_values'
    AND COLUMN_NAME = 'price_modifier'
");
$stmtCheckPriceModifier->execute([$dbname]);
if ($stmtCheckPriceModifier->fetchColumn() == 0) {
    $pdo->exec("ALTER TABLE option_values ADD COLUMN price_modifier DECIMAL(10,2) DEFAULT 0.00 NOT NULL");
}

// Insert default categories if they don't exist
$stmt = $pdo->query("SELECT COUNT(*) FROM categories");
if ($stmt->fetchColumn() == 0) {
    $categories = [
        ['Customised Gifts', null],
        ['Birthday', 1],
        ['Anniversary', 1],
        ['Love Story Frame', 1],
        ['Preservation', null],
        ['Flower/Varmala', 5],
        ['Baby Keepsake', 5],
        ['Other Memorable Things', 5],
        ['Jewellery', null],
        ['Pendant', 9],
        ['Bracelet', 9],
        ['Earings', 9],
        ['Keychains', null],
        ['Alphabets', 13],
        ['Photo Keychains', 13],
        ['Others', null],
        ['Fridge Magnet', 16],
        ['Vane Board', 16],
        ['Clocks', null],
        ['Flower Preservation', 19],
        ['Photo Clocks', 19]
    ];

    $insertCat = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
    foreach ($categories as $category) {
        $parentId = $category[1] !== null ? $category[1] : null;
        $insertCat->execute([$category[0], $parentId]);
    }
}

// Insert default product options if they don't exist
$stmt = $pdo->query("SELECT COUNT(*) FROM product_options");
if ($stmt->fetchColumn() == 0) {
    $options = [
        ['Shape', 'dropdown', 1],
        ['Size', 'dropdown', 2],
        ['Color', 'dropdown', 3],
        ['Light', 'radio', 4],
        ['Frame', 'dropdown', 5]
    ];

    $insertOption = $pdo->prepare("INSERT INTO product_options (option_name, option_type, display_order) VALUES (?, ?, ?)");
    foreach ($options as $option) {
        $insertOption->execute([$option[0], $option[1], $option[2]]);
    }

    // Insert option values
    $optionValues = [
        [1, 'Circle', 1],
        [1, 'Uneven', 2],
        [1, 'Square', 3],
        [1, 'Heart', 4],
        [2, '4', 1],
        [2, '5', 2],
        [2, '6', 3],
        [2, '8', 4],
        [2, '10', 5],
        [2, '12', 6],
        [3, 'Red', 1],
        [3, 'Blue', 2],
        [3, 'Green', 3],
        [3, 'Yellow', 4],
        [3, 'Custom', 5],
        [4, 'Yes', 1],
        [4, 'No', 2],
        [5, 'Wood', 1],
        [5, 'Mould', 2]
    ];

    $woodSizes = ['6x6', '8x8', '10x10', '10x12', '12x12', '12x14', '14x16', 'Customized size'];
    $mouldSizes = ['4', '5', '6', '8', '10', '12'];

    $insertValue = $pdo->prepare("INSERT INTO option_values (option_id, value, display_order) VALUES (?, ?, ?)");
    foreach ($optionValues as $value) {
        $insertValue->execute([$value[0], $value[1], $value[2]]);
    }
}
?>