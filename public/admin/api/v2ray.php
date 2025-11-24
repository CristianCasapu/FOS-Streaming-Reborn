<?php
/**
 * V2Ray Management API
 * FOS-Streaming v70
 */

require_once '../../../config.php';
require_once '../../../app/Services/V2RayService.php';

use Illuminate\Database\Capsule\Manager as DB;
use App\Services\V2RayService;

// Check authentication
logincheck();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$v2rayService = new V2RayService();

try {
    switch ($action) {
        case 'list_servers':
            $servers = DB::table('v2ray_servers')
                ->select('v2ray_servers.*')
                ->get()
                ->map(function($server) {
                    $server->tls_settings = json_decode($server->tls_settings);
                    $server->ws_settings = json_decode($server->ws_settings);
                    $server->domain_fronting = json_decode($server->domain_fronting);
                    return $server;
                });

            echo json_encode(['success' => true, 'data' => $servers]);
            break;

        case 'get_server':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Server ID required');
            }

            $server = DB::table('v2ray_servers')->find($id);
            if (!$server) {
                throw new Exception('Server not found');
            }

            $server->tls_settings = json_decode($server->tls_settings);
            $server->ws_settings = json_decode($server->ws_settings);
            $server->domain_fronting = json_decode($server->domain_fronting);

            echo json_encode(['success' => true, 'data' => $server]);
            break;

        case 'create_server':
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['name']) || empty($data['hostname']) || empty($data['port'])) {
                throw new Exception('Name, hostname and port are required');
            }

            // Prepare JSON fields
            $data['tls_settings'] = isset($data['tls_settings']) ? json_encode($data['tls_settings']) : null;
            $data['ws_settings'] = isset($data['ws_settings']) ? json_encode($data['ws_settings']) : null;
            $data['domain_fronting'] = isset($data['domain_fronting']) ? json_encode($data['domain_fronting']) : null;
            $data['created_at'] = date('Y-m-d H:i:s');

            $id = DB::table('v2ray_servers')->insertGetId($data);
            $server = DB::table('v2ray_servers')->find($id);

            echo json_encode(['success' => true, 'data' => $server]);
            break;

        case 'update_server':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Server ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Prepare JSON fields
            if (isset($data['tls_settings'])) {
                $data['tls_settings'] = json_encode($data['tls_settings']);
            }
            if (isset($data['ws_settings'])) {
                $data['ws_settings'] = json_encode($data['ws_settings']);
            }
            if (isset($data['domain_fronting'])) {
                $data['domain_fronting'] = json_encode($data['domain_fronting']);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            DB::table('v2ray_servers')->where('id', $id)->update($data);
            $server = DB::table('v2ray_servers')->find($id);

            echo json_encode(['success' => true, 'data' => $server]);
            break;

        case 'delete_server':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Server ID required');
            }

            DB::table('v2ray_servers')->where('id', $id)->delete();
            echo json_encode(['success' => true]);
            break;

        case 'toggle_server':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Server ID required');
            }

            $server = DB::table('v2ray_servers')->find($id);
            if (!$server) {
                throw new Exception('Server not found');
            }

            DB::table('v2ray_servers')
                ->where('id', $id)
                ->update([
                    'status' => $server->status ? 0 : 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            echo json_encode(['success' => true]);
            break;

        case 'list_users':
            $search = $_GET['search'] ?? '';
            $protocol = $_GET['protocol'] ?? '';
            $server = $_GET['server'] ?? '';

            $query = DB::table('v2ray_users')
                ->select('v2ray_users.*', 'subscribers.email as subscriber_email', 'v2ray_servers.name as server_name')
                ->join('subscribers', 'v2ray_users.subscriber_id', '=', 'subscribers.id')
                ->join('v2ray_servers', 'v2ray_users.server_id', '=', 'v2ray_servers.id');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('v2ray_users.email', 'like', "%$search%")
                      ->orWhere('v2ray_users.uuid', 'like', "%$search%")
                      ->orWhere('subscribers.email', 'like', "%$search%");
                });
            }

            if ($protocol) {
                $query->where('v2ray_users.protocol', $protocol);
            }

            if ($server) {
                $query->where('v2ray_users.server_id', $server);
            }

            $users = $query->orderBy('v2ray_users.created_at', 'desc')
                          ->limit(100)
                          ->get();

            echo json_encode(['success' => true, 'data' => $users]);
            break;

        case 'create_user':
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['subscriber_id']) || empty($data['server_id']) || empty($data['protocol'])) {
                throw new Exception('Subscriber, server and protocol are required');
            }

            // Generate UUID if not provided
            if (empty($data['uuid'])) {
                $data['uuid'] = \Ramsey\Uuid\Uuid::uuid4()->toString();
            }

            // Get subscriber email
            $subscriber = DB::table('subscribers')->find($data['subscriber_id']);
            if (!$subscriber) {
                throw new Exception('Subscriber not found');
            }

            $data['email'] = $data['email'] ?? $subscriber->email;
            $data['created_at'] = date('Y-m-d H:i:s');

            $id = DB::table('v2ray_users')->insertGetId($data);
            $user = DB::table('v2ray_users')->find($id);

            echo json_encode(['success' => true, 'data' => $user]);
            break;

        case 'toggle_user':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('User ID required');
            }

            $user = DB::table('v2ray_users')->find($id);
            if (!$user) {
                throw new Exception('User not found');
            }

            DB::table('v2ray_users')
                ->where('id', $id)
                ->update([
                    'enabled' => $user->enabled ? 0 : 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            echo json_encode(['success' => true]);
            break;

        case 'reset_traffic':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('User ID required');
            }

            DB::table('v2ray_users')
                ->where('id', $id)
                ->update([
                    'traffic_used' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            echo json_encode(['success' => true]);
            break;

        case 'generate_config':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('User ID required');
            }

            $user = DB::table('v2ray_users')
                ->select('v2ray_users.*', 'subscribers.id as subscriber_id', 'subscribers.email as subscriber_email')
                ->join('subscribers', 'v2ray_users.subscriber_id', '=', 'subscribers.id')
                ->where('v2ray_users.id', $id)
                ->first();

            if (!$user) {
                throw new Exception('User not found');
            }

            $server = DB::table('v2ray_servers')->find($user->server_id);
            if (!$server) {
                throw new Exception('Server not found');
            }

            // Generate configuration based on protocol
            $config = [];
            switch ($user->protocol) {
                case 'vmess':
                    $vmessData = [
                        'v' => '2',
                        'ps' => 'FOS-' . $user->subscriber_email,
                        'add' => $server->hostname,
                        'port' => $server->port,
                        'id' => $user->uuid,
                        'aid' => $user->alter_id ?? 64,
                        'net' => $server->network,
                        'type' => 'none',
                        'host' => $server->hostname,
                        'path' => '/streaming',
                        'tls' => $server->security === 'tls' ? 'tls' : ''
                    ];
                    $config['url'] = 'vmess://' . base64_encode(json_encode($vmessData));
                    $config['json'] = $vmessData;
                    break;

                case 'vless':
                    $params = [
                        'encryption' => 'none',
                        'security' => $server->security,
                        'sni' => $server->hostname,
                        'type' => $server->network
                    ];
                    if ($user->flow) {
                        $params['flow'] = $user->flow;
                    }
                    $queryString = http_build_query($params);
                    $config['url'] = sprintf(
                        'vless://%s@%s:%d?%s#%s',
                        $user->uuid,
                        $server->hostname,
                        $server->port,
                        $queryString,
                        urlencode('FOS-' . $user->subscriber_email)
                    );
                    $config['json'] = [
                        'protocol' => 'vless',
                        'id' => $user->uuid,
                        'address' => $server->hostname,
                        'port' => $server->port,
                        'encryption' => 'none',
                        'flow' => $user->flow ?? null,
                        'network' => $server->network,
                        'security' => $server->security
                    ];
                    break;

                case 'trojan':
                    $config['url'] = sprintf(
                        'trojan://%s@%s:%d?security=%s&sni=%s&type=%s#%s',
                        $user->uuid,
                        $server->hostname,
                        $server->port,
                        $server->security,
                        $server->hostname,
                        $server->network,
                        urlencode('FOS-' . $user->subscriber_email)
                    );
                    $config['json'] = [
                        'protocol' => 'trojan',
                        'password' => $user->uuid,
                        'address' => $server->hostname,
                        'port' => $server->port,
                        'network' => $server->network,
                        'security' => $server->security
                    ];
                    break;
            }

            // Generate QR code
            if (!empty($config['url'])) {
                $config['qrcode'] = $v2rayService->generateQRCode($config['url']);
            }

            // Store configuration
            DB::table('v2ray_client_configs')->insert([
                'user_id' => $id,
                'config_type' => 'url',
                'config_data' => $config['url'] ?? '',
                'share_link' => $config['url'] ?? '',
                'qr_code' => base64_decode(str_replace('data:image/png;base64,', '', $config['qrcode'] ?? '')),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            echo json_encode(['success' => true, 'data' => $config]);
            break;

        case 'list_domain_fronting':
            $fronting = DB::table('v2ray_domain_fronting')
                ->select('v2ray_domain_fronting.*', 'v2ray_servers.name as server_name')
                ->join('v2ray_servers', 'v2ray_domain_fronting.server_id', '=', 'v2ray_servers.id')
                ->get();

            echo json_encode(['success' => true, 'data' => $fronting]);
            break;

        case 'create_fronting':
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['server_id']) || empty($data['cdn_provider']) || empty($data['front_domain']) || empty($data['real_domain'])) {
                throw new Exception('Server, CDN provider, front domain and real domain are required');
            }

            $data['created_at'] = date('Y-m-d H:i:s');

            $id = DB::table('v2ray_domain_fronting')->insertGetId($data);
            $fronting = DB::table('v2ray_domain_fronting')->find($id);

            echo json_encode(['success' => true, 'data' => $fronting]);
            break;

        case 'test_fronting':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Fronting ID required');
            }

            $fronting = DB::table('v2ray_domain_fronting')->find($id);
            if (!$fronting) {
                throw new Exception('Fronting configuration not found');
            }

            // Test the domain fronting configuration
            $testUrl = $fronting->test_url ?? 'https://' . $fronting->front_domain . '/';
            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Host: ' . $fronting->real_domain
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $success = $httpCode >= 200 && $httpCode < 400;

            // Update test status
            DB::table('v2ray_domain_fronting')
                ->where('id', $id)
                ->update([
                    'last_test_at' => date('Y-m-d H:i:s'),
                    'last_test_status' => $success ? 1 : 0
                ]);

            echo json_encode([
                'success' => true,
                'message' => $success ? 'Test successful (HTTP ' . $httpCode . ')' : 'Test failed (HTTP ' . $httpCode . ')'
            ]);
            break;

        case 'delete_fronting':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Fronting ID required');
            }

            DB::table('v2ray_domain_fronting')->where('id', $id)->delete();
            echo json_encode(['success' => true]);
            break;

        case 'get_stats':
            // Get overall statistics
            $stats = [
                'total_users' => DB::table('v2ray_users')->count(),
                'active_users' => DB::table('v2ray_users')->where('enabled', 1)->count(),
                'total_servers' => DB::table('v2ray_servers')->count(),
                'active_servers' => DB::table('v2ray_servers')->where('status', 1)->count(),
                'total_traffic' => DB::table('v2ray_users')->sum('traffic_used'),
                'connections_today' => DB::table('v2ray_logs')
                    ->whereDate('connected_at', date('Y-m-d'))
                    ->count(),
                'protocols' => DB::table('v2ray_users')
                    ->select('protocol', DB::raw('COUNT(*) as count'))
                    ->groupBy('protocol')
                    ->get()
            ];

            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        case 'get_traffic_stats':
            $userId = $_GET['user_id'] ?? null;
            $serverId = $_GET['server_id'] ?? null;
            $days = $_GET['days'] ?? 7;

            $query = DB::table('v2ray_traffic_stats')
                ->select(
                    DB::raw('DATE(hour_timestamp) as date'),
                    DB::raw('SUM(bytes_sent) as sent'),
                    DB::raw('SUM(bytes_received) as received'),
                    DB::raw('SUM(connections_count) as connections')
                )
                ->where('hour_timestamp', '>=', date('Y-m-d H:i:s', strtotime("-$days days")));

            if ($userId) {
                $query->where('user_id', $userId);
            }
            if ($serverId) {
                $query->where('server_id', $serverId);
            }

            $stats = $query->groupBy('date')
                          ->orderBy('date', 'asc')
                          ->get();

            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}