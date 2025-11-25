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

            // Apply filters - use state as single source of truth
            if ($running !== null) {
                if ($running == '1') {
                    $query->whereIn('state', ['running', 'starting']);
                } elseif ($running == '2') {
                    $query->whereIn('state', ['error', 'crashed']);
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
                    'enabled' => (bool) $stream->enabled,
                    'status' => $stream->status,
                    'status_label' => $stream->statusLabel,
                    'state' => $stream->state,
                    'running' => $stream->running,
                    'logo' => $stream->logo, // Picon/channel logo URL
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
                    'enabled' => (bool) $stream->enabled,
                    'status' => $stream->status,
                    'state' => $stream->state,
                    'running' => $stream->running,
                    // M3U_Plus fields
                    'logo' => $stream->logo,
                    'tvid' => $stream->tvid,
                    'xui_id' => $stream->xui_id,
                    'timeshift' => $stream->timeshift,
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
            $stream->streamurl2 = ''; // Optional backup URL
            $stream->streamurl3 = ''; // Optional backup URL
            $stream->cat_id = $input['cat_id'] ?? 0;
            $stream->trans_id = $input['trans_id'] ?? 0;
            $stream->state = 'stopped'; // Use state as single source of truth

            // M3U_Plus fields
            $stream->logo = $input['logo'] ?? '';
            $stream->tvid = $input['tvid'] ?? '';
            $stream->xui_id = $input['xui_id'] ?? '';
            $stream->timeshift = isset($input['timeshift']) && $input['timeshift'] !== null ? (int)$input['timeshift'] : null;

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

            // M3U_Plus fields
            if (isset($input['logo'])) {
                $stream->logo = $input['logo'];
            }
            if (isset($input['tvid'])) {
                $stream->tvid = $input['tvid'];
            }
            if (isset($input['xui_id'])) {
                $stream->xui_id = $input['xui_id'];
            }
            if (array_key_exists('timeshift', $input)) {
                $stream->timeshift = $input['timeshift'] !== null ? (int)$input['timeshift'] : null;
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
            if ($stream->isActive()) {
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
                    if ($stream->isActive()) {
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

        case 'enable':
            // Enable a stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            $stream->enable();

            echo json_encode([
                'success' => true,
                'message' => 'Stream enabled successfully'
            ]);
            break;

        case 'disable':
            // Disable a stream (will be stopped by monitor worker)
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            $stream->disable();

            echo json_encode([
                'success' => true,
                'message' => 'Stream disabled successfully'
            ]);
            break;

        case 'mass_enable':
            // Enable multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream) {
                    $stream->enable();
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) enabled successfully"
            ]);
            break;

        case 'mass_disable':
            // Disable multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream) {
                    $stream->disable();
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) disabled successfully"
            ]);
            break;

        case 'mass_restart':
            // Restart multiple streams
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            $count = 0;
            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if ($stream && $stream->isEnabled()) {
                    $stream->queueCommand('restart');
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) queued for restart"
            ]);
            break;

        case 'enable_all':
            // Enable all streams
            $count = Stream::where('enabled', 0)->update(['enabled' => 1]);

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) enabled successfully"
            ]);
            break;

        case 'disable_all':
            // Disable all streams (will be stopped by monitor worker)
            $count = Stream::where('enabled', 1)->update(['enabled' => 0]);

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) disabled successfully"
            ]);
            break;

        case 'start_all':
            // Start all enabled streams that are not running
            $streams = Stream::where('enabled', 1)
                ->whereNotIn('state', ['running', 'starting'])
                ->get();

            $count = 0;
            foreach ($streams as $stream) {
                $stream->queueCommand('start');
                $count++;
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) queued to start"
            ]);
            break;

        case 'stop_all':
            // Stop all running streams
            $streams = Stream::whereIn('state', ['running', 'starting'])->get();

            $count = 0;
            foreach ($streams as $stream) {
                $stream->queueCommand('stop');
                $count++;
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) queued to stop"
            ]);
            break;

        case 'restart_all':
            // Restart all enabled running streams
            $streams = Stream::where('enabled', 1)
                ->whereIn('state', ['running', 'starting'])
                ->get();

            $count = 0;
            foreach ($streams as $stream) {
                $stream->queueCommand('restart');
                $count++;
            }

            echo json_encode([
                'success' => true,
                'message' => "{$count} stream(s) queued to restart"
            ]);
            break;

        case 'stats':
            // Get stream statistics
            $stats = [
                'total' => Stream::count(),
                'enabled' => Stream::where('enabled', 1)->count(),
                'disabled' => Stream::where('enabled', 0)->count(),
                'running' => Stream::whereIn('state', ['running', 'starting'])->count(),
                'stopped' => Stream::where('state', 'stopped')->count(),
                'error' => Stream::whereIn('state', ['error', 'crashed'])->count(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
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

        case 'analyze':
            // Analyze a single stream with FFprobe
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            // Mark as analyzing
            $stream->analysis_status = 'analyzing';
            $stream->save();

            // Analyze stream using FFprobeService
            require_once __DIR__ . '/../../../app/Services/FFprobeService.php';
            $ffprobeService = new \App\Services\FFprobeService();

            // Determine if it's a live stream (default to true)
            $isLive = $stream->stream_type !== 'vod';

            $analysis = $ffprobeService->analyzeStream($stream->streamurl, $isLive);

            // Update stream with analysis results
            $stream->updateFromAnalysis($analysis);

            echo json_encode([
                'success' => true,
                'message' => 'Stream analyzed successfully',
                'data' => [
                    'analysis_status' => $stream->analysis_status,
                    'technical_summary' => $stream->getTechnicalSummary()
                ]
            ]);
            break;

        case 'analyze_batch':
            // Analyze multiple streams in batch
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) {
                throw new Exception('No stream IDs provided');
            }

            // Limit batch size to prevent timeouts
            if (count($ids) > 50) {
                throw new Exception('Maximum 50 streams can be analyzed in one batch');
            }

            require_once __DIR__ . '/../../../app/Services/FFprobeService.php';
            $ffprobeService = new \App\Services\FFprobeService();

            $results = [
                'analyzed' => 0,
                'failed' => 0,
                'skipped' => 0
            ];

            foreach ($ids as $id) {
                $stream = Stream::find($id);
                if (!$stream) {
                    $results['skipped']++;
                    continue;
                }

                // Mark as analyzing
                $stream->analysis_status = 'analyzing';
                $stream->save();

                $isLive = $stream->stream_type !== 'vod';

                try {
                    $analysis = $ffprobeService->analyzeStream($stream->streamurl, $isLive);
                    $stream->updateFromAnalysis($analysis);

                    if ($stream->analysis_status === 'completed') {
                        $results['analyzed']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (Exception $e) {
                    $stream->analysis_status = 'failed';
                    $stream->analysis_error = $e->getMessage();
                    $stream->save();
                    $results['failed']++;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Batch analysis completed",
                'data' => $results
            ]);
            break;

        case 'check_accessibility':
            // Quick check if stream is accessible
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            require_once __DIR__ . '/../../../app/Services/FFprobeService.php';
            $ffprobeService = new \App\Services\FFprobeService();

            $isAccessible = $ffprobeService->isStreamAccessible($stream->streamurl);

            echo json_encode([
                'success' => true,
                'data' => [
                    'accessible' => $isAccessible,
                    'url' => $stream->streamurl
                ]
            ]);
            break;

        case 'get_technical_info':
            // Get technical information for a stream
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $stream->id,
                    'name' => $stream->name,
                    'analysis_status' => $stream->analysis_status,
                    'analysis_status_label' => $stream->analysisStatusLabel,
                    'last_analyzed' => $stream->last_analyzed,
                    'technical_summary' => $stream->getTechnicalSummary(),
                    'recommended_settings' => $stream->recommended_settings ? json_decode($stream->recommended_settings, true) : null
                ]
            ]);
            break;

        case 'preview_urls':
            // Get preview URLs for a stream in all available formats
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            // Check if stream is running
            if (!$stream->isActive()) {
                throw new Exception('Stream is not running');
            }

            // Get streaming settings
            $setting = Setting::first();
            $streamingPort = $setting->streaming_port ?? 8001;
            // Use the same host that the request came from (server's actual IP/hostname)
            // Remove port from HTTP_HOST if present
            $requestHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $streamingHost = preg_replace('/:\d+$/', '', $requestHost);
            $streamsPath = $setting->streams_path ?: \App\Services\PathDetectionService::detectProjectRoot() . '/fospackv69/fos/streams';

            // Check if HLS/DASH files exist (nginx-rtmp creates nested directories)
            // Format: streams_path/hls/{stream_id}/index.m3u8 (nested) or streams_path/hls/{stream_id}_.m3u8 (flat)
            $hlsNestedFile = "{$streamsPath}/hls/{$stream->id}/index.m3u8";
            $hlsFlatFile = "{$streamsPath}/hls/{$stream->id}_.m3u8";
            $dashNestedFile = "{$streamsPath}/dash/{$stream->id}/index.mpd";

            $hlsExists = file_exists($hlsNestedFile) || file_exists($hlsFlatFile);
            $dashExists = file_exists($dashNestedFile);

            // Generate preview URLs
            $previewUrls = [];

            // HLS via nginx streaming server (preferred)
            if ($hlsExists) {
                if (file_exists($hlsNestedFile)) {
                    $previewUrls['hls'] = "http://{$streamingHost}:{$streamingPort}/hls/{$stream->id}/index.m3u8";
                } else {
                    $previewUrls['hls'] = "http://{$streamingHost}:{$streamingPort}/hls/{$stream->id}_.m3u8";
                }
            }

            // DASH via nginx streaming server
            if ($dashExists) {
                $previewUrls['dash'] = "http://{$streamingHost}:{$streamingPort}/dash/{$stream->id}/index.mpd";
            }

            // Direct source as fallback (if HTTP/HTTPS)
            if ($stream->streamurl && (strpos($stream->streamurl, 'http://') === 0 || strpos($stream->streamurl, 'https://') === 0)) {
                $previewUrls['direct'] = $stream->streamurl;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'stream_id' => $stream->id,
                    'stream_name' => $stream->name,
                    'urls' => $previewUrls,
                    'hls_exists' => $hlsExists,
                    'dash_exists' => $dashExists,
                    'recommended_format' => $hlsExists ? 'hls' : ($dashExists ? 'dash' : 'direct'),
                    'debug' => [
                        'streams_path' => $streamsPath,
                        'hls_nested_path' => $hlsNestedFile,
                        'dash_nested_path' => $dashNestedFile,
                    ]
                ]
            ]);
            break;

        case 'history':
            // Get stream history including health logs, crashes, and command history
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Stream ID is required');
            }

            $stream = Stream::find($id);
            if (!$stream) {
                throw new Exception('Stream not found');
            }

            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;

            $stateInfo = [
                'state' => $stream->state,
                'state_label' => $stream->stateLabel,
                'enabled' => (bool) $stream->enabled,
                'pid' => $stream->pid,
                'crash_count' => $stream->crash_count ?? 0,
                'restart_attempts' => $stream->restart_attempts ?? 0,
                'max_restart_attempts' => $stream->max_restart_attempts ?? 3,
                'auto_restart_enabled' => (bool) ($stream->auto_restart_enabled ?? true),
                'last_crash_at' => $stream->last_crash_at,
                'last_command_at' => $stream->last_command_at,
                'last_command_result' => $stream->last_command_result,
                'scheduled_command' => $stream->scheduled_command,
                'stream_started_at' => $stream->stream_started_at,
                'stream_stopped_at' => $stream->stream_stopped_at,
                'current_uptime' => $stream->current_uptime,
                'total_uptime' => $stream->total_uptime,
                'analysis_status' => $stream->analysis_status,
                'analysis_error' => $stream->analysis_error,
                'last_analyzed' => $stream->last_analyzed,
                'last_health_check' => $stream->last_health_check,
                'health_check_failures' => $stream->health_check_failures ?? 0,
            ];

            $totalLogs = StreamHealthLog::where('stream_id', $id)->count();
            $healthLogs = StreamHealthLog::where('stream_id', $id)
                ->orderBy('checked_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'check_type' => $log->check_type,
                        'status' => $log->status,
                        'pid' => $log->pid,
                        'pid_exists' => (bool) $log->pid_exists,
                        'action_taken' => $log->action_taken,
                        'error_message' => $log->error_message,
                        'checked_at' => $log->checked_at,
                    ];
                });

            $last24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $stats = [
                'total_checks' => $totalLogs,
                'checks_24h' => StreamHealthLog::where('stream_id', $id)->where('checked_at', '>=', $last24h)->count(),
                'failures_24h' => StreamHealthLog::where('stream_id', $id)->where('checked_at', '>=', $last24h)->where('status', 'failed')->count(),
                'crashes_24h' => StreamHealthLog::where('stream_id', $id)->where('checked_at', '>=', $last24h)->where('check_type', 'pid_check')->where('status', 'failed')->count(),
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'stream_id' => $stream->id,
                    'stream_name' => $stream->name,
                    'state_info' => $stateInfo,
                    'stats' => $stats,
                    'health_logs' => $healthLogs,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalLogs,
                        'total_pages' => ceil($totalLogs / $limit),
                    ],
                ]
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
