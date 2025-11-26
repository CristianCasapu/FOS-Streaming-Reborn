<?php
/**
 * Secure Stream Access Handler
 *
 * Handles token-based authentication for streaming access.
 * Supports staff (admin preview) and subscriber (customer) access.
 *
 * URL Formats:
 * - /secure/{token}/{stream_id}/index.m3u8 (HLS)
 * - /secure/{token}/{stream_id}/manifest.mpd (DASH)
 * - /secure/{token}/{stream_id} (Direct)
 *
 * Token validation includes:
 * - Expiration check
 * - Revocation check
 * - IP binding (for staff tokens)
 * - Stream access validation (for subscriber tokens)
 */

error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/StreamAuthService.php';

// CORS headers for player compatibility
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Activity tracking
$user_activity_id = 0;
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

/**
 * Clean up activity on connection close
 */
function cleanupActivity()
{
    global $user_activity_id;
    if ($user_activity_id != 0) {
        try {
            $active = Activity::find($user_activity_id);
            if ($active) {
                $active->date_end = date('Y-m-d H:i:s');
                $active->save();
            }
        } catch (Exception $e) {
            // Silently fail
        }
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

register_shutdown_function('cleanupActivity');

/**
 * Send error response
 */
function sendError(int $code, string $message, string $errorCode = 'ERROR')
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $errorCode
    ]);
    exit;
}

/**
 * Log security event
 */
