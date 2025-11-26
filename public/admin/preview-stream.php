<?php
/**
 * Staff Stream Preview Handler
 *
 * Internal preview endpoint for staff/admin users.
 * Requires admin session authentication - no token generation needed.
 *
 * URL Format:
 * /admin/preview-stream.php?stream={stream_id}&format={hls|dash}&file={optional_file}
 *
 * Security: Staff must be authenticated via admin session
 */

require_once __DIR__ . '/../../config.php';

// CORS headers for player compatibility
// When using credentials, origin must match exactly (not *)
$origin = '*';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);
    $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost');
    if (isset($parsed['port'])) {
        $origin .= ':' . $parsed['port'];
    }
}
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($origin !== '*') {
    header("Access-Control-Allow-Credentials: true");
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Custom auth check that returns JSON error instead of redirect
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required. Please log in to admin panel first.'
    ]);
    exit;
}

// Release session lock early to allow concurrent requests (important for HLS segment loading)
session_write_close();

/**
 * Send error response
 */
function sendPreviewError(int $code, string $message)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// Parse URL parameters
$streamId = isset($_GET['stream']) ? intval($_GET['stream']) : null;
$format = $_GET['format'] ?? 'hls';
$file = $_GET['file'] ?? null;

// Validate required parameters
if (!$streamId) {
    sendPreviewError(400, 'Stream ID is required');
}

// Get stream details
$stream = Stream::find($streamId);
if (!$stream) {
    sendPreviewError(404, 'Stream not found');
}

// Get settings
$setting = Setting::first();
$streamsPath = $setting->streams_path ?? __DIR__ . '/../../fospackv69/fos/streams';

// Determine content path based on format
switch ($format) {
    case 'dash':
        $basePath = "{$streamsPath}/dash/{$streamId}";
        $indexFile = 'index.mpd';
        break;

    case 'hls':
    default:
        $basePath = "{$streamsPath}/hls/{$streamId}";
        $indexFile = 'index.m3u8';
        break;
}

// Check for specific file request
$requestedFile = $file ?? $indexFile;

// Security: prevent directory traversal
$requestedFile = basename($requestedFile);
$filePath = "{$basePath}/{$requestedFile}";

// Check if file exists
if (!file_exists($filePath)) {
    // For segment files, return plain 404 so player can handle it gracefully
    // (live streams have rolling windows - old segments get deleted)
    if (preg_match('/\.(ts|m4s|m4v|m4a)$/', $requestedFile)) {
        http_response_code(404);
        exit;
    }
    if ($stream->state !== 'running') {
        sendPreviewError(503, 'Stream is not currently running');
    }
    sendPreviewError(404, 'Stream file not found');
}

// For HLS manifest files, rewrite URLs to include preview endpoint
if (preg_match('/\.m3u8$/', $requestedFile)) {
    $content = file_get_contents($filePath);

    // Rewrite .ts segment URLs (.*? to match short filenames like 0.ts)
    $baseUrl = "/admin/preview-stream.php?stream={$streamId}&format={$format}&file=";
    $content = preg_replace('/^([^#].*?\.ts)$/m', $baseUrl . '$1', $content);

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $content;
    exit;
}

// For DASH MPD files, add BaseURL to route segments through preview endpoint
if (preg_match('/\.mpd$/', $requestedFile)) {
    $content = file_get_contents($filePath);

    // Add BaseURL after <MPD ...> tag to redirect segment requests through preview endpoint
    $baseUrl = "/admin/preview-stream.php?stream={$streamId}&format={$format}&file=";
    $content = preg_replace(
        '/(<MPD[^>]*>)/s',
        '$1' . "\n  <BaseURL>{$baseUrl}</BaseURL>",
        $content
    );

    header('Content-Type: application/dash+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $content;
    exit;
}

// Set appropriate content type for segment files
if (preg_match('/\.ts$/', $requestedFile)) {
    header('Content-Type: video/mp2t');
    header('Cache-Control: max-age=86400, public');
} elseif (preg_match('/\.m4s$/', $requestedFile)) {
    header('Content-Type: video/iso.segment');
    header('Cache-Control: max-age=86400, public');
} elseif (preg_match('/\.(m4v|mp4)$/', $requestedFile)) {
    header('Content-Type: video/mp4');
    header('Cache-Control: max-age=86400, public');
} elseif (preg_match('/\.m4a$/', $requestedFile)) {
    header('Content-Type: audio/mp4');
    header('Cache-Control: max-age=86400, public');
} else {
    header('Content-Type: application/octet-stream');
}

// Stream the file
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
