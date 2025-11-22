<?php
/**
 * Favicon Upload API Endpoint
 * Handles favicon image uploads with validation
 */

require_once __DIR__ . '/../../../config.php';

logincheck();
header('Content-Type: application/json');

try {
    // Check if file was uploaded
    if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['favicon'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
        throw new Exception($errorMsg);
    }

    // Validate file size (1MB max for favicon)
    $maxSize = 1 * 1024 * 1024; // 1MB in bytes
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 1MB limit');
    }

    // Validate file type
    $allowedMimes = [
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/png',
        'image/jpeg',
        'image/jpg'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type. Only ICO and PNG images are allowed for favicons');
    }

    // Validate file extension
    $allowedExtensions = ['ico', 'png', 'jpg', 'jpeg'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file extension');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../../public/uploads/favicons';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename (always .ico)
    $fileName = 'favicon_' . time() . '_' . uniqid() . '.ico';
    $uploadPath = $uploadDir . '/' . $fileName;

    // Handle ICO files (no conversion needed)
    if ($fileExtension === 'ico') {
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
    } else {
        // Convert image to ICO format
        $tempPath = $file['tmp_name'];

        // Try using ImageMagick extension first (if available)
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($tempPath);

                // Resize to standard favicon sizes and create multi-resolution ICO
                $sizes = [16, 32, 48];
                $images = new Imagick();

                foreach ($sizes as $size) {
                    $resized = clone $image;
                    $resized->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
                    $resized->setImageFormat('ico');
                    $images->addImage($resized);
                    $resized->destroy();
                }

                $images->writeImages($uploadPath, true);
                $images->destroy();
                $image->destroy();
            } catch (Exception $e) {
                throw new Exception('Failed to convert image to ICO using Imagick: ' . $e->getMessage());
            }
        } else {
            // Fallback to command-line ImageMagick convert
            if (shell_exec('which convert 2>/dev/null')) {
                // Create multi-resolution ICO using ImageMagick
                $convertCmd = sprintf(
                    'convert %s -resize 16x16 -resize 32x32 -resize 48x48 %s 2>&1',
                    escapeshellarg($tempPath),
                    escapeshellarg($uploadPath)
                );

                $output = [];
                $returnCode = 0;
                exec($convertCmd, $output, $returnCode);

                if ($returnCode !== 0 || !file_exists($uploadPath)) {
                    // Try simple single-size conversion as fallback
                    $convertCmd = sprintf(
                        'convert %s -resize 32x32 %s 2>&1',
                        escapeshellarg($tempPath),
                        escapeshellarg($uploadPath)
                    );
                    exec($convertCmd, $output, $returnCode);

                    if ($returnCode !== 0 || !file_exists($uploadPath)) {
                        throw new Exception('Failed to convert image to ICO: ' . implode("\n", $output));
                    }
                }
            } else {
                throw new Exception('No image conversion tool available. Please install ImageMagick.');
            }
        }
    }

    // Generate public URL
    $faviconUrl = '/uploads/favicons/' . $fileName;

    // Update setting in database
    $setting = Setting::first();
    if ($setting) {
        // Delete old favicon file if it exists
        if (!empty($setting->faviconurl) && strpos($setting->faviconurl, '/uploads/favicons/') === 0) {
            $oldFaviconPath = __DIR__ . '/../../../public' . $setting->faviconurl;
            if (file_exists($oldFaviconPath)) {
                @unlink($oldFaviconPath);
            }
        }

        $setting->faviconurl = $faviconUrl;
        $setting->save();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Favicon uploaded successfully',
        'data' => [
            'favicon_url' => $faviconUrl,
            'file_name' => $fileName,
            'file_size' => $file['size'],
            'mime_type' => $mimeType
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
