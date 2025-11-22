<?php
/**
 * Streams API Endpoint
 * Manages stream CRUD operations and controls
 *
 * Actions:
 * - list: Get all streams with optional filters
 * - get: Get single stream by ID
 * - create: Create new stream
 * - update: Update existing stream
 * - delete: Delete stream
 * - mass_delete: Delete multiple streams
 * - start: Start a stream
 * - stop: Stop a stream
 * - mass_start: Start multiple streams
 * - mass_stop: Stop multiple streams
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../functions.php';

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get all streams with optional filters
            $running = $_GET['running'] ?? null;
            $search = $_GET['search'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = Stream::with('category');

            // Apply filters
            if ($running !== null) {
                if ($running == '1') {
                    $query->where('status', '=', 1);
                } elseif ($running == '2') {
                    $query->where('status', '=', 2);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('streamurl', 'LIKE', "%{$search}%"); // Database column is 'streamurl'
                });
            }

            // Get total count
            $total = $query->count();

            // Paginate
            $streams = $query->skip(($page - 1) * $perPage)
                             ->take($perPage)
                             ->get();

            // Format streams
            $formattedStreams = $streams->map(function($stream) {
                return [
                    'id' => $stream->id,
                    'name' => $stream->name,
                    'stream_source' => $stream->streamurl, // Map from database column 'streamurl'
                    'category' => $stream->category ? $stream->category->name : 'N/A',
                    'category_id' => $stream->cat_id,
                    'status' => $stream->status,
                    'status_label' => $stream->statusLabel,
                    'running' => $stream->running,
                    'created_at' => $stream->created_at,
                    'updated_at' => $stream->updated_at
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formattedStreams,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'get':
            // Get single stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::with('category')->find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $stream->id,
                    'name' => $stream->name,
                    'stream_source' => $stream->streamurl, // Map from database column 'streamurl'
                    'cat_id' => $stream->cat_id,
                    'trans_id' => $stream->trans_id,
                    'status' => $stream->status,
                    'running' => $stream->running,
                    'created_at' => $stream->created_at,
                    'updated_at' => $stream->updated_at
                ]
            ]);
            break;

        case 'create':
            // Create new stream
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['name']) || !isset($input['stream_source'])) {
                throw new Exception('Name and stream source are required');
            }

            $stream = new Stream();
            $stream->name = $input['name'];
            $stream->streamurl = $input['stream_source']; // Database column is 'streamurl'
            $stream->cat_id = $input['cat_id'] ?? 0;
            $stream->trans_id = $input['trans_id'] ?? 0;
            $stream->status = 0;
            $stream->running = 0;
            $stream->save();

            echo json_encode([
                'success' => true,
                'message' => 'Stream created successfully',
                'data' => [
                    'id' => $stream->id
                ]
            ]);
            break;

        case 'update':
            // Update existing stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['name'])) {
                $stream->name = $input['name'];
            }
            if (isset($input['stream_source'])) {
                $stream->streamurl = $input['stream_source']; // Database column is 'streamurl'
            }
            if (isset($input['cat_id'])) {
                $stream->cat_id = $input['cat_id'];
            }
            if (isset($input['trans_id'])) {
                $stream->trans_id = $input['trans_id'];
            }

            $stream->save();

            echo json_encode([
                'success' => true,
                'message' => 'Stream updated successfully'
            ]);
            break;

        case 'delete':
            // Delete stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            // Stop stream if running
            if ($stream->running == 1 || $stream->status == 1) {
                stop_stream($id);
            }

            $stream->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Stream deleted successfully'
            ]);
            break;

        case 'mass_delete':
            // Delete multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream) {
                    // Stop stream if running
                    if ($stream->running == 1 || $stream->status == 1) {
                        stop_stream($id);
                    }
                    $stream->delete();
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) deleted successfully"
            ]);
            break;

        case 'start':
            // Start a stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            start_stream($id);

            echo json_encode([
                'success' => true,
                'message' => 'Stream started successfully'
            ]);
            break;

        case 'stop':
            // Stop a stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            stop_stream($id);

            echo json_encode([
                'success' => true,
                'message' => 'Stream stopped successfully'
            ]);
            break;

        case 'mass_start':
            // Start multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream) {
                    start_stream($id);
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) started successfully"
            ]);
            break;

        case 'mass_stop':
            // Stop multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream) {
                    stop_stream($id);
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) stopped successfully"
            ]);
            break;

        case 'fetch_m3u':
            // Fetch M3U playlist from URL
            $input = json_decode(file_get_contents('php://input'), true);
            $url = $input['url'] ?? null;

            if (!$url) {
                throw new Exception('URL is required');
            }

            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL format');
            }

            // Fetch content using cURL with timeout
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS URLs
            curl_setopt($ch, CURLOPT_USERAGENT, 'FOS-Streaming-v70/1.0');

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($content === false || $httpCode !== 200) {
                throw new Exception('Failed to fetch playlist: ' . ($error ?: "HTTP $httpCode"));
            }

            // Validate it's an M3U file
            if (stripos($content, '#EXTM3U') === false && stripos($content, '#EXTINF') === false) {
                throw new Exception('Invalid M3U playlist format');
            }

            echo json_encode([
                'success' => true,
                'content' => $content
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
