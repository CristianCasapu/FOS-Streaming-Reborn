<?php
/**
 * Subscriber Stream Access Handler
 *
 * Handles subscription-based authentication for streaming access.
 * Uses subscription access tokens (not per-stream tokens).
 *
 * URL Format:
 * /stream.php?token={subscription_token}&stream={stream_id}&format={hls|dash|direct}
 *
 * Or with session ID for heartbeat:
 * /stream.php?token={token}&stream={id}&format=hls&session={session_id}&file={segment.ts}
 *
 * Security Features:
 * - Subscription token validation (expires with subscription)
 * - ISP restriction checking
 * - IP restriction checking
 * - Concurrent connection limit enforcement
 * - Device fingerprinting (optional)
 * - Security event logging
 */

error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../config.php';

// CORS headers for player compatibility
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Connection tracking
$activeConnection = null;
$sessionId = null;

/**
 * Clean up connection on script end
 */
function cleanupConnection()
{
    global $sessionId;
    if ($sessionId) {
        ActiveConnection::heartbeat($sessionId);
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

register_shutdown_function('cleanupConnection');

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
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    try {
        $db = \Illuminate\Database\Capsule\Manager::connection();
        $db->table('security_events')->insert([
            'event_type' => 'subscriber_stream_' . $event,
            'severity' => 'info',
            'ip_address' => $userIp,
            'details' => json_encode(array_merge($details, [
                'user_agent' => $userAgent
            ])),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Table might not exist, silently fail
    }
}

/**
 * Get ISP from IP (simple implementation)
 */
function getIspFromIp(string $ip): ?string
{
    // In production, use a GeoIP database or API
    // For now, return null (no ISP checking unless we implement it)
    return null;
}

// Parse URL parameters
$token = $_GET['token'] ?? null;
$streamId = isset($_GET['stream']) ? intval($_GET['stream']) : null;
$format = $_GET['format'] ?? 'hls';
$sessionId = $_GET['session'] ?? null;
$file = $_GET['file'] ?? null;

$userIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Validate required parameters
if (!$token || !$streamId) {
    sendError(400, 'Missing token or stream ID', 'MISSING_PARAMS');
}

// Check for blocked IPs
if (BlockedIp::where('ip', '=', $userIp)->first()) {
    logSecurityEvent('blocked_ip', ['stream_id' => $streamId]);
    sendError(403, 'Access denied', 'IP_BLOCKED');
}

// Check for blocked user agents
if (BlockedUseragent::where('name', '=', $userAgent)->first()) {
    logSecurityEvent('blocked_ua', ['stream_id' => $streamId]);
    sendError(403, 'Access denied', 'UA_BLOCKED');
}

// Find subscription by access token
$subscription = Subscription::findByAccessToken($token);
if (!$subscription) {
    logSecurityEvent('invalid_token', [
        'stream_id' => $streamId,
        'token_prefix' => substr($token, 0, 16)
    ]);
    sendError(401, 'Invalid or expired subscription token', 'TOKEN_INVALID');
}

// Get subscriber
$subscriber = $subscription->subscriber;
if (!$subscriber || !$subscriber->enabled) {
    sendError(403, 'Account disabled or not found', 'ACCOUNT_DISABLED');
}

// Get ISP (for ISP restriction checking)
$isp = getIspFromIp($userIp);

// Validate subscription access (validity + IP + ISP + concurrent connections)
$accessCheck = $subscription->validateAccess($userIp, $isp);
if (!$accessCheck['valid']) {
    logSecurityEvent('access_denied', [
        'stream_id' => $streamId,
        'subscription_id' => $subscription->id,
        'reason' => $accessCheck['error']
    ]);
    sendError(403, $accessCheck['error'], 'ACCESS_DENIED');
}

// Check if subscriber has access to this specific stream via bouquets
$hasStreamAccess = false;
$bouquets = $subscription->bouquets;
foreach ($bouquets as $bouquet) {
    if ($bouquet->stream_ids) {
        $streamIds = json_decode($bouquet->stream_ids, true) ?: [];
        if (in_array($streamId, $streamIds)) {
            $hasStreamAccess = true;
            break;
        }
    }
}

if (!$hasStreamAccess) {
    logSecurityEvent('unauthorized_stream', [
        'stream_id' => $streamId,
        'subscription_id' => $subscription->id
    ]);
    sendError(403, 'Stream not included in your subscription', 'NO_STREAM_ACCESS');
}

// Get stream details
$stream = Stream::find($streamId);
if (!$stream || !$stream->enabled) {
    sendError(404, 'Stream not found or disabled', 'STREAM_NOT_FOUND');
}

// Handle session/connection tracking
if (!$sessionId) {
    // New connection - register it
    $activeConnection = $subscription->registerConnection(
        $streamId,
        $userIp,
        $userAgent,
        $isp,
        $_GET['fingerprint'] ?? null
    );

    if (!$activeConnection) {
        sendError(429, 'Maximum concurrent connections reached', 'CONNECTION_LIMIT');
    }

    $sessionId = $activeConnection->session_id;
} else {
    // Existing connection - update heartbeat
    if (!ActiveConnection::heartbeat($sessionId)) {
        // Session expired or invalid, create new one
        $activeConnection = $subscription->registerConnection(
            $streamId,
            $userIp,
            $userAgent,
            $isp,
            $_GET['fingerprint'] ?? null
        );

        if (!$activeConnection) {
            sendError(429, 'Maximum concurrent connections reached', 'CONNECTION_LIMIT');
        }

        $sessionId = $activeConnection->session_id;
    }
}

// Update subscription last connection info
$subscription->recordConnection($userIp, $userAgent, $isp);

// Log successful access (only for manifest requests, not segments)
if (!$file || preg_match('/\.(m3u8|mpd)$/', $file)) {
    logSecurityEvent('stream_access', [
        'stream_id' => $streamId,
        'subscription_id' => $subscription->id,
        'subscriber_id' => $subscriber->id,
        'format' => $format,
        'session_id' => $sessionId
    ]);
}

// Get settings
$setting = Setting::first();
$streamsPath = $setting->streams_path ?? __DIR__ . '/../fospackv69/fos/streams';

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
$requestedFile = $file ?? $indexFile;

// Security: prevent directory traversal
$requestedFile = basename($requestedFile);
$filePath = "{$basePath}/{$requestedFile}";

// Check if file exists
if (!file_exists($filePath)) {
    if ($stream->state !== 'running') {
        sendError(503, 'Stream is not currently running', 'STREAM_OFFLINE');
    }
    sendError(404, 'Stream file not found', 'FILE_NOT_FOUND');
}

// For manifest files, rewrite URLs to include session
if (preg_match('/\.m3u8$/', $requestedFile)) {
    $content = file_get_contents($filePath);

    // Rewrite .ts segment URLs to include session
    $baseUrl = "/stream.php?token={$token}&stream={$streamId}&format={$format}&session={$sessionId}&file=";
    $content = preg_replace('/^([^#].+\.ts)$/m', $baseUrl . '$1', $content);

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Session-ID: ' . $sessionId);
    echo $content;
    exit;
}

// Set appropriate content type for other files
if (preg_match('/\.mpd$/', $requestedFile)) {
    header('Content-Type: application/dash+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
} elseif (preg_match('/\.ts$/', $requestedFile)) {
    header('Content-Type: video/mp2t');
    header('Cache-Control: max-age=86400, public');
} elseif (preg_match('/\.m4s$/', $requestedFile)) {
    header('Content-Type: video/iso.segment');
    header('Cache-Control: max-age=86400, public');
} elseif (preg_match('/\.mp4$/', $requestedFile)) {
    header('Content-Type: video/mp4');
    header('Cache-Control: max-age=86400, public');
} else {
    header('Content-Type: application/octet-stream');
}

// Stream the file
header('Content-Length: ' . filesize($filePath));
header('X-Session-ID: ' . $sessionId);
readfile($filePath);
exit;
