<?php
/**
 * Packages API Endpoint
 *
 * Manage subscription packages with connection limits and bouquet assignments
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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
            $packages = $query->with('bouquets')->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $packages->map(function($package) use ($withBouquets) {
                $data = [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'max_concurrent_devices' => $package->max_concurrent_devices,
                    'bandwidth_limit_mbps' => $package->bandwidth_limit_mbps,
                    'video_quality' => $package->video_quality,
                    'allow_recording' => $package->allow_recording,
                    'allow_timeshifting' => $package->allow_timeshifting,
                    'features' => $package->features,
                    'price' => $package->price,
                    'duration_days' => $package->duration_days,
                    'is_active' => $package->is_active,
                    'bouquet_count' => $package->bouquets()->count(),
                    'stream_count' => $package->stream_count,
                    'active_subscriptions_count' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', date('Y-m-d H:i:s'))->count(),
                    'active_trials_count' => $package->trials()->where('is_active', 1)->where('expires_at', '>', date('Y-m-d H:i:s'))->count(),
                    'created_at' => $package->created_at,
                    'updated_at' => $package->updated_at
                ];

                if ($withBouquets) {
                    $data['bouquets'] = $package->bouquets->map(function($bouquet) {
                        return [
                            'id' => $bouquet->id,
                            'name' => $bouquet->name,
                            'stream_count' => $bouquet->stream_count
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

            $package = Package::with('bouquets')->find($id);
            if (!$package) throw new Exception('Package not found');

            $bouquets = $package->bouquets->map(function($bouquet) {
                return [
                    'id' => $bouquet->id,
                    'name' => $bouquet->name,
                    'description' => $bouquet->description,
                    'stream_count' => $bouquet->stream_count
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'max_concurrent_devices' => $package->max_concurrent_devices,
                    'bandwidth_limit_mbps' => $package->bandwidth_limit_mbps,
                    'video_quality' => $package->video_quality,
                    'allow_recording' => $package->allow_recording,
                    'allow_timeshifting' => $package->allow_timeshifting,
                    'features' => $package->features,
                    'price' => $package->price,
                    'duration_days' => $package->duration_days,
                    'is_active' => $package->is_active,
                    'bouquets' => $bouquets,
                    'bouquet_count' => $bouquets->count(),
                    'stream_count' => $package->stream_count,
                    'active_subscriptions_count' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', date('Y-m-d H:i:s'))->count(),
                    'active_trials_count' => $package->trials()->where('is_active', 1)->where('expires_at', '>', date('Y-m-d H:i:s'))->count(),
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
            $package->description = !empty($input['description']) ? $input['description'] : null;
            $package->max_concurrent_devices = !empty($input['max_concurrent_devices']) ? (int)$input['max_concurrent_devices'] : 1;
            $package->bandwidth_limit_mbps = !empty($input['bandwidth_limit_mbps']) ? (int)$input['bandwidth_limit_mbps'] : null;
            $package->video_quality = !empty($input['video_quality']) ? $input['video_quality'] : null;
            $package->allow_recording = isset($input['allow_recording']) ? (bool)$input['allow_recording'] : false;
            $package->allow_timeshifting = isset($input['allow_timeshifting']) ? (bool)$input['allow_timeshifting'] : false;
            $package->features = !empty($input['features']) ? $input['features'] : null;
            $package->price = !empty($input['price']) ? (float)$input['price'] : null;
            $package->duration_days = !empty($input['duration_days']) ? (int)$input['duration_days'] : null;
            $package->is_active = isset($input['is_active']) ? (bool)$input['is_active'] : true;
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
            if (isset($input['description'])) $package->description = !empty($input['description']) ? $input['description'] : null;
            if (isset($input['max_concurrent_devices'])) $package->max_concurrent_devices = !empty($input['max_concurrent_devices']) ? (int)$input['max_concurrent_devices'] : 1;
            if (isset($input['bandwidth_limit_mbps'])) $package->bandwidth_limit_mbps = !empty($input['bandwidth_limit_mbps']) ? (int)$input['bandwidth_limit_mbps'] : null;
            if (isset($input['video_quality'])) $package->video_quality = !empty($input['video_quality']) ? $input['video_quality'] : null;
            if (isset($input['allow_recording'])) $package->allow_recording = (bool)$input['allow_recording'];
            if (isset($input['allow_timeshifting'])) $package->allow_timeshifting = (bool)$input['allow_timeshifting'];
            if (isset($input['features'])) $package->features = !empty($input['features']) ? $input['features'] : null;
            if (isset($input['price'])) $package->price = !empty($input['price']) ? (float)$input['price'] : null;
            if (isset($input['duration_days'])) $package->duration_days = !empty($input['duration_days']) ? (int)$input['duration_days'] : null;
            if (isset($input['is_active'])) $package->is_active = (bool)$input['is_active'];
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
            $activeSubscriptions = $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', date('Y-m-d H:i:s'))->count();
            $activeTrials = $package->trials()->where('is_active', 1)->where('expires_at', '>', date('Y-m-d H:i:s'))->count();

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
                'active_subscriptions' => $package->subscriptions()->where('is_active', 1)->where('expire_date', '>', date('Y-m-d H:i:s'))->count(),
                'expired_subscriptions' => $package->subscriptions()->where('expire_date', '<=', date('Y-m-d H:i:s'))->count(),
                'total_trials' => $package->trials()->count(),
                'active_trials' => $package->trials()->where('is_active', 1)->where('expires_at', '>', date('Y-m-d H:i:s'))->count(),
                'expired_trials' => $package->trials()->where('expires_at', '<=', date('Y-m-d H:i:s'))->count(),
                'converted_trials' => $package->trials()->where('converted_to_subscription', 1)->count(),
                'total_bouquets' => $package->bouquets()->count(),
                'total_streams' => $package->stream_count
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
