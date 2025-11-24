<?php
/**
 * Test V2Ray Integration
 * FOS-Streaming v70
 */

require_once 'config.php';
require_once 'app/Services/V2RayService.php';

use Illuminate\Database\Capsule\Manager;
use App\Services\V2RayService;

// Colors for console output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$blue}========================================{$reset}\n";
echo "{$blue}V2Ray Integration Test{$reset}\n";
echo "{$blue}========================================{$reset}\n\n";

// Initialize V2Ray service
$v2rayService = new V2RayService();

// Test 1: Check V2Ray Worker in Database
echo "{$yellow}Test 1: Checking V2Ray Worker Configuration{$reset}\n";
$v2rayWorker = Manager::table('pm2_workers')
    ->where('name', 'v2ray-proxy-worker')
    ->first();

if ($v2rayWorker) {
    echo "  {$green}✓{$reset} V2Ray worker configured in database\n";
    echo "  {$blue}ℹ{$reset} Script: {$v2rayWorker->script}\n";
    echo "  {$blue}ℹ{$reset} Status: " . ($v2rayWorker->enabled ? 'Enabled' : 'Disabled') . "\n";
} else {
    echo "  {$red}✗{$reset} V2Ray worker not found in database\n";
}

// Test 2: Check V2Ray Binary Installation
echo "\n{$yellow}Test 2: Checking V2Ray Installation{$reset}\n";
$v2rayPath = '/usr/local/v2ray/v2ray';

if (file_exists($v2rayPath)) {
    echo "  {$green}✓{$reset} V2Ray binary found\n";

    // Check version
    $version = shell_exec($v2rayPath . ' version 2>&1');
    if ($version) {
        preg_match('/V2Ray (\S+)/', $version, $matches);
        if (isset($matches[1])) {
            echo "  {$blue}ℹ{$reset} Version: {$matches[1]}\n";
        }
    }
} else {
    echo "  {$yellow}⚠{$reset} V2Ray not installed (run install-v2ray.sh)\n";
}

// Test 3: Check V2Ray Service Status
echo "\n{$yellow}Test 3: Checking V2Ray Service{$reset}\n";
$serviceStatus = shell_exec('systemctl is-active v2ray 2>&1');
$servicePid = shell_exec('pgrep -f v2ray 2>&1');

if (trim($serviceStatus) === 'active') {
    echo "  {$green}✓{$reset} V2Ray service is running\n";
    echo "  {$blue}ℹ{$reset} PID: " . trim($servicePid) . "\n";
} else {
    echo "  {$yellow}⚠{$reset} V2Ray service is not running\n";
}

// Test 4: Check V2Ray Servers in Database
echo "\n{$yellow}Test 4: Checking V2Ray Servers{$reset}\n";
$servers = Manager::table('v2ray_servers')->get();

if ($servers->count() > 0) {
    echo "  {$green}✓{$reset} Found {$servers->count()} V2Ray server(s)\n";
    foreach ($servers as $server) {
        echo "  {$blue}ℹ{$reset} {$server->name}: {$server->protocol} on port {$server->port} ";
        echo "(" . ($server->status ? 'Active' : 'Inactive') . ")\n";
    }
} else {
    echo "  {$yellow}⚠{$reset} No V2Ray servers configured\n";
}

// Test 5: Check V2Ray Port Availability
echo "\n{$yellow}Test 5: Checking V2Ray Ports{$reset}\n";
$portsToCheck = [443, 8443, 8444];

foreach ($portsToCheck as $port) {
    $portCheck = shell_exec("netstat -tuln | grep :$port 2>&1");
    if (strpos($portCheck, ":$port") !== false) {
        echo "  {$green}✓{$reset} Port $port is listening\n";
    } else {
        echo "  {$yellow}⚠{$reset} Port $port is not listening\n";
    }
}

// Test 6: Create Test V2Ray User
echo "\n{$yellow}Test 6: Creating Test V2Ray User{$reset}\n";

// Check if test subscriber exists
$testSubscriber = Manager::table('subscribers')
    ->where('email', 'v2ray-test@example.com')
    ->first();

