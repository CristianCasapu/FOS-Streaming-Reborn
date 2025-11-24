<?php
/**
 * Subscribers API Endpoint
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

            $query = Subscriber::query();

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
            $subscribers = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $subscribers->map(function($subscriber) {
                return [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username,
                    'email' => $subscriber->email ?? '',
                    'enabled' => $subscriber->enabled,
                    'created_at' => $subscriber->created_at,
                    'updated_at' => $subscriber->updated_at
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
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username,
                    'email' => $subscriber->email,
                    'enabled' => $subscriber->enabled
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['username']) || !isset($input['password'])) {
                throw new Exception('Username and password required');
            }

            $subscriber = new Subscriber();
            $subscriber->username = $input['username'];
            $subscriber->password = password_hash($input['password'], PASSWORD_DEFAULT);
            $subscriber->email = $input['email'] ?? '';
            $subscriber->phone = $input['phone'] ?? '';
            $subscriber->country = $input['country'] ?? '';
            $subscriber->city = $input['city'] ?? '';
            $subscriber->address = $input['address'] ?? '';
            $subscriber->postal_code = $input['postal_code'] ?? '';
            $subscriber->notes = $input['notes'] ?? '';
            $subscriber->enabled = $input['enabled'] ?? 1;
            $subscriber->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscriber created',
                'data' => ['id' => $subscriber->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['username'])) $subscriber->username = $input['username'];
            if (isset($input['email'])) $subscriber->email = $input['email'];
            if (isset($input['phone'])) $subscriber->phone = $input['phone'];
            if (isset($input['country'])) $subscriber->country = $input['country'];
            if (isset($input['city'])) $subscriber->city = $input['city'];
            if (isset($input['address'])) $subscriber->address = $input['address'];
            if (isset($input['postal_code'])) $subscriber->postal_code = $input['postal_code'];
            if (isset($input['notes'])) $subscriber->notes = $input['notes'];
            if (isset($input['enabled'])) $subscriber->enabled = $input['enabled'];
            if (isset($input['password']) && !empty($input['password'])) {
                $subscriber->password = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $subscriber->save();

            echo json_encode(['success' => true, 'message' => 'Subscriber updated']);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $subscriber->delete();
            echo json_encode(['success' => true, 'message' => 'Subscriber deleted']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
