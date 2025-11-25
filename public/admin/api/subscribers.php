<?php
/**
 * Subscribers API Endpoint
 * Enhanced with comprehensive data, statistics, and bulk operations
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
            $country = $_GET['country'] ?? null;
            $hasSubscriptions = $_GET['has_subscriptions'] ?? null;
            $hasTrials = $_GET['has_trials'] ?? null;
            $sortBy = $_GET['sort_by'] ?? 'created_at';
            $sortOrder = $_GET['sort_order'] ?? 'desc';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $perPage = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 100) : 20;

            $now = date('Y-m-d H:i:s');
            $query = Subscriber::query()
                ->withCount(['subscriptions', 'subscriptions as active_subscriptions_count' => function($q) use ($now) {
                    $q->where('is_active', 1)->where('expire_date', '>', $now);
                }])
                ->with(['trial' => function($q) {
                    $q->select('id', 'subscriber_id', 'is_active', 'started_at', 'trial_duration_hours');
                }]);

            // Search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('country', 'LIKE', "%{$search}%")
                      ->orWhere('city', 'LIKE', "%{$search}%");
                });
            }

            // Status filter
            if ($enabled !== null && $enabled !== '') {
                $query->where('enabled', $enabled);
            }

            // Country filter
            if ($country) {
                $query->where('country', $country);
            }

            // Has subscriptions filter
            if ($hasSubscriptions !== null && $hasSubscriptions !== '') {
                if ($hasSubscriptions == '1') {
                    $query->has('subscriptions');
                } else {
                    $query->doesntHave('subscriptions');
                }
            }

            // Has trials filter
            if ($hasTrials !== null && $hasTrials !== '') {
                if ($hasTrials == '1') {
                    $query->has('trial');
                } else {
                    $query->doesntHave('trial');
                }
            }

            // Sorting
            $allowedSorts = ['id', 'username', 'email', 'country', 'enabled', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            $total = $query->count();
            $subscribers = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

            $formatted = $subscribers->map(function($subscriber) {
                $hasTrial = $subscriber->trial !== null;
                $trialActive = $hasTrial && $subscriber->trial->is_active &&
                    (strtotime($subscriber->trial->started_at) + ($subscriber->trial->trial_duration_hours * 3600)) > time();

                return [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username,
                    'email' => $subscriber->email ?? '',
                    'phone' => $subscriber->phone ?? '',
                    'country' => $subscriber->country ?? '',
                    'city' => $subscriber->city ?? '',
                    'address' => $subscriber->address ?? '',
                    'postal_code' => $subscriber->postal_code ?? '',
                    'notes' => $subscriber->notes ?? '',
                    'enabled' => (int)$subscriber->enabled,
                    'subscriptions_count' => $subscriber->subscriptions_count ?? 0,
                    'active_subscriptions_count' => $subscriber->active_subscriptions_count ?? 0,
                    'has_trial' => $hasTrial,
                    'trial_active' => $trialActive,
                    'created_at' => $subscriber->created_at,
                    'updated_at' => $subscriber->updated_at,
                    'full_address' => $subscriber->full_address ?? ''
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

            $subscriber = Subscriber::with([
                'subscriptions' => function($q) {
                    $q->with('package:id,name,price,duration_days')
                      ->orderBy('created_at', 'desc');
                },
                'trial' => function($q) {
                    $q->with('package:id,name');
                }
            ])->find($id);

            if (!$subscriber) throw new Exception('Subscriber not found');

            // Calculate statistics
            $activeSubscriptions = $subscriber->subscriptions->filter(function($sub) {
                return $sub->is_active && strtotime($sub->expire_date) > time();
            });

            $expiredSubscriptions = $subscriber->subscriptions->filter(function($sub) {
                return strtotime($sub->expire_date) <= time();
            });

            $hasTrial = $subscriber->trial !== null;
            $trialActive = $hasTrial && $subscriber->trial->is_active &&
                (strtotime($subscriber->trial->started_at) + ($subscriber->trial->trial_duration_hours * 3600)) > time();

            // Get last connection from subscriptions or trial
            $lastConnection = null;
            $lastConnectedSubscription = $subscriber->subscriptions->sortByDesc('last_connected')->first();
            if ($lastConnectedSubscription && $lastConnectedSubscription->last_connected) {
                $lastConnection = $lastConnectedSubscription->last_connected;
            }
            if ($subscriber->trial && $subscriber->trial->last_connected) {
                if (!$lastConnection || strtotime($subscriber->trial->last_connected) > strtotime($lastConnection)) {
                    $lastConnection = $subscriber->trial->last_connected;
                }
            }

            // Total connection count
            $totalConnections = $subscriber->subscriptions->sum('connection_count');
            if ($subscriber->trial) {
                $totalConnections += $subscriber->trial->connection_count ?? 0;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username,
                    'email' => $subscriber->email ?? '',
                    'phone' => $subscriber->phone ?? '',
                    'country' => $subscriber->country ?? '',
                    'city' => $subscriber->city ?? '',
                    'address' => $subscriber->address ?? '',
                    'postal_code' => $subscriber->postal_code ?? '',
                    'notes' => $subscriber->notes ?? '',
                    'enabled' => (int)$subscriber->enabled,
                    'created_at' => $subscriber->created_at,
                    'updated_at' => $subscriber->updated_at,
                    'full_address' => $subscriber->full_address ?? '',
                    // Statistics
                    'stats' => [
                        'total_subscriptions' => $subscriber->subscriptions->count(),
                        'active_subscriptions' => $activeSubscriptions->count(),
                        'expired_subscriptions' => $expiredSubscriptions->count(),
                        'has_trial' => $hasTrial,
                        'trial_active' => $trialActive,
                        'total_connections' => $totalConnections,
                        'last_connection' => $lastConnection,
                        'member_days' => $subscriber->created_at ?
                            (int)((time() - strtotime($subscriber->created_at)) / 86400) : 0,
                    ],
                    // Related data
                    'subscriptions' => $subscriber->subscriptions->map(function($sub) {
                        return [
                            'id' => $sub->id,
                            'package_id' => $sub->package_id,
                            'package_name' => $sub->package->name ?? 'Unknown',
                            'package_price' => $sub->package->price ?? 0,
                            'is_active' => (int)$sub->is_active,
                            'is_expired' => strtotime($sub->expire_date) <= time(),
                            'is_expiring_soon' => strtotime($sub->expire_date) > time() &&
                                strtotime($sub->expire_date) < strtotime('+7 days'),
                            'expire_date' => $sub->expire_date,
                            'auto_renew' => (int)$sub->auto_renew,
                            'max_concurrent_connections' => $sub->max_concurrent_connections,
                            'current_connections' => $sub->current_connections ?? 0,
                            'connection_count' => $sub->connection_count ?? 0,
                            'last_connected' => $sub->last_connected,
                            'device' => $sub->device ?? '',
                            'device_mac' => $sub->device_mac ?? '',
                            'ip_address' => $sub->ip_address ?? '',
                            'isp' => $sub->isp ?? '',
                            'created_at' => $sub->created_at,
                        ];
                    }),
                    'trial' => $subscriber->trial ? [
                        'id' => $subscriber->trial->id,
                        'package_id' => $subscriber->trial->package_id,
                        'package_name' => $subscriber->trial->package->name ?? 'Unknown',
                        'is_active' => (int)$subscriber->trial->is_active,
                        'is_expired' => !$trialActive,
                        'started_at' => $subscriber->trial->started_at,
                        'duration_hours' => $subscriber->trial->trial_duration_hours,
                        'remaining_hours' => max(0, round(
                            ($subscriber->trial->trial_duration_hours * 3600 -
                            (time() - strtotime($subscriber->trial->started_at))) / 3600, 1
                        )),
                        'connection_count' => $subscriber->trial->connection_count ?? 0,
                        'last_connected' => $subscriber->trial->last_connected,
                        'converted_to_subscription' => (int)$subscriber->trial->converted_to_subscription,
                    ] : null,
                ]
            ]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['username']) || !isset($input['password'])) {
                throw new Exception('Username and password required');
            }

            // Check for duplicate username
            $existing = Subscriber::where('username', $input['username'])->first();
            if ($existing) {
                throw new Exception('Username already exists');
            }

            // Check for duplicate email if provided
            if (!empty($input['email'])) {
                $existingEmail = Subscriber::where('email', $input['email'])->first();
                if ($existingEmail) {
                    throw new Exception('Email already exists');
                }
            }

            $subscriber = new Subscriber();
            $subscriber->username = trim($input['username']);
            $subscriber->password = password_hash($input['password'], PASSWORD_DEFAULT);
            $subscriber->email = !empty($input['email']) ? trim($input['email']) : null;
            $subscriber->phone = $input['phone'] ?? null;
            $subscriber->country = $input['country'] ?? null;
            $subscriber->city = $input['city'] ?? null;
            $subscriber->address = $input['address'] ?? null;
            $subscriber->postal_code = $input['postal_code'] ?? null;
            $subscriber->notes = $input['notes'] ?? null;
            $subscriber->enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;
            $subscriber->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscriber created successfully',
                'data' => [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username
                ]
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $input = json_decode(file_get_contents('php://input'), true);

            // Check for duplicate username if changed
            if (isset($input['username']) && $input['username'] !== $subscriber->username) {
                $existing = Subscriber::where('username', $input['username'])->where('id', '!=', $id)->first();
                if ($existing) {
                    throw new Exception('Username already exists');
                }
                $subscriber->username = trim($input['username']);
            }

            // Check for duplicate email if changed
            if (isset($input['email']) && $input['email'] !== $subscriber->email) {
                if (!empty($input['email'])) {
                    $existingEmail = Subscriber::where('email', $input['email'])->where('id', '!=', $id)->first();
                    if ($existingEmail) {
                        throw new Exception('Email already exists');
                    }
                }
                $subscriber->email = !empty($input['email']) ? trim($input['email']) : null;
            }

            if (isset($input['phone'])) $subscriber->phone = $input['phone'] ?: null;
            if (isset($input['country'])) $subscriber->country = $input['country'] ?: null;
            if (isset($input['city'])) $subscriber->city = $input['city'] ?: null;
            if (isset($input['address'])) $subscriber->address = $input['address'] ?: null;
            if (isset($input['postal_code'])) $subscriber->postal_code = $input['postal_code'] ?: null;
            if (isset($input['notes'])) $subscriber->notes = $input['notes'] ?: null;
            if (isset($input['enabled'])) $subscriber->enabled = (int)$input['enabled'];

            if (isset($input['password']) && !empty($input['password'])) {
                $subscriber->password = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            $subscriber->save();

            echo json_encode([
                'success' => true,
                'message' => 'Subscriber updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            // Delete related subscriptions and trials first
            $subscriber->subscriptions()->delete();
            if ($subscriber->trial) {
                $subscriber->trial->delete();
            }

            $subscriber->delete();

            echo json_encode([
                'success' => true,
                'message' => 'Subscriber and all related data deleted successfully'
            ]);
            break;

        case 'toggle':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $subscriber->enabled = !$subscriber->enabled;
            $subscriber->save();

            echo json_encode([
                'success' => true,
                'message' => $subscriber->enabled ? 'Subscriber enabled' : 'Subscriber disabled',
                'data' => ['enabled' => (int)$subscriber->enabled]
            ]);
            break;

        case 'bulk_toggle':
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];
            $enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;

            if (empty($ids)) throw new Exception('No subscribers selected');

            $updated = Subscriber::whereIn('id', $ids)->update(['enabled' => $enabled]);

            echo json_encode([
                'success' => true,
                'message' => "{$updated} subscriber(s) " . ($enabled ? 'enabled' : 'disabled'),
                'data' => ['updated' => $updated]
            ]);
            break;

        case 'bulk_delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $ids = $input['ids'] ?? [];

            if (empty($ids)) throw new Exception('No subscribers selected');

            // Delete related data first
            Subscription::whereIn('subscriber_id', $ids)->delete();
            Trial::whereIn('subscriber_id', $ids)->delete();

            $deleted = Subscriber::whereIn('id', $ids)->delete();

            echo json_encode([
                'success' => true,
                'message' => "{$deleted} subscriber(s) deleted",
                'data' => ['deleted' => $deleted]
            ]);
            break;

        case 'stats':
            $now = date('Y-m-d H:i:s');
            $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $total = Subscriber::count();
            $active = Subscriber::where('enabled', 1)->count();
            $inactive = Subscriber::where('enabled', 0)->count();
            $withSubscriptions = Subscriber::has('subscriptions')->count();
            $withActiveSubscriptions = Subscriber::whereHas('subscriptions', function($q) use ($now) {
                $q->where('is_active', 1)->where('expire_date', '>', $now);
            })->count();
            $withTrials = Subscriber::has('trial')->count();
            $recentlyCreated = Subscriber::where('created_at', '>=', $sevenDaysAgo)->count();
            $recentlyActive = Subscription::where('last_connected', '>=', $sevenDaysAgo)
                ->distinct('subscriber_id')->count('subscriber_id');

            // Countries breakdown
            $countries = Subscriber::select('country')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->groupBy('country')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive,
                    'with_subscriptions' => $withSubscriptions,
                    'with_active_subscriptions' => $withActiveSubscriptions,
                    'with_trials' => $withTrials,
                    'recently_created' => $recentlyCreated,
                    'recently_active' => $recentlyActive,
                    'countries' => $countries
                ]
            ]);
            break;

        case 'countries':
            $countries = Subscriber::select('country')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->groupBy('country')
                ->orderBy('country')
                ->get();

            echo json_encode([
                'success' => true,
                'data' => $countries
            ]);
            break;

        case 'reset_password':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Subscriber ID required');

            $subscriber = Subscriber::find($id);
            if (!$subscriber) throw new Exception('Subscriber not found');

            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['password']) || empty($input['password'])) {
                throw new Exception('New password required');
            }

            if (strlen($input['password']) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }

            $subscriber->password = password_hash($input['password'], PASSWORD_DEFAULT);
            $subscriber->save();

            echo json_encode([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
            break;

        case 'export':
            $format = $_GET['format'] ?? 'json';
            $now = date('Y-m-d H:i:s');

            $subscribers = Subscriber::withCount(['subscriptions', 'subscriptions as active_subscriptions_count' => function($q) use ($now) {
                $q->where('is_active', 1)->where('expire_date', '>', $now);
            }])->get();

            $data = $subscribers->map(function($s) {
                return [
                    'id' => $s->id,
                    'username' => $s->username,
                    'email' => $s->email ?? '',
                    'phone' => $s->phone ?? '',
                    'country' => $s->country ?? '',
                    'city' => $s->city ?? '',
                    'enabled' => $s->enabled ? 'Yes' : 'No',
                    'subscriptions' => $s->subscriptions_count,
                    'active_subscriptions' => $s->active_subscriptions_count,
                    'created_at' => $s->created_at,
                ];
            });

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="subscribers_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, array_keys($data->first() ?? []));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
