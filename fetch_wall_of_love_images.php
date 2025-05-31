<?php
header('Content-Type: application/json');

$imageDir = "uploads/wall_of_love/";
$galleryData = [];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!is_dir($imageDir)) {
    echo json_encode(['error' => 'Image directory not found. Please ensure it exists and is accessible.']);
    exit;
}

if ($dh = opendir($imageDir)) {
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($fileExtension, $allowedExtensions)) {
            $imageFilePath = $imageDir . $file;

            $captionFilename = pathinfo($file, PATHINFO_FILENAME) . ".txt";
            $captionFilePath = $imageDir . $captionFilename;

            $caption = "";
            if (file_exists($captionFilePath)) {
                $caption = trim(file_get_contents($captionFilePath));
            }

            $galleryData[] = [
                'imagePath' => $imageFilePath,
                'caption' => $caption
            ];
        }
    }
    closedir($dh);
} else {
    echo json_encode(['error' => 'Could not open the image directory. Check permissions.']);
    exit;
}

usort($galleryData, function($a, $b) {
    return strcmp($a['imagePath'], $b['imagePath']);
});

echo json_encode(['images' => $galleryData]);
?>