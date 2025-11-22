#!/usr/bin/env php
<?php
/**
 * UFW Default Rules Seeder
 *
 * Intelligently seeds default UFW rules to prevent server lockouts.
 * Detects ports from:
 * - .env configuration
 * - Currently listening services
 * - Standard server ports
 *
 * Usage:
 *   php scripts/seed_ufw_default_rules.php
 *
 * Or from anywhere:
 *   php /path/to/project/scripts/seed_ufw_default_rules.php
 */

// Load configuration
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  FOS Streaming - UFW Default Rules Seeder                     ║\n";
echo "║  Lockout Prevention & Smart Port Detection                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

/**
 * Detect ports from environment configuration
 */
function detectEnvPorts(): array
{
    $ports = [];

    // Application port
    if ($appPort = env('APP_PORT')) {
        $ports[] = [
            'port' => (int)$appPort,
            'protocol' => 'tcp',
            'description' => 'FOS Streaming Web Panel (APP_PORT)',
            'priority' => 10
        ];
    }

    // Streaming port
    if ($streamPort = env('STREAMING_PORT')) {
        $ports[] = [
            'port' => (int)$streamPort,
            'protocol' => 'tcp',
            'description' => 'FOS Streaming Port (STREAMING_PORT)',
            'priority' => 20
        ];
    }

    // RTMP port
    if ($rtmpPort = env('RTMP_PORT')) {
        $ports[] = [
            'port' => (int)$rtmpPort,
            'protocol' => 'tcp',
            'description' => 'RTMP Streaming Port (RTMP_PORT)',
            'priority' => 30
        ];
    }

    // HTTP port
    if ($httpPort = env('NGINX_HTTP_PORT', 80)) {
        $ports[] = [
            'port' => (int)$httpPort,
            'protocol' => 'tcp',
            'description' => 'HTTP Web Server (NGINX_HTTP_PORT)',
            'priority' => 40
        ];
    }

    // HTTPS port
    if ($httpsPort = env('NGINX_HTTPS_PORT', 443)) {
        $ports[] = [
            'port' => (int)$httpsPort,
            'protocol' => 'tcp',
            'description' => 'HTTPS Secure Web (NGINX_HTTPS_PORT)',
            'priority' => 50
        ];
    }

    // Database port (only if accessible externally)
    if ($dbPort = env('FORWARD_DB_PORT')) {
        $ports[] = [
            'port' => (int)$dbPort,
            'protocol' => 'tcp',
            'description' => 'MariaDB Database (FORWARD_DB_PORT)',
            'priority' => 60,
            'from_ip' => '127.0.0.1' // Localhost only by default
        ];
    }

    // Redis port (only if accessible externally)
    if ($redisPort = env('FORWARD_REDIS_PORT')) {
        $ports[] = [
            'port' => (int)$redisPort,
            'protocol' => 'tcp',
            'description' => 'Redis Cache (FORWARD_REDIS_PORT)',
            'priority' => 70,
            'from_ip' => '127.0.0.1' // Localhost only by default
        ];
    }

    // Vite dev server port (only in local environment)
    if (env('APP_ENV') === 'local' && $vitePort = env('VITE_PORT')) {
        $ports[] = [
            'port' => (int)$vitePort,
            'protocol' => 'tcp',
            'description' => 'Vite Dev Server (VITE_PORT - Development)',
            'priority' => 80
        ];
    }

    return $ports;
}

/**
 * Detect currently listening ports
 */
function detectListeningPorts(): array
{
    $ports = [];

    // Try ss command first (modern)
    exec('ss -tulpn 2>/dev/null | grep LISTEN', $output, $returnCode);

    if ($returnCode !== 0 || empty($output)) {
        // Fallback to netstat
        exec('netstat -tulpn 2>/dev/null | grep LISTEN', $output, $returnCode);
    }

    if ($returnCode === 0 && !empty($output)) {
        foreach ($output as $line) {
            // Parse port from line like: "tcp   0   0 0.0.0.0:7777   0.0.0.0:*   LISTEN"
            if (preg_match('/(tcp|udp)\s+.*?:(\d+)\s/', $line, $matches)) {
                $protocol = $matches[1];
                $port = (int)$matches[2];

                // Only add if not already detected and is non-standard port
                if ($port > 0 && !in_array($port, [22, 80, 443])) {
                    $ports[$port] = [
                        'port' => $port,
                        'protocol' => $protocol,
                        'description' => "Detected listening service on port {$port}",
                        'priority' => 90
                    ];
                }
            }
        }
    }

    return array_values($ports);
}

/**
 * Get critical default rules (always needed)
 */
function getCriticalRules(): array
{
    return [
        [
            'port' => 22,
            'protocol' => 'tcp',
            'action' => 'limit',
            'description' => 'SSH - Rate Limited (Critical for Server Access)',
            'is_protected' => true,
            'priority' => 1
        ]
    ];
}

/**
 * Get standard server ports
 */
