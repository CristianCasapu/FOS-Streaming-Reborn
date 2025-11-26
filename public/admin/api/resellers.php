<?php
/**
 * Resellers API Endpoint
 * Handles CRUD operations for reseller management
 */

require_once '../../../config.php';
logincheck(); // Enforce admin authentication

header('Content-Type: application/json');

// Models are autoloaded via composer

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
            $search = $_GET['search'] ?? null;
            $status = $_GET['status'] ?? null;

            $query = Reseller::query()->orderBy('created_at', 'desc');

            // Apply filters
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('company_name', 'LIKE', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $total = $query->count();
            $resellers = $query->skip(($page - 1) * $perPage)
                               ->take($perPage)
                               ->get();

            // Add computed fields
            foreach ($resellers as $reseller) {
                $reseller->subscriber_count = $reseller->subscribers()->count();
                $reseller->active_subscriptions = $reseller->subscribers()
                    ->whereHas('subscriptions', function($q) {
                        $q->where('is_active', true);
                    })->count();
            }

            echo json_encode([
                'success' => true,
                'data' => $resellers,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ],
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $reseller = Reseller::with(['subscribers', 'transactions' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(20);
            }])->findOrFail($id);

            // Add statistics
            $reseller->stats = [
                'total_subscribers' => $reseller->subscribers()->count(),
                'active_subscribers' => $reseller->subscribers()
                    ->whereHas('subscriptions', function($q) {
                        $q->where('is_active', true);
                    })->count(),
                'monthly_earnings' => $reseller->getMonthlyEarnings(),
                'available_balance' => $reseller->available_balance,
                'pending_balance' => $reseller->pending_balance,
            ];

            echo json_encode([
                'success' => true,
                'data' => $reseller,
            ]);
            break;

        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['username', 'email', 'password', 'company_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '{$field}' is required");
                }
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Check for duplicates
            if (Reseller::where('username', $data['username'])->exists()) {
                throw new Exception('Username already exists');
            }
            if (Reseller::where('email', $data['email'])->exists()) {
                throw new Exception('Email already exists');
            }

            // Create reseller
            $reseller = Reseller::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'company_name' => $data['company_name'],
                'commission_rate' => $data['commission_rate'] ?? 10.00,
                'max_subscribers' => $data['max_subscribers'] ?? 100,
                'max_packages' => $data['max_packages'] ?? 10,
                'status' => $data['status'] ?? 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            // Generate API credentials
            $credentials = $reseller->generateApiKey();

            // Log action
            AuditLog::log('reseller_created', 'Reseller', $reseller->id, null, $reseller->toArray(),
                "Created reseller: {$reseller->username}");

            echo json_encode([
                'success' => true,
                'data' => $reseller,
                'api_credentials' => $credentials,
                'message' => 'Reseller created successfully',
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $reseller = Reseller::findOrFail($id);
            $oldValues = $reseller->toArray();

            $data = json_decode(file_get_contents('php://input'), true);

            // Update allowed fields
            $allowed = ['email', 'company_name', 'commission_rate', 'max_subscribers',
                       'max_packages', 'status', 'notes', 'custom_domain', 'brand_name',
                       'theme_settings'];

            $updates = array_intersect_key($data, array_flip($allowed));

            // Validate email if changed
            if (isset($updates['email']) && $updates['email'] !== $reseller->email) {
                if (!filter_var($updates['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                if (Reseller::where('email', $updates['email'])->where('id', '!=', $id)->exists()) {
                    throw new Exception('Email already exists');
                }
            }

            // Handle password update separately
            if (!empty($data['password'])) {
                $updates['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            }

            $reseller->update($updates);
            $reseller->refresh();

            // Log action
            AuditLog::log('reseller_updated', 'Reseller', $reseller->id, $oldValues, $reseller->toArray(),
                "Updated reseller: {$reseller->username}");

            echo json_encode([
                'success' => true,
                'data' => $reseller,
                'message' => 'Reseller updated successfully',
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $reseller = Reseller::findOrFail($id);

            // Check if reseller has active subscribers
            $activeCount = $reseller->subscribers()
                ->whereHas('subscriptions', function($q) {
                    $q->where('is_active', true);
                })->count();

            if ($activeCount > 0) {
                throw new Exception("Cannot delete reseller with {$activeCount} active subscribers. Deactivate or transfer subscribers first.");
            }

            $username = $reseller->username;
            $oldValues = $reseller->toArray();

            $reseller->delete();

            // Log action
            AuditLog::log('reseller_deleted', 'Reseller', $id, $oldValues, null,
                "Deleted reseller: {$username}");

            echo json_encode([
                'success' => true,
                'message' => 'Reseller deleted successfully',
            ]);
            break;

        case 'regenerate_api_key':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $reseller = Reseller::findOrFail($id);
            $credentials = $reseller->generateApiKey();

            // Log action
            AuditLog::log('reseller_api_key_regenerated', 'Reseller', $reseller->id, null, null,
                "Regenerated API key for reseller: {$reseller->username}");

            echo json_encode([
                'success' => true,
                'data' => $credentials,
                'message' => 'API key regenerated successfully. Save these credentials securely.',
            ]);
            break;

        case 'toggle_api':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $reseller = Reseller::findOrFail($id);
            $reseller->update(['api_enabled' => !$reseller->api_enabled]);

            echo json_encode([
                'success' => true,
                'data' => ['api_enabled' => $reseller->api_enabled],
                'message' => 'API access ' . ($reseller->api_enabled ? 'enabled' : 'disabled'),
            ]);
            break;

        case 'transactions':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
            $type = $_GET['type'] ?? null;

            $reseller = Reseller::findOrFail($id);
            $query = $reseller->transactions()->orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', $type);
            }

            $total = $query->count();
            $transactions = $query->skip(($page - 1) * $perPage)
                                  ->take($perPage)
                                  ->get();

            echo json_encode([
                'success' => true,
                'data' => $transactions,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage),
                ],
            ]);
            break;

        case 'add_commission':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['amount']) || $data['amount'] <= 0) {
                throw new Exception('Valid amount is required');
            }

            $reseller = Reseller::findOrFail($id);
            $transaction = $reseller->addCommission(
                $data['amount'],
                $data['subscription_id'] ?? null,
                $data['description'] ?? 'Manual commission adjustment'
            );

            echo json_encode([
                'success' => true,
                'data' => $transaction,
                'message' => 'Commission added successfully',
            ]);
            break;

        case 'withdraw':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Reseller ID is required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['amount']) || $data['amount'] <= 0) {
                throw new Exception('Valid amount is required');
            }

            $reseller = Reseller::findOrFail($id);

            if ($data['amount'] > $reseller->available_balance) {
                throw new Exception('Insufficient available balance');
            }

            $transaction = $reseller->withdraw($data['amount'], $data['notes'] ?? null);

            echo json_encode([
                'success' => true,
                'data' => $transaction,
                'message' => 'Withdrawal processed successfully',
            ]);
            break;

        case 'stats':
            $stats = [
                'total_resellers' => Reseller::count(),
                'active_resellers' => Reseller::where('status', 'active')->count(),
                'total_commission_paid' => ResellerTransaction::where('type', 'withdrawal')
                    ->where('status', 'completed')->sum('amount'),
                'pending_commissions' => Reseller::sum('pending_balance'),
                'total_subscribers' => Subscriber::whereHas('reseller')->count(),
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats,
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
