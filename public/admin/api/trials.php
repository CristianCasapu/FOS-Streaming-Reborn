<?php
/**
 * Trials API Endpoint
 *
 * Manage time-limited trial subscriptions
 */

require_once __DIR__ . '/../../../config.php';
logincheck();
header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? null;
            $subscriberId = $_GET['subscriber_id'] ?? null;
            $packageId = $_GET['package_id'] ?? null;
            $status = $_GET['status'] ?? null; // 'active', 'expired', 'converted'
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = Trial::with(['subscriber', 'package']);

            // Search by subscriber username/email
            if ($search) {
                $query->whereHas('subscriber', function($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            if ($subscriberId) {
                $query->where('subscriber_id', $subscriberId);
            }

            if ($packageId) {
                $query->where('package_id', $packageId);
            }

            // Filter by status
            if ($status === 'active') {
                $query->where('is_active', 1)
                      ->where('expires_at', '>', now());
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            } elseif ($status === 'converted') {
                $query->where('converted_to_subscription', 1);
            } elseif ($status === 'not_converted') {
                $query->where('converted_to_subscription', 0);
            }

            $total = $query->count();
            $trials = $query->orderBy('expires_at', 'desc')
                            ->skip(($page - 1) * $perPage)
                            ->take($perPage)
                            ->get();

            $formatted = $trials->map(function($trial) {
                return [
                    'id' => $trial->id,
                    'subscriber_id' => $trial->subscriber_id,
                    'subscriber_name' => $trial->subscriber ? $trial->subscriber->username : 'Unknown',
                    'subscriber_email' => $trial->subscriber ? $trial->subscriber->email : '',
                    'package_id' => $trial->package_id,
                    'package_name' => $trial->package ? $trial->package->name : 'Unknown',
                    'device' => $trial->device,
                    'device_mac' => $trial->device_mac,
                    'ip_address' => $trial->ip_address,
                    'isp' => $trial->isp,
                    'started_at' => $trial->started_at,
                    'trial_duration_hours' => $trial->trial_duration_hours,
                    'expires_at' => $trial->expires_at,
                    'last_connected' => $trial->last_connected,
                    'connection_count' => $trial->connection_count,
                    'is_active' => $trial->is_active,
                    'converted_to_subscription' => $trial->converted_to_subscription,
                    'is_expired' => $trial->isExpired(),
                    'is_valid' => $trial->isValid(),
                    'remaining_hours' => $trial->remaining_hours,
                    'remaining_time' => $trial->remaining_time,
                    'progress_percentage' => $trial->progress_percentage,
                    'created_at' => $trial->created_at,
                    'updated_at' => $trial->updated_at
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
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::with(['subscriber', 'package'])->find($id);
            if (!$trial) throw new Exception('Trial not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $trial->id,
                    'subscriber_id' => $trial->subscriber_id,
                    'subscriber' => $trial->subscriber ? [
                        'id' => $trial->subscriber->id,
                        'username' => $trial->subscriber->username,
                        'email' => $trial->subscriber->email,
                        'full_name' => $trial->subscriber->full_name
                    ] : null,
                    'package_id' => $trial->package_id,
                    'package' => $trial->package ? [
                        'id' => $trial->package->id,
                        'name' => $trial->package->name,
                        'max_connections' => $trial->package->max_connections
                    ] : null,
                    'device' => $trial->device,
                    'device_mac' => $trial->device_mac,
                    'ip_address' => $trial->ip_address,
                    'isp' => $trial->isp,
                    'user_agent' => $trial->user_agent,
                    'started_at' => $trial->started_at,
                    'trial_duration_hours' => $trial->trial_duration_hours,
                    'expires_at' => $trial->expires_at,
                    'last_connected' => $trial->last_connected,
                    'connection_count' => $trial->connection_count,
                    'is_active' => $trial->is_active,
                    'converted_to_subscription' => $trial->converted_to_subscription,
                    'notes' => $trial->notes,
                    'is_expired' => $trial->isExpired(),
                    'is_valid' => $trial->isValid(),
                    'remaining_hours' => $trial->remaining_hours,
                    'remaining_minutes' => $trial->remaining_minutes,
                    'remaining_time' => $trial->remaining_time,
                    'progress_percentage' => $trial->progress_percentage,
                    'created_at' => $trial->created_at,
                    'updated_at' => $trial->updated_at
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['subscriber_id'])) {
                throw new Exception('Subscriber ID is required');
            }

            if (!isset($input['package_id'])) {
                throw new Exception('Package ID is required');
            }

            // Verify subscriber exists
            $subscriber = Subscriber::find($input['subscriber_id']);
            if (!$subscriber) {
                throw new Exception('Subscriber not found');
            }

            // Check if subscriber already has a trial
            if (Trial::where('subscriber_id', $input['subscriber_id'])->exists()) {
                throw new Exception('Subscriber already has a trial. Only one trial per subscriber is allowed.');
            }

            // Verify package exists
            $package = Package::find($input['package_id']);
            if (!$package) {
                throw new Exception('Package not found');
            }

            // Get trial duration from settings or use provided value
            $defaultDuration = Trial::getDefaultDuration();
            $duration = $input['trial_duration_hours'] ?? $defaultDuration;

            $trial = new Trial();
            $trial->subscriber_id = $input['subscriber_id'];
            $trial->package_id = $input['package_id'];
            $trial->device = $input['device'] ?? null;
            $trial->device_mac = $input['device_mac'] ?? null;
            $trial->ip_address = $input['ip_address'] ?? null;
            $trial->isp = $input['isp'] ?? null;
            $trial->user_agent = $input['user_agent'] ?? null;
            $trial->started_at = $input['started_at'] ?? now();
            $trial->trial_duration_hours = $duration;
            $trial->is_active = $input['is_active'] ?? 1;
            $trial->notes = $input['notes'] ?? null;
            $trial->save();

            echo json_encode([
                'success' => true,
                'message' => 'Trial created successfully',
                'data' => [
                    'id' => $trial->id,
                    'expires_at' => $trial->expires_at,
                    'trial_duration_hours' => $trial->trial_duration_hours
                ]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['package_id'])) {
                $package = Package::find($input['package_id']);
                if (!$package) throw new Exception('Package not found');
                $trial->package_id = $input['package_id'];
            }

            if (isset($input['device'])) $trial->device = $input['device'];
            if (isset($input['device_mac'])) $trial->device_mac = $input['device_mac'];
            if (isset($input['ip_address'])) $trial->ip_address = $input['ip_address'];
            if (isset($input['isp'])) $trial->isp = $input['isp'];
            if (isset($input['user_agent'])) $trial->user_agent = $input['user_agent'];
            if (isset($input['trial_duration_hours'])) $trial->trial_duration_hours = $input['trial_duration_hours'];
            if (isset($input['is_active'])) $trial->is_active = $input['is_active'];
            if (isset($input['notes'])) $trial->notes = $input['notes'];
            $trial->save();

            echo json_encode([
                'success' => true,
                'message' => 'Trial updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $trial->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Trial deleted successfully'
            ]);
            break;

        case 'activate':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $trial->is_active = 1;
            $trial->save();

            echo json_encode([
                'success' => true,
                'message' => 'Trial activated'
            ]);
            break;

        case 'deactivate':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $trial->is_active = 0;
            $trial->save();

            echo json_encode([
                'success' => true,
                'message' => 'Trial deactivated'
            ]);
            break;

        case 'extend':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $hours = $input['hours'] ?? 24;

            $trial->extendTrial($hours);

            echo json_encode([
                'success' => true,
                'message' => "Trial extended by {$hours} hours",
                'data' => [
                    'new_duration' => $trial->trial_duration_hours,
                    'new_expires_at' => $trial->expires_at,
                    'remaining_hours' => $trial->remaining_hours
                ]
            ]);
            break;

        case 'convert':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            if ($trial->converted_to_subscription) {
                throw new Exception('Trial has already been converted to subscription');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $expireDate = $input['expire_date'] ?? now()->addDays(30);
            $autoRenew = $input['auto_renew'] ?? false;

            $subscription = $trial->convertToSubscription($expireDate, $autoRenew);

            if (!$subscription) {
                throw new Exception('Failed to convert trial to subscription');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Trial converted to subscription successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'expire_date' => $subscription->expire_date
                ]
            ]);
            break;

        case 'by_subscriber':
            $subscriberId = $_GET['subscriber_id'] ?? null;
            if (!$subscriberId) throw new Exception('Subscriber ID required');

            $trial = Trial::with('package')
                ->where('subscriber_id', $subscriberId)
                ->first();

            if (!$trial) {
                echo json_encode([
                    'success' => true,
                    'data' => null,
                    'message' => 'No trial found for this subscriber'
                ]);
                break;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $trial->id,
                    'package_name' => $trial->package ? $trial->package->name : 'Unknown',
                    'device' => $trial->device,
                    'started_at' => $trial->started_at,
                    'expires_at' => $trial->expires_at,
                    'is_active' => $trial->is_active,
                    'is_valid' => $trial->isValid(),
                    'remaining_time' => $trial->remaining_time,
                    'progress_percentage' => $trial->progress_percentage,
                    'converted_to_subscription' => $trial->converted_to_subscription
                ]
            ]);
            break;

        case 'record_connection':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Trial ID required');

            $trial = Trial::find($id);
            if (!$trial) throw new Exception('Trial not found');

            $input = json_decode(file_get_contents('php://input'), true);

            $trial->recordConnection(
                $input['ip_address'] ?? null,
                $input['user_agent'] ?? null,
                $input['isp'] ?? null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Connection recorded',
                'data' => [
                    'last_connected' => $trial->last_connected,
                    'connection_count' => $trial->connection_count
                ]
            ]);
            break;

        case 'stats':
            $stats = [
                'total' => Trial::count(),
                'active' => Trial::valid()->count(),
                'expired' => Trial::expired()->count(),
                'converted' => Trial::converted()->count(),
                'not_converted' => Trial::notConverted()->count(),
                'by_package' => []
            ];

            $packages = Package::all();
            foreach ($packages as $package) {
                $stats['by_package'][$package->name] = [
                    'total' => $package->trials()->count(),
                    'active' => $package->trials()->valid()->count(),
                    'converted' => $package->trials()->converted()->count()
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'settings':
            // Get trial system settings
            $settings = Setting::first();

            echo json_encode([
                'success' => true,
                'data' => [
                    'trial_duration_hours' => $settings->trial_duration_hours ?? 24,
                    'trial_enabled' => $settings->trial_enabled ?? 1,
                    'trial_requires_approval' => $settings->trial_requires_approval ?? 0,
                    'max_trials_per_user' => $settings->max_trials_per_user ?? 1
                ]
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
