<?php
/**
 * Admin Stream Preview
 *
 * Proxies stream content for admin preview without subscriber auth.
 * Requires admin session to be active.
 *
 * Usage: /admin_preview.php?stream={stream_id}
 */

error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../config.php';

// Check admin authentication (session already started in config.php)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized - Admin login required']);
    exit;
}

// Get stream ID
$stream_id = isset($_GET['stream']) ? intval($_GET['stream']) : 0;

if (!$stream_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Stream ID required']);
    exit;
}

// Get stream
$stream = Stream::find($stream_id);

if (!$stream) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Stream not found']);
    exit;
}

// Check if stream is running
if ($stream->status != 1) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Stream is not running']);
    exit;
}

// Get settings
$setting = Setting::first();
$hlsFolder = $setting->hlsfolder ?? 'hl';

// Check for local HLS buffer first
$hlsFile = __DIR__ . '/' . $hlsFolder . '/' . $stream->id . '_.m3u8';
$legacyHlsFile = '/home/fos-streaming/fos/www/' . $hlsFolder . '/' . $stream->id . '_.m3u8';

// Determine stream URL
$streamUrl = null;

if (file_exists($hlsFile)) {
    // Serve local HLS
    $streamUrl = $hlsFile;
    $isLocal = true;
} elseif (file_exists($legacyHlsFile)) {
    // Serve from legacy path
    $streamUrl = $legacyHlsFile;
    $isLocal = true;
} else {
    // Proxy from source
    $streamUrl = $stream->streamurl;
    if ($stream->checker == 2) {
        $streamUrl = $stream->streamurl2;
    } elseif ($stream->checker == 3) {
        $streamUrl = $stream->streamurl3;
    }
    $isLocal = false;
}

// Set headers for streaming
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// If local HLS file, serve it directly
if ($isLocal) {
    // Detect if requesting .m3u8 or .ts segment
    $segment = isset($_GET['segment']) ? $_GET['segment'] : null;

    if ($segment) {
        // Serve TS segment
        $segmentPath = dirname($streamUrl) . '/' . $segment;
        if (file_exists($segmentPath)) {
            header('Content-Type: video/mp2t');
            readfile($segmentPath);
            exit;
        } else {
            http_response_code(404);
            echo 'Segment not found';
            exit;
        }
    }

    // Serve M3U8 playlist
    header('Content-Type: application/vnd.apple.mpegurl');
    readfile($streamUrl);
    exit;
}

// Proxy from remote source
header("Content-Type: video/mp2t");
ob_end_flush();

// Set up stream context with user agent
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: " . ($setting->user_agent ?? 'FOS-Streaming') . "\r\n",
        'timeout' => 30,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

// Open stream and proxy
$remoteStream = @fopen($streamUrl, 'rb', false, $context);

if (!$remoteStream) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to connect to stream source']);
    exit;
}

// Stream data to client
while (!feof($remoteStream) && connection_status() === CONNECTION_NORMAL) {
    $chunk = fread($remoteStream, 8192);
    if ($chunk !== false) {
        echo $chunk;
        flush();
    }
}

fclose($remoteStream);
