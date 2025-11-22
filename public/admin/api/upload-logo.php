<?php
/**
 * Logo Upload API Endpoint
 * Handles logo image uploads with validation
 */

require_once __DIR__ . '/../../../config.php';

logincheck();
header('Content-Type: application/json');

try {
    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['logo'];

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

    // Validate file size (2MB max)
    $maxSize = 2 * 1024 * 1024; // 2MB in bytes
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds 2MB limit');
    }

    // Validate file type
    $allowedMimes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, WebP, and SVG images are allowed');
    }

    // Validate file extension
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file extension');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../../public/uploads/logos';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename (always .webp for non-SVG images)
    $finalExtension = ($fileExtension === 'svg') ? 'svg' : 'webp';
    $fileName = 'logo_' . time() . '_' . uniqid() . '.' . $finalExtension;
    $uploadPath = $uploadDir . '/' . $fileName;

    // Handle SVG files (no conversion needed)
    if ($fileExtension === 'svg') {
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }
    } else {
        // Convert image to WebP using ImageMagick
        $tempPath = $file['tmp_name'];

        // Try using ImageMagick extension first (if available)
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($tempPath);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality(85);
                $image->writeImage($uploadPath);
                $image->destroy();
            } catch (Exception $e) {
                throw new Exception('Failed to convert image to WebP using Imagick: ' . $e->getMessage());
            }
        } else {
            // Fallback to command-line cwebp or convert
            $convertCmd = null;

            // Try cwebp first
            if (shell_exec('which cwebp 2>/dev/null')) {
                $convertCmd = sprintf(
                    'cwebp -q 85 %s -o %s 2>&1',
                    escapeshellarg($tempPath),
                    escapeshellarg($uploadPath)
                );
            }
            // Try ImageMagick convert
            elseif (shell_exec('which convert 2>/dev/null')) {
                $convertCmd = sprintf(
                    'convert %s -quality 85 %s 2>&1',
                    escapeshellarg($tempPath),
                    escapeshellarg($uploadPath)
                );
            } else {
                throw new Exception('No image conversion tool available. Please install ImageMagick or WebP tools.');
            }

            $output = [];
            $returnCode = 0;
            exec($convertCmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($uploadPath)) {
                throw new Exception('Failed to convert image to WebP: ' . implode("\n", $output));
            }
        }
    }

    // Generate public URL
    $logoUrl = '/uploads/logos/' . $fileName;

    // Update setting in database
    $setting = Setting::first();
    if ($setting) {
        // Delete old logo file if it exists
        if (!empty($setting->logourl) && strpos($setting->logourl, '/uploads/logos/') === 0) {
            $oldLogoPath = __DIR__ . '/../../../public' . $setting->logourl;
            if (file_exists($oldLogoPath)) {
                @unlink($oldLogoPath);
            }
        }

        $setting->logourl = $logoUrl;
        $setting->save();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Logo uploaded successfully',
        'data' => [
            'logo_url' => $logoUrl,
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
