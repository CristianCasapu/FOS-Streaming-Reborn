<?php
/**
 * PM2 Workers CRUD API
 *
 * Manages PM2 worker configurations in the database
 *
 * Actions:
 * - list: Get all workers
 * - get: Get single worker by name
 * - create: Create new worker
 * - update: Update existing worker
 * - delete: Delete worker
 * - toggle: Enable/disable worker
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../functions.php';

use App\Services\PM2WorkerService;

// Ensure user is authenticated
logincheck();

// Set JSON response header
header('Content-Type: application/json');

// Get action from query parameter
$action = $_GET['action'] ?? 'list';

try {
    $workerService = new PM2WorkerService();

    switch ($action) {
        case 'list':
            // Get all workers (enabled and disabled)
            $workers = PM2Worker::byPriority()->get();

            echo json_encode([
                'success' => true,
                'data' => $workers->map(function ($worker) {
                    return [
                        'id' => $worker->id,
                        'name' => $worker->name,
                        'display_name' => $worker->display_name,
                        'description' => $worker->description,
                        'script' => $worker->script,
                        'exec_mode' => $worker->exec_mode,
                        'instances' => $worker->instances,
                        'category' => $worker->category,
                        'priority' => $worker->priority,
                        'enabled' => $worker->enabled,
                        'auto_start' => $worker->auto_start,
                        'max_memory_restart' => $worker->max_memory_restart,
                        'max_restarts' => $worker->max_restarts,
                        'min_uptime' => $worker->min_uptime,
                        'cron_restart' => $worker->cron_restart,
                        'log_level' => $worker->log_level,
                        'created_at' => $worker->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $worker->updated_at?->format('Y-m-d H:i:s'),
                    ];
                })
            ]);
            break;

        case 'get':
            // Get single worker by name
            $name = $_GET['name'] ?? null;

            if (!$name) {
                throw new Exception('Worker name is required');
            }

            $worker = $workerService->getWorkerByName($name);

            if (!$worker) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Worker not found'
                ]);
                break;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'display_name' => $worker->display_name,
                    'description' => $worker->description,
                    'script' => $worker->script,
                    'cwd' => $worker->cwd,
                    'args' => $worker->args,
                    'exec_mode' => $worker->exec_mode,
                    'instances' => $worker->instances,
                    'max_memory_restart' => $worker->max_memory_restart,
                    'max_restarts' => $worker->max_restarts,
                    'min_uptime' => $worker->min_uptime,
                    'restart_delay' => $worker->restart_delay,
                    'autorestart' => $worker->autorestart,
                    'cron_restart' => $worker->cron_restart,
                    'log_level' => $worker->log_level,
                    'error_file' => $worker->error_file,
                    'out_file' => $worker->out_file,
                    'log_file' => $worker->log_file,
                    'log_date_format' => $worker->log_date_format,
                    'merge_logs' => $worker->merge_logs,
                    'env_vars' => $worker->env_vars,
                    'watch' => $worker->watch,
                    'ignore_watch' => $worker->ignore_watch,
                    'kill_timeout' => $worker->kill_timeout,
                    'listen_timeout' => $worker->listen_timeout,
                    'shutdown_with_message' => $worker->shutdown_with_message,
                    'enabled' => $worker->enabled,
                    'auto_start' => $worker->auto_start,
                    'priority' => $worker->priority,
                    'category' => $worker->category,
                    'tags' => $worker->tags,
                ]
            ]);
            break;

        case 'create':
            // Create new worker
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                throw new Exception('Invalid request data');
            }

            // Validate worker configuration
            $errors = $workerService->validateWorkerConfig($data);

            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
                break;
            }

            // Check if worker already exists
            $existing = PM2Worker::where('name', $data['name'])->first();
            if ($existing) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Worker with this name already exists'
                ]);
                break;
            }

            // Create worker
            $worker = new PM2Worker();
            $worker->fill($data);
            $worker->created_by = $_SESSION['admin_id'] ?? null;
            $worker->save();

            echo json_encode([
                'success' => true,
                'message' => 'Worker created successfully',
                'data' => [
                    'id' => $worker->id,
                    'name' => $worker->name
                ]
            ]);
            break;

        case 'update':
            // Update existing worker
            $name = $_GET['name'] ?? null;

            if (!$name) {
                throw new Exception('Worker name is required');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                throw new Exception('Invalid request data');
            }

            $worker = PM2Worker::where('name', $name)->first();

            if (!$worker) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Worker not found'
                ]);
                break;
            }

            // Validate worker configuration
            $errors = $workerService->validateWorkerConfig($data);

            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
                break;
            }

            // Update worker
            $worker->fill($data);
            $worker->updated_by = $_SESSION['admin_id'] ?? null;
            $worker->save();

            echo json_encode([
                'success' => true,
                'message' => 'Worker updated successfully'
            ]);
            break;

        case 'delete':
            // Delete worker
            $name = $_GET['name'] ?? null;

            if (!$name) {
                throw new Exception('Worker name is required');
            }

            $worker = PM2Worker::where('name', $name)->first();

            if (!$worker) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Worker not found'
                ]);
                break;
            }

            // Delete worker
            $worker->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Worker deleted successfully'
            ]);
            break;

        case 'toggle':
            // Enable/disable worker
            $name = $_GET['name'] ?? null;

            if (!$name) {
                throw new Exception('Worker name is required');
            }

            $worker = PM2Worker::where('name', $name)->first();

            if (!$worker) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Worker not found'
                ]);
                break;
            }

            // Toggle enabled status
            $worker->enabled = !$worker->enabled;
            $worker->updated_by = $_SESSION['admin_id'] ?? null;
            $worker->save();

            echo json_encode([
                'success' => true,
                'message' => $worker->enabled ? 'Worker enabled' : 'Worker disabled',
                'enabled' => $worker->enabled
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