function logSecurityEvent(string $event, array $details = [])
{
    global $user_ip, $user_agent;

    try {
        $db = \Illuminate\Database\Capsule\Manager::connection();
        $db->table('security_events')->insert([
            'event_type' => 'stream_' . $event,
            'severity' => 'info',
            'ip_address' => $user_ip,
            'details' => json_encode(array_merge($details, [
                'user_agent' => $user_agent
            ])),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Table might not exist, silently fail
    }
}

// Parse URL parameters
$token = $_GET['token'] ?? null;
$streamId = isset($_GET['stream']) ? intval($_GET['stream']) : null;
$format = $_GET['format'] ?? 'hls'; // hls, dash, direct

// Validate required parameters
if (!$token || !$streamId) {
    sendError(400, 'Missing token or stream ID', 'MISSING_PARAMS');
}

// Check for blocked IPs
if (BlockedIp::where('ip', '=', $user_ip)->first()) {
    logSecurityEvent('blocked_ip_access', ['stream_id' => $streamId]);
    sendError(403, 'Access denied', 'IP_BLOCKED');
}

// Check for blocked user agents
if (BlockedUseragent::where('name', '=', $user_agent)->first()) {
    logSecurityEvent('blocked_ua_access', ['stream_id' => $streamId]);
    sendError(403, 'Access denied', 'UA_BLOCKED');
}

// Validate token
$streamToken = StreamToken::where('token', $token)
    ->where('stream_id', $streamId)
    ->first();

if (!$streamToken) {
    logSecurityEvent('invalid_token', ['stream_id' => $streamId, 'token_prefix' => substr($token, 0, 8)]);
    sendError(401, 'Invalid token', 'TOKEN_INVALID');
}

// Check if token is revoked
if ($streamToken->is_revoked) {
    logSecurityEvent('revoked_token', ['stream_id' => $streamId, 'token_id' => $streamToken->id]);
    sendError(401, 'Token has been revoked', 'TOKEN_REVOKED');
}

// Check expiration
if (strtotime($streamToken->expires_at) < time()) {
    logSecurityEvent('expired_token', ['stream_id' => $streamId, 'token_id' => $streamToken->id]);
    sendError(401, 'Token has expired', 'TOKEN_EXPIRED');
}

// For staff tokens, validate IP binding
if ($streamToken->type === 'staff' && $streamToken->ip_address) {
    if ($streamToken->ip_address !== $user_ip) {
        logSecurityEvent('ip_mismatch', [
            'stream_id' => $streamId,
            'token_id' => $streamToken->id,
            'expected_ip' => $streamToken->ip_address,
            'actual_ip' => $user_ip
        ]);
        sendError(403, 'Token IP mismatch', 'IP_MISMATCH');
    }
}

// For subscriber tokens, validate subscription access
if ($streamToken->type === 'subscriber') {
    $subscriber = Subscriber::find($streamToken->user_id);
    if (!$subscriber || !$subscriber->enabled) {
        sendError(403, 'Account disabled or not found', 'ACCOUNT_DISABLED');
    }

    if (!$subscriber->hasValidAccess()) {
        sendError(403, 'No active subscription', 'NO_SUBSCRIPTION');
    }

    // Check if subscriber has access to this specific stream
    $streamAuthService = new \App\Services\StreamAuthService();
    $accessCheck = $streamAuthService->validateSubscriberStreamAccess($subscriber->id, $streamId);
    if (!$accessCheck['valid']) {
        logSecurityEvent('unauthorized_stream', [
            'stream_id' => $streamId,
            'subscriber_id' => $subscriber->id,
            'reason' => $accessCheck['error']
        ]);
        sendError(403, $accessCheck['error'], 'NO_STREAM_ACCESS');
    }
}

// Get stream details
$stream = Stream::find($streamId);
if (!$stream) {
    sendError(404, 'Stream not found', 'STREAM_NOT_FOUND');
}

// Update token usage
$streamToken->last_used_at = date('Y-m-d H:i:s');
$streamToken->use_count += 1;
$streamToken->save();

// Get settings
$setting = Setting::first();
$streamsPath = $setting->streams_path ?? __DIR__ . '/../fospackv69/fos/streams';

// Log successful access
logSecurityEvent('stream_access', [
    'stream_id' => $streamId,
    'token_type' => $streamToken->type,
    'user_id' => $streamToken->user_id,
    'format' => $format
]);

// Record activity
try {
    $active = new Activity();
    $active->user_id = $streamToken->user_id ?? 0;
    $active->stream_id = $streamId;
    $active->user_agent = $user_agent;
    $active->user_ip = $user_ip;
    $active->pid = getmypid();
    $active->bandwidth = 0;
    $active->date_start = date('Y-m-d H:i:s');
    $active->save();
    $user_activity_id = $active->id;
} catch (Exception $e) {
    // Activity logging is optional
}

// Determine content path based on format
switch ($format) {
    case 'dash':
        $basePath = "{$streamsPath}/dash/{$streamId}";
        $indexFile = 'manifest.mpd';
        $contentType = 'application/dash+xml';
        break;

    case 'hls':
    default:
        $basePath = "{$streamsPath}/hls/{$streamId}";
        $indexFile = 'index.m3u8';
        $contentType = 'application/vnd.apple.mpegurl';
        break;
}

// Check for specific file request
$file = $_GET['file'] ?? $indexFile;

// Security: prevent directory traversal
$file = basename($file);
$filePath = "{$basePath}/{$file}";

// Check if file exists
if (!file_exists($filePath)) {
    // For live streams that haven't started yet, return appropriate error
    if ($stream->state !== 'running') {
        sendError(503, 'Stream is not currently running', 'STREAM_OFFLINE');
    }
    sendError(404, 'Stream file not found', 'FILE_NOT_FOUND');
}

// Set appropriate content type
if (preg_match('/\.m3u8$/', $file)) {
    header('Content-Type: application/vnd.apple.mpegurl');
} elseif (preg_match('/\.mpd$/', $file)) {
    header('Content-Type: application/dash+xml');
} elseif (preg_match('/\.ts$/', $file)) {
    header('Content-Type: video/mp2t');
} elseif (preg_match('/\.m4s$/', $file)) {
    header('Content-Type: video/iso.segment');
} elseif (preg_match('/\.mp4$/', $file)) {
    header('Content-Type: video/mp4');
} else {
    header('Content-Type: application/octet-stream');
}

// Cache headers for segments
if (preg_match('/\.(ts|m4s)$/', $file)) {
    header('Cache-Control: max-age=86400, public');
} else {
    // No cache for manifest files
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Stream the file
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