function getStandardRules(): array
{
    return [
        [
            'port' => 21,
            'protocol' => 'tcp',
            'description' => 'FTP - File Transfer Protocol',
            'priority' => 100,
            'enabled' => false // Disabled by default, user can enable
        ],
        [
            'port' => 25,
            'protocol' => 'tcp',
            'description' => 'SMTP - Mail Server',
            'priority' => 110,
            'enabled' => false
        ],
        [
            'port' => 53,
            'protocol' => 'both',
            'description' => 'DNS - Domain Name System',
            'priority' => 120,
            'enabled' => false
        ],
        [
            'port' => 123,
            'protocol' => 'udp',
            'description' => 'NTP - Network Time Protocol',
            'priority' => 130,
            'enabled' => false
        ]
    ];
}

/**
 * Seed the database with default rules
 */
function seedDefaultRules()
{
    echo "🔍 Detecting ports and services...\n\n";

    // Collect all rules
    $allRules = [];

    // 1. Critical rules (SSH)
    echo "✓ Adding critical rules (SSH)...\n";
    $criticalRules = getCriticalRules();
    $allRules = array_merge($allRules, $criticalRules);

    // 2. Environment-detected ports
    echo "✓ Detecting ports from .env configuration...\n";
    $envPorts = detectEnvPorts();
    foreach ($envPorts as $rule) {
        echo "  - Port {$rule['port']} ({$rule['description']})\n";
    }
    $allRules = array_merge($allRules, $envPorts);

    // 3. Listening ports
    echo "✓ Detecting currently listening ports...\n";
    $listeningPorts = detectListeningPorts();
    foreach ($listeningPorts as $rule) {
        echo "  - Port {$rule['port']} (detected from running services)\n";
    }

    // Merge listening ports, avoiding duplicates
    foreach ($listeningPorts as $listenRule) {
        $exists = false;
        foreach ($allRules as $existingRule) {
            if ($existingRule['port'] === $listenRule['port']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $allRules[] = $listenRule;
        }
    }

    // 4. Standard server ports (disabled by default)
    echo "✓ Adding standard server ports (disabled by default)...\n";
    $standardRules = getStandardRules();
    $allRules = array_merge($allRules, $standardRules);

    echo "\n📊 Summary:\n";
    echo "  Total rules to seed: " . count($allRules) . "\n\n";

    // Check if rules already exist
    $existingCount = UfwRule::where('is_default', true)->count();

    if ($existingCount > 0) {
        echo "⚠️  Found {$existingCount} existing default rules.\n";
        echo "   Options:\n";
        echo "   1) Skip seeding (keep existing rules)\n";
        echo "   2) Update existing rules\n";
        echo "   3) Delete and recreate all default rules\n\n";
        echo "   Choice (1-3): ";

        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);

        if ($choice === '1') {
            echo "\n✓ Skipping seeding. Existing rules preserved.\n";
            return;
        } elseif ($choice === '3') {
            echo "\n🗑️  Deleting existing default rules...\n";
            UfwRule::where('is_default', true)
                ->where('is_protected', false)
                ->delete();
            echo "✓ Deleted non-protected default rules.\n\n";
        }
    }

    // Insert rules
    echo "💾 Seeding default UFW rules...\n\n";
    $seeded = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($allRules as $ruleData) {
        // Set defaults
        $ruleData['action'] = $ruleData['action'] ?? 'allow';
        $ruleData['direction'] = $ruleData['direction'] ?? 'in';
        $ruleData['is_default'] = true;
        $ruleData['is_protected'] = $ruleData['is_protected'] ?? false;
        $ruleData['enabled'] = $ruleData['enabled'] ?? true;

        // Check if rule exists
        $existing = UfwRule::where('port', $ruleData['port'])
            ->where('protocol', $ruleData['protocol'])
            ->where('is_default', true)
            ->first();

        if ($existing && $choice === '2') {
            // Update existing
            $existing->update($ruleData);
            echo "  ↻ Updated: {$ruleData['description']}\n";
            $updated++;
        } elseif (!$existing) {
            // Create new
            UfwRule::create($ruleData);
            $status = $ruleData['enabled'] ? '✓' : '○';
            $protection = $ruleData['is_protected'] ? '🔒' : '  ';
            echo "  {$status} {$protection} Port {$ruleData['port']}/{$ruleData['protocol']}: {$ruleData['description']}\n";
            $seeded++;
        } else {
            $skipped++;
        }
    }

    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  Seeding Complete!                                             ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "  ✓ Rules created: {$seeded}\n";
    if ($updated > 0) echo "  ↻ Rules updated: {$updated}\n";
    if ($skipped > 0) echo "  ○ Rules skipped: {$skipped}\n";
    echo "\n";
    echo "🔐 Security Notes:\n";
    echo "  • Protected rules (🔒) cannot be deleted via the web interface\n";
    echo "  • SSH is rate-limited to prevent brute force attacks\n";
    echo "  • Some standard ports are disabled by default - enable as needed\n";
    echo "  • Database/Redis ports are restricted to localhost\n";
    echo "\n";
    echo "📋 Next Steps:\n";
    echo "  1. Review rules in the admin panel: /admin/security/advanced\n";
    echo "  2. Enable/disable rules as needed\n";
    echo "  3. Apply rules to UFW firewall from the admin panel\n";
    echo "  4. Test connectivity after applying rules\n";
    echo "\n";
}

// Run the seeder
try {
    seedDefaultRules();
    exit(0);
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
    exit(1);
}
