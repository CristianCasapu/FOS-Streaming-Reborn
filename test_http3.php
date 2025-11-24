<?php
/**
 * Test HTTP/3 and QUIC Configuration
 */

require_once 'config.php';

// Colors for console output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

echo "{$blue}========================================{$reset}\n";
echo "{$blue}HTTP/3 & QUIC Configuration Test{$reset}\n";
echo "{$blue}========================================{$reset}\n\n";

// Test 1: Check QUIC Worker in Database
echo "{$yellow}Test 1: Checking QUIC Worker Configuration{$reset}\n";
$quicWorker = \Illuminate\Database\Capsule\Manager::table('pm2_workers')
    ->where('name', 'quic-proxy-worker')
    ->first();

if ($quicWorker) {
    echo "  {$green}✓{$reset} QUIC worker configured in database\n";
    echo "  {$blue}ℹ{$reset} Script: {$quicWorker->script}\n";
    echo "  {$blue}ℹ{$reset} Status: " . ($quicWorker->enabled ? 'Enabled' : 'Disabled') . "\n";
} else {
    echo "  {$red}✗{$reset} QUIC worker not found in database\n";
}

// Test 2: Check TLS Certificates
echo "\n{$yellow}Test 2: Checking TLS Certificates{$reset}\n";
$certPath = __DIR__ . '/certs/server.crt';
$keyPath = __DIR__ . '/certs/server.key';

if (file_exists($certPath) && file_exists($keyPath)) {
    echo "  {$green}✓{$reset} TLS certificates found\n";

    // Check certificate details
    $certContent = file_get_contents($certPath);
    $certData = openssl_x509_parse($certContent);

    if ($certData) {
        echo "  {$blue}ℹ{$reset} Certificate CN: " . ($certData['subject']['CN'] ?? 'Unknown') . "\n";
        echo "  {$blue}ℹ{$reset} Valid until: " . date('Y-m-d', $certData['validTo_time_t']) . "\n";
    }
} else {
    echo "  {$yellow}⚠{$reset} TLS certificates not found (will be generated on first run)\n";
}

// Test 3: Check Node.js QUIC Support
echo "\n{$yellow}Test 3: Checking Node.js QUIC Support{$reset}\n";
$nodeVersion = shell_exec('node --version 2>&1');
echo "  {$blue}ℹ{$reset} Node.js version: " . trim($nodeVersion) . "\n";

// Check if QUIC module is available
$quicCheck = shell_exec('node -e "console.log(typeof require(\'net\').createQuicSocket)" 2>&1');
if (strpos($quicCheck, 'undefined') === false) {
    echo "  {$green}✓{$reset} Node.js QUIC support available\n";
} else {
    echo "  {$yellow}⚠{$reset} Node.js QUIC support may need experimental flag\n";
    echo "  {$blue}ℹ{$reset} Run with: node --experimental-quic\n";
}

// Test 4: Check Nginx HTTP/3 Support
echo "\n{$yellow}Test 4: Checking Nginx HTTP/3 Support{$reset}\n";
$nginxVersion = shell_exec('nginx -V 2>&1');

if (strpos($nginxVersion, '--with-http_v3_module') !== false) {
    echo "  {$green}✓{$reset} Nginx compiled with HTTP/3 module\n";
} else if (strpos($nginxVersion, 'quic') !== false) {
    echo "  {$green}✓{$reset} Nginx QUIC support detected\n";
} else {
    echo "  {$yellow}⚠{$reset} Nginx may need recompilation for HTTP/3\n";
    echo "  {$blue}ℹ{$reset} Current Nginx build:\n";

    // Show configure arguments
    preg_match('/configure arguments:(.*)/', $nginxVersion, $matches);
    if (isset($matches[1])) {
        $args = explode(' ', trim($matches[1]));
        foreach ($args as $arg) {
            if (strpos($arg, '--with-') === 0 || strpos($arg, '--add-') === 0) {
                echo "      • " . $arg . "\n";
            }
        }
    }
}

