<?php
/**
 * Admins API Endpoint
 * Manages administrator accounts CRUD operations
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

            $query = Staff::query();

            if ($search) {
                $query->where('username', 'LIKE', "%{$search}%");
            }

            $total = $query->count();
            $admins = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $admins->map(function($admin) {
                return [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'created_at' => $admin->created_at,
                    'updated_at' => $admin->updated_at,
                    'is_main' => $admin->id == 1 // Main admin cannot be deleted
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
            if (!$id) throw new Exception('Admin ID required');

            $admin = Staff::find($id);
            if (!$admin) throw new Exception('Admin not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $admin->id,
                    'username' => $admin->username,
                    'is_main' => $admin->id == 1
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['username']) || empty($input['username'])) {
                throw new Exception('Username is required');
            }

            if (!isset($input['password']) || empty($input['password'])) {
                throw new Exception('Password is required');
            }

            // Check for duplicate username
            $exists = Staff::where('username', '=', $input['username'])->count();
            if ($exists > 0) {
                throw new Exception('Username already exists');
            }

            $admin = new Staff();
            $admin->username = $input['username'];
            $admin->password = password_hash($input['password'], PASSWORD_DEFAULT);
            $admin->save();

            echo json_encode([
                'success' => true,
                'message' => 'Admin account created successfully',
                'data' => ['id' => $admin->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Admin ID required');

            $admin = Staff::find($id);
            if (!$admin) throw new Exception('Admin not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['username']) && !empty($input['username'])) {
                // Check for duplicate username (excluding current admin)
                $exists = Staff::where('username', '=', $input['username'])
                    ->where('id', '!=', $id)
                    ->count();
                if ($exists > 0) {
                    throw new Exception('Username already exists');
                }
                $admin->username = $input['username'];
            }

            // Only update password if provided
            if (isset($input['password']) && !empty($input['password'])) {
                $admin->password = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            $admin->save();

            echo json_encode(['success' => true, 'message' => 'Admin account updated successfully']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Admin ID required');

            // Prevent deletion of main admin (ID 1)
            if ($id == 1) {
                throw new Exception('Cannot delete the main administrator account');
            }

            $admin = Staff::find($id);
            if (!$admin) throw new Exception('Admin not found');

            $admin->delete();
            echo json_encode(['success' => true, 'message' => 'Admin account deleted successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
