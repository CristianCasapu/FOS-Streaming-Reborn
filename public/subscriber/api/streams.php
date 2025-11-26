<?php
/**
 * Subscriber Streams API
 *
 * Provides secure streaming access for authenticated subscribers.
 * Validates subscription/trial access and generates token-authenticated URLs.
 *
 * Actions:
 * - list: Get streams accessible to subscriber (based on bouquets)
 * - get_secure_url: Generate secure streaming URL with token
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../app/Services/StreamAuthService.php';

// Set JSON response header
header('Content-Type: application/json');

/**
 * Check subscriber authentication
 * Returns subscriber object or sends error response
 */
function checkSubscriberAuth()
{
    if (!isset($_SESSION['subscriber_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Not authenticated',
            'code' => 'NOT_AUTHENTICATED'
        ]);
        exit;
    }

    $subscriber = Subscriber::find($_SESSION['subscriber_id']);
    if (!$subscriber) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Subscriber not found',
            'code' => 'SUBSCRIBER_NOT_FOUND'
        ]);
        exit;
    }

    if (!$subscriber->enabled) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Account disabled',
            'code' => 'ACCOUNT_DISABLED'
        ]);
        exit;
    }

    return $subscriber;
}

// Get action from query parameter
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get all streams accessible to this subscriber
            $subscriber = checkSubscriberAuth();

            // Check for valid access (subscription or trial)
            if (!$subscriber->hasValidAccess()) {
                echo json_encode([
                    'success' => true,
                    'data' => [],
                    'message' => 'No active subscription or trial'
                ]);
                break;
            }

            // Get accessible bouquets
            $bouquets = $subscriber->getAccessibleBouquetsAttribute();

            // Collect all stream IDs from bouquets
            $streamIds = [];
            foreach ($bouquets as $bouquet) {
                if ($bouquet->stream_ids) {
                    $ids = json_decode($bouquet->stream_ids, true) ?: [];
                    $streamIds = array_merge($streamIds, $ids);
                }
            }
            $streamIds = array_unique($streamIds);

            if (empty($streamIds)) {
                echo json_encode([
                    'success' => true,
                    'data' => [],
                    'message' => 'No streams available'
                ]);
                break;
            }

            // Get streams
            $streams = Stream::whereIn('id', $streamIds)
                ->where('enabled', 1)
                ->get()
                ->map(function ($stream) {
                    return [
                        'id' => $stream->id,
                        'name' => $stream->name,
                        'logo' => $stream->logo,
                        'category' => $stream->category ? $stream->category->name : null,
                        'state' => $stream->state,
                        'is_running' => $stream->isActive(),
                    ];
                });

            echo json_encode([
                'success' => true,
                'data' => $streams
            ]);
            break;

        case 'get_secure_url':
            // Generate secure streaming URL for a specific stream
            $subscriber = checkSubscriberAuth();

            $streamId = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : null;
            $format = $_GET['format'] ?? 'hls'; // hls, dash, direct

            if (!$streamId) {
                throw new Exception('Stream ID is required');
            }

            // Validate subscriber has access to this stream
            $authService = new \App\Services\StreamAuthService();
            $accessCheck = $authService->validateSubscriberStreamAccess($subscriber->id, $streamId);

            if (!$accessCheck['valid']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => $accessCheck['error'],
                    'code' => 'NO_ACCESS'
                ]);
                exit;
            }

            // Generate secure URLs
            $result = $authService->generateSecureUrls($streamId, 'subscriber', $subscriber->id);

            if (!isset($result['success']) || !$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to generate secure URL');
            }

            // Get settings for URL construction
            $setting = Setting::first();
            $adminPort = $setting->port ?? 7777;
            $requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $adminHost = preg_replace('/:\d+$/', '', $requestHost);
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

            // Build full URL
            $baseUrl = "{$protocol}://{$adminHost}:{$adminPort}";

            $stream = Stream::find($streamId);

            echo json_encode([
                'success' => true,
                'data' => [
                    'stream_id' => $streamId,
                    'stream_name' => $stream->name,
                    'url' => "{$baseUrl}{$result['urls'][$format]}",
                    'format' => $format,
                    'token' => $result['token'],
                    'expires_at' => $result['expires_at'],
                    'all_urls' => [
                        'hls' => "{$baseUrl}{$result['urls']['hls']}",
                        'dash' => "{$baseUrl}{$result['urls']['dash']}",
                        'direct' => "{$baseUrl}{$result['urls']['direct']}",
                    ]
                ]
            ]);
            break;

        case 'categories':
            // Get categories for streams accessible to subscriber
            $subscriber = checkSubscriberAuth();

            if (!$subscriber->hasValidAccess()) {
                echo json_encode([
                    'success' => true,
                    'data' => []
                ]);
                break;
            }

            // Get accessible stream IDs
            $bouquets = $subscriber->getAccessibleBouquetsAttribute();
            $streamIds = [];
            foreach ($bouquets as $bouquet) {
                if ($bouquet->stream_ids) {
                    $ids = json_decode($bouquet->stream_ids, true) ?: [];
                    $streamIds = array_merge($streamIds, $ids);
                }
            }
            $streamIds = array_unique($streamIds);

            // Get unique categories from these streams
            $categoryIds = Stream::whereIn('id', $streamIds)
                ->where('enabled', 1)
                ->whereNotNull('cat_id')
                ->where('cat_id', '>', 0)
                ->pluck('cat_id')
                ->unique()
                ->toArray();

            $categories = Category::whereIn('id', $categoryIds)
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                    ];
                });

            echo json_encode([
                'success' => true,
                'data' => $categories
            ]);
            break;

        case 'by_category':
            // Get streams by category for subscriber
            $subscriber = checkSubscriberAuth();

            $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

            if (!$subscriber->hasValidAccess()) {
                echo json_encode([
                    'success' => true,
                    'data' => []
                ]);
                break;
            }

            // Get accessible stream IDs
            $bouquets = $subscriber->getAccessibleBouquetsAttribute();
            $streamIds = [];
            foreach ($bouquets as $bouquet) {
                if ($bouquet->stream_ids) {
                    $ids = json_decode($bouquet->stream_ids, true) ?: [];
                    $streamIds = array_merge($streamIds, $ids);
                }
            }
            $streamIds = array_unique($streamIds);

            // Filter by category
            $query = Stream::whereIn('id', $streamIds)
                ->where('enabled', 1);

            if ($categoryId) {
                $query->where('cat_id', $categoryId);
            }

            $streams = $query->get()
                ->map(function ($stream) {
                    return [
                        'id' => $stream->id,
                        'name' => $stream->name,
                        'logo' => $stream->logo,
                        'category' => $stream->category ? $stream->category->name : null,
                        'state' => $stream->state,
                        'is_running' => $stream->isActive(),
                    ];
                });

            echo json_encode([
                'success' => true,
                'data' => $streams
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
