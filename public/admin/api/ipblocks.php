<?php
/**
 * IP Blocks API Endpoint
 * Manages blocked IP addresses CRUD operations
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

            $query = BlockedIp::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('ip', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();
            $ipblocks = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $ipblocks->map(function($block) {
                return [
                    'id' => $block->id,
                    'ip' => $block->ip,
                    'description' => $block->description ?? '',
                    'created_at' => $block->created_at,
                    'updated_at' => $block->updated_at
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
            if (!$id) throw new Exception('IP Block ID required');

            $block = BlockedIp::find($id);
            if (!$block) throw new Exception('IP Block not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $block->id,
                    'ip' => $block->ip,
                    'description' => $block->description ?? ''
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['ip']) || empty($input['ip'])) {
                throw new Exception('IP address is required');
            }

            // Validate IP format
            if (!filter_var($input['ip'], FILTER_VALIDATE_IP) && !preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $input['ip'])) {
                throw new Exception('Invalid IP address format. Use single IP (192.168.1.1) or CIDR notation (192.168.1.0/24)');
            }

            // Check for duplicate
            $exists = BlockedIp::where('ip', '=', $input['ip'])->count();
            if ($exists > 0) {
                throw new Exception('This IP address is already blocked');
            }

            $block = new BlockedIp();
            $block->ip = $input['ip'];
            $block->description = $input['description'] ?? '';
            $block->save();

            echo json_encode([
                'success' => true,
                'message' => 'IP address blocked successfully',
                'data' => ['id' => $block->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('IP Block ID required');

            $block = BlockedIp::find($id);
            if (!$block) throw new Exception('IP Block not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['ip']) && !empty($input['ip'])) {
                // Validate IP format
                if (!filter_var($input['ip'], FILTER_VALIDATE_IP) && !preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $input['ip'])) {
                    throw new Exception('Invalid IP address format');
                }

                // Check for duplicate (excluding current block)
                $exists = BlockedIp::where('ip', '=', $input['ip'])
                    ->where('id', '!=', $id)
                    ->count();
                if ($exists > 0) {
                    throw new Exception('This IP address is already blocked');
                }

                $block->ip = $input['ip'];
            }

            if (isset($input['description'])) {
                $block->description = $input['description'];
            }

            $block->save();

            echo json_encode(['success' => true, 'message' => 'IP block updated successfully']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('IP Block ID required');

            $block = BlockedIp::find($id);
            if (!$block) throw new Exception('IP Block not found');

            $block->delete();
            echo json_encode(['success' => true, 'message' => 'IP block removed successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
