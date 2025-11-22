<?php
/**
 * User Agent Blocks API Endpoint
 * Manages blocked user agents CRUD operations
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

            $query = BlockedUseragent::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('useragent', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();
            $useragents = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $useragents->map(function($block) {
                return [
                    'id' => $block->id,
                    'useragent' => $block->useragent,
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
            if (!$id) throw new Exception('User Agent Block ID required');

            $block = BlockedUseragent::find($id);
            if (!$block) throw new Exception('User Agent Block not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $block->id,
                    'useragent' => $block->useragent,
                    'description' => $block->description ?? ''
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['useragent']) || empty($input['useragent'])) {
                throw new Exception('User agent string is required');
            }

            // Check for duplicate
            $exists = BlockedUseragent::where('useragent', '=', $input['useragent'])->count();
            if ($exists > 0) {
                throw new Exception('This user agent is already blocked');
            }

            $block = new BlockedUseragent();
            $block->useragent = $input['useragent'];
            $block->description = $input['description'] ?? '';
            $block->save();

            echo json_encode([
                'success' => true,
                'message' => 'User agent blocked successfully',
                'data' => ['id' => $block->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('User Agent Block ID required');

            $block = BlockedUseragent::find($id);
            if (!$block) throw new Exception('User Agent Block not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['useragent']) && !empty($input['useragent'])) {
                // Check for duplicate (excluding current block)
                $exists = BlockedUseragent::where('useragent', '=', $input['useragent'])
                    ->where('id', '!=', $id)
                    ->count();
                if ($exists > 0) {
                    throw new Exception('This user agent is already blocked');
                }

                $block->useragent = $input['useragent'];
            }

            if (isset($input['description'])) {
                $block->description = $input['description'];
            }

            $block->save();

            echo json_encode(['success' => true, 'message' => 'User agent block updated successfully']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('User Agent Block ID required');

            $block = BlockedUseragent::find($id);
            if (!$block) throw new Exception('User Agent Block not found');

            $block->delete();
            echo json_encode(['success' => true, 'message' => 'User agent block removed successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
