<?php
/**
 * Activities API Endpoint
 * Manages streaming activity logs and monitoring
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $streamId = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            $query = Activity::with(['user', 'stream'])
                ->where('date_end', '<>', '0000-00-00 00:00:00')
                ->orderBy('date_start', 'DESC');

            // Filter by user
            if ($userId) {
                $query->where('user_id', '=', $userId);
            }

            // Filter by stream
            if ($streamId) {
                $query->where('stream_id', '=', $streamId);
            }

            // Filter by date range
            if ($dateFrom) {
                $query->where('date_start', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('date_start', '<=', $dateTo . ' 23:59:59');
            }

            // Search in user IP or user agent
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('user_ip', 'LIKE', "%{$search}%")
                      ->orWhere('user_agent', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();
            $activities = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $activities->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'user_id' => $activity->user_id,
                    'username' => $activity->user ? $activity->user->username : 'Unknown',
                    'stream_id' => $activity->stream_id,
                    'stream_name' => $activity->stream ? $activity->stream->name : 'Unknown',
                    'date_start' => $activity->date_start,
                    'date_end' => $activity->date_end,
                    'user_ip' => $activity->user_ip,
                    'user_agent' => $activity->user_agent,
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formatted,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Activity ID required');

            $activity = Activity::with(['user', 'stream'])->find($id);
            if (!$activity) throw new Exception('Activity not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $activity->id,
                    'user_id' => $activity->user_id,
                    'username' => $activity->user ? $activity->user->username : 'Unknown',
                    'stream_id' => $activity->stream_id,
                    'stream_name' => $activity->stream ? $activity->stream->name : 'Unknown',
                    'date_start' => $activity->date_start,
                    'date_end' => $activity->date_end,
                    'user_ip' => $activity->user_ip,
                    'user_agent' => $activity->user_agent,
                ]
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Activity ID required');

            $activity = Activity::find($id);
            if (!$activity) throw new Exception('Activity not found');

            $activity->delete();
            echo json_encode(['success' => true, 'message' => 'Activity log deleted successfully']);
            break;

        case 'delete_all':
            $count = Activity::where('date_end', '<>', '0000-00-00 00:00:00')->count();
            Activity::where('date_end', '<>', '0000-00-00 00:00:00')->delete();

            echo json_encode([
                'success' => true,
                'message' => "Deleted {$count} activity log(s) successfully"
            ]);
            break;

        case 'stats':
            // Get activity statistics
            $totalActivities = Activity::where('date_end', '<>', '0000-00-00 00:00:00')->count();
            $uniqueUsers = Activity::where('date_end', '<>', '0000-00-00 00:00:00')
                ->distinct('user_id')->count('user_id');
            $uniqueStreams = Activity::where('date_end', '<>', '0000-00-00 00:00:00')
                ->distinct('stream_id')->count('stream_id');

            // Recent activities (last 24 hours)
            $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $recentActivities = Activity::where('date_start', '>=', $yesterday)->count();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_activities' => $totalActivities,
                    'unique_users' => $uniqueUsers,
                    'unique_streams' => $uniqueStreams,
                    'recent_24h' => $recentActivities
                ]
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
