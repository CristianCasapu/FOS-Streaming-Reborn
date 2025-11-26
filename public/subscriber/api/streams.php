<?php
/**
 * Subscriber Streams API
 *
 * Provides secure streaming access for authenticated subscribers.
 * Uses subscription-based access tokens (generated on subscription activation).
 *
 * Actions:
 * - list: Get streams accessible to subscriber (based on bouquets)
 * - get_stream_url: Get subscription-token-based streaming URL
 * - subscriptions: Get subscriber's active subscriptions with access tokens
 * - categories: Get available categories
 * - by_category: Get streams filtered by category
 */

require_once __DIR__ . '/../../../config.php';

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

/**
 * Get base URL for streaming
 */
function getBaseUrl()
{
    $setting = Setting::first();
    $adminPort = $setting->port ?? 7777;
    $requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/:\d+$/', '', $requestHost);
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return "{$protocol}://{$host}:{$adminPort}";
}

/**
 * Find subscription that gives access to a stream
 */
function findSubscriptionForStream(Subscriber $subscriber, int $streamId): ?Subscription
{
    // Check active subscriptions
    $subscriptions = $subscriber->subscriptions()->valid()->get();

    foreach ($subscriptions as $subscription) {
        $bouquets = $subscription->bouquets;
        foreach ($bouquets as $bouquet) {
            if ($bouquet->stream_ids) {
                $streamIds = json_decode($bouquet->stream_ids, true) ?: [];
                if (in_array($streamId, $streamIds)) {
                    return $subscription;
                }
            }
        }
    }

    return null;
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

        case 'get_stream_url':
        case 'get_secure_url': // Backwards compatibility
            // Get subscription-token-based streaming URL for a stream
            $subscriber = checkSubscriberAuth();

            $streamId = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : null;
            $format = $_GET['format'] ?? 'hls';

            if (!$streamId) {
                throw new Exception('Stream ID is required');
            }

            // Find subscription that gives access to this stream
            $subscription = findSubscriptionForStream($subscriber, $streamId);

            if (!$subscription) {
                // Check trial
                $trial = $subscriber->trial;
                if ($trial && $trial->isValid()) {
                    // Trial users can access via trial - generate temporary token
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Trial access not yet implemented for streaming',
                        'code' => 'TRIAL_ACCESS'
                    ]);
                    exit;
                }

                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'No subscription grants access to this stream',
                    'code' => 'NO_ACCESS'
                ]);
                exit;
            }

            // Get or create subscription access token
            $accessToken = $subscription->getOrCreateAccessToken();

            // Get stream details
            $stream = Stream::find($streamId);
            if (!$stream || !$stream->enabled) {
                throw new Exception('Stream not found or disabled');
            }

            // Build URLs
            $baseUrl = getBaseUrl();

            echo json_encode([
                'success' => true,
                'data' => [
                    'stream_id' => $streamId,
                    'stream_name' => $stream->name,
                    'subscription_id' => $subscription->id,
                    'url' => "{$baseUrl}/stream.php?token={$accessToken}&stream={$streamId}&format={$format}",
                    'format' => $format,
                    'token' => $accessToken,
                    'expires_at' => $subscription->expire_date ? $subscription->expire_date->format('Y-m-d H:i:s') : null,
                    'all_urls' => [
                        'hls' => "{$baseUrl}/stream.php?token={$accessToken}&stream={$streamId}&format=hls",
                        'dash' => "{$baseUrl}/stream.php?token={$accessToken}&stream={$streamId}&format=dash",
                    ],
                    'access_info' => [
                        'max_connections' => $subscription->max_concurrent_connections,
                        'current_connections' => $subscription->getActiveConnectionCount(),
                        'package' => $subscription->package ? $subscription->package->name : null,
                    ]
                ]
            ]);
            break;

        case 'subscriptions':
            // Get subscriber's active subscriptions with access tokens
            $subscriber = checkSubscriberAuth();

            $subscriptions = $subscriber->subscriptions()->valid()->with('package')->get();

            $data = $subscriptions->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'package_id' => $sub->package_id,
                    'package_name' => $sub->package ? $sub->package->name : null,
                    'access_token' => $sub->getOrCreateAccessToken(),
                    'expires_at' => $sub->expire_date ? $sub->expire_date->format('Y-m-d H:i:s') : null,
                    'days_remaining' => $sub->days_until_expiration,
                    'max_connections' => $sub->max_concurrent_connections,
                    'current_connections' => $sub->getActiveConnectionCount(),
                    'ip_restricted' => !empty($sub->allowed_ips),
                    'isp_restricted' => !empty($sub->allowed_isps),
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $data
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