// Test 5: Check UDP Port 443 (QUIC)
echo "\n{$yellow}Test 5: Checking UDP Port for QUIC{$reset}\n";
$udpCheck = shell_exec('netstat -uln | grep :443 2>&1');

if (strpos($udpCheck, ':443') !== false) {
    echo "  {$green}✓{$reset} UDP port 443 is listening (QUIC ready)\n";
} else {
    echo "  {$yellow}⚠{$reset} UDP port 443 not listening\n";
    echo "  {$blue}ℹ{$reset} QUIC requires UDP port 443 to be open\n";
}

// Test 6: Redis ECH Configuration
echo "\n{$yellow}Test 6: Checking ECH Configuration in Redis{$reset}\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    $echConfig = $redis->get('ech:config');
    if ($echConfig) {
        echo "  {$green}✓{$reset} ECH configuration found in Redis\n";
        $config = json_decode(base64_decode($echConfig), true);
        echo "  {$blue}ℹ{$reset} Public name: " . ($config['publicName'] ?? 'Unknown') . "\n";
    } else {
        echo "  {$yellow}⚠{$reset} ECH configuration not found (will be generated on worker start)\n";
    }

    $redis->close();
} catch (Exception $e) {
    echo "  {$red}✗{$reset} Redis connection failed: " . $e->getMessage() . "\n";
}

// Test 7: Create Test HTTP/3 Stream Configuration
echo "\n{$yellow}Test 7: Creating Test HTTP/3 Stream{$reset}\n";

use Illuminate\Database\Capsule\Manager as DB;

$testStream = [
    'name' => 'Test HTTP/3 Stream',
    'streamurl' => 'https://source.example.com/stream.m3u8',
    'source_url' => 'h3://source.example.com/stream.m3u8',
    'stream_mode' => 'proxy',
    'stream_type' => 'live',
    'running' => 0,
    'status' => 1,
    'cat_id' => 1,
    'logo' => '',
    'tvid' => '',
    'require_device_lock' => 1,
    'max_connections' => 20,
    'proxy_settings' => json_encode([
        'protocol' => 'http3',
        'quic_version' => 'h3',
        'zero_rtt' => true,
        'ech_enabled' => true
    ])
];

$existing = DB::table('streams')
    ->where('name', 'Test HTTP/3 Stream')
    ->first();

if ($existing) {
    echo "  {$blue}ℹ{$reset} Test HTTP/3 stream already exists\n";
} else {
    DB::table('streams')->insert($testStream);
    echo "  {$green}✓{$reset} Created test HTTP/3 stream\n";
}

// Test 8: Browser HTTP/3 Support Detection
echo "\n{$yellow}Test 8: Browser Support Information{$reset}\n";
echo "  {$blue}ℹ{$reset} Chrome/Edge: chrome://flags/#enable-quic\n";
echo "  {$blue}ℹ{$reset} Firefox: about:config → network.http.http3.enabled\n";
echo "  {$blue}ℹ{$reset} Safari: Experimental Features → HTTP/3\n";

// Summary
echo "\n{$blue}========================================{$reset}\n";
echo "{$blue}Test Summary{$reset}\n";
echo "{$blue}========================================{$reset}\n";

$results = [
    'QUIC Worker' => isset($quicWorker) && $quicWorker,
    'TLS Ready' => file_exists($certPath) || file_exists($keyPath),
    'Node.js QUIC' => strpos($quicCheck, 'undefined') === false,
    'Nginx HTTP/3' => strpos($nginxVersion, 'http_v3_module') !== false || strpos($nginxVersion, 'quic') !== false,
    'UDP Port 443' => strpos($udpCheck, ':443') !== false,
    'Redis ECH' => isset($echConfig) && $echConfig
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
echo "1. Start QUIC worker: pm2 start quic-proxy-worker\n";
echo "2. Configure nginx with HTTP/3 module\n";
echo "3. Open UDP port 443 in firewall\n";
echo "4. Test with HTTP/3 client:\n";
echo "   curl --http3 https://your-domain.com/quic-stats\n";

echo "\n{$green}HTTP/3 & QUIC configuration test completed!{$reset}\n";