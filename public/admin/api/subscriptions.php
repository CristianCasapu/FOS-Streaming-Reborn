<?php
/**
 * Subscriptions API Endpoint
 * Enhanced with comprehensive data, statistics, bulk operations, and sorting
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
            $status = $_GET['status'] ?? null;
            $autoRenew = $_GET['auto_renew'] ?? null;
            $sortBy = $_GET['sort_by'] ?? 'expire_date';
            $sortOrder = $_GET['sort_order'] ?? 'desc';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 100) : 20;

            $now = date('Y-m-d H:i:s');
            $query = Subscription::with(['subscriber', 'package']);

            // Search by subscriber username/email or notes
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('subscriber', function($sq) use ($search) {
                        $sq->where('username', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%");
                    })
                    ->orWhere('device', 'LIKE', "%{$search}%")
                    ->orWhere('device_mac', 'LIKE', "%{$search}%")
                    ->orWhere('ip_address', 'LIKE', "%{$search}%")
                    ->orWhere('isp', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%");
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
                $query->where('is_active', 1)->where('expire_date', '>', $now);
            } elseif ($status === 'expired') {
                $query->where('expire_date', '<=', $now);
            } elseif ($status === 'expiring') {
                $future = date('Y-m-d H:i:s', strtotime('+7 days'));
                $query->where('is_active', 1)->whereBetween('expire_date', [$now, $future]);
            } elseif ($status === 'inactive') {
                $query->where('is_active', 0);
            }

            // Filter by auto-renew
            if ($autoRenew !== null && $autoRenew !== '') {
                $query->where('auto_renew', $autoRenew);
            }

            // Sorting
            $allowedSorts = ['id', 'expire_date', 'created_at', 'updated_at', 'last_connected', 'connection_count'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            $total = $query->count();
            $subscriptions = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $subscriptions->map(function($sub) use ($now) {
                $isExpired = strtotime($sub->expire_date) <= strtotime($now);
                $daysUntil = max(0, (int)((strtotime($sub->expire_date) - strtotime($now)) / 86400));

                return [
                    'id' => $sub->id,
                    'subscriber_id' => $sub->subscriber_id,
                    'subscriber_name' => $sub->subscriber ? $sub->subscriber->username : 'Unknown',
                    'subscriber_email' => $sub->subscriber ? ($sub->subscriber->email ?? '') : '',
                    'subscriber_enabled' => $sub->subscriber ? (int)$sub->subscriber->enabled : 0,
                    'package_id' => $sub->package_id,
                    'package_name' => $sub->package ? $sub->package->name : 'Unknown',
                    'package_price' => $sub->package ? $sub->package->price : 0,
                    'device' => $sub->device ?? '',
                    'device_mac' => $sub->device_mac ?? '',
                    'device_fingerprint' => $sub->device_fingerprint ?? '',
                    'ip_address' => $sub->ip_address ?? '',
                    'last_ip_address' => $sub->last_ip_address ?? '',
                    'isp' => $sub->isp ?? '',
                    'user_agent' => $sub->user_agent ?? '',
                    'last_connected' => $sub->last_connected,
                    'connection_count' => $sub->connection_count ?? 0,
                    'max_concurrent_connections' => $sub->max_concurrent_connections ?? 1,
                    'current_connections' => $sub->current_connections ?? 0,
                    'expire_date' => $sub->expire_date,
                    'is_active' => (int)$sub->is_active,
                    'auto_renew' => (int)$sub->auto_renew,
                    'notes' => $sub->notes ?? '',
                    'is_expired' => $isExpired,
                    'is_valid' => $sub->is_active && !$isExpired,
                    'days_until_expiration' => $daysUntil,
                    'is_expiring_soon' => !$isExpired && $daysUntil <= 7,
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

            $now = date('Y-m-d H:i:s');
            $isExpired = strtotime($subscription->expire_date) <= strtotime($now);
            $daysUntil = max(0, (int)((strtotime($subscription->expire_date) - strtotime($now)) / 86400));
            $hoursUntil = max(0, (int)((strtotime($subscription->expire_date) - strtotime($now)) / 3600));

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $subscription->id,
                    'subscriber_id' => $subscription->subscriber_id,
                    'subscriber' => $subscription->subscriber ? [
                        'id' => $subscription->subscriber->id,
                        'username' => $subscription->subscriber->username,
                        'email' => $subscription->subscriber->email ?? '',
                        'phone' => $subscription->subscriber->phone ?? '',
                        'country' => $subscription->subscriber->country ?? '',
                        'enabled' => (int)$subscription->subscriber->enabled
                    ] : null,
                    'package_id' => $subscription->package_id,
                    'package' => $subscription->package ? [
                        'id' => $subscription->package->id,
                        'name' => $subscription->package->name,
                        'price' => $subscription->package->price,
                        'duration_days' => $subscription->package->duration_days,
                        'max_concurrent_devices' => $subscription->package->max_concurrent_devices
                    ] : null,
                    'device' => $subscription->device ?? '',
                    'device_mac' => $subscription->device_mac ?? '',
                    'device_fingerprint' => $subscription->device_fingerprint ?? '',
                    'ip_address' => $subscription->ip_address ?? '',
                    'last_ip_address' => $subscription->last_ip_address ?? '',
                    'isp' => $subscription->isp ?? '',
                    'user_agent' => $subscription->user_agent ?? '',
                    'last_connected' => $subscription->last_connected,
                    'connection_count' => $subscription->connection_count ?? 0,
                    'max_concurrent_connections' => $subscription->max_concurrent_connections ?? 1,
                    'current_connections' => $subscription->current_connections ?? 0,
                    'expire_date' => $subscription->expire_date,
                    'is_active' => (int)$subscription->is_active,
                    'auto_renew' => (int)$subscription->auto_renew,
                    'notes' => $subscription->notes ?? '',
                    'is_expired' => $isExpired,
                    'is_valid' => $subscription->is_active && !$isExpired,
                    'days_until_expiration' => $daysUntil,
                    'hours_until_expiration' => $hoursUntil,
                    'is_expiring_soon' => !$isExpired && $daysUntil <= 7,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['subscriber_id'])) throw new Exception('Subscriber ID is required');
            if (!isset($input['package_id'])) throw new Exception('Package ID is required');
            if (!isset($input['expire_date'])) throw new Exception('Expiration date is required');

            $subscriber = Subscriber::find($input['subscriber_id']);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $package = Package::find($input['package_id']);
            if (!$package) throw new Exception('Package not found');

            $subscription = new Subscription();
            $subscription->subscriber_id = $input['subscriber_id'];
            $subscription->package_id = $input['package_id'];
            $subscription->device = $input['device'] ?? null;
            $subscription->device_mac = $input['device_mac'] ?? null;
            $subscription->device_fingerprint = $input['device_fingerprint'] ?? null;
            $subscription->ip_address = $input['ip_address'] ?? null;
            $subscription->last_ip_address = $input['last_ip_address'] ?? null;
            $subscription->isp = $input['isp'] ?? null;
            $subscription->user_agent = $input['user_agent'] ?? null;
            $subscription->max_concurrent_connections = $input['max_concurrent_connections'] ?? $package->max_concurrent_devices ?? 1;
            $subscription->current_connections = 0;
            $subscription->connection_count = 0;
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

            if (isset($input['device'])) $subscription->device = $input['device'] ?: null;
            if (isset($input['device_mac'])) $subscription->device_mac = $input['device_mac'] ?: null;
            if (isset($input['device_fingerprint'])) $subscription->device_fingerprint = $input['device_fingerprint'] ?: null;
            if (isset($input['ip_address'])) $subscription->ip_address = $input['ip_address'] ?: null;
            if (isset($input['last_ip_address'])) $subscription->last_ip_address = $input['last_ip_address'] ?: null;
            if (isset($input['isp'])) $subscription->isp = $input['isp'] ?: null;
            if (isset($input['user_agent'])) $subscription->user_agent = $input['user_agent'] ?: null;
            if (isset($input['max_concurrent_connections'])) $subscription->max_concurrent_connections = $input['max_concurrent_connections'];
            if (isset($input['current_connections'])) $subscription->current_connections = $input['current_connections'];
            if (isset($input['expire_date'])) $subscription->expire_date = $input['expire_date'];
            if (isset($input['is_active'])) $subscription->is_active = $input['is_active'];
            if (isset($input['auto_renew'])) $subscription->auto_renew = $input['auto_renew'];
            if (isset($input['notes'])) $subscription->notes = $input['notes'] ?: null;
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
                'message' => $subscription->is_active ? 'Subscription enabled' : 'Subscription disabled',
                'data' => ['is_active' => (int)$subscription->is_active]
            ]);
            break;

        case 'bulk_toggle':
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            if (empty($ids)) throw new Exception('No subscriptions selected');

            $updated = Subscription::whereIn('id', $ids)->update(['is_active' => $isActive]);

            echo json_encode([
                'success' => true,
                'message' => "{$updated} subscription(s) " . ($isActive ? 'enabled' : 'disabled'),
                'data' => ['updated' => $updated]
            ]);
            break;

        case 'bulk_delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) throw new Exception('No subscriptions selected');

            $deleted = Subscription::whereIn('id', $ids)->delete();

            echo json_encode([
                'success' => true,
                'message' => "{$deleted} subscription(s) deleted",
                'data' => ['deleted' => $deleted]
            ]);
            break;

        case 'bulk_renew':
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
            $days = $input['days'] ?? 30;

            if (empty($ids)) throw new Exception('No subscriptions selected');

            $subscriptions = Subscription::whereIn('id', $ids)->get();
            $renewed = 0;
            foreach ($subscriptions as $sub) {
                $currentExpire = strtotime($sub->expire_date);
                $now = time();
                $baseDate = $currentExpire > $now ? $currentExpire : $now;
                $sub->expire_date = date('Y-m-d H:i:s', $baseDate + ($days * 86400));
                $sub->is_active = 1;
                $sub->save();
                $renewed++;
            }

            echo json_encode([
                'success' => true,
                'message' => "{$renewed} subscription(s) renewed for {$days} days",
                'data' => ['renewed' => $renewed]
            ]);
            break;

        case 'renew':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $input = json_decode(file_get_contents('php://input'), true);
            $days = $input['days'] ?? 30;

            $currentExpire = strtotime($subscription->expire_date);
            $now = time();
            $baseDate = $currentExpire > $now ? $currentExpire : $now;
            $subscription->expire_date = date('Y-m-d H:i:s', $baseDate + ($days * 86400));
            $subscription->is_active = 1;
            $subscription->save();

            $daysUntil = max(0, (int)((strtotime($subscription->expire_date) - $now) / 86400));

            echo json_encode([
                'success' => true,
                'message' => "Subscription renewed for {$days} days",
                'data' => [
                    'new_expire_date' => $subscription->expire_date,
                    'days_until_expiration' => $daysUntil
                ]
            ]);
            break;

        case 'by_subscriber':
            $subscriberId = $_GET['subscriber_id'] ?? null;
            if (!$subscriberId) throw new Exception('Subscriber ID required');

            $now = date('Y-m-d H:i:s');
            $subscriptions = Subscription::with('package')
                ->where('subscriber_id', $subscriberId)
                ->orderBy('expire_date', 'desc')
                ->get();

            $formatted = $subscriptions->map(function($sub) use ($now) {
                $isExpired = strtotime($sub->expire_date) <= strtotime($now);
                $daysUntil = max(0, (int)((strtotime($sub->expire_date) - strtotime($now)) / 86400));
                return [
                    'id' => $sub->id,
                    'package_id' => $sub->package_id,
                    'package_name' => $sub->package ? $sub->package->name : 'Unknown',
                    'device' => $sub->device ?? '',
                    'device_mac' => $sub->device_mac ?? '',
                    'expire_date' => $sub->expire_date,
                    'is_active' => (int)$sub->is_active,
                    'is_expired' => $isExpired,
                    'is_valid' => $sub->is_active && !$isExpired,
                    'days_until_expiration' => $daysUntil,
                    'connection_count' => $sub->connection_count ?? 0,
                    'last_connected' => $sub->last_connected,
                    'auto_renew' => (int)$sub->auto_renew
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

            $subscription->ip_address = $input['ip_address'] ?? $subscription->ip_address;
            $subscription->last_ip_address = $subscription->ip_address;
            $subscription->user_agent = $input['user_agent'] ?? $subscription->user_agent;
            $subscription->isp = $input['isp'] ?? $subscription->isp;
            $subscription->device = $input['device'] ?? $subscription->device;
            $subscription->device_mac = $input['device_mac'] ?? $subscription->device_mac;
            $subscription->last_connected = date('Y-m-d H:i:s');
            $subscription->connection_count = ($subscription->connection_count ?? 0) + 1;
            $subscription->save();

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
            $now = date('Y-m-d H:i:s');
            $sevenDaysLater = date('Y-m-d H:i:s', strtotime('+7 days'));
            $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

            $total = Subscription::count();
            $active = Subscription::where('is_active', 1)->where('expire_date', '>', $now)->count();
            $expired = Subscription::where('expire_date', '<=', $now)->count();
            $expiringSoon = Subscription::where('is_active', 1)
                ->whereBetween('expire_date', [$now, $sevenDaysLater])->count();
            $inactive = Subscription::where('is_active', 0)->count();
            $autoRenewEnabled = Subscription::where('auto_renew', 1)->count();
            $recentlyCreated = Subscription::where('created_at', '>=', $sevenDaysAgo)->count();
            $recentlyConnected = Subscription::where('last_connected', '>=', $sevenDaysAgo)->count();
            $totalConnections = Subscription::sum('connection_count');

            // By package
            $byPackage = [];
            $packages = Package::all();
            foreach ($packages as $package) {
                $pkgTotal = Subscription::where('package_id', $package->id)->count();
                $pkgActive = Subscription::where('package_id', $package->id)
                    ->where('is_active', 1)->where('expire_date', '>', $now)->count();
                $byPackage[] = [
                    'id' => $package->id,
                    'name' => $package->name,
                    'total' => $pkgTotal,
                    'active' => $pkgActive
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'expired' => $expired,
                    'expiring_soon' => $expiringSoon,
                    'inactive' => $inactive,
                    'auto_renew_enabled' => $autoRenewEnabled,
                    'recently_created' => $recentlyCreated,
                    'recently_connected' => $recentlyConnected,
                    'total_connections' => $totalConnections,
                    'by_package' => $byPackage
                ]
            ]);
            break;

        case 'export':
            $format = $_GET['format'] ?? 'json';
            $now = date('Y-m-d H:i:s');

            $subscriptions = Subscription::with(['subscriber', 'package'])->get();

            $data = $subscriptions->map(function($sub) use ($now) {
                $isExpired = strtotime($sub->expire_date) <= strtotime($now);
                return [
                    'id' => $sub->id,
                    'subscriber' => $sub->subscriber ? $sub->subscriber->username : 'Unknown',
                    'subscriber_email' => $sub->subscriber ? ($sub->subscriber->email ?? '') : '',
                    'package' => $sub->package ? $sub->package->name : 'Unknown',
                    'device' => $sub->device ?? '',
                    'device_mac' => $sub->device_mac ?? '',
                    'ip_address' => $sub->ip_address ?? '',
                    'isp' => $sub->isp ?? '',
                    'expire_date' => $sub->expire_date,
                    'status' => $isExpired ? 'Expired' : ($sub->is_active ? 'Active' : 'Inactive'),
                    'auto_renew' => $sub->auto_renew ? 'Yes' : 'No',
                    'connections' => $sub->connection_count ?? 0,
                    'last_connected' => $sub->last_connected ?? '',
                    'created_at' => $sub->created_at,
                ];
            });

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="subscriptions_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');
                if ($data->count() > 0) {
                    fputcsv($output, array_keys($data->first()));
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                }
                fclose($output);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        case 'clear_device':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscription ID required');

            $subscription = Subscription::find($id);
            if (!$subscription) throw new Exception('Subscription not found');

            $subscription->device = null;
            $subscription->device_mac = null;
            $subscription->device_fingerprint = null;
            $subscription->ip_address = null;
            $subscription->last_ip_address = null;
            $subscription->isp = null;
            $subscription->user_agent = null;
            $subscription->current_connections = 0;
            $subscription->save();

            echo json_encode([
                'success' => true,
                'message' => 'Device information cleared'
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
