<?php
/**
 * Subscriptions API Endpoint
 *
 * Manage subscriber subscriptions with device tracking and security
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
            $status = $_GET['status'] ?? null; // 'active', 'expired', 'expiring'
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

            $query = Subscription::with(['subscriber', 'package']);

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
                      ->where('expire_date', '>', now());
            } elseif ($status === 'expired') {
                $query->where('expire_date', '<=', now());
            } elseif ($status === 'expiring') {
                // Expiring within 7 days
                $query->where('is_active', 1)
                      ->whereBetween('expire_date', [now(), now()->addDays(7)]);
            }

            $total = $query->count();
            $subscriptions = $query->orderBy('expire_date', 'desc')
                                   ->skip(($page - 1) * $perPage)
                                   ->take($perPage)
                                   ->get();

            $formatted = $subscriptions->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'subscriber_id' => $sub->subscriber_id,
                    'subscriber_name' => $sub->subscriber ? $sub->subscriber->username : 'Unknown',
                    'subscriber_email' => $sub->subscriber ? $sub->subscriber->email : '',
                    'package_id' => $sub->package_id,
                    'package_name' => $sub->package ? $sub->package->name : 'Unknown',
                    'device' => $sub->device,
                    'device_mac' => $sub->device_mac,
                    'ip_address' => $sub->ip_address,
                    'isp' => $sub->isp,
                    'last_connected' => $sub->last_connected,
                    'connection_count' => $sub->connection_count,
                    'expire_date' => $sub->expire_date,
                    'is_active' => $sub->is_active,
                    'auto_renew' => $sub->auto_renew,
                    'is_expired' => $sub->isExpired(),
                    'is_valid' => $sub->isValid(),
                    'days_until_expiration' => $sub->days_until_expiration,
                    'is_expiring_soon' => $sub->isExpiringSoon(),
                    'created_at' => $sub->created_at,
                    'updated_at' => $sub->updated_at
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
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::with(['subscriber', 'package'])->find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $subscription->id,
                    'subscriber_id' => $subscription->subscriber_id,
                    'subscriber' => $subscription->subscriber ? [
                        'id' => $subscription->subscriber->id,
                        'username' => $subscription->subscriber->username,
                        'email' => $subscription->subscriber->email,
                        'full_name' => $subscription->subscriber->full_name
                    ] : null,
                    'package_id' => $subscription->package_id,
                    'package' => $subscription->package ? [
                        'id' => $subscription->package->id,
                        'name' => $subscription->package->name,
                        'max_connections' => $subscription->package->max_connections
                    ] : null,
                    'device' => $subscription->device,
                    'device_mac' => $subscription->device_mac,
                    'ip_address' => $subscription->ip_address,
                    'isp' => $subscription->isp,
                    'user_agent' => $subscription->user_agent,
                    'last_connected' => $subscription->last_connected,
                    'connection_count' => $subscription->connection_count,
                    'expire_date' => $subscription->expire_date,
                    'is_active' => $subscription->is_active,
                    'auto_renew' => $subscription->auto_renew,
                    'notes' => $subscription->notes,
                    'is_expired' => $subscription->isExpired(),
                    'is_valid' => $subscription->isValid(),
                    'days_until_expiration' => $subscription->days_until_expiration,
                    'hours_until_expiration' => $subscription->hours_until_expiration,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at
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

            if (!isset($input['expire_date'])) {
                throw new Exception('Expiration date is required');
            }

            // Verify subscriber exists
            $subscriber = Subscriber::find($input['subscriber_id']);
            if (!$subscriber) {
                throw new Exception('Subscriber not found');
            }

            // Verify package exists
            $package = Package::find($input['package_id']);
            if (!$package) {
                throw new Exception('Package not found');
            }

            $subscription = new Subscription();
            $subscription->subscriber_id = $input['subscriber_id'];
            $subscription->package_id = $input['package_id'];
            $subscription->device = $input['device'] ?? null;
            $subscription->device_mac = $input['device_mac'] ?? null;
            $subscription->ip_address = $input['ip_address'] ?? null;
            $subscription->isp = $input['isp'] ?? null;
            $subscription->user_agent = $input['user_agent'] ?? null;
            $subscription->expire_date = $input['expire_date'];
            $subscription->is_active = $input['is_active'] ?? 1;
            $subscription->auto_renew = $input['auto_renew'] ?? 0;
            $subscription->notes = $input['notes'] ?? null;
            $subscription->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscription created successfully',
                'data' => ['id' => $subscription->id]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['package_id'])) {
                $package = Package::find($input['package_id']);
                if (!$package) throw new Exception('Package not found');
                $subscription->package_id = $input['package_id'];
            }

            if (isset($input['device'])) $subscription->device = $input['device'];
            if (isset($input['device_mac'])) $subscription->device_mac = $input['device_mac'];
            if (isset($input['ip_address'])) $subscription->ip_address = $input['ip_address'];
            if (isset($input['isp'])) $subscription->isp = $input['isp'];
            if (isset($input['user_agent'])) $subscription->user_agent = $input['user_agent'];
            if (isset($input['expire_date'])) $subscription->expire_date = $input['expire_date'];
            if (isset($input['is_active'])) $subscription->is_active = $input['is_active'];
            if (isset($input['auto_renew'])) $subscription->auto_renew = $input['auto_renew'];
            if (isset($input['notes'])) $subscription->notes = $input['notes'];
            $subscription->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscription updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $subscription->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Subscription deleted successfully'
            ]);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $subscription->is_active = !$subscription->is_active;
            $subscription->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscription status updated',
                'data' => ['is_active' => $subscription->is_active]
            ]);
            break;

        case 'renew':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $days = $input['days'] ?? 30;

            $subscription->renew($days);

            echo json_encode([
                'success' => true,
                'message' => "Subscription renewed for {$days} days",
                'data' => [
                    'new_expire_date' => $subscription->expire_date,
                    'days_until_expiration' => $subscription->days_until_expiration
                ]
            ]);
            break;

        case 'by_subscriber':
            $subscriberId = $_GET['subscriber_id'] ?? null;
            if (!$subscriberId) throw new Exception('Subscriber ID required');

            $subscriptions = Subscription::with('package')
                ->where('subscriber_id', $subscriberId)
                ->orderBy('expire_date', 'desc')
                ->get();

            $formatted = $subscriptions->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'package_name' => $sub->package ? $sub->package->name : 'Unknown',
                    'device' => $sub->device,
                    'expire_date' => $sub->expire_date,
                    'is_active' => $sub->is_active,
                    'is_valid' => $sub->isValid(),
                    'days_until_expiration' => $sub->days_until_expiration
                ];
            });

            echo json_encode([
                'success' => true,
                'data' => $formatted,
                'total' => $formatted->count()
            ]);
            break;

        case 'record_connection':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $input = json_decode(file_get_contents('php://input'), true);

            $subscription->recordConnection(
                $input['ip_address'] ?? null,
                $input['user_agent'] ?? null,
                $input['isp'] ?? null
            );

            echo json_encode([
                'success' => true,
                'message' => 'Connection recorded',
                'data' => [
                    'last_connected' => $subscription->last_connected,
                    'connection_count' => $subscription->connection_count
                ]
            ]);
            break;

        case 'stats':
            $stats = [
                'total' => Subscription::count(),
                'active' => Subscription::valid()->count(),
                'expired' => Subscription::expired()->count(),
                'expiring_soon' => Subscription::expiringSoon(7)->count(),
                'by_package' => []
            ];

            $packages = Package::all();
            foreach ($packages as $package) {
                $stats['by_package'][$package->name] = [
                    'total' => $package->subscriptions()->count(),
                    'active' => $package->subscriptions()->valid()->count()
                ];
            }

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
