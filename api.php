<?php
/**
 * REST API for Vue.js Frontend
 */
include('config.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Parse JSON body for POST/PUT requests
$input = null;
if (in_array($method, ['POST', 'PUT'])) {
    $input = json_decode(file_get_contents('php://input'), true);
}

// Simple auth check (replace with proper JWT later)
function isAuthenticated() {
    return isset($_SESSION['user_id']) ||
           (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION']));
}

// API Routes
try {
    switch ($path) {
        case 'login':
            if ($method === 'POST') {
                $username = $input['username'] ?? '';
                $password = $input['password'] ?? '';

                $user = Staff::where('username', '=', $username)
                            ->where('password', '=', md5($password))
                            ->first();

                if ($user) {
                    $_SESSION['user_id'] = $username;
                    echo json_encode([
                        'success' => true,
                        'user' => ['username' => $user->username],
                        'token' => base64_encode($username . ':' . time())
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid credentials'
                    ]);
                }
            }
            break;

        case 'dashboard':
            if (!isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                break;
            }

            // Create settings if not exists
            $settings = Setting::first();
            if (is_null($settings)) {
                $settings = new Setting;
                $settings->webip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
                $settings->webport = 8000;
                $settings->save();
            }

            $all = Stream::all()->count();
            $online = Stream::where('running', '=', 1)->count();
            $offline = Stream::where('running', '=', 0)->count();

            $space_free = round((disk_free_space('/')) / 1048576, 1);
            $space_total = round((disk_total_space('/')) / 1048576, 1);
            $space_pr = (int)(100 * ($space_free / $space_total));

            if (stristr(PHP_OS, 'win')) {
                $cpu_pr = 50;
                $mem_usage = 20;
                $mem_total = 120;
                $mem_pr = (int)(100 * ($mem_usage / $mem_total));
            } else {
                $loads = sys_getloadavg();
                $core_nums = trim(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
                $cpu_pr = round($loads[0] / ($core_nums + 1) * 100, 2);

                $free = shell_exec('free');
                $free = (string)trim($free);
                $free_arr = explode("\n", $free);
                $mem = explode(" ", $free_arr[1]);
                $mem = array_filter($mem);
                $mem = array_merge($mem);
                $mem_usage = $mem[2];
                $mem_total = $mem[1];
                $mem_pr = $mem[2] / $mem[1] * 100;
            }

            echo json_encode([
                'all' => $all,
                'online' => $online,
                'offline' => $offline,
                'space' => [
                    'pr' => $space_pr,
                    'count' => $space_free,
                    'total' => $space_total
                ],
                'cpu' => [
                    'pr' => $cpu_pr,
                    'count' => $cpu_pr,
                    'total' => 100
                ],
                'mem' => [
                    'pr' => $mem_pr,
                    'count' => $mem_usage,
                    'total' => $mem_total
                ]
            ]);
            break;

        case 'streams':
            if (!isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                break;
            }

            if ($method === 'GET') {
                $streams = Stream::all();
                echo json_encode($streams);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
