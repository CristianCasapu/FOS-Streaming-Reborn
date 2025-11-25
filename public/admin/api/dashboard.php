<?php
/**
 * Dashboard API Endpoint
 * Provides dashboard statistics and data
 *
 * Actions:
 * - stats: Get dashboard statistics
 * - activities: Get recent activities
 * - streams: Get active streams
 * - categories: Get category distribution
 * - security: Get security overview
 * - system: Get system resources
 */

require_once __DIR__ . '/../../../config.php';

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {
        case 'stats':
            // Get basic statistics
            $stats = [
                'totalStreams' => Stream::count(),
                'onlineStreams' => Stream::whereIn('state', ['running', 'starting'])->count(),
                'offlineStreams' => Stream::whereIn('state', ['stopped', 'error', 'crashed'])->orWhereNull('state')->count(),
                'totalSubscribers' => Subscriber::count(),
                'activeSubscribers' => Subscriber::where('enabled', '=', 1)->count(),
                'inactiveSubscribers' => Subscriber::where('enabled', '=', 0)->count(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'activities':
            // Get recent activities (last 10)
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

            $activities = Activity::with(['user', 'stream'])
                ->orderBy('date_end', 'desc')
                ->limit($limit)
                ->get();

            $formattedActivities = $activities->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'user' => $activity->user ? $activity->user->username : 'N/A',
                    'stream' => $activity->stream ? $activity->stream->name : 'N/A',
                    'date_end' => $activity->date_end,
                    'formatted_date' => date('M d, H:i', strtotime($activity->date_end))
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formattedActivities
            ]);
            break;

        case 'streams':
            // Get active streams (last 5 or specified limit)
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

            $streams = Stream::with('category')
                ->whereIn('state', ['running', 'starting'])
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();

            $formattedStreams = $streams->map(function($stream) {
                return [
                    'id' => $stream->id,
                    'name' => $stream->name,
                    'category' => $stream->category ? $stream->category->name : 'N/A',
                    'running' => $stream->running,
                    'status' => 'LIVE'
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formattedStreams
            ]);
            break;

        case 'categories':
            // Get category distribution
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $totalStreams = Stream::count();

            $categories = Category::withCount('streams')
                ->orderBy('streams_count', 'desc')
                ->limit($limit)
                ->get();

            $formattedCategories = $categories->map(function($category) use ($totalStreams) {
                $percentage = $totalStreams > 0 ? ($category->streams_count / $totalStreams) * 100 : 0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'streams_count' => $category->streams_count,
                    'percentage' => round($percentage, 1)
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'categories' => $formattedCategories,
                    'total_streams' => $totalStreams
                ]
            ]);
            break;

        case 'security':
            // Get security statistics
            $securityStats = [
                'banned_ips' => 0,
                'whitelisted_ips' => 0,
                'recent_events' => 0,
                'failed_logins_1h' => 0
            ];

            try {
                $securityStats['banned_ips'] = BannedIP::where('type', '=', 'banned')
                    ->where(function($query) {
                        $query->where('permanent', '=', 1)
                            ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
                    })
                    ->count();

                $securityStats['whitelisted_ips'] = BannedIP::where('type', '=', 'whitelisted')->count();

                // Check if SecurityEvent model/table exists
                if (class_exists('SecurityEvent')) {
                    $securityStats['recent_events'] = DB::table('security_events')
                        ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-24 hours')))
                        ->count();
                }
            } catch (Exception $e) {
                // Security tables may not exist yet
            }

            echo json_encode([
                'success' => true,
                'data' => $securityStats
            ]);
            break;

        case 'system':
            // Get system resources
            $space_free = round((disk_free_space('/')) / 1048576, 1);
            $space_total = round((disk_total_space('/')) / 1048576, 1);
            $space_used = $space_total - $space_free;
            $space_pr = (int)(100 * ($space_free / $space_total));

            $cpu_pr = 0;
            $mem_pr = 0;
            $mem_used = 0;
            $mem_total = 0;

            if (!stristr(PHP_OS, 'win')) {
                $loads = sys_getloadavg();
                $core_nums = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
                $cpu_pr = round($loads[0] / ($core_nums + 1) * 100, 2);

                $free = shell_exec('free');
                $free = (string)trim($free);
                $free_arr = explode("\n", $free);
                $mem = explode(" ", $free_arr[1]);
                $mem = array_filter($mem);
                $mem = array_merge($mem);
                $mem_used = $mem[2];
                $mem_total = $mem[1];
                $mem_pr = round($mem[2] / $mem[1] * 100, 2);
            }

            $systemStats = [
                'disk' => [
                    'free' => $space_free,
                    'total' => $space_total,
                    'used' => $space_used,
                    'percentage' => $space_pr
                ],
                'cpu' => [
                    'percentage' => $cpu_pr
                ],
                'memory' => [
                    'used' => $mem_used,
                    'total' => $mem_total,
                    'percentage' => $mem_pr
                ]
            ];

            echo json_encode([
                'success' => true,
                'data' => $systemStats
            ]);
            break;

        case 'alerts':
            // Generate system health alerts
            $alerts = [];

            // Get system stats first
            $space_free = round((disk_free_space('/')) / 1048576, 1);
            $space_total = round((disk_total_space('/')) / 1048576, 1);
            $space_pr = (int)(100 * ($space_free / $space_total));

            $cpu_pr = 0;
            $mem_pr = 0;

            if (!stristr(PHP_OS, 'win')) {
                $loads = sys_getloadavg();
                $core_nums = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
                $cpu_pr = round($loads[0] / ($core_nums + 1) * 100, 2);

                $free = shell_exec('free');
                $free = (string)trim($free);
                $free_arr = explode("\n", $free);
                $mem = explode(" ", $free_arr[1]);
                $mem = array_filter($mem);
                $mem = array_merge($mem);
                $mem_pr = round($mem[2] / $mem[1] * 100, 2);
            }

            // Check disk space
            if ($space_pr < 10) {
                $alerts[] = ['type' => 'error', 'message' => 'Critical: Disk space below 10%'];
            } elseif ($space_pr < 20) {
                $alerts[] = ['type' => 'warning', 'message' => 'Warning: Disk space below 20%'];
            }

            // Check CPU
            if ($cpu_pr > 90) {
                $alerts[] = ['type' => 'error', 'message' => 'Critical: CPU usage above 90%'];
            } elseif ($cpu_pr > 75) {
                $alerts[] = ['type' => 'warning', 'message' => 'Warning: CPU usage above 75%'];
            }

            // Check Memory
            if ($mem_pr > 90) {
                $alerts[] = ['type' => 'error', 'message' => 'Critical: Memory usage above 90%'];
            } elseif ($mem_pr > 75) {
                $alerts[] = ['type' => 'warning', 'message' => 'Warning: Memory usage above 75%'];
            }

            echo json_encode([
                'success' => true,
                'data' => $alerts
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
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
