<?php
/**
 * Users API Endpoint
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $enabled = $_GET['enabled'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = User::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            if ($enabled !== null) {
                $query->where('enabled', $enabled);
            }

            $total = $query->count();
            $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email ?? '',
                    'enabled' => $user->enabled,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
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
            if (!$id) throw new Exception('User ID required');

            $user = User::find($id);
            if (!$user) throw new Exception('User not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'enabled' => $user->enabled
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['username']) || !isset($input['password'])) {
                throw new Exception('Username and password required');
            }

            $user = new User();
            $user->username = $input['username'];
            $user->password = password_hash($input['password'], PASSWORD_DEFAULT);
            $user->email = $input['email'] ?? '';
            $user->enabled = $input['enabled'] ?? 1;
            $user->save();

            echo json_encode([
                'success' => true,
                'message' => 'User created',
                'data' => ['id' => $user->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('User ID required');

            $user = User::find($id);
            if (!$user) throw new Exception('User not found');

            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['username'])) $user->username = $input['username'];
            if (isset($input['email'])) $user->email = $input['email'];
            if (isset($input['enabled'])) $user->enabled = $input['enabled'];
            if (isset($input['password']) && !empty($input['password'])) {
                $user->password = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $user->save();

            echo json_encode(['success' => true, 'message' => 'User updated']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('User ID required');

            $user = User::find($id);
            if (!$user) throw new Exception('User not found');

            $user->delete();
            echo json_encode(['success' => true, 'message' => 'User deleted']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
