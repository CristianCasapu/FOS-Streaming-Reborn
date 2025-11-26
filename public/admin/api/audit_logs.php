<?php
/**
 * Audit Logs API
 *
 * Manage audit trail records
 */

require_once '../../../config.php';
logincheck();

header('Content-Type: application/json');

// Models are autoloaded via composer
use Carbon\Carbon;

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $page = intval($_GET['page'] ?? 1);
            $perPage = intval($_GET['per_page'] ?? 50);
            $userType = $_GET['user_type'] ?? null;
            $actionFilter = $_GET['action_filter'] ?? null;
            $entityType = $_GET['entity_type'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            $query = AuditLog::query()->orderBy('created_at', 'desc');

            if ($userType) {
                $query->byUserType($userType);
            }
            if ($actionFilter) {
                $query->byAction($actionFilter);
            }
            if ($entityType) {
                $query->byEntityType($entityType);
            }
            if ($startDate && $endDate) {
                $query->dateRange($startDate, $endDate);
            }

            $total = $query->count();
            $logs = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            echo json_encode([
                'success' => true,
                'data' => $logs,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $total > 0 ? ceil($total / $perPage) : 1
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID required');
            }

            $log = AuditLog::find($id);
            if (!$log) {
                throw new Exception('Audit log not found');
            }

            echo json_encode([
                'success' => true,
                'data' => $log
            ]);
            break;

        case 'search':
            $search = $_GET['q'] ?? '';
            $logs = AuditLog::where('description', 'LIKE', "%{$search}%")
                ->orWhere('action', 'LIKE', "%{$search}%")
                ->orWhere('url', 'LIKE', "%{$search}%")
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            echo json_encode([
                'success' => true,
                'data' => $logs
            ]);
            break;

        case 'stats':
            $days = intval($_GET['days'] ?? 30);
            $cutoffDate = Carbon::now()->subDays($days);
            $yesterday = Carbon::now()->subDay();

            // Get total actions in period
            $totalActions = AuditLog::where('created_at', '>=', $cutoffDate)->count();

            // Get success/failed counts
            $successCount = AuditLog::where('created_at', '>=', $cutoffDate)
                ->where('status', 'success')
                ->count();
            $failedCount = AuditLog::where('created_at', '>=', $cutoffDate)
                ->where('status', 'failed')
                ->count();

            // Calculate success rate
            $successRate = $totalActions > 0 ? ($successCount / $totalActions) * 100 : 100;

            // Get last 24h count
            $last24h = AuditLog::where('created_at', '>=', $yesterday)->count();

            $stats = [
                'total_actions' => $totalActions,
                'success_rate' => round($successRate, 1),
                'failed_actions' => $failedCount,
                'last_24h' => $last24h,
                'by_user_type' => AuditLog::where('created_at', '>=', $cutoffDate)
                    ->selectRaw('user_type, COUNT(*) as count')
                    ->groupBy('user_type')
                    ->get(),
                'by_action' => AuditLog::where('created_at', '>=', $cutoffDate)
                    ->selectRaw('action, COUNT(*) as count')
                    ->groupBy('action')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'by_status' => AuditLog::where('created_at', '>=', $cutoffDate)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'recent_failures' => AuditLog::where('status', 'failed')
                    ->where('created_at', '>=', $cutoffDate)
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'delete':
            // Only admins with admin role can delete audit logs
            $admin = Staff::where('username', $_SESSION['user_data']['username'] ?? '')->first();
            if (!$admin || $admin->role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID required');
            }

            AuditLog::destroy($id);

            echo json_encode([
                'success' => true,
                'message' => 'Audit log deleted'
            ]);
            break;

        case 'cleanup':
            // Only admins can cleanup old logs
            $admin = Staff::where('username', $_SESSION['user_data']['username'] ?? '')->first();
            if (!$admin || $admin->role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }

            $days = intval($_GET['days'] ?? 90);
            $deleted = AuditLog::where('created_at', '<', Carbon::now()->subDays($days))->delete();

            echo json_encode([
                'success' => true,
                'message' => "Deleted $deleted old audit logs"
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