if (!$testSubscriber) {
    // Create test subscriber
    $subscriberId = Manager::table('subscribers')->insertGetId([
        'username' => 'v2ray_test',
        'email' => 'v2ray-test@example.com',
        'password' => password_hash('test123', PASSWORD_BCRYPT),
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    echo "  {$green}✓{$reset} Created test subscriber\n";
} else {
    $subscriberId = $testSubscriber->id;
    echo "  {$blue}ℹ{$reset} Using existing test subscriber\n";
}

// Create V2Ray user
$v2rayUser = Manager::table('v2ray_users')
    ->where('subscriber_id', $subscriberId)
    ->first();

if (!$v2rayUser) {
    $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();

    Manager::table('v2ray_users')->insert([
        'subscriber_id' => $subscriberId,
        'server_id' => 1, // Main V2Ray server
        'uuid' => $uuid,
        'alter_id' => 64,
        'protocol' => 'vmess',
        'email' => 'v2ray-test@example.com',
        'enabled' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    echo "  {$green}✓{$reset} Created V2Ray user\n";
    echo "  {$blue}ℹ{$reset} UUID: {$uuid}\n";
} else {
    echo "  {$blue}ℹ{$reset} V2Ray user exists: {$v2rayUser->uuid}\n";
}

// Test 7: Generate Client Configurations
echo "\n{$yellow}Test 7: Generating Client Configurations{$reset}\n";

$subscriber = Manager::table('subscribers')->find($subscriberId);
$stream = (object)[
    'id' => 1,
    'name' => 'Test Stream',
    'source_url' => 'https://example.com/stream.m3u8'
];

// Generate VMess configuration
$vmessConfig = $v2rayService->generateVMessConfig($subscriber, $stream);
if ($vmessConfig) {
    echo "  {$green}✓{$reset} Generated VMess configuration\n";

    // Generate VMess URL
    $vmessData = [
        'v' => '2',
        'ps' => 'FOS-Test-Stream',
        'add' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'port' => 443,
        'id' => $vmessConfig['id'],
        'aid' => $vmessConfig['aid'],
        'net' => 'ws',
        'type' => 'none',
        'host' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'path' => '/streaming',
        'tls' => 'tls'
    ];

    $vmessUrl = 'vmess://' . base64_encode(json_encode($vmessData));
    echo "  {$blue}ℹ{$reset} VMess URL: " . substr($vmessUrl, 0, 50) . "...\n";
}

// Generate VLESS configuration
$vlessConfig = $v2rayService->generateVLESSConfig($subscriber, $stream);
if ($vlessConfig) {
    echo "  {$green}✓{$reset} Generated VLESS configuration\n";

    $vlessUrl = sprintf(
        'vless://%s@%s:8443?encryption=none&flow=xtls-rprx-direct&security=xtls&sni=%s&type=tcp#%s',
        $vlessConfig['id'],
        $_SERVER['SERVER_NAME'] ?? 'localhost',
        $_SERVER['SERVER_NAME'] ?? 'localhost',
        urlencode('FOS-Test-Stream')
    );

    echo "  {$blue}ℹ{$reset} VLESS URL: " . substr($vlessUrl, 0, 50) . "...\n";
}

// Test 8: Check Domain Fronting Configuration
echo "\n{$yellow}Test 8: Checking Domain Fronting{$reset}\n";
$domainFronting = Manager::table('v2ray_domain_fronting')
    ->where('enabled', 1)
    ->get();

if ($domainFronting->count() > 0) {
    echo "  {$green}✓{$reset} Found {$domainFronting->count()} domain fronting config(s)\n";
    foreach ($domainFronting as $config) {
        echo "  {$blue}ℹ{$reset} {$config->cdn_provider}: {$config->front_domain} → {$config->real_domain}\n";
    }
} else {
    echo "  {$yellow}⚠{$reset} No domain fronting configured\n";
}

// Test 9: Check Routing Rules
echo "\n{$yellow}Test 9: Checking Routing Rules{$reset}\n";
$routingRules = Manager::table('v2ray_routing_rules')
    ->where('enabled', 1)
    ->orderBy('priority')
    ->limit(5)
    ->get();

if ($routingRules->count() > 0) {
    echo "  {$green}✓{$reset} Found {$routingRules->count()} routing rule(s)\n";
    foreach ($routingRules as $rule) {
        echo "  {$blue}ℹ{$reset} [{$rule->priority}] {$rule->type}: {$rule->pattern} → {$rule->strategy}\n";
    }
} else {
    echo "  {$yellow}⚠{$reset} No routing rules configured\n";
}

// Test 10: WebSocket Connection Test
echo "\n{$yellow}Test 10: WebSocket Connection Test{$reset}\n";
$wsUrl = 'wss://localhost:443/streaming';
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

$headers = [
    'Host: localhost',
    'Upgrade: websocket',
    'Connection: Upgrade',
    'Sec-WebSocket-Key: ' . base64_encode(random_bytes(16)),
    'Sec-WebSocket-Version: 13'
];

// Try to connect (will likely fail without proper V2Ray client, but tests connectivity)
$errno = 0;
$errstr = '';
$fp = @stream_socket_client('ssl://localhost:443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);

if ($fp) {
    echo "  {$green}✓{$reset} SSL connection to port 443 successful\n";
    fclose($fp);
} else {
    echo "  {$yellow}⚠{$reset} Could not establish SSL connection: $errstr\n";
}

// Test 11: V2Ray Configuration File
echo "\n{$yellow}Test 11: Checking V2Ray Configuration{$reset}\n";
$configPath = '/etc/v2ray/config.json';

if (file_exists($configPath)) {
    echo "  {$green}✓{$reset} Configuration file exists\n";

    // Validate JSON
    $configContent = file_get_contents($configPath);
    $configJson = json_decode($configContent, true);

    if ($configJson) {
        echo "  {$green}✓{$reset} Configuration is valid JSON\n";
        echo "  {$blue}ℹ{$reset} Inbounds: " . count($configJson['inbounds'] ?? []) . "\n";
        echo "  {$blue}ℹ{$reset} Outbounds: " . count($configJson['outbounds'] ?? []) . "\n";
        echo "  {$blue}ℹ{$reset} Routing rules: " . count($configJson['routing']['rules'] ?? []) . "\n";
    } else {
        echo "  {$red}✗{$reset} Invalid JSON configuration\n";
    }
} else {
    echo "  {$yellow}⚠{$reset} Configuration file not found\n";
}

// Test 12: Traffic Statistics
echo "\n{$yellow}Test 12: Checking Traffic Statistics{$reset}\n";
$stats = Manager::table('v2ray_traffic_stats')
    ->select(Manager::raw('COUNT(*) as records, SUM(bytes_sent) as sent, SUM(bytes_received) as received'))
    ->first();

if ($stats && $stats->records > 0) {
    echo "  {$green}✓{$reset} Traffic statistics available\n";
    echo "  {$blue}ℹ{$reset} Records: {$stats->records}\n";
    echo "  {$blue}ℹ{$reset} Total sent: " . number_format($stats->sent / 1024 / 1024, 2) . " MB\n";
    echo "  {$blue}ℹ{$reset} Total received: " . number_format($stats->received / 1024 / 1024, 2) . " MB\n";
} else {
    echo "  {$blue}ℹ{$reset} No traffic statistics yet\n";
}

// Summary
echo "\n{$blue}========================================{$reset}\n";
echo "{$blue}Test Summary{$reset}\n";
echo "{$blue}========================================{$reset}\n";

$results = [
    'V2Ray Worker' => isset($v2rayWorker) && $v2rayWorker,
    'V2Ray Binary' => file_exists($v2rayPath),
    'V2Ray Service' => trim($serviceStatus) === 'active',
    'Database Tables' => $servers->count() > 0,
    'Port Listening' => strpos(shell_exec("netstat -tuln | grep :443 2>&1"), ':443') !== false,
    'Configuration' => file_exists($configPath) && json_decode(@file_get_contents($configPath), true) !== null
];

$passed = 0;
$warnings = 0;

foreach ($results as $test => $result) {
    if ($result) {
        echo "  {$green}✓{$reset} $test\n";
        $passed++;
    } else {
        echo "  {$yellow}⚠{$reset} $test (needs configuration)\n";
        $warnings++;
    }
}

echo "\n{$blue}Results: {$green}$passed ready{$reset}, {$yellow}$warnings need configuration{$reset}\n";

// Next Steps
echo "\n{$yellow}Next Steps:{$reset}\n";
echo "1. Install V2Ray: bash fospackv69/nginx-builder/install-v2ray.sh\n";
echo "2. Start V2Ray service: systemctl start v2ray\n";
echo "3. Start V2Ray worker: pm2 start v2ray-proxy-worker\n";
echo "4. Configure clients with generated URLs\n";
echo "5. Test connections:\n";
echo "   - VMess: Use V2Ray client with generated vmess:// URL\n";
echo "   - VLESS: Use V2Ray client with generated vless:// URL\n";
echo "   - Web: Access https://your-domain.com/streaming\n";

echo "\n{$green}V2Ray integration test completed!{$reset}\n";