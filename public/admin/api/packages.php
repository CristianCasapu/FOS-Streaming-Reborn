<?php
/**
 * Packages API Endpoint
 *
 * Manage subscription packages with connection limits and bouquet assignments
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $active = $_GET['active'] ?? null;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
            $withBouquets = isset($_GET['with_bouquets']) && $_GET['with_bouquets'] == '1';

            $query = Package::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            if ($active !== null) {
                $query->where('is_active', $active);
            }

            $total = $query->count();
            $packages = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $packages->map(function($package) use ($withBouquets) {
                $data = [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'max_connections' => $package->max_connections,
                    'price' => $package->price,
                    'duration_days' => $package->duration_days,
                    'is_active' => $package->is_active,
                    'bouquet_count' => $package->bouquets()->count(),
                    'channel_count' => $package->channels->count(),
                    'active_subscriptions_count' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', now())->count(),
                    'active_trials_count' => $package->trials()->where('is_active', 1)->where('expires_at', '>', now())->count(),
                    'created_at' => $package->created_at,
                    'updated_at' => $package->updated_at
                ];

                if ($withBouquets) {
                    $data['bouquets'] = $package->bouquets->map(function($bouquet) {
                        return [
                            'id' => $bouquet->id,
                            'name' => $bouquet->name
                        ];
                    });
                }

                return $data;
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
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $bouquets = $package->bouquets->map(function($bouquet) {
                return [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'description' => $bouquet->description,
                    'channel_count' => $bouquet->channels()->count()
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'max_connections' => $package->max_connections,
                    'price' => $package->price,
                    'duration_days' => $package->duration_days,
                    'is_active' => $package->is_active,
                    'bouquets' => $bouquets,
                    'bouquet_count' => $bouquets->count(),
                    'channel_count' => $package->channels->count(),
                    'active_subscriptions_count' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', now())->count(),
                    'active_trials_count' => $package->trials()->where('is_active', 1)->where('expires_at', '>', now())->count(),
                    'created_at' => $package->created_at,
                    'updated_at' => $package->updated_at
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['name'])) {
                throw new Exception('Package name is required');
            }

            // Check for duplicate name
            if (Package::where('name', $input['name'])->exists()) {
                throw new Exception('A package with this name already exists');
            }

            $package = new Package();
            $package->name = $input['name'];
            $package->description = $input['description'] ?? null;
            $package->max_connections = $input['max_connections'] ?? 1;
            $package->price = $input['price'] ?? null;
            $package->duration_days = $input['duration_days'] ?? null;
            $package->is_active = $input['is_active'] ?? 1;
            $package->save();

            // Assign bouquets if provided
            if (isset($input['bouquet_ids']) && is_array($input['bouquet_ids'])) {
                $package->bouquets()->sync($input['bouquet_ids']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Package created successfully',
                'data' => ['id' => $package->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $input = json_decode(file_get_contents('php://input'), true);

            // Check for duplicate name (excluding current package)
            if (isset($input['name']) && $input['name'] != $package->name) {
                if (Package::where('name', $input['name'])->where('id', '!=', $id)->exists()) {
                    throw new Exception('A package with this name already exists');
                }
            }

            if (isset($input['name'])) $package->name = $input['name'];
            if (isset($input['description'])) $package->description = $input['description'];
            if (isset($input['max_connections'])) $package->max_connections = $input['max_connections'];
            if (isset($input['price'])) $package->price = $input['price'];
            if (isset($input['duration_days'])) $package->duration_days = $input['duration_days'];
            if (isset($input['is_active'])) $package->is_active = $input['is_active'];
            $package->save();

            // Update bouquets if provided
            if (isset($input['bouquet_ids']) && is_array($input['bouquet_ids'])) {
                $package->bouquets()->sync($input['bouquet_ids']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Package updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            // Check if package has active subscriptions or trials
            $activeSubscriptions = $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', now())->count();
            $activeTrials = $package->trials()->where('is_active', 1)->where('expires_at', '>', now())->count();

            if ($activeSubscriptions > 0 || $activeTrials > 0) {
                throw new Exception("Cannot delete package with active subscriptions ({$activeSubscriptions}) or trials ({$activeTrials})");
            }

            $package->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Package deleted successfully'
            ]);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $package->is_active = !$package->is_active;
            $package->save();

            echo json_encode([
                'success' => true,
                'message' => 'Package status updated',
                'data' => ['is_active' => $package->is_active]
            ]);
            break;

        case 'assign_bouquets':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $bouquetIds = $input['bouquet_ids'] ?? [];

            if (!is_array($bouquetIds)) {
                throw new Exception('bouquet_ids must be an array');
            }

            $package->bouquets()->sync($bouquetIds);

            echo json_encode([
                'success' => true,
                'message' => 'Bouquets assigned successfully',
                'data' => [
                    'bouquet_count' => $package->bouquets()->count()
                ]
            ]);
            break;

        case 'remove_bouquet':
            $id = $_GET['id'] ?? null;
            $bouquetId = $_GET['bouquet_id'] ?? null;

            if (!$id) throw new Exception('Package ID required');
            if (!$bouquetId) throw new Exception('Bouquet ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $package->bouquets()->detach($bouquetId);

            echo json_encode([
                'success' => true,
                'message' => 'Bouquet removed from package'
            ]);
            break;

        case 'stats':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Package ID required');

            $package = Package::find($id);
            if (!$package) throw new Exception('Package not found');

            $stats = [
                'total_subscriptions' => $package->subscriptions()->count(),
                'active_subscriptions' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', now())->count(),
                'expired_subscriptions' => $package->subscriptions()->where('expire_date', '<=', now())->count(),
                'total_trials' => $package->trials()->count(),
                'active_trials' => $package->trials()->where('is_active', 1)->where('expires_at', '>', now())->count(),
                'expired_trials' => $package->trials()->where('expires_at', '<=', now())->count(),
                'converted_trials' => $package->trials()->where('converted_to_subscription', 1)->count(),
                'total_bouquets' => $package->bouquets()->count(),
                'total_channels' => $package->channels->count()
            ];

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
